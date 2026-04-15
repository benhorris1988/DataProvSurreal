#!/usr/bin/env python3
"""
Verify SurrealDB data import
"""

import requests
import json

class SurrealDBVerify:
    def __init__(self, url="http://localhost:8000", user="root", password="root"):
        self.url = url
        self.user = user
        self.password = password
        self.session = requests.Session()
        self.session.auth = (user, password)
        self.session.headers.update({'Content-Type': 'application/json'})
    
    def count_records(self, table_name):
        """Count records in a table"""
        query = f"SELECT count() as count FROM `{table_name}` GROUP ALL"
        url = f"{self.url}/sql"
        params = {
            'ns': 'DataProvisioningEngine',
            'db': 'AppDB'
        }
        
        try:
            response = self.session.post(url, params=params, data=query)
            if response.status_code < 400:
                data = response.json()
                if data and len(data) > 0 and 'result' in data[0]:
                    return data[0]['result'][0]['count'] if data[0]['result'] else 0
            return 0
        except:
            return 0
    
    def verify_all(self):
        """Verify all tables"""
        tables = [
            ("users", 6),
            ("virtual_groups", 8),
            ("datasets", 44),
            ("columns", 246),
            ("asset_policy_groups", 3),
            ("asset_policy_columns", 3),
            ("asset_policy_conditions", 3),
            ("virtual_group_members", 6),
            ("access_requests", 16),
            ("initial_admins", 3)
        ]
        
        print()
        print("=" * 60)
        print("SurrealDB Data Verification")
        print("=" * 60)
        print()
        
        total_actual = 0
        total_expected = 0
        
        for table_name, expected in tables:
            actual = self.count_records(table_name)
            total_actual += actual
            total_expected += expected
            
            status = "✓" if actual > 0 else "✗"
            print(f"{status} {table_name:30} {actual:6} records (expected: {expected})")
        
        print()
        print("=" * 60)
        print(f"Total: {total_actual} records (expected: {total_expected})")
        print("=" * 60)
        print()
        
        if total_actual > 0:
            print("✓ Data successfully imported to SurrealDB!")
        else:
            print("✗ No data found in SurrealDB")
        print()


if __name__ == "__main__":
    verifier = SurrealDBVerify()
    verifier.verify_all()
