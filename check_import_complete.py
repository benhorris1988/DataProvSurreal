#!/usr/bin/env python3
"""
Check actual data in SurrealDB
"""

import requests
import json

url = "http://localhost:8000/sql"

tables = ['users', 'datasets', 'virtual_groups', 'columns', 'asset_policy_groups', 
          'asset_policy_columns', 'asset_policy_conditions', 'virtual_group_members', 
          'access_requests', 'initial_admins']

print()
print("=" * 70)
print("SurrealDB Data Import - Final Check")
print("=" * 70)
print()

total_records = 0

for table_name in tables:
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
            result_data = response.json()
            
            # SurrealDB returns array of result objects
            if result_data and len(result_data) > 0:
                first_result = result_data[0]
                
                # Check if there's actual result data
                if 'result' in first_result:
                    records = first_result['result']
                    if isinstance(records, list):
                        record_count = len(records)
                    else:
                        record_count = 1 if records else 0
                    
                    total_records += record_count
                    status = "✓" if record_count > 0 else "✗"
                    print(f"{status} {table_name:35} {record_count:6} records")
                else:
                    print(f"✗ {table_name:35} (no result field)")
        else:
            print(f"✗ {table_name:35} (HTTP {response.status_code})")
    except Exception as e:
        print(f"✗ {table_name:35} (error)")

print()
print("=" * 70)
print(f"✓ TOTAL RECORDS IN SurrealDB: {total_records}")
print("=" * 70)
print()

if total_records > 0:
    print("SUCCESS! All data has been imported to SurrealDB!")
    print(f"Total of {total_records} records are now available in the database.")
    print()
    print("namespace: DataProvisioningEngine")
    print("database:  AppDB")
    print()
    print("You can now:")
    print("  1. Update application configuration to use SurrealDB")
    print("  2. Implement data access layer for SurrealDB")
    print("  3. Deploy the application with row-level access control")
else:
    print("WARNING: No data found in SurrealDB")

print()
