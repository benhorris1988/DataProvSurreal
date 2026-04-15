#!/usr/bin/env python3
"""
Verify SurrealDB data - with proper USE statements
"""

import requests
import json

url = "http://localhost:8000/sql"

queries = [
    ("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT COUNT(*) as count FROM users;", "User count"),
    ("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT COUNT(*) as count FROM datasets;", "Dataset count"),
    ("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT COUNT(*) as count FROM columns;", "Column count"),
    ("USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`; SELECT * FROM users LIMIT 1;", "Sample user"),
]

print()
print("=" * 60)
print("SurrealDB Data Verification")
print("=" * 60)
print()

for query, description in queries:
    print(f"{description}:")
    
    try:
        response = requests.post(
            url,
            auth=('root', 'root'),
            headers={'Content-Type': 'application/json'},
            data=query,
            timeout=10
        )
        
        if response.status_code == 200:
            result = response.json()
            if result and len(result) > 0:
                # Print first result
                first_result = result[0]
                if 'result' in first_result and first_result['result']:
                    print(f"  ✓ {json.dumps(first_result['result'][0] if isinstance(first_result['result'], list) else first_result['result'], indent=2)[:150]}")
                else:
                    print(f"  Status: {first_result.get('status', 'unknown')}")
            else:
               print(f"  Empty response")
        else:
            print(f"  Error {response.status_code}: {response.text[:100]}")
    except Exception as e:
        print(f"  Exception: {e}")
    
    print()

print("=" * 60)
print()
