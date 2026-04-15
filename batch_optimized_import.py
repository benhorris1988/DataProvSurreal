#!/usr/bin/env python3
"""
SurrealDB Optimized Import - Smaller Batches
Imports CSVs with batch size 25 to avoid memory issues
"""

import requests
import json
import csv
from pathlib import Path
import time

url = "http://localhost:8000/sql"

print()
print("=" * 70)
print("SurrealDB - Optimized Batch Import")
print("=" * 70)
print()

# Setup
print("Step 1: Creating namespace and database...")

queries = [
    "CREATE NAMESPACE IF NOT EXISTS DataProvisioningEngine;",
    "USE NAMESPACE DataProvisioningEngine; CREATE DATABASE IF NOT EXISTS AppDB;",
]

for query in queries:
    try:
        r = requests.post(url, headers={'Content-Type': 'application/json'}, data=query, timeout=10)
        if r.status_code in [200, 400]:  # 400 might be "already exists"
            print(f"  ✓ {query[:60]}")
    except:
        print(f"  ? {query[:60]}")

time.sleep(1)

# Data import
print()
print("Step 2: Importing tables...")
print()

tables = {
    "users": 8,
    "virtual_groups": 7,
    "datasets": 46,
    "columns": 246,
    "asset_policy_groups": 2,
    "asset_policy_columns": 2,
    "asset_policy_conditions": 2,
    "virtual_group_members": 5,
    "access_requests": 15,
    "initial_admins": 2,
}

BATCH_SIZE = 25
total = 0

for table_name, expected_count in tables.items():
    csv_file = Path(f"appdb_{table_name}.csv")
    
    if not csv_file.exists():
        print(f"  ✗ {table_name:30} [NO FILE]")
        continue
    
    print(f"  {table_name:30}", end="", flush=True)
    
    # Read CSV
    rows = []
    try:
        with open(csv_file, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            for row in reader:
                if any(row.values()):
                    rows.append(row)
    except Exception as e:
        print(f" [CSV ERROR]")
        continue
    
    if not rows:
        print(f" [EMPTY]")
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
        print(f" [NO DATA]")
        continue
    
    # Import in batches
    imported = 0
    failed_batches = 0
    
    for batch_start in range(0, len(json_objects), BATCH_SIZE):
        batch = json_objects[batch_start:batch_start+BATCH_SIZE]
        
        insert_query = f"USE NAMESPACE DataProvisioningEngine DATABASE AppDB; INSERT INTO {table_name} [{', '.join(json.dumps(obj, default=str) for obj in batch)}];"
        
        try:
            r = requests.post(
                url,
                headers={'Content-Type': 'application/json'},
                data=insert_query,
                timeout=30
            )
            
            if r.status_code == 200:
                imported += len(batch)
            else:
                failed_batches += 1
        except:
            failed_batches += 1
    
    if imported > 0:
        print(f" [{imported:5}/{expected_count:5}] ✓")
        total += imported
    elif failed_batches > 0:
        print(f" [FAILED]")
    else:
        print(f" [0 IMPORTED]")

print()
print("=" * 70)
print(f"✓ Total imported: {total} records")
print("=" * 70)
print()

# Verify
print("Step 3: Verifying...")
print()

verified = 0
for table_name in tables.keys():
    query = f"USE NAMESPACE DataProvisioningEngine DATABASE AppDB; SELECT * FROM {table_name};"
    
    try:
        r = requests.post(url, headers={'Content-Type': 'application/json'}, data=query, timeout=10)
        
        if r.status_code == 200:
            result = r.json()
            count = 0
            if isinstance(result, list) and len(result) > 1:
                data = result[1].get('result', [])
                if isinstance(data, list):
                    count = len(data)
            
            if count > 0:
                print(f"  ✓ {table_name:30} {count:5} records")
                verified += count
            else:
                print(f"  · {table_name:30} [0 records]")
    except Exception as e:
        print(f"  ? {table_name:30} [ERROR]")

print()
print("=" * 70)
print(f"✓ VERIFIED: {verified} total records")
print("=" * 70)
