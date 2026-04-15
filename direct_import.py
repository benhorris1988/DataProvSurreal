#!/usr/bin/env python3
"""
Direct approach: Insert data - will create tables if they don't exist
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
        r = requests.post(url, auth=auth, headers=headers, data=q, timeout=10)
        return r.status_code, r.json() if r.text else None
    except Exception as e:
        return -1, None

print()
print("=" * 70)
print("SurrealDB - Direct Data Import")
print("=" * 70)
print()

# First test - try to insert into users table (will create table if needed)
print("Testing table creation via insert...")

test_insert = """
USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;
INSERT INTO users {
  id: 1,
  name: "Test User",
  email: "test@example.com",
  role: "admin",
  created_at: "2026-04-14T00:00:00Z"
};
"""

status, result = query(test_insert)
print(f"Insert status: {status}")
if status == 200:
    print("✓ Users table created/exists and can accept data")
else:
    print(f"✗ Insert failed: {status}")
    if result:
        print(f"  {result}")

print()
print("=" * 70)
print()
print("Importing all data from CSV files...")
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
        print(f"  {table_name:30} [SKIP] no CSV file")
        continue
    
    print(f"  {table_name:30}", end="", flush=True)
    
    try:
        records_imported = 0
        with open(csv_file, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            
            for row in reader:
                # Convert row to proper types
                record = {}
                for key, value in row.items():
                    if value == '' or value is None:
                        continue
                    
                    # Type conversion
                    if isinstance(value, str):
                        if value.lower() == 'true':
                            record[key] = True
                        elif value.lower() == 'false':
                            record[key] = False
                        elif value.lstrip('-').isdigit():
                            record[key] = int(value)
                        else:
                            record[key] = value
                
                if record:
                    json_data = json.dumps(record, default=str)
                    insert_query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INSERT INTO {table_name} {json_data};"
                    
                    status, _ = query(insert_query)
                    if status == 200:
                        records_imported += 1
        
        print(f" [{records_imported:4} records]")
        total_imported += records_imported
        
    except Exception as e:
        print(f" [ERROR: {str(e)[:30]}]")

print()
print("=" * 70)
print(f"✓ Total imported: {total_imported} records")
print("=" * 70)
print()
