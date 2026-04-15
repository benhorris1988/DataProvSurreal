#!/usr/bin/env python3
"""
Verify SurrealDB data - with correct syntax
"""

import requests
import json

url = "http://localhost:8000/sql"

queries = [
    ("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT * FROM users;", "All users"),
    ("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT * FROM datasets;", "All datasets"),
    ("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT * FROM columns;", "Columns (first 5)"),
]

print()
print("=" * 60)
print("SurrealDB Data Import Verification")
print("=" * 60)
print()

all_data = {
    'users': 0,
    'datasets': 0,
    'columns': 0,
    'virtual_groups': 0,
    'asset_policy_groups': 0,
    'asset_policy_columns': 0,
    'asset_policy_conditions': 0,
    'virtual_group_members': 0,
    'access_requests': 0,
    'initial_admins': 0,
}

# Check each table
for table_name in all_data.keys():
    query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT * FROM {table_name};"
    
    try:
        response = requests.post(
            url,
            auth=('root', 'root'),
            headers={'Content-Type': 'application/json'},
            data=query,
            timeout=10
        )
        
        if response.status_code == 200:
            result = response.json()
            if result and len(result) > 0:
                first_result = result[0]
                if 'result' in first_result and first_result['result']:
                    count = len(first_result['result']) if isinstance(first_result['result'], list) else 1
                    all_data[table_name] = count
                    print(f"  {table_name:30} ✓ {count:6} records")
                else:
                    print(f"  {table_name:30} × Error: {first_result.get('status', 'unknown')}")
            else:
                print(f"  {table_name:30} × No response")
        else:
            print(f"  {table_name:30} × HTTP {response.status_code}")
    except Exception as e:
        print(f"  {table_name:30} × Exception: {str(e)[:30]}")

print()
print("=" * 60)
total = sum(all_data.values())
print(f"Total Records: {total}")
print("=" * 60)
print()

if total > 0:
    print("✓ DATA SUCCESSFULLY IMPORTED TO SURREAL DB!")
    print()
    print("Breakdown:")
    for table, count in sorted(all_data.items()):
        print(f"  • {table:30} {count:4} records")
else:
    print("✗ No data found")

print()
