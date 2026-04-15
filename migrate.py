#!/usr/bin/env python3
"""
DataPrivNet → SurrealDB Migration
==================================
Namespace : 'Data Provisioning Engine'
Databases : AppDB         (access control, datasets, policies)
            DataWarehouse (fact/dim/staging tables)

Usage
-----
    pip install requests
    pip install pyodbc      # optional — needed for DataWarehouse SQL Server import
    python migrate.py

What this script does
---------------------
1.  Creates namespace 'Data Provisioning Engine' in SurrealDB
2.  Creates databases AppDB and DataWarehouse inside that namespace
3.  Defines SCHEMAFULL schema for AppDB (11 tables, indexes, row-level permissions)
4.  Defines base schema for DataWarehouse (PermissionsMap + SCHEMALESS data tables)
5.  Imports all AppDB data from the CSV exports in this folder
6.  Connects to SQL Server datawarehouse_DEV and migrates all tables
7.  Prints a verification summary of record counts
"""

import datetime
import json
import sys
from pathlib import Path

import requests

try:
    import pyodbc
    HAS_PYODBC = True
except ImportError:
    HAS_PYODBC = False

# ──────────────────────────────────────────────────────────────────────────────
# CONFIGURATION
# ──────────────────────────────────────────────────────────────────────────────
SURREAL_URL  = "http://localhost:8000"
SURREAL_USER = "root"
SURREAL_PASS = "root"

NAMESPACE = "Data Provisioning Engine"
APPDB     = "AppDB"
DW_DB     = "DataWarehouse"

MIGRATION_DIR = Path(__file__).parent

# SQL Server DataWarehouse connection string
DW_SQL_CONN = (
    "DRIVER={ODBC Driver 17 for SQL Server};"
    "SERVER=(local);"
    "DATABASE=datawarehouse_DEV;"
    "UID=dmp;PWD=dmp1234;"
    "TrustServerCertificate=yes"
)

APPDB_BATCH_SIZE = 30   # SurrealQL UPSERT statements per HTTP request (AppDB)
DW_INSERT_BATCH  = 250  # Rows per INSERT INTO [...] request (DataWarehouse)


# ──────────────────────────────────────────────────────────────────────────────
# APPDB SCHEMAFULL SCHEMA  (SurrealDB 3.x syntax)
# ──────────────────────────────────────────────────────────────────────────────
APPDB_SCHEMA = """\
-- ── Users ────────────────────────────────────────────────────────────────────
DEFINE TABLE users SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, update, delete WHERE $auth.role = 'Admin';
DEFINE FIELD name       ON TABLE users TYPE string;
DEFINE FIELD email      ON TABLE users TYPE string;
DEFINE FIELD role       ON TABLE users TYPE string;
DEFINE FIELD avatar     ON TABLE users TYPE option<string>;
DEFINE FIELD created_at ON TABLE users TYPE datetime;
DEFINE INDEX users_email_unique ON TABLE users COLUMNS email UNIQUE;

-- ── Virtual Groups ────────────────────────────────────────────────────────────
DEFINE TABLE virtual_groups SCHEMAFULL;
DEFINE FIELD name        ON TABLE virtual_groups TYPE string;
DEFINE FIELD owner_id    ON TABLE virtual_groups TYPE record<users>;
DEFINE FIELD description ON TABLE virtual_groups TYPE option<string>;
DEFINE FIELD created_at  ON TABLE virtual_groups TYPE datetime;

-- ── Datasets ─────────────────────────────────────────────────────────────────
DEFINE TABLE datasets SCHEMAFULL;
DEFINE FIELD name           ON TABLE datasets TYPE string;
DEFINE FIELD type           ON TABLE datasets TYPE string;
DEFINE FIELD description    ON TABLE datasets TYPE option<string>;
DEFINE FIELD owner_group_id ON TABLE datasets TYPE option<record<virtual_groups>>;
DEFINE FIELD created_at     ON TABLE datasets TYPE datetime;

-- ── Columns (dataset field metadata) ─────────────────────────────────────────
DEFINE TABLE columns SCHEMAFULL;
DEFINE FIELD dataset_id  ON TABLE columns TYPE record<datasets>;
DEFINE FIELD name        ON TABLE columns TYPE string;
DEFINE FIELD data_type   ON TABLE columns TYPE option<string>;
DEFINE FIELD definition  ON TABLE columns TYPE option<string>;
DEFINE FIELD is_pii      ON TABLE columns TYPE bool;
DEFINE FIELD sample_data ON TABLE columns TYPE option<string>;
DEFINE INDEX columns_dataset ON TABLE columns COLUMNS dataset_id;

-- ── Asset Policy Groups ───────────────────────────────────────────────────────
DEFINE TABLE asset_policy_groups SCHEMAFULL;
DEFINE FIELD dataset_id  ON TABLE asset_policy_groups TYPE record<datasets>;
DEFINE FIELD owner_id    ON TABLE asset_policy_groups TYPE option<record<users>>;
DEFINE FIELD name        ON TABLE asset_policy_groups TYPE string;
DEFINE FIELD description ON TABLE asset_policy_groups TYPE option<string>;
DEFINE FIELD created_at  ON TABLE asset_policy_groups TYPE datetime;
DEFINE INDEX apg_dataset ON TABLE asset_policy_groups COLUMNS dataset_id;

-- ── Asset Policy Columns ──────────────────────────────────────────────────────
DEFINE TABLE asset_policy_columns SCHEMAFULL;
DEFINE FIELD policy_group_id ON TABLE asset_policy_columns TYPE record<asset_policy_groups>;
DEFINE FIELD column_name     ON TABLE asset_policy_columns TYPE string;
DEFINE FIELD is_hidden       ON TABLE asset_policy_columns TYPE bool;

-- ── Asset Policy Conditions ───────────────────────────────────────────────────
DEFINE TABLE asset_policy_conditions SCHEMAFULL;
DEFINE FIELD policy_group_id ON TABLE asset_policy_conditions TYPE record<asset_policy_groups>;
DEFINE FIELD column_name     ON TABLE asset_policy_conditions TYPE string;
DEFINE FIELD operator        ON TABLE asset_policy_conditions TYPE string;
DEFINE FIELD value           ON TABLE asset_policy_conditions TYPE string;

-- ── Virtual Group Members (junction) ─────────────────────────────────────────
DEFINE TABLE virtual_group_members SCHEMAFULL;
DEFINE FIELD group_id  ON TABLE virtual_group_members TYPE record<virtual_groups>;
DEFINE FIELD user_id   ON TABLE virtual_group_members TYPE record<users>;
DEFINE FIELD added_at  ON TABLE virtual_group_members TYPE datetime;
DEFINE INDEX vgm_user  ON TABLE virtual_group_members COLUMNS user_id;
DEFINE INDEX vgm_group ON TABLE virtual_group_members COLUMNS group_id;

-- ── Access Requests ───────────────────────────────────────────────────────────
DEFINE TABLE access_requests SCHEMAFULL
    PERMISSIONS
        FOR select WHERE user_id = $auth.id
                      OR $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR create WHERE $auth != NONE,
        FOR update WHERE $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR delete WHERE $auth.role = 'Admin';
DEFINE FIELD user_id               ON TABLE access_requests TYPE record<users>;
DEFINE FIELD dataset_id            ON TABLE access_requests TYPE record<datasets>;
DEFINE FIELD status                ON TABLE access_requests TYPE string;
DEFINE FIELD requested_rls_filters ON TABLE access_requests TYPE option<string>;
DEFINE FIELD justification         ON TABLE access_requests TYPE option<string>;
DEFINE FIELD reviewed_by           ON TABLE access_requests TYPE option<record<users>>;
DEFINE FIELD reviewed_at           ON TABLE access_requests TYPE option<datetime>;
DEFINE FIELD created_at            ON TABLE access_requests TYPE datetime;
DEFINE FIELD policy_group_id       ON TABLE access_requests TYPE option<record<asset_policy_groups>>;
DEFINE INDEX ar_user    ON TABLE access_requests COLUMNS user_id;
DEFINE INDEX ar_dataset ON TABLE access_requests COLUMNS dataset_id;
DEFINE INDEX ar_status  ON TABLE access_requests COLUMNS status;

-- ── Reports ───────────────────────────────────────────────────────────────────
DEFINE TABLE reports SCHEMAFULL;
DEFINE FIELD name        ON TABLE reports TYPE string;
DEFINE FIELD url         ON TABLE reports TYPE option<string>;
DEFINE FIELD description ON TABLE reports TYPE option<string>;

-- ── Report Datasets (junction) ────────────────────────────────────────────────
DEFINE TABLE report_datasets SCHEMAFULL;
DEFINE FIELD dataset_id ON TABLE report_datasets TYPE record<datasets>;
DEFINE FIELD report_id  ON TABLE report_datasets TYPE record<reports>;

-- ── Initial Admins ────────────────────────────────────────────────────────────
DEFINE TABLE initial_admins SCHEMAFULL;
DEFINE FIELD username ON TABLE initial_admins TYPE string;
DEFINE FIELD added_at ON TABLE initial_admins TYPE option<datetime>;
"""

# DataWarehouse base schema — data tables are created dynamically (SCHEMALESS)
DW_SCHEMA = """\
-- Permissions map — row-level security enforcement between AppDB and DW tables
DEFINE TABLE PermissionsMap SCHEMAFULL;
DEFINE FIELD user_id          ON TABLE PermissionsMap TYPE string;
DEFINE FIELD table_name       ON TABLE PermissionsMap TYPE string;
DEFINE FIELD column_id        ON TABLE PermissionsMap TYPE option<string>;
DEFINE FIELD authorized_value ON TABLE PermissionsMap TYPE option<string>;
DEFINE FIELD created_at       ON TABLE PermissionsMap TYPE option<datetime>;
DEFINE INDEX pm_user  ON TABLE PermissionsMap COLUMNS user_id;
DEFINE INDEX pm_table ON TABLE PermissionsMap COLUMNS table_name;
"""


# ──────────────────────────────────────────────────────────────────────────────
# SURREALDB HTTP CLIENT
# ──────────────────────────────────────────────────────────────────────────────
class SurrealClient:
    def __init__(self, url: str, user: str, password: str):
        self.url  = url
        self.auth = (user, password)

    def query(self, sql: str, ns: str = None, db: str = None) -> list:
        """Execute SurrealQL and return the parsed result list. Raises on errors."""
        headers = {
            "Accept":       "application/json",
            "Content-Type": "text/plain",
        }
        if ns:
            headers["surreal-ns"] = ns
        if db:
            headers["surreal-db"] = db

        resp = requests.post(
            f"{self.url}/sql",
            data=sql.encode("utf-8"),
            headers=headers,
            auth=self.auth,
            timeout=120,
        )
        if resp.status_code >= 400:
            raise RuntimeError(f"HTTP {resp.status_code}: {resp.text[:300]}")
        return resp.json()

    def run(self, sql: str, ns: str = None, db: str = None) -> list | None:
        """Like query() but prints the error and returns None instead of raising."""
        try:
            return self.query(sql, ns=ns, db=db)
        except Exception as exc:
            print(f"    [ERR] {exc}")
            return None

    def run_batch(self, statements: list[str], ns: str, db: str) -> tuple[int, int]:
        """Send multiple SurrealQL statements in one HTTP request. Returns (ok, err)."""
        sql = "\n".join(statements)
        try:
            results = self.query(sql, ns=ns, db=db)
        except Exception as exc:
            print(f"    [BATCH ERR] {exc}")
            return 0, len(statements)

        ok = err = 0
        for r in results:
            if isinstance(r, dict) and r.get("status") == "OK":
                ok += 1
            else:
                err += 1
        return ok, err


# ──────────────────────────────────────────────────────────────────────────────
# CSV PARSER  (SQL Server fixed-width text export format)
# ──────────────────────────────────────────────────────────────────────────────
def parse_sql_csv(filepath: str) -> tuple[list, list]:
    """
    Parse SQL Server fixed-width CSV exports.

    Format:
        col1       ,col2          ,...
        -----------,--------------,...   ← separator line defines column widths
        value1     ,value2        ,...
        (N rows affected)

    Returns (headers, rows) where rows is a list of dicts.
    NULL or empty values become Python None.
    """
    path = Path(filepath)
    if not path.exists():
        return [], []

    with open(path, "r", encoding="utf-8") as fh:
        lines = fh.readlines()

    if len(lines) < 2:
        return [], []

    sep = lines[1].rstrip("\n")
    positions: list[tuple[int, int]] = []
    start = 0
    for i, ch in enumerate(sep):
        if ch == ",":
            positions.append((start, i))
            start = i + 1
    positions.append((start, len(sep)))

    def split_line(line: str) -> list[str]:
        line = line.rstrip("\n")
        parts = []
        for idx, (s, e) in enumerate(positions):
            if s >= len(line):
                parts.append("")
            elif idx == len(positions) - 1:
                parts.append(line[s:].strip())
            else:
                parts.append(line[s : min(e, len(line))].strip())
        return parts

    headers = split_line(lines[0])
    rows: list[dict] = []
    for line in lines[2:]:
        stripped = line.strip()
        if not stripped or "rows affected" in stripped.lower():
            continue
        vals = split_line(line)
        row: dict = {}
        for h, v in zip(headers, vals):
            row[h] = None if (v == "" or v.upper() == "NULL") else v
        rows.append(row)

    return headers, rows


# ──────────────────────────────────────────────────────────────────────────────
# APPDB — SURREALQL VALUE BUILDER
# ──────────────────────────────────────────────────────────────────────────────

# Foreign key field → target table mapping
FK_MAP: dict[str, dict[str, str]] = {
    "virtual_groups":          {"owner_id":        "users"},
    "datasets":                {"owner_group_id":  "virtual_groups"},
    "columns":                 {"dataset_id":      "datasets"},
    "asset_policy_groups":     {"dataset_id":      "datasets",
                                "owner_id":         "users"},
    "asset_policy_columns":    {"policy_group_id": "asset_policy_groups"},
    "asset_policy_conditions": {"policy_group_id": "asset_policy_groups"},
    "virtual_group_members":   {"group_id":        "virtual_groups",
                                "user_id":          "users"},
    "access_requests":         {"user_id":         "users",
                                "dataset_id":       "datasets",
                                "reviewed_by":      "users",
                                "policy_group_id":  "asset_policy_groups"},
}

BOOL_FIELDS     = {"is_pii", "is_hidden"}
DATETIME_FIELDS = {"created_at", "reviewed_at", "added_at"}


def _to_iso(val: str) -> str:
    """Convert SQL Server datetime string to ISO 8601 with Z suffix."""
    val = val.strip().replace(" ", "T")
    if "." in val:
        base, frac = val.split(".", 1)
        val = f"{base}.{frac[:3]}"
    if not (val.endswith("Z") or "+" in val):
        val += "Z"
    return val


def _appdb_val(field: str, raw, fks: dict) -> str:
    """Return a SurrealQL literal for use in an AppDB UPSERT SET clause."""
    if raw is None:
        return "NONE"
    if field in fks:
        return f"{fks[field]}:{raw}"
    if field in BOOL_FIELDS:
        return "true" if raw in ("1", "True", "true", "TRUE", True, 1) else "false"
    if field in DATETIME_FIELDS and isinstance(raw, str):
        return f'd"{_to_iso(raw)}"'
    if isinstance(raw, (datetime.datetime, datetime.date)):
        return f'd"{raw.isoformat()}Z"'
    if isinstance(raw, bool):
        return "true" if raw else "false"
    if isinstance(raw, (int, float)):
        return str(raw)
    s = str(raw).replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n").replace("\r", "")
    return f'"{s}"'


def _build_upsert(table: str, record_id: str, row: dict) -> str:
    fks   = FK_MAP.get(table, {})
    parts = [
        f"{field} = {_appdb_val(field, val, fks)}"
        for field, val in row.items()
        if field != "id"
    ]
    return f"UPSERT {table}:{record_id} SET {', '.join(parts)};"


# ──────────────────────────────────────────────────────────────────────────────
# DATAWAREHOUSE — BULK INSERT BUILDER
# ──────────────────────────────────────────────────────────────────────────────
def _dw_val(val) -> str:
    """Return a SurrealQL literal for use inside a DataWarehouse INSERT object."""
    if val is None:
        return "NONE"
    if isinstance(val, bool):
        return "true" if val else "false"
    if isinstance(val, (int, float)):
        return str(val)
    if isinstance(val, (datetime.datetime, datetime.date)):
        return f'd"{val.isoformat()}Z"'
    if isinstance(val, bytes):
        return f'"{val.hex()}"'
    s = str(val).replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n").replace("\r", "")
    return f'"{s}"'


def _build_insert_array(table: str, col_names: list, rows: list, id_start: int) -> str:
    """
    Builds a single:
        INSERT INTO `<table>` [ {id: `<table>`:<n>, col: val, ...}, ... ];
    for a batch of rows — one HTTP round-trip for up to DW_INSERT_BATCH rows.
    """
    objects = []
    for offset, row_data in enumerate(rows):
        record_id = id_start + offset
        fields = [f"id: `{table}`:{record_id}"]
        for col, val in zip(col_names, row_data):
            fields.append(f"`{col}`: {_dw_val(val)}")
        objects.append("{ " + ", ".join(fields) + " }")
    joined = ",\n    ".join(objects)
    return f"INSERT INTO `{table}` [\n    {joined}\n];"


# ──────────────────────────────────────────────────────────────────────────────
# APPDB DATA IMPORT  (from CSV exports)
# ──────────────────────────────────────────────────────────────────────────────
APPDB_TABLES = [
    # (surreal_table,             csv_filename)
    ("users",                   "appdb_users.csv"),
    ("virtual_groups",          "appdb_virtual_groups.csv"),
    ("datasets",                "appdb_datasets.csv"),
    ("columns",                 "appdb_columns.csv"),
    ("asset_policy_groups",     "appdb_asset_policy_groups.csv"),
    ("asset_policy_columns",    "appdb_asset_policy_columns.csv"),
    ("asset_policy_conditions", "appdb_asset_policy_conditions.csv"),
    ("virtual_group_members",   "appdb_virtual_group_members.csv"),
    ("access_requests",         "appdb_access_requests.csv"),
    ("initial_admins",          "appdb_initial_admins.csv"),
]


def migrate_appdb(client: SurrealClient) -> int:
    total_ok = 0

    for table, csv_file in APPDB_TABLES:
        _, rows = parse_sql_csv(str(MIGRATION_DIR / csv_file))

        if not rows:
            print(f"  {table:<34} SKIP  (no data)")
            continue

        statements: list[str] = []
        for idx, row in enumerate(rows):
            raw_id = row.get("id")
            if raw_id is not None:
                record_id = str(raw_id)
            elif table == "virtual_group_members":
                record_id = f"vgm_{row.get('group_id', idx)}_{row.get('user_id', idx)}"
            else:
                record_id = str(idx + 1)
            statements.append(_build_upsert(table, record_id, row))

        ok = err = 0
        for i in range(0, len(statements), APPDB_BATCH_SIZE):
            b_ok, b_err = client.run_batch(
                statements[i : i + APPDB_BATCH_SIZE], ns=NAMESPACE, db=APPDB
            )
            ok  += b_ok
            err += b_err

        label = f"OK  ({ok} records)"
        if err:
            label += f"  [{err} failed]"
        print(f"  {table:<34} {label}")
        total_ok += ok

    return total_ok


# ──────────────────────────────────────────────────────────────────────────────
# DATAWAREHOUSE IMPORT  (from SQL Server via pyodbc)
# ──────────────────────────────────────────────────────────────────────────────
def migrate_datawarehouse(client: SurrealClient) -> int:
    if not HAS_PYODBC:
        print("  pyodbc not installed — DataWarehouse SQL Server import skipped.")
        print("  To enable: pip install pyodbc")
        return 0

    try:
        conn = pyodbc.connect(DW_SQL_CONN, timeout=10)
        print("  Connected to SQL Server  (datawarehouse_DEV)")
    except Exception as exc:
        print(f"  Cannot connect to SQL Server: {exc}")
        print("  DataWarehouse import skipped.")
        return 0

    cursor = conn.cursor()

    # Discover all user tables, ordered by row count descending
    cursor.execute("""
        SELECT t.TABLE_SCHEMA, t.TABLE_NAME, ISNULL(p.rows, 0) AS row_count
        FROM   INFORMATION_SCHEMA.TABLES t
        JOIN   sys.tables st ON st.name = t.TABLE_NAME
        JOIN   sys.partitions p
               ON p.object_id = st.object_id AND p.index_id IN (0, 1)
        WHERE  t.TABLE_TYPE = 'BASE TABLE'
          AND  t.TABLE_NAME NOT LIKE '__EF%'
        ORDER  BY p.rows DESC, t.TABLE_NAME
    """)
    tables = cursor.fetchall()

    print(f"  Found {len(tables)} tables in datawarehouse_DEV")
    print()
    print(f"  {'Table':<47} {'Rows':>8}  Status")
    print(f"  {'-'*47}  {'-'*8}  ------")

    grand_total = 0

    for sql_schema, table_name, row_count in tables:
        full_sql = f"[{sql_schema}].[{table_name}]"

        if row_count == 0:
            print(f"  {table_name:<47} {row_count:>8,}  SKIP (empty)")
            continue

        client.run(f"DEFINE TABLE `{table_name}` SCHEMALESS;", ns=NAMESPACE, db=DW_DB)

        try:
            cursor.execute(f"SELECT * FROM {full_sql}")
            col_names = [d[0] for d in cursor.description]
            all_rows  = cursor.fetchall()
        except Exception as exc:
            print(f"  {table_name:<47} {row_count:>8,}  ERR (read): {exc}")
            continue

        ok = err = 0
        for i in range(0, len(all_rows), DW_INSERT_BATCH):
            chunk  = all_rows[i : i + DW_INSERT_BATCH]
            stmt   = _build_insert_array(table_name, col_names, chunk, id_start=i + 1)
            result = client.run(stmt, ns=NAMESPACE, db=DW_DB)

            if result:
                for r in result:
                    if isinstance(r, dict) and r.get("status") == "OK":
                        inserted = len(r.get("result", []))
                        ok += inserted if inserted else len(chunk)
                    else:
                        err += len(chunk)
            else:
                err += len(chunk)

        pct    = int(100 * ok / row_count) if row_count else 0
        status = f"OK ({ok:,} rows, {pct}%)"
        if err:
            status += f"  [{err:,} failed]"
        print(f"  {table_name:<47} {row_count:>8,}  {status}")
        grand_total += ok

    conn.close()
    return grand_total


# ──────────────────────────────────────────────────────────────────────────────
# VERIFICATION
# ──────────────────────────────────────────────────────────────────────────────
VERIFY_APPDB = [
    ("users",                 6),
    ("virtual_groups",        5),
    ("datasets",             44),
    ("columns",             247),
    ("access_requests",      13),
    ("virtual_group_members", 3),
]


def verify_appdb(client: SurrealClient) -> bool:
    all_ok = True
    for table, expected in VERIFY_APPDB:
        try:
            result = client.query(
                f"SELECT count() FROM {table} GROUP ALL;",
                ns=NAMESPACE, db=APPDB
            )
            rows   = result[0].get("result", []) if result else []
            actual = rows[0].get("count", 0) if rows else 0
            tick   = "[OK]" if actual >= expected else "[!!]"
            note   = "" if actual >= expected else f"  (expected >={expected})"
            print(f"  {tick}  {table:<34} {actual:>4} records{note}")
            if actual < expected:
                all_ok = False
        except Exception as exc:
            print(f"  [??] {table:<34} ERR: {exc}")
            all_ok = False
    return all_ok


# ──────────────────────────────────────────────────────────────────────────────
# MAIN
# ──────────────────────────────────────────────────────────────────────────────
def main() -> None:
    BOLD  = "\033[1m"
    RESET = "\033[0m"
    GREEN = "\033[32m"
    RED   = "\033[31m"
    CYAN  = "\033[36m"
    SEP   = "=" * 62

    def header(msg: str) -> None:
        print(f"\n{CYAN}{BOLD}{msg}{RESET}")

    print()
    print(f"{BOLD}{SEP}{RESET}")
    print(f"{BOLD}  DataPrivNet -> SurrealDB Migration{RESET}")
    print(f"{BOLD}  Namespace: '{NAMESPACE}'{RESET}")
    print(f"{BOLD}{SEP}{RESET}")

    client = SurrealClient(SURREAL_URL, SURREAL_USER, SURREAL_PASS)

    # ── Connectivity check ─────────────────────────────────────────────────────
    try:
        requests.get(f"{SURREAL_URL}/health", timeout=5).raise_for_status()
        print(f"\n{GREEN}[OK] SurrealDB is reachable at {SURREAL_URL}{RESET}")
    except Exception as exc:
        print(f"\n{RED}[!!] Cannot reach SurrealDB: {exc}{RESET}")
        print("     Make sure SurrealDB is running:  surreal start")
        sys.exit(1)

    # ── 1. Namespace & Databases ───────────────────────────────────────────────
    header("[1/5]  Namespace & Databases")
    client.run("DEFINE NAMESPACE `Data Provisioning Engine`;")
    client.run("DEFINE DATABASE AppDB;",         ns=NAMESPACE)
    client.run("DEFINE DATABASE DataWarehouse;", ns=NAMESPACE)
    print(f"  Namespace : {NAMESPACE}")
    print(f"  Databases : AppDB,  DataWarehouse")

    # ── 2. AppDB Schema ────────────────────────────────────────────────────────
    header("[2/5]  AppDB Schema  (SCHEMAFULL)")
    try:
        client.query(APPDB_SCHEMA, ns=NAMESPACE, db=APPDB)
        print("  11 tables defined (users, virtual_groups, datasets, columns,")
        print("  asset_policy_groups, asset_policy_columns, asset_policy_conditions,")
        print("  virtual_group_members, access_requests, reports, report_datasets,")
        print("  initial_admins)  +  indexes  +  row-level permissions")
    except Exception as exc:
        print(f"{RED}  Schema error: {exc}{RESET}")
        print("  Aborting — fix the schema and re-run.")
        sys.exit(1)

    # ── 3. DataWarehouse Schema ────────────────────────────────────────────────
    header("[3/5]  DataWarehouse Schema  (base tables)")
    try:
        client.query(DW_SCHEMA, ns=NAMESPACE, db=DW_DB)
        print("  PermissionsMap defined (SCHEMAFULL)")
        print("  Data tables will be created as SCHEMALESS during import")
    except Exception as exc:
        print(f"  Warning: {exc}")

    # ── 4. AppDB Data Import ───────────────────────────────────────────────────
    header("[4/5]  AppDB Data Import  (from CSV exports)")
    app_total = migrate_appdb(client)
    print(f"  {'-'*50}")
    print(f"  AppDB total imported: {app_total} records")

    # ── 5. DataWarehouse Import ────────────────────────────────────────────────
    header("[5/5]  DataWarehouse Import  (from SQL Server)")
    dw_total = migrate_datawarehouse(client)
    if dw_total:
        print(f"  {'-'*50}")
        print(f"  DataWarehouse total imported: {dw_total:,} records")

    # ── Verification ───────────────────────────────────────────────────────────
    header("Verification  —  AppDB record counts")
    ok = verify_appdb(client)

    # ── Summary ────────────────────────────────────────────────────────────────
    print()
    print(f"{BOLD}{SEP}{RESET}")
    status = (f"{GREEN}Migration complete [OK]{RESET}"
              if ok else f"{RED}Migration complete (with warnings){RESET}")
    print(f"{BOLD}  {status}{RESET}")
    print(f"{BOLD}{SEP}{RESET}")
    print()
    print(f"  Namespace    : {NAMESPACE}")
    print(f"  AppDB        : {app_total} records")
    print(f"  DataWarehouse: {dw_total:,} records")
    print()
    print("  To query the data:")
    print('    surreal sql --conn http://localhost:8000 \\')
    print('      --ns "Data Provisioning Engine" --db AppDB \\')
    print('      -u root -p root')
    print()
    print("  Next: repoint the application to SurrealDB")
    print()


if __name__ == "__main__":
    main()
