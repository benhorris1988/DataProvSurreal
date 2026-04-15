#!/usr/bin/env python3
"""
DataPrivNet SurrealDB — Security & Graph Upgrade (v2)
=====================================================
Run this after migrate.py to apply the full security model.

What this does
--------------
1. AppDB — DEFINE ACCESS   : email-based JWT auth so the .NET app can sign
                              users in after Windows Auth validation
2. AppDB — Table PERMISSIONS: every table gets proper role-based RLS
                              (Admin / IAO / IAA / User)
3. AppDB — Graph edge tables: member_of, has_access, governed_by
4. AppDB — Populate edges  : from virtual_group_members + approved access_requests
5. DataWarehouse — PermissionsMap schema: make SCHEMAFULL + add missing indexes
6. DataWarehouse — Table PERMISSIONS: every DW table enforces PermissionsMap
                              so users only see rows they are authorised for

Usage
-----
    pip install requests
    python upgrade.py

After running
-------------
The .NET app can authenticate a user like this (SurrealDB HTTP API):
    POST /signin
    { "ns": "Data Provisioning Engine", "db": "AppDB",
      "ac": "user_token", "email": "alice@example.com" }
    -> returns a JWT.  Pass it as Bearer token on subsequent requests.

$auth inside SurrealDB will then contain the full user record:
    $auth.id    -> users:2
    $auth.role  -> "IAO"
    $auth.email -> "alice@example.com"
"""

import json
import sys

import requests

# ──────────────────────────────────────────────────────────────────────────────
# CONFIGURATION
# ──────────────────────────────────────────────────────────────────────────────
SURREAL_URL  = "http://localhost:8000"
SURREAL_USER = "root"
SURREAL_PASS = "root"
NAMESPACE    = "Data Provisioning Engine"
APPDB        = "AppDB"
DW_DB        = "DataWarehouse"


# ──────────────────────────────────────────────────────────────────────────────
# CLIENT
# ──────────────────────────────────────────────────────────────────────────────
class SurrealClient:
    def __init__(self, url, user, password):
        self.url  = url
        self.auth = (user, password)

    def query(self, sql: str, ns: str = None, db: str = None) -> list:
        headers = {"Accept": "application/json", "Content-Type": "text/plain"}
        if ns:
            headers["surreal-ns"] = ns
        if db:
            headers["surreal-db"] = db
        resp = requests.post(
            f"{self.url}/sql",
            data=sql.encode("utf-8"),
            headers=headers,
            auth=self.auth,
            timeout=60,
        )
        if resp.status_code >= 400:
            raise RuntimeError(f"HTTP {resp.status_code}: {resp.text[:400]}")
        return resp.json()

    def run(self, sql: str, ns: str = None, db: str = None) -> list | None:
        try:
            return self.query(sql, ns=ns, db=db)
        except Exception as exc:
            print(f"    [ERR] {exc}")
            return None

    def ok(self, sql: str, ns: str = None, db: str = None) -> bool:
        """Returns True if all statements in sql succeeded."""
        results = self.run(sql, ns=ns, db=db)
        if results is None:
            return False
        return all(
            isinstance(r, dict) and r.get("status") == "OK"
            for r in results
        )


# ──────────────────────────────────────────────────────────────────────────────
# PHASE 1 — AppDB: DEFINE ACCESS  (email-based JWT auth)
# ──────────────────────────────────────────────────────────────────────────────
DEFINE_ACCESS = """\
-- Windows Auth validates the user at the .NET layer.
-- The backend then calls SIGNIN with the user's email to get a SurrealDB JWT.
-- The JWT embeds the full user record so $auth.id / $auth.role / $auth.email
-- are available in every PERMISSIONS clause.
DEFINE ACCESS OVERWRITE user_token ON DATABASE TYPE RECORD
    SIGNIN (
        SELECT * FROM users WHERE email = $email
    )
    DURATION FOR SESSION 7d, FOR TOKEN 1h;
"""


# ──────────────────────────────────────────────────────────────────────────────
# PHASE 2 — AppDB: Table PERMISSIONS
# ──────────────────────────────────────────────────────────────────────────────
# Role hierarchy used throughout:
#   Admin  — full control everywhere
#   IAO    — Information Asset Owner: manage datasets/policies, approve requests
#   IAA    — Information Asset Administrator: approve requests, view policies
#   User   — read catalogue, raise access requests, view own requests

APPDB_PERMISSIONS = """\
-- ── users ────────────────────────────────────────────────────────────────────
-- Any authenticated user can read the user list (needed for group management).
-- Only Admins can create/modify/delete users.
DEFINE TABLE OVERWRITE users TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, update, delete WHERE $auth.role = 'Admin';

-- ── virtual_groups ────────────────────────────────────────────────────────────
-- Everyone can browse groups (needed to request access via a group).
-- Group owners and Admins can update; only Admins can delete.
DEFINE TABLE OVERWRITE virtual_groups TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create WHERE $auth != NONE,
        FOR update WHERE $auth.id = owner_id OR $auth.role = 'Admin',
        FOR delete WHERE $auth.role = 'Admin';

-- ── datasets ─────────────────────────────────────────────────────────────────
-- Public catalogue — any authenticated user can browse dataset metadata.
-- IAO/Admin can create and manage datasets.
DEFINE TABLE OVERWRITE datasets TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, update WHERE $auth.role IN ['Admin', 'IAO'],
        FOR delete WHERE $auth.role = 'Admin';

-- ── columns ──────────────────────────────────────────────────────────────────
-- Column metadata is part of the public catalogue.
-- IAO/Admin can manage column definitions.
DEFINE TABLE OVERWRITE columns TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, update WHERE $auth.role IN ['Admin', 'IAO'],
        FOR delete WHERE $auth.role = 'Admin';

-- ── asset_policy_groups ───────────────────────────────────────────────────────
-- Policy groups are visible to IAO/IAA/Admin only.
-- Owners and Admins can modify.
DEFINE TABLE OVERWRITE asset_policy_groups TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR create WHERE $auth.role IN ['Admin', 'IAO'],
        FOR update WHERE $auth.id = owner_id OR $auth.role = 'Admin',
        FOR delete WHERE $auth.role = 'Admin';

-- ── asset_policy_columns ──────────────────────────────────────────────────────
-- Column-level policy rules — IAO/IAA/Admin only.
DEFINE TABLE OVERWRITE asset_policy_columns TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR create, update WHERE $auth.role IN ['Admin', 'IAO'],
        FOR delete WHERE $auth.role = 'Admin';

-- ── asset_policy_conditions ───────────────────────────────────────────────────
-- Row-filter conditions — IAO/IAA/Admin only.
DEFINE TABLE OVERWRITE asset_policy_conditions TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR create, update WHERE $auth.role IN ['Admin', 'IAO'],
        FOR delete WHERE $auth.role = 'Admin';

-- ── virtual_group_members ─────────────────────────────────────────────────────
-- Members can see who else is in their groups.
-- Group owners and Admins can add/remove members.
DEFINE TABLE OVERWRITE virtual_group_members TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, delete WHERE $auth.role = 'Admin'
            OR (SELECT 1 FROM virtual_groups WHERE id = $parent.group_id
                AND owner_id = $auth.id LIMIT 1).len() > 0;

-- ── access_requests ───────────────────────────────────────────────────────────
-- Users see their own requests; IAO/IAA/Admin see all.
-- Create: any authenticated user.
-- Update (approve/reject): IAO/IAA/Admin.
-- Delete: Admin only.
DEFINE TABLE OVERWRITE access_requests TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE user_id = $auth.id
                      OR $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR create WHERE $auth != NONE,
        FOR update WHERE $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR delete WHERE $auth.role = 'Admin';

-- ── reports ───────────────────────────────────────────────────────────────────
-- Report catalogue is visible to all authenticated users.
DEFINE TABLE OVERWRITE reports TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, update WHERE $auth.role IN ['Admin', 'IAO'],
        FOR delete WHERE $auth.role = 'Admin';

-- ── report_datasets ───────────────────────────────────────────────────────────
DEFINE TABLE OVERWRITE report_datasets TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, update WHERE $auth.role IN ['Admin', 'IAO'],
        FOR delete WHERE $auth.role = 'Admin';

-- ── initial_admins ────────────────────────────────────────────────────────────
-- Bootstrap table — Admin eyes only.
DEFINE TABLE OVERWRITE initial_admins TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select, create, update, delete WHERE $auth.role = 'Admin';
"""


# ──────────────────────────────────────────────────────────────────────────────
# PHASE 3 — AppDB: Graph edge tables
# ──────────────────────────────────────────────────────────────────────────────
# SurrealDB TYPE RELATION tables are first-class graph edges.
# They store the relationship itself (and any metadata) and can be traversed
# with -> / <- graph operators in SurrealQL.

GRAPH_EDGE_SCHEMA = """\
-- ── member_of: users -> virtual_groups ───────────────────────────────────────
-- Replaces the flat virtual_group_members junction table with a proper edge.
-- Query: SELECT ->member_of->virtual_groups FROM users:1
DEFINE TABLE OVERWRITE member_of TYPE RELATION IN users OUT virtual_groups SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, delete WHERE $auth.role = 'Admin'
            OR (SELECT 1 FROM virtual_groups WHERE id = $parent.out
                AND owner_id = $auth.id LIMIT 1).len() > 0;
DEFINE FIELD OVERWRITE added_at ON member_of TYPE datetime DEFAULT time::now();
DEFINE INDEX OVERWRITE member_of_unique ON member_of FIELDS in, out UNIQUE;

-- ── has_access: users -> datasets ─────────────────────────────────────────────
-- Created when an access_request is Approved.
-- Carries the RLS filter(s) that apply to this user/dataset combination.
-- Query: SELECT ->has_access->datasets FROM users:1
--        SELECT <-has_access<-users  FROM datasets:14
DEFINE TABLE OVERWRITE has_access TYPE RELATION IN users OUT datasets SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth.id = in OR $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR create, update WHERE $auth.role IN ['Admin', 'IAO', 'IAA'],
        FOR delete WHERE $auth.role = 'Admin';
DEFINE FIELD OVERWRITE granted_at      ON has_access TYPE datetime DEFAULT time::now();
DEFINE FIELD OVERWRITE granted_by      ON has_access TYPE option<record<users>>;
DEFINE FIELD OVERWRITE rls_filters     ON has_access TYPE option<string>;
DEFINE FIELD OVERWRITE policy_group_id ON has_access TYPE option<record<asset_policy_groups>>;
DEFINE INDEX OVERWRITE has_access_unique ON has_access FIELDS in, out UNIQUE;

-- ── governed_by: datasets -> asset_policy_groups ──────────────────────────────
-- Links a dataset to the policy group that governs its column/row restrictions.
-- Query: SELECT ->governed_by->asset_policy_groups FROM datasets:1
DEFINE TABLE OVERWRITE governed_by TYPE RELATION IN datasets OUT asset_policy_groups SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create, update WHERE $auth.role IN ['Admin', 'IAO'],
        FOR delete WHERE $auth.role = 'Admin';
DEFINE INDEX OVERWRITE governed_by_unique ON governed_by FIELDS in, out UNIQUE;
"""


# ──────────────────────────────────────────────────────────────────────────────
# PHASE 4 — AppDB: Populate graph edges from existing data
# ──────────────────────────────────────────────────────────────────────────────
def populate_graph_edges(client: SurrealClient) -> tuple[int, int, int]:
    """
    Reads existing flat data and creates graph edges.
    Returns (member_of_count, has_access_count, governed_by_count).
    """
    mo_count = ha_count = gb_count = 0

    # ── member_of edges (from virtual_group_members) ──────────────────────────
    result = client.query(
        "SELECT group_id, user_id FROM virtual_group_members;",
        ns=NAMESPACE, db=APPDB
    )
    members = result[0].get("result", []) if result else []

    for row in members:
        gid = row.get("group_id")
        uid = row.get("user_id")
        if not gid or not uid:
            continue
        r = client.run(
            f"RELATE {uid}->member_of->{gid} SET added_at = time::now();",
            ns=NAMESPACE, db=APPDB
        )
        if r and r[0].get("status") == "OK":
            mo_count += 1

    # ── has_access edges (from approved access_requests) ─────────────────────
    result = client.query(
        """SELECT user_id, dataset_id, reviewed_by, reviewed_at,
                  requested_rls_filters, policy_group_id
           FROM access_requests
           WHERE status = 'Approved';""",
        ns=NAMESPACE, db=APPDB
    )
    requests_rows = result[0].get("result", []) if result else []

    for row in requests_rows:
        uid  = row.get("user_id")
        did  = row.get("dataset_id")
        if not uid or not did:
            continue

        # Build SET clause
        fields = [
            f"granted_at = time::now()",
        ]
        if row.get("reviewed_by"):
            fields.append(f"granted_by = {row['reviewed_by']}")
        if row.get("requested_rls_filters"):
            f_val = row["requested_rls_filters"].replace('"', '\\"')
            fields.append(f'rls_filters = "{f_val}"')
        if row.get("policy_group_id"):
            fields.append(f"policy_group_id = {row['policy_group_id']}")

        set_clause = ", ".join(fields)
        r = client.run(
            f"RELATE {uid}->has_access->{did} SET {set_clause};",
            ns=NAMESPACE, db=APPDB
        )
        if r and r[0].get("status") == "OK":
            ha_count += 1

    # ── governed_by edges (from asset_policy_groups) ──────────────────────────
    result = client.query(
        "SELECT id, dataset_id FROM asset_policy_groups;",
        ns=NAMESPACE, db=APPDB
    )
    apgs = result[0].get("result", []) if result else []

    for row in apgs:
        apg_id     = row.get("id")
        dataset_id = row.get("dataset_id")
        if not apg_id or not dataset_id:
            continue
        r = client.run(
            f"RELATE {dataset_id}->governed_by->{apg_id};",
            ns=NAMESPACE, db=APPDB
        )
        if r and r[0].get("status") == "OK":
            gb_count += 1

    return mo_count, ha_count, gb_count


# ──────────────────────────────────────────────────────────────────────────────
# PHASE 5 — DataWarehouse: Harden PermissionsMap schema
# ──────────────────────────────────────────────────────────────────────────────
# The existing data uses PascalCase field names (UserID, TableName, ColumnID,
# AuthorizedValue).  We keep those names and make the table SCHEMAFULL so
# SurrealDB enforces the shape going forward.

PERMISSIONS_MAP_SCHEMA = """\
DEFINE TABLE OVERWRITE PermissionsMap TYPE NORMAL SCHEMAFULL
    PERMISSIONS
        FOR select, create, update, delete WHERE $auth.role IN ['Admin', 'IAO', 'IAA'];
DEFINE FIELD OVERWRITE UserID          ON PermissionsMap TYPE string;
DEFINE FIELD OVERWRITE TableName       ON PermissionsMap TYPE string;
DEFINE FIELD OVERWRITE ColumnID        ON PermissionsMap TYPE option<string>;
DEFINE FIELD OVERWRITE AuthorizedValue ON PermissionsMap TYPE option<string>;
DEFINE FIELD OVERWRITE CreatedDate     ON PermissionsMap TYPE option<datetime>;
DEFINE INDEX OVERWRITE pm_user       ON PermissionsMap FIELDS UserID;
DEFINE INDEX OVERWRITE pm_table      ON PermissionsMap FIELDS TableName;
DEFINE INDEX OVERWRITE pm_user_table ON PermissionsMap FIELDS UserID, TableName;
"""


# ──────────────────────────────────────────────────────────────────────────────
# PHASE 6 — DataWarehouse: Apply PERMISSIONS to every data table
# ──────────────────────────────────────────────────────────────────────────────
# Security model for DataWarehouse rows:
#
#   Admin / IAO  -> see everything in every table
#   Everyone else -> can only see a row if there is a PermissionsMap entry where:
#       UserID    = their email
#       TableName = this table
#       AND either:
#           ColumnID is NONE  (unrestricted — they see all rows)
#           OR  the current row's value for that column = AuthorizedValue
#
# The $parent variable refers to the current row being evaluated.
# $parent[ColumnID] performs dynamic field access using ColumnID as the key.

DW_TABLE_PERMISSIONS_TEMPLATE = """\
DEFINE TABLE OVERWRITE `{table}` TYPE ANY SCHEMALESS
    PERMISSIONS
        FOR select WHERE (
            $auth.role IN ['Admin', 'IAO']
            OR (
                SELECT 1 FROM PermissionsMap
                WHERE UserID    = $auth.email
                  AND TableName = '{table}'
                  AND (ColumnID = NONE OR $parent[ColumnID] = AuthorizedValue)
                LIMIT 1
            ).len() > 0
        )
        FOR create, update, delete WHERE $auth.role IN ['Admin', 'IAO'];
"""


def apply_dw_permissions(client: SurrealClient) -> int:
    """Apply row-level permissions to all DataWarehouse tables (except PermissionsMap)."""
    result = client.query("INFO FOR DB;", ns=NAMESPACE, db=DW_DB)
    if not result or result[0].get("status") != "OK":
        print("  [ERR] Could not query DataWarehouse tables")
        return 0

    tables = list(result[0]["result"]["tables"].keys())
    data_tables = [t for t in tables if t != "PermissionsMap"]

    count = 0
    for table in data_tables:
        sql = DW_TABLE_PERMISSIONS_TEMPLATE.format(table=table)
        if client.ok(sql, ns=NAMESPACE, db=DW_DB):
            count += 1
        else:
            print(f"    [WARN] Failed to set permissions on {table}")

    return count


# ──────────────────────────────────────────────────────────────────────────────
# VERIFICATION
# ──────────────────────────────────────────────────────────────────────────────
def verify(client: SurrealClient) -> None:
    print()

    # Check ACCESS is defined
    r = client.query("INFO FOR DB;", ns=NAMESPACE, db=APPDB)
    accesses = r[0]["result"].get("accesses", {}) if r else {}
    if "user_token" in accesses:
        print("  [OK] DEFINE ACCESS user_token — present")
    else:
        print("  [!!] DEFINE ACCESS user_token — NOT FOUND")

    # Check graph edge tables
    tables = r[0]["result"].get("tables", {}) if r else {}
    for edge in ["member_of", "has_access", "governed_by"]:
        if edge in tables:
            print(f"  [OK] Edge table '{edge}' — defined")
        else:
            print(f"  [!!] Edge table '{edge}' — NOT FOUND")

    # Edge counts
    for edge in ["member_of", "has_access", "governed_by"]:
        res = client.query(
            f"SELECT count() FROM {edge} GROUP ALL;",
            ns=NAMESPACE, db=APPDB
        )
        rows = res[0].get("result", []) if res else []
        n = rows[0].get("count", 0) if rows else 0
        print(f"  [OK] {edge:<22} {n:>3} edges")

    # Check DW permissions
    r2 = client.query("INFO FOR DB;", ns=NAMESPACE, db=DW_DB)
    dw_tables = r2[0]["result"].get("tables", {}) if r2 else {}
    none_count = sum(
        1 for v in dw_tables.values() if "PERMISSIONS NONE" in v
    )
    if none_count == 0:
        print(f"  [OK] DataWarehouse — all {len(dw_tables)} tables have permissions")
    else:
        print(f"  [!!] DataWarehouse — {none_count} tables still have PERMISSIONS NONE")

    # Demo graph query
    res = client.query(
        "SELECT id, ->has_access->datasets AS accessible_datasets FROM users;",
        ns=NAMESPACE, db=APPDB
    )
    if res and res[0].get("status") == "OK":
        print()
        print("  Graph query — datasets accessible per user:")
        for u in res[0].get("result", []):
            ds = u.get("accessible_datasets", [])
            print(f"    {str(u['id']):<16} -> {len(ds)} dataset(s): {ds}")


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
    print(f"{BOLD}  DataPrivNet SurrealDB — Security Upgrade (v2){RESET}")
    print(f"{BOLD}{SEP}{RESET}")

    client = SurrealClient(SURREAL_URL, SURREAL_USER, SURREAL_PASS)

    # Health check
    try:
        requests.get(f"{SURREAL_URL}/health", timeout=5).raise_for_status()
        print(f"\n{GREEN}[OK] SurrealDB reachable at {SURREAL_URL}{RESET}")
    except Exception as exc:
        print(f"\n{RED}[!!] Cannot reach SurrealDB: {exc}{RESET}")
        sys.exit(1)

    # ── 1. DEFINE ACCESS ──────────────────────────────────────────────────────
    header("[1/6]  AppDB — DEFINE ACCESS  (JWT auth via email)")
    if client.ok(DEFINE_ACCESS, ns=NAMESPACE, db=APPDB):
        print("  user_token access defined")
        print("  Session duration: 7 days  |  Token duration: 1 hour")
        print()
        print("  To sign a user in from the .NET app:")
        print('    POST /signin')
        print('    { "ns": "Data Provisioning Engine", "db": "AppDB",')
        print('      "ac": "user_token", "email": "alice@example.com" }')
    else:
        print(f"{RED}  Failed — check SurrealDB version (requires 3.x){RESET}")
        sys.exit(1)

    # ── 2. AppDB Table Permissions ────────────────────────────────────────────
    header("[2/6]  AppDB — Table PERMISSIONS  (all 11 tables)")
    results = client.query(APPDB_PERMISSIONS, ns=NAMESPACE, db=APPDB)
    if results:
        ok  = sum(1 for r in results if isinstance(r, dict) and r.get("status") == "OK")
        err = len(results) - ok
        print(f"  {ok} statements OK" + (f"  [{err} failed]" if err else ""))
    else:
        print(f"{RED}  Failed to apply permissions{RESET}")

    # ── 3. Graph Edge Tables ──────────────────────────────────────────────────
    header("[3/6]  AppDB — Graph edge tables  (member_of, has_access, governed_by)")
    results = client.query(GRAPH_EDGE_SCHEMA, ns=NAMESPACE, db=APPDB)
    if results:
        ok  = sum(1 for r in results if isinstance(r, dict) and r.get("status") == "OK")
        err = len(results) - ok
        print(f"  {ok} statements OK" + (f"  [{err} failed]" if err else ""))
    else:
        print(f"{RED}  Failed to define edge tables{RESET}")

    # ── 4. Populate Graph Edges ───────────────────────────────────────────────
    header("[4/6]  AppDB — Populate graph edges from existing data")
    mo, ha, gb = populate_graph_edges(client)
    print(f"  member_of edges created  : {mo}  (users -> virtual_groups)")
    print(f"  has_access edges created : {ha}  (users -> datasets, from approved requests)")
    print(f"  governed_by edges created: {gb}  (datasets -> asset_policy_groups)")

    # ── 5. PermissionsMap Schema ──────────────────────────────────────────────
    header("[5/6]  DataWarehouse — Harden PermissionsMap schema")
    if client.ok(PERMISSIONS_MAP_SCHEMA, ns=NAMESPACE, db=DW_DB):
        # Count existing entries
        r = client.query(
            "SELECT count() FROM PermissionsMap GROUP ALL;",
            ns=NAMESPACE, db=DW_DB
        )
        rows = r[0].get("result", []) if r else []
        n = rows[0].get("count", 0) if rows else 0
        print(f"  PermissionsMap — SCHEMAFULL, {n} existing entries preserved")
    else:
        print(f"{RED}  Failed to update PermissionsMap{RESET}")

    # ── 6. DataWarehouse Table Permissions ───────────────────────────────────
    header("[6/6]  DataWarehouse — Apply row-level PERMISSIONS to all tables")
    applied = apply_dw_permissions(client)
    print(f"  {applied} DataWarehouse tables now enforce PermissionsMap RLS")

    # ── Verification ─────────────────────────────────────────────────────────
    header("Verification")
    verify(client)

    # ── Summary ──────────────────────────────────────────────────────────────
    print()
    print(f"{BOLD}{SEP}{RESET}")
    print(f"{BOLD}  {GREEN}Upgrade complete{RESET}{BOLD} — security model is live{RESET}")
    print(f"{BOLD}{SEP}{RESET}")
    print()
    print("  What's enforced now:")
    print("  • JWT auth via email (Windows Auth -> SurrealDB token)")
    print("  • Role-based permissions on all 11 AppDB tables")
    print("  • Graph edges: member_of, has_access, governed_by")
    print("  • DataWarehouse rows filtered by PermissionsMap per user")
    print()
    print("  Useful graph queries:")
    print('    SELECT ->has_access->datasets FROM users:1;')
    print('    SELECT <-has_access<-users     FROM datasets:14;')
    print('    SELECT ->member_of->virtual_groups FROM users:4;')
    print('    SELECT ->governed_by->asset_policy_groups FROM datasets:1;')
    print()
    print("  To add access when a request is approved:")
    print('    RELATE users:1->has_access->datasets:14')
    print('      SET granted_by = users:3, rls_filters = \'{"Sector":"Marine"}\';')
    print()


if __name__ == "__main__":
    main()
