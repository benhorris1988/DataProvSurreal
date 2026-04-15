#!/usr/bin/env python3
"""
Create SurrealDB Schema - Simplified Version
"""

import requests
import json

url = "http://localhost:8000/sql"
auth = ('root', 'root')
headers = {'Content-Type': 'application/json'}

def execute_query(query_string):
    """Execute a query"""
    try:
        response = requests.post(url, auth=auth, headers=headers, data=query_string, timeout=10)
        status = response.status_code
        try:
            result = response.json()
        except:
            result = response.text
        return status, result
    except Exception as e:
        return -1, str(e)

print()
print("=" * 70)
print("Creating SurrealDB Schema - DataProvisioningEngine/AppDB")
print("=" * 70)
print()

# Query to create all tables at once using simpler syntax
schema_query = """
USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;

DEFINE TABLE users;
DEFINE TABLE virtual_groups;
DEFINE TABLE datasets;
DEFINE TABLE columns;
DEFINE TABLE asset_policy_groups;
DEFINE TABLE asset_policy_columns;
DEFINE TABLE asset_policy_conditions;
DEFINE TABLE virtual_group_members;
DEFINE TABLE access_requests;
DEFINE TABLE initial_admins;
"""

print("Creating tables...")
status, result = execute_query(schema_query)

if isinstance(result, list):
    # Check individual results
    success_count = 0
    for i, res in enumerate(result):
        if isinstance(res, dict):
            if res.get('status') == 'OK' or (200 <= status < 300):
                success_count += 1
            else:
                error = res.get('result', 'unknown')
                if 'already defined' in str(error).lower():
                    success_count += 1
                    print(f"  Table {i} already exists")
                else:
                    print(f"  Result {i}: {error}")
    
    if success_count > 0:
        print(f"✓ {success_count} table creation results")
else:
    print(f"Status: {status}")
    print(f"Result: {str(result)[:200]}")

print()
print("Verifying tables...")
verify_query = "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INFO FOR DB;"
status, result = execute_query(verify_query)

if status == 200 and isinstance(result, list) and len(result) > 0:
    try:
        db_info = result[0]['result']
        if isinstance(db_info, dict) and 'tables' in db_info:
            tables = db_info['tables']
            print(f"✓ Database contains {len(tables)} tables:")
            for table_name in sorted(tables.keys()):
                print(f"    [{len(tables)}] {table_name}")
        else:
            print(f"Unexpected format: {str(db_info)[:100]}")
    except Exception as e:
        print(f"Error parsing: {e}")
        print(f"Raw: {result}")
else:
    print(f"Status: {status}")
    if isinstance(result, str):
        print(f"Error: {result[:200]}")

print()
print("=" * 70)
print()
