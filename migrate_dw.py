#!/usr/bin/env python3
"""
DataWarehouse SQL Server → SurrealDB Migration
================================================
Migrates all tables from datawarehouse_DEV into
namespace 'Data Provisioning Engine', database 'DataWarehouse'.

AppDB has already been imported by migrate_to_surreal.py.
This script handles the DataWarehouse only.

Run with: python migrate_dw.py
Requires: pip install requests pyodbc
"""

import datetime
import sys
from pathlib import Path

import requests

try:
    import pyodbc
except ImportError:
    print("[FAIL] pyodbc is required:  pip install pyodbc")
    sys.exit(1)

# ──────────────────────────────────────────────────────────────────────────────
# CONFIGURATION
# ──────────────────────────────────────────────────────────────────────────────
SURREAL_URL  = "http://localhost:8000"
SURREAL_USER = "root"
SURREAL_PASS = "root"
NAMESPACE    = "Data Provisioning Engine"
DW_DB        = "DataWarehouse"

# Use (local) for named-pipe connection (TCP/1433 not enabled on this machine)
DW_SQL_CONN = (
    "DRIVER={ODBC Driver 17 for SQL Server};"
    "SERVER=(local);"
    "DATABASE=datawarehouse_DEV;"
    "UID=dmp;PWD=dmp1234;"
    "TrustServerCertificate=yes"
)

# How many rows to pack into a single INSERT INTO [...] statement.
# Larger = fewer HTTP round-trips, faster import.
INSERT_BATCH = 250


# ──────────────────────────────────────────────────────────────────────────────
# SURREALDB CLIENT
# ──────────────────────────────────────────────────────────────────────────────
class SurrealClient:
    def __init__(self, url, user, password):
        self.url  = url
        self.auth = (user, password)

    def query(self, sql: str, ns: str = None, db: str = None) -> list:
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

    def run(self, sql: str, ns: str = None, db: str = None):
        try:
            return self.query(sql, ns=ns, db=db)
        except Exception as exc:
            print(f"    [ERR] {exc}")
            return None


# ──────────────────────────────────────────────────────────────────────────────
# VALUE FORMATTER  (Python → SurrealQL literal)
# ──────────────────────────────────────────────────────────────────────────────
def fmt(val) -> str:
    """Return a SurrealQL value string for use inside an object literal { }."""
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
    # String — escape double-quotes, backslashes, newlines
    s = str(val).replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n").replace("\r", "")
    return f'"{s}"'


# ──────────────────────────────────────────────────────────────────────────────
# BULK INSERT
# Builds a single  INSERT INTO `table` [ {id: t:1, col: val, ...}, ... ]
# statement for a batch of rows.
# ──────────────────────────────────────────────────────────────────────────────
def build_insert_array(table: str, col_names: list, rows: list, id_start: int) -> str:
    """
    Returns:
        INSERT INTO `<table>` [
            { id: `<table>`:<n>, `col1`: val, `col2`: val, ... },
            ...
        ];
    """
    objects = []
    for offset, row_data in enumerate(rows):
        record_id = id_start + offset
        fields = [f"id: `{table}`:{record_id}"]
        for col, val in zip(col_names, row_data):
            fields.append(f"`{col}`: {fmt(val)}")
        objects.append("{ " + ", ".join(fields) + " }")

    joined = ",\n    ".join(objects)
    return f"INSERT INTO `{table}` [\n    {joined}\n];"


# ──────────────────────────────────────────────────────────────────────────────
# MAIN
# ──────────────────────────────────────────────────────────────────────────────
def main():
    SEP = "=" * 62

    print()
    print(SEP)
    print("  DataWarehouse Migration  (SQL Server -> SurrealDB)")
    print(f"  Target: '{NAMESPACE}' / {DW_DB}")
    print(SEP)
    print()

    client = SurrealClient(SURREAL_URL, SURREAL_USER, SURREAL_PASS)

    # SurrealDB health check
    try:
        requests.get(f"{SURREAL_URL}/health", timeout=5).raise_for_status()
        print("[OK] SurrealDB is reachable")
    except Exception as exc:
        print(f"[FAIL] Cannot reach SurrealDB: {exc}")
        sys.exit(1)

    # SQL Server connection
    print("[..] Connecting to SQL Server (datawarehouse_DEV)...")
    try:
        conn = pyodbc.connect(DW_SQL_CONN, timeout=10)
        print("[OK] Connected to SQL Server")
    except Exception as exc:
        print(f"[FAIL] SQL Server connection failed: {exc}")
        sys.exit(1)

    cursor = conn.cursor()

    # Ensure namespace and database exist
    client.run("DEFINE NAMESPACE `Data Provisioning Engine`;")
    client.run("DEFINE DATABASE DataWarehouse;", ns=NAMESPACE)

    # Define PermissionsMap (SCHEMAFULL — the AppDB-to-DW bridge)
    client.run("""
DEFINE TABLE PermissionsMap SCHEMAFULL;
DEFINE FIELD user_id          ON TABLE PermissionsMap TYPE string;
DEFINE FIELD table_name       ON TABLE PermissionsMap TYPE string;
DEFINE FIELD column_id        ON TABLE PermissionsMap TYPE option<string>;
DEFINE FIELD authorized_value ON TABLE PermissionsMap TYPE option<string>;
DEFINE FIELD created_at       ON TABLE PermissionsMap TYPE option<datetime>;
DEFINE INDEX pm_user  ON TABLE PermissionsMap COLUMNS user_id;
DEFINE INDEX pm_table ON TABLE PermissionsMap COLUMNS table_name;
""", ns=NAMESPACE, db=DW_DB)

    # Discover all user tables
    cursor.execute("""
        SELECT t.TABLE_SCHEMA, t.TABLE_NAME, ISNULL(p.rows, 0) AS row_count
        FROM   INFORMATION_SCHEMA.TABLES t
        JOIN   sys.tables st ON st.name = t.TABLE_NAME
        JOIN   sys.partitions p ON p.object_id = st.object_id AND p.index_id IN (0,1)
        WHERE  t.TABLE_TYPE = 'BASE TABLE'
          AND  t.TABLE_NAME NOT LIKE '__EF%'
        ORDER  BY p.rows DESC, t.TABLE_NAME
    """)
    tables = cursor.fetchall()

    print()
    print(f"Found {len(tables)} tables in datawarehouse_DEV")
    print(f"Importing into '{NAMESPACE}' / {DW_DB}")
    print()
    print(f"  {'Table':<47} {'Rows':>8}  Status")
    print(f"  {'-'*47}  {'-'*8}  ------")

    grand_total = 0

    for sql_schema, table_name, row_count in tables:
        full_sql = f"[{sql_schema}].[{table_name}]"
        label    = table_name

        if row_count == 0:
            print(f"  {label:<47} {row_count:>8,}  SKIP (empty)")
            continue

        # Create a SCHEMALESS table in SurrealDB
        client.run(f"DEFINE TABLE `{table_name}` SCHEMALESS;", ns=NAMESPACE, db=DW_DB)

        # Read all rows
        try:
            cursor.execute(f"SELECT * FROM {full_sql}")
            col_names  = [d[0] for d in cursor.description]
            all_rows   = cursor.fetchall()
        except Exception as exc:
            print(f"  {label:<47} {row_count:>8,}  ERR (read): {exc}")
            continue

        # Send in INSERT_BATCH-sized chunks
        ok = err = 0
        for i in range(0, len(all_rows), INSERT_BATCH):
            chunk    = all_rows[i : i + INSERT_BATCH]
            sql_stmt = build_insert_array(table_name, col_names, chunk, id_start=i + 1)
            results  = client.run(sql_stmt, ns=NAMESPACE, db=DW_DB)

            if results:
                for r in results:
                    if isinstance(r, dict) and r.get("status") == "OK":
                        inserted = len(r.get("result", []))
                        ok += inserted if inserted else len(chunk)
                    else:
                        err += len(chunk)
            else:
                err += len(chunk)

        # Progress report for large tables
        if row_count > 5000:
            pct = int(100 * ok / row_count) if row_count else 0
            print(f"  {label:<47} {row_count:>8,}  OK ({ok:,} imported, {pct}%)")
        else:
            status = f"OK ({ok:,} rows)" if not err else f"OK ({ok:,}) [{err:,} failed]"
            print(f"  {label:<47} {row_count:>8,}  {status}")

        grand_total += ok

    conn.close()

    print()
    print(f"  {'-'*47}")
    print(f"  DataWarehouse total imported: {grand_total:,} records")

    # Verification
    print()
    print("Verification:")
    for _, table_name, row_count in tables:
        if row_count == 0:
            continue
        try:
            r = client.query(
                f"SELECT count() FROM `{table_name}` GROUP ALL;",
                ns=NAMESPACE, db=DW_DB
            )
            rows = r[0].get("result", []) if r else []
            actual = rows[0].get("count", 0) if rows else 0
            tick = "[OK]" if actual >= row_count else "[!!]"
            print(f"  {tick}  {table_name:<47} {actual:>8,} / {row_count:>8,}")
        except Exception as exc:
            print(f"  [??]  {table_name:<47} ERR: {exc}")

    print()
    print(SEP)
    print("  DataWarehouse migration complete")
    print(SEP)
    print()


if __name__ == "__main__":
    main()
