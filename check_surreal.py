#!/usr/bin/env python3
"""
Verify SurrealDB data - using proper REST API
"""

import requests
import json
from pathlib import Path

def check_data():
    """Check what's in SurrealDB"""
    url = "http://localhost:8000/sql"
    
    # Simple test - just try to select from users table
    queries_to_test = [
        ("Check namespace", "INFO FOR NAMESPACE"),
        ("List users", "SELECT * FROM users LIMIT 1"),
        ("Count users", "SELECT VALUE count() FROM users"),
    ]
    
    print()
    print("=" * 60)
    print("SurrealDB Data Verification")
    print("=" * 60)
    print()
    
    for desc, query in queries_to_test:
        print(f"{desc}:")
        print(f"  Query: {query}")
        
        try:
            # Use basic auth
            response = requests.post(
                url,
                auth=('root', 'root'),
                headers={'Content-Type': 'application/json'},
                data=query,
                params={'ns': 'DataProvisioningEngine', 'db': 'AppDB'},
                timeout=5
            )
            
            print(f"  Status: {response.status_code}")
            
            if response.status_code < 400:
                try:
                    result = response.json()
                    print(f"  Result: {json.dumps(result, indent=2)[:200]}")
                except:
                    print(f"  Result: {response.text[:200]}")
            else:
                print(f"  Error: {response.text[:200]}")
        except Exception as e:
            print(f"  Exception: {e}")
        
        print()

def check_csv_files():
    """Check the CSV files"""
    print("CSV Files in Migration Folder:")
    print()
    
    migration_folder = Path("c:/development/DataPrivNet/migration")
    
    csv_files = list(migration_folder.glob("appdb_*.csv"))
    
    total_records = 0
    for csv_file in sorted(csv_files):
        try:
            import csv
            with open(csv_file, 'r') as f:
                reader = csv.DictReader(f)
                count = sum(1 for _ in reader)
                total_records += count
                print(f"  {csv_file.name:40} {count:6} records")
        except:
            print(f"  {csv_file.name:40} (error reading)")
    
    print()
    print(f"Total CSV records: {total_records}")
    print()

if __name__ == "__main__":
    check_csv_files()
    check_data()
