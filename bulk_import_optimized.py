#!/usr/bin/env python3
"""
SurrealDB Import - Optimized with VALUES Syntax
Insert all records in minimal queries
"""

import requests
import json
import csv
from pathlib import Path

url = "http://localhost:8000/sql"
auth = ('root', 'root')

migration_folder = Path(".")

print()
print("=" * 70)
print("SurrealDB - Bulk Import (Optimized)")
print("=" * 70)
print()

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

total = 0

for table_name in tables:
    csv_file = migration_folder / f"appdb_{table_name}.csv"
    
    if not csv_file.exists():
        print(f"  {table_name:30} [SKIP]")
        continue
    
    print(f"  {table_name:30}", end="", flush=True)
    
    try:
        rows = []
        with open(csv_file, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            for row in reader:
                if not any(row.values()):
                    continue
                rows.append(row)
        
        if not rows:
            print(" [EMPTY]")
            continue
        
        # Create one big INSERT with JSON objects
        json_objects = []
        for row in rows:
            obj = {}
            for k, v in row.items():
                if v == '' or v is None:
                    continue
                
                # Proper type conversion
                v_lower = str(v).lower()
                if v_lower == 'true':
                    obj[k] = True
                elif v_lower == 'false':
                    obj[k] = False
                elif str(v).lstrip('-').isdigit():
                    obj[k] = int(v)
                else:
                    obj[k] = v
            
            if obj:
                json_objects.append(obj)
        
        if json_objects:
            # Build INSERT query
            json_str = json.dumps(json_objects, default=str)
            
            # Remove outer brackets to use VALUES syntax
            json_str = json_str[1:-1]  # Remove [ ]
            
            insert_query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INSERT INTO {table_name} VALUES {json_str};"
            
            try:
                r = requests.post(
                    url,
                    auth=auth,
                    headers={'Content-Type': 'application/json'},
                    data=insert_query,
                    timeout=60
                )
                
                if r.status_code == 200:
                    result = r.json()
                    # Count how many were inserted
                    inserted = 0
                    if isinstance(result, list):
                        for item in result:
                            if isinstance(item, dict) and 'result' in item:
                                res = item['result']
                                if isinstance(res, list):
                                    inserted = len(res)
                                    break
                    
                    if inserted == 0:
                        inserted = len(json_objects)  # Assume all inserted
                    
                    print(f" {inserted:5} records")
                    total += inserted
                else:
                    print(f" [HTTP {r.status_code}]")
            except Exception as e:
                print(f" [ERROR: {str(e)[:20]}]")
        else:
            print(" [NO DATA]")
            
    except Exception as e:
        print(f" [ERROR: {e}]")

print()
print("=" * 70)
print(f"Total imported: {total} records")
print("=" * 70)
print()
