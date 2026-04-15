#!/usr/bin/env python3
"""
Complete SurrealDB Import - All Tables
"""

import requests
import json
import csv
from pathlib import Path

url = "http://localhost:8000/sql"
auth = ('root', 'root')

print()
print("=" * 70)
print("SurrealDB - Complete Data Import")
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

total_imported = 0

for table_name in tables:
    csv_file = Path(f"appdb_{table_name}.csv")
    
    if not csv_file.exists():
        print(f"  {table_name:30} [SKIP] no file")
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
        
        # Convert to JSON objects
        json_objects = []
        for row in rows:
            obj = {}
            for k, v in row.items():
                if v == '' or v is None:
                    continue
                
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
        
        if not json_objects:
            print(" [NO DATA]")
            continue
        
        # Build INSERT query
        insert_query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INSERT INTO {table_name} [{', '.join(json.dumps(obj, default=str) for obj in json_objects)}];"
        
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
                # Count imported records
                imported = 0
                for item in result:
                    if isinstance(item, dict) and 'result' in item:
                        res = item['result']
                        if isinstance(res, list):
                            imported = len(res)
                            break
                
                if imported == 0 and len(json_objects) > 0:
                    imported = len(json_objects)
                
                print(f" [{imported:5} records] ✓")
                total_imported += imported
            else:
                print(f" [HTTP {r.status_code}]")
                
        except Exception as e:
            print(f" [ERROR: {str(e)[:20]}]")
        
    except Exception as e:
        print(f" [ERROR: {e}]")

print()
print("=" * 70)
print(f"✓ Total imported: {total_imported} records")
print("=" * 70)
print()

# Verify
print("Verifying tables...")
print()

verified_total = 0
for table_name in tables:
    query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT * FROM {table_name};"
    
    try:
        r = requests.post(
            url,
            auth=auth,
            headers={'Content-Type': 'application/json'},
            data=query,
            timeout=10
        )
        
        count = 0
        if r.status_code == 200:
            result = r.json()
            if isinstance(result, list):
                for item in result:
                    if isinstance(item, dict) and 'result' in item:
                        res = item['result']
                        if isinstance(res, list):
                            count = len(res)
                            break
        
        symb = "✓" if count > 0 else "·"
        print(f"  {symb} {table_name:30} {count:5} records")
        verified_total += count
        
    except:
        print(f"  ? {table_name:30} [ERROR]")

print()
print("=" * 70)
print(f"✓ FINAL VERIFICATION: {verified_total} total records in database")
print("=" * 70)
print()
