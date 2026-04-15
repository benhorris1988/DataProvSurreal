#!/usr/bin/env python3
"""
Create SurrealDB Schema - Table and Index Definitions
"""

import requests
import json

url = "http://localhost:8000/sql"
auth = ('root', 'root')
headers = {'Content-Type': 'application/json'}

def execute_query(query):
    """Execute a query and return result"""
    try:
        response = requests.post(url, auth=auth, headers=headers, data=query, timeout=10)
        return response.status_code, response.json()
    except Exception as e:
        return -1, str(e)

print()
print("=" * 70)
print("Creating SurrealDB Schema for Data Provisioning Engine")
print("=" * 70)
print()

# First, ensure we're using the right namespace and database
setup_queries = [
    ("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;", "Set namespace/database"),
]

# Create tables
table_queries = [
    ("""
    DEFINE TABLE users SCHEMAFULL
        PERMISSIONS
            FOR select ALLOW true,
            FOR create ALLOW true,
            FOR update ALLOW true,
            FOR delete ALLOW true;
    """, "Create users table"),
    
    ("""
    DEFINE TABLE virtual_groups SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create virtual_groups table"),
    
    ("""
    DEFINE TABLE datasets SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create datasets table"),
    
    ("""
    DEFINE TABLE columns SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create columns table"),
    
    ("""
    DEFINE TABLE asset_policy_groups SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create asset_policy_groups table"),
    
    ("""
    DEFINE TABLE asset_policy_columns SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create asset_policy_columns table"),
    
    ("""
    DEFINE TABLE asset_policy_conditions SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create asset_policy_conditions table"),
    
    ("""
    DEFINE TABLE virtual_group_members SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create virtual_group_members table"),
    
    ("""
    DEFINE TABLE access_requests SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create access_requests table"),
    
    ("""
    DEFINE TABLE initial_admins SCHEMAFULL
        PERMISSIONS FOR select ALLOW true, FOR create ALLOW true, FOR update ALLOW true, FOR delete ALLOW true;
    """, "Create initial_admins table"),
]

# First check what we have
print("Checking existing tables...")
status, result = execute_query("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INFO FOR DB;")
if status == 200:
    try:
        info = result[0]['result']
        tables = info.get('tables', {}) if isinstance(info, dict) else {}
        print(f"  Found {len(tables)} tables")
        for table in tables:
            print(f"    - {table}")
    except:
        print(f"  Could not parse table info")
else:
    print(f"  Status: {status}")

print()
print("Creating schema...")
print()

# Create tables
success_count = 0
for query, description in table_queries:
    full_query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; {query}"
    status, result = execute_query(full_query)
    
    if status == 200:
        print(f"  ✓ {description}")
        success_count += 1
    else:
        print(f"  ✗ {description} (Status: {status})")
        if isinstance(result, list) and len(result) > 0:
            print(f"      Error: {result[0].get('result', 'unknown')[:100]}")

print()
print("=" * 70)
print(f"Created {success_count}/{len(table_queries)} tables")
print("=" * 70)
print()

# Verify tables were created
print("Verifying tables...")
status, result = execute_query("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INFO FOR DB;")
if status == 200:
    try:
        info = result[0]['result']
        tables = info.get('tables', {}) if isinstance(info, dict) else {}
        print(f"✓ Database now contains {len(tables)} tables:")
        for table in sorted(tables.keys()):
            print(f"    - {table}")
    except Exception as e:
        print(f"  Error: {e}")
else:
    print(f"  Could not verify (Status: {status})")

print()
