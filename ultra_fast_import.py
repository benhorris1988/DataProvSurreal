#!/usr/bin/env python3
"""
Ultra-Fast SurrealDB Import - Using Raw SQL Inserts
"""

import requests
import csv
from pathlib import Path

url = "http://localhost:8000/sql"

print("Starting optimized import...")
print()

migration_folder = Path("c:/development/DataPrivNet/migration")

# Import one table at a time with optimized queries
tables_data = {
    "users": "appdb_users.csv",
    "virtual_groups": "appdb_virtual_groups.csv",
    "datasets": "appdb_datasets.csv",
    "columns": "appdb_columns.csv",
    "asset_policy_groups": "appdb_asset_policy_groups.csv",
    "asset_policy_columns": "appdb_asset_policy_columns.csv",
    "asset_policy_conditions": "appdb_asset_policy_conditions.csv",
    "virtual_group_members": "appdb_virtual_group_members.csv",
    "access_requests": "appdb_access_requests.csv",
    "initial_admins": "appdb_initial_admins.csv"
}

for table_name, csv_filename in tables_data.items():
    csv_file = migration_folder / csv_filename
    if not csv_file.exists():
        print(f"  {table_name:30} [SKIP] no file")
        continue
    
    print(f"  {table_name:30}", end="", flush=True)
    
    try:
        count = 0
        with open(csv_file, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            for row in reader:
                # Skip if all values are empty
                if not any(row.values()):
                    continue
                
                # Build INSERT statement for this row
                fields = []
                values = []
                
                for k, v in row.items():
                    if v == '' or v is None:
                        continue
                    
                    fields.append(f"`{k}`")
                    
                    # Format value
                    if v.lower() == 'true':
                        values.append('true')
                    elif v.lower() == 'false':
                        values.append('false')
                    elif v.lstrip('-').isdigit():
                        values.append(v)
                    else:
                        # String - escape quotes
                        safe_v = v.replace("'", "''")
                        values.append(f"'{safe_v}'")
                
                if fields:
                    insert = f"INSERT INTO {table_name} ({', '.join(fields)}) VALUES ({', '.join(values)});"
                    full_query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; {insert}"
                    
                    try:
                        r = requests.post(
                            url,
                            auth=('root', 'root'),
                            headers={'Content-Type': 'application/json'},
                            data=full_query,
                            timeout=5
                        )
                        if r.status_code == 200:
                            count += 1
                    except:
                        pass
        
        print(f" {count:5} records")
        
    except Exception as e:
        print(f" ERROR: {e}")

print()
print("Import complete!")
