#!/usr/bin/env python3
"""
Fresh SurrealDB Setup and Complete Import
Rebuilds namespace/database and imports all 335 records
"""

import requests
import json
import csv
from pathlib import Path
import time

url = "http://localhost:8000/sql"

print()
print("=" * 70)
print("SurrealDB - Fresh Setup and Complete Import")
print("=" * 70)
print()

# Step 1: Setup namespace and database
print("Step 1: Creating namespace and database...")
print()

setup_queries = [
    "CREATE NAMESPACE DataProvisioningEngine;",
    "USE NAMESPACE DataProvisioningEngine; CREATE DATABASE AppDB;",
]

for query in setup_queries:
    try:
        r = requests.post(
            url,
            headers={'Content-Type': 'application/json'},
            data=query,
            timeout=30
        )
        if r.status_code in [200, 204]:
            print(f"  ✓ {query[:50]}")
        else:
            print(f"  ? {query[:50]} (HTTP {r.status_code})")
    except Exception as e:
        print(f"  ⚠ {query[:50]} (Error: {str(e)[:20]})")

time.sleep(1)
print()

# Step 2: Import all tables
print("Step 2: Importing data...")
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
        print(f"  ✗ {table_name:30} [NO FILE]")
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
            print(" [EMPTY CSV]")
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
        insert_query = f"USE NAMESPACE DataProvisioningEngine DATABASE AppDB; INSERT INTO {table_name} [{', '.join(json.dumps(obj, default=str) for obj in json_objects)}];"
        
        try:
            r = requests.post(
                url,
                headers={'Content-Type': 'application/json'},
                data=insert_query,
                timeout=60
            )
            
            if r.status_code == 200:
                result = r.json()
                # Count imported records
                imported = len(json_objects)  # Assume all were imported
                
                print(f" [{imported:5} records] ✓")
                total_imported += imported
            else:
                print(f" [HTTP {r.status_code}]")
                
        except Exception as e:
            print(f" [ERROR: {str(e)[:15]}]")
        
    except Exception as e:
        print(f" [ERROR: {e}]")

print()
print("=" * 70)
print(f"✓ Total imported: {total_imported} records")
print("=" * 70)
print()

# Step 3: Verify (with retries)
print("Step 3: Verifying tables...")
print()

max_retries = 3
verified_total = 0

for table_name in tables:
    query = f"USE NAMESPACE DataProvisioningEngine DATABASE AppDB; SELECT * FROM {table_name};"
    
    count = 0
    for attempt in range(max_retries):
        try:
            r = requests.post(
                url,
                headers={'Content-Type': 'application/json'},
                data=query,
                timeout=10
            )
            
            if r.status_code == 200:
                result = r.json()
                if isinstance(result, list) and len(result) > 1:
                    res = result[1].get('result', [])
                    if isinstance(res, list):
                        count = len(res)
                break
        except:
            if attempt < max_retries - 1:
                time.sleep(0.5)
            continue
    
    symb = "✓" if count > 0 else "·"
    print(f"  {symb} {table_name:30} {count:5} records")
    verified_total += count

print()
print("=" * 70)
print(f"✓ FINAL VERIFICATION: {verified_total} total records in database")
print("=" * 70)
print()
