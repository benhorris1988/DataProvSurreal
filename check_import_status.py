#!/usr/bin/env python3
"""
Check how much data was imported
"""

import requests

url = "http://localhost:8000/sql"
auth = ('root', 'root')

tables = [
    "users",
    "virtual_groups",
    "datasets",
    "columns",
    "asset_policy_groups",
    "asset_policy_columns",
    "asset_policy_conditions",
    "virtual_group_members",
    "access_requests",
    "initial_admins"
]

print()
print("=" * 70)
print("Checking current data in SurrealDB")
print("=" * 70)
print()

total = 0

for table in tables:
    query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT * FROM {table};"
    
    try:
        r = requests.post(
            url,
            auth=auth,
            headers={'Content-Type': 'application/json'},
            data=query,
            timeout=10
        )
        
        if r.status_code == 200:
            result = r.json()
            if result and len(result) > 0:
                res = result[0]
                if 'result' in res and res['result']:
                    count = len(res['result']) if isinstance(res['result'], list) else 1
                    print(f"  ✓ {table:30} {count:6} records")
                    total += count
                else:
                    print(f"  · {table:30}      0 records")
    except:
        pass

print()
print("=" * 70)
print(f"Total records: {total}")
print("=" * 70)
print()

if total == 0:
    print("No data found, trying batch import...")
else:
    print(f"Found {total} records! Import was partially successful.")

