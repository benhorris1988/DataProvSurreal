#!/usr/bin/env python3
"""
Verify and List Tables in SurrealDB
"""

import requests

url = "http://localhost:8000/sql"
auth = ('root', 'root')
headers = {'Content-Type': 'application/json'}

def query(q):
    """Execute query"""
    try:
        r = requests.post(url, auth=auth, headers=headers, data=q, timeout=10)
        return r.status_code, r.json()
    except Exception as e:
        return -1, str(e)

print()
print("=" * 70)
print("SurrealDB Table Verification")
print("=" * 70)
print()

# Try different ways to list tables
queries = [
    ("SELECT * FROM users LIMIT 1;", "Query users table"),
    ("SELECT * FROM datasets LIMIT 1;", "Query datasets table"),
    ("SELECT * FROM columns LIMIT 1;", "Query columns table"),
]

print("Attempting to query tables...")
print()

table_found = {}

for query_str, desc in queries:
    full_query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; {query_str}"
    status, result = query(full_query)
    
    table_name = query_str.split("FROM")[1].strip().split()[0]
    
    if status == 200 and isinstance(result, list) and len(result) > 0:
        first = result[0]
        if isinstance(first, dict) and 'result' in first:
            result_data = first['result']
            if result_data:
                print(f"✓ {table_name:20} exists with data")
                table_found[table_name] = True
            else:
                print(f"✓ {table_name:20} exists (empty)")
                table_found[table_name] = True
        else:
            print(f"? {table_name:20} unknown response format")
    else:
        print(f"✗ {table_name:20} not found or error (HTTP {status})")

print()
print("=" * 70)
print()

# Now try to import data
if table_found.get('users') or table_found.get('datasets'):
    print("Tables exist! Proceeding with data import...")
    print()
    
    # Run the import script
    import subprocess
    import os
    
    os.chdir('c:/development/DataPrivNet/migration')
    
    # Re-run the setup and import
    result = subprocess.run(['python', 'setup_and_import.py'], capture_output=True, text=True)
    print(result.stdout)
    if result.stderr:
        print("Errors:", result.stderr)
else:
    print("No tables found. Trying to create them manually...")
    
    create_queries = [
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE users;",
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE datasets;",  
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE columns;",
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE virtual_groups;",
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE asset_policy_groups;",
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE asset_policy_columns;",
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE asset_policy_conditions;",
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE virtual_group_members;",
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE access_requests;",
        "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; CREATE TABLE initial_admins;",
    ]
    
    print("Creating tables...")
    for q in create_queries:
        status, result = query(q)
        table = q.split("TABLE")[1].strip().rstrip(';')
        if status in [200, 201]:
            print(f"  ✓ {table}")
        else:
            print(f"  ? {table} (status {status})")
    
    print()
    print("Re-attempting import...")
    
    result = subprocess.run(['python', 'setup_and_import.py'], capture_output=True, text=True)
    print(result.stdout)

print()
