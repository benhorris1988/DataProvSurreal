#!/usr/bin/env python3
"""
Fast SurrealDB Data Import - Batch Insert Version
"""

import requests
import json
import csv
from pathlib import Path

url = "http://localhost:8000/sql"
auth = ('root', 'root')
headers = {'Content-Type': 'application/json'}

def query(q):
    """Execute query"""
    try:
        r = requests.post(url, auth=auth, headers=headers, data=q, timeout=30)
        return r.status_code, r.json() if r.text else None
    except Exception as e:
        return -1, None

print()
print("=" * 70)
print("SurrealDB - Fast Batch Data Import")
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

total_imported = 0

for table_name in tables:
    csv_file = migration_folder / f"appdb_{table_name}.csv"
    
    if not csv_file.exists():
        print(f"  {table_name:30} [SKIP] no CSV")
        continue
    
    print(f"  {table_name:30}", end="", flush=True)
    
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
        
        # Insert all records for this table at once
        if records:
            # Build batch insert
            values_list = []
            for rec in records:
                json_str = json.dumps(rec, default=str)
                values_list.append(json_str)
            
            # Create combined insert with multiple records
            insert_statement = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INSERT INTO {table_name} [{', '.join(values_list)}];"
            
            status, result = query(insert_statement)
            
            if status == 200:
                print(f" [{len(records):4} records] ✓")
                total_imported += len(records)
            else:
                # Fallback to single inserts
                print(f" (batch failed, trying single inserts)...", end="", flush=True)
                count = 0
                for rec in records:
                    json_str = json.dumps(rec, default=str)
                    single_insert = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INSERT INTO {table_name} {json_str};"
                    status, _ = query(single_insert)
                    if status == 200:
                        count += 1
                
                print(f" [{count:4} records] ✓")
                total_imported += count
        else:
            print(f" [0 records]")
        
    except Exception as e:
        print(f" [ERROR: {str(e)[:40]}]")

print()
print("=" * 70)
print(f"✓ Total records imported: {total_imported}")
print("=" * 70)
print()

# Verify data
print("Verifying tables...")
print()

for table_name in tables:
    query_str = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT COUNT(*) as count FROM {table_name};"
    status, result = query(query_str)
    
    count = 0
    if status == 200 and isinstance(result, list) and len(result) > 0:
        try:
            count = result[0]['result'][0]['count'] if result[0]['result'] else 0
        except:
            count = 0
    
    symbol = "✓" if count > 0 else "·"
    print(f"  {symbol} {table_name:30} {count:5} records")

print()
