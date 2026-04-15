#!/usr/bin/env python3
"""
SurrealDB Import - Array Syntax (Corrected)
"""

import requests
import json
import csv
from pathlib import Path

url = "http://localhost:8000/sql"
auth = ('root', 'root')

print()
print("=" * 70)
print("SurrealDB - Bulk Import (Array Syntax)")
print("=" * 70)
print()

# Just test with users first
csv_file = Path("appdb_users.csv")

if not csv_file.exists():
    print(f"File not found: {csv_file}")
    exit(1)

print("Reading users.csv...")

rows = []
with open(csv_file, 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        if not any(row.values()):
            continue
        rows.append(row)

print(f"Found {len(rows)} rows")
print()

if rows:
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
    
    print(f"Converted to {len(json_objects)} objects")
    print()
    
    # Build INSERT with array syntax: INSERT INTO table [$obj1, $obj2, ...]
    insert_query = f"USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; INSERT INTO users [{', '.join(json.dumps(obj, default=str) for obj in json_objects)}];"
    
    print(f"Query length: {len(insert_query)}")
    print()
    
    print("Sending import request...")
    
    try:
        r = requests.post(
            url,
            auth=auth,
            headers={'Content-Type': 'application/json'},
            data=insert_query,
            timeout=60
        )
        
        print(f"Status: {r.status_code}")
        print()
        
        if r.status_code != 200:
            print("Error response:")
            print(r.text[:500])
        else:
            try:
                result = r.json()
                print("Success!")
                print(json.dumps(result, indent=2)[:300])
            except:
                print(f"Response: {r.text[:200]}")
                
    except Exception as e:
        print(f"Exception: {e}")

print()
