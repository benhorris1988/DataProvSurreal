#!/usr/bin/env python3
"""
Simple, Robust SurrealDB Import
Uses the proven successful test_import.py approach
"""

import requests
import json
import csv
from pathlib import Path
import time
import sys

url = "http://localhost:8000/sql"

print()
print("=" * 70)
print("SurrealDB - Simple, Robust Import")
print("=" * 70)
print()

# Tables with batch sizes (larger tables in smaller batches)
tables = {
    "users": (8, 8),                                # (expected count, batch size)
    "virtual_groups": (7, 7),
    "datasets": (46, 23),
    "columns": (246, 20),                           # LARGE - batch in 20s
    "asset_policy_groups": (2, 2),
    "asset_policy_columns": (2, 2),
    "asset_policy_conditions": (2, 2),
    "virtual_group_members": (5, 5),
    "access_requests": (15, 15),
    "initial_admins": (2, 2),
}

print("Setup: Creating namespace and database...")

# Create namespace (will error if exists, but that's OK)
setup_query =  "CREATE NAMESPACE DataProvisioningEngine;"
try:
    r = requests.post(url, headers={'Content-Type': 'application/json'}, data=setup_query, timeout=10)
    print(f"  Namespace: Status {r.status_code}")
except:
    print("  Namespace: Connection error (may already exist)")

time.sleep(0.5)

# Create database
setup_query = "USE NAMESPACE DataProvisioningEngine; CREATE DATABASE AppDB;"
try:
    r = requests.post(url, headers={'Content-Type': 'application/json'}, data=setup_query, timeout=10)
    print(f"  Database: Status {r.status_code}")
except:
    print("  Database: Connection error (may already exist)")

time.sleep(1)

print()
print("Import: Processing tables...")
print()

total_imported = 0
total_failed = 0

for table_name, (expected, batch_size) in tables.items():
    csv_file = Path(f"appdb_{table_name}.csv")
    
    if not csv_file.exists():
        print(f"  {table_name:30} [SKIP - no file]")
        continue
    
    # Read CSV
    rows = []
    try:
        with open(csv_file, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            rows = [r for r in reader if any(r.values())]
    except Exception as e:
        print(f"  {table_name:30} [ERROR reading CSV: {str(e)[:20]}]")
        continue
    
    if not rows:
        print(f"  {table_name:30} [SKIP - empty]")
        continue
    
    # Convert rows to JSON objects
    json_objects = []
    for row in rows:
        obj = {}
        for k, v in row.items():
            if not v or v == '':
                continue
            # Type conversion
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
    
    print(f"  {table_name:30}", end=" ", flush=True)
    
    # Import in batches
    imported_count = 0
    batch_count = 0
    
    for i in range(0, len(json_objects), batch_size):
        batch = json_objects[i:i+batch_size]
        
        # Build the insert query
        json_str = ', '.join(json.dumps(obj, default=str) for obj in batch)
        insert_query = f"USE NAMESPACE DataProvisioningEngine DATABASE AppDB; INSERT INTO {table_name} [{json_str}];"
        
        try:
            r = requests.post(
                url,
                headers={'Content-Type': 'application/json'},
                data=insert_query,
                timeout=30
            )
            
            if r.status_code == 200:
                imported_count += len(batch)
                batch_count += 1
            else:
                print(f"\n    Batch {batch_count+1}: HTTP {r.status_code}")
                total_failed += len(batch)
        
        except requests.exceptions.Timeout:
            print(f"\n    Batch {batch_count+1}: TIMEOUT")
            total_failed += len(batch)
        except ConnectionRefusedError:
            print(f"\n    Batch {batch_count+1}: CONNECTION REFUSED (SurrealDB crashed?)")
            total_failed += len(batch)
            # Don't continue if connection is refused
            sys.exit(1)
        except Exception as e:
            print(f"\n    Batch {batch_count+1}: {str(e)[:30]}")
            total_failed += len(batch)
        
        # Small delay between batches
        time.sleep(0.1)
    
    symb = "✓" if imported_count == expected else "⚠" if imported_count > 0 else "✗"
    print(f"[{imported_count:3}/{expected:3}] {symb}")
    total_imported += imported_count

print()
print("=" * 70)
print(f"Import Complete: {total_imported} records (failed: {total_failed})")
print("=" * 70)
print()

# Verify
print("Verification: Querying tables...")
print()

verified_total = 0

for table_name in tables.keys():
    query = f"USE NAMESPACE DataProvisioningEngine DATABASE AppDB; SELECT * FROM {table_name};"
    
    count = 0
    try:
        r = requests.post(url, headers={'Content-Type': 'application/json'}, data=query, timeout=10)
        
        if r.status_code == 200:
            result = r.json()
            if isinstance(result, list) and len(result) > 1:
                data = result[1].get('result', [])
                if isinstance(data, list):
                    count = len(data)
    except:
        pass
    
    symb = "✓" if count > 0 else "·"
    print(f"  {symb} {table_name:30} {count:5} records")
    verified_total += count

print()
print("=" * 70)
print(f"FINAL: {verified_total} records verified in SurrealDB")
print("=" * 70)
print()
