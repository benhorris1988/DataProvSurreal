#!/usr/bin/env python3
"""
Complete Data Import to SurrealDB - Final Version
"""

import requests
import json
import csv
from pathlib import Path

url = "http://localhost:8000/sql"
auth = ('root', 'root')
headers = {'Content-Type': 'application/json'}

def query(q):
    try:
        r = requests.post(url, auth=auth, headers=headers, data=q, timeout=30)
        return r.status_code, r.json() if r.text else None
    except Exception as e:
        return -1, str(e)

print()
print("=" * 70)
print("SurrealDB - Complete Data Import")
print("=" * 70)
print()

migration_folder = Path("c:/development/DataPrivNet/migration")
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

print("Importing data...")
print()

grand_total = 0

for table_name in tables:
    csv_file = migration_folder / f"appdb_{table_name}.csv"
    
    if not csv_file.exists():
        continue
    
    # Read CSV
    try:
        records = []
        with open(csv_file, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            
            for row in reader:
                record = {}
                for key, value in row.items():
                    if value == '' or value is None:
                        continue
                    
                    if isinstance(value, str):
                        val_lower = value.lower()
                        if val_lower == 'true':
                            record[key] = True
                        elif val_lower == 'false':
                            record[key] = False
                        elif value.lstrip('-').isdigit():
                            record[key] = int(value)
                        else:
                            record[key] = value
                
                if record:
                    records.append(record)
        
        # Insert in batches of 50 to avoid timeout
        batch_size = 50
        total_for_table = 0
        
        for i in range(0, len(records), batch_size):
            batch = records[i:i+batch_size]
            
            for rec in batch:
                json_str = json.dumps(rec, default=str)
                insert_query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INSERT INTO {table_name} {json_str};"
                
                status, _ = query(insert_query)
                if status == 200:
                    total_for_table += 1
        
        symbol = "✓" if total_for_table > 0 else "·"
        print(f"  {symbol} {table_name:30} {total_for_table:5} records")
        grand_total += total_for_table
        
    except Exception as e:
        print(f"  ✗ {table_name:30} error: {str(e)[:30]}")

print()
print("=" * 70)
print(f"Total records imported: {grand_total}")
print("=" * 70)
print()

# Final verification
print("Final Verification:")
print()

for table_name in tables:
    query_str = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT * FROM {table_name};"
    status, result = query(query_str)
    
    count = 0
    if status == 200 and isinstance(result, list) and len(result) > 0:
        res = result[0]
        if 'result' in res and res['result']:
            count = len(res['result']) if isinstance(res['result'], list) else 1
    
    symbol = "✓" if count > 0 else "·"
    print(f"  {symbol} {table_name:30} {count:5} records")

print()
