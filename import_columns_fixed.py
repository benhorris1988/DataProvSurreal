#!/usr/bin/env python3
"""
SurrealDB Data Import - Columns table fix
"""

import requests
import json
import csv
from pathlib import Path

class SurrealDBImport:
    def __init__(self, url="http://localhost:8000", user="root", password="root"):
        self.url = url
        self.user = user
        self.password = password
        self.session = requests.Session()
        self.session.auth = (user, password)
        self.session.headers.update({'Content-Type': 'application/json'})
    
    def execute_query(self, query, namespace=None, database=None):
        """Execute a single query"""
        url = f"{self.url}/sql"
        params = {}
        if namespace:
            params['ns'] = namespace
        if database:
            params['db'] = database
        
        try:
            response = self.session.post(url, params=params, data=query)
            return response.status_code < 400, response.text
        except Exception as e:
            return False, str(e)
    
    def import_columns(self):
        """Import columns table with proper data type handling"""
        csv_file = "appdb_columns.csv"
        
        print(f"Importing {csv_file}...")
        
        try:
            records = []
            with open(csv_file, 'r', encoding='utf-8') as f:
                reader = csv.DictReader(f)
                
                for row in reader:
                    record = {}
                    for key, value in row.items():
                        if value == '' or value is None:
                            continue
                        
                        # Handle as string - convert types after
                        if isinstance(value, list):
                            value = str(value)
                        
                        # Type conversion
                        if value.lower() == 'true':
                            record[key] = True
                        elif value.lower() == 'false':
                            record[key] = False
                        elif self._is_int(value):
                            record[key] = int(value)
                        else:
                            record[key] = value
                    
                    if record:
                        records.append(record)
            
            # Import records
            imported = 0
            for record in records:
                json_data = json.dumps(record, default=str)
                query = f"INSERT INTO `columns` {json_data}"
                success, msg = self.execute_query(
                    query,
                    "DataProvisioningEngine",
                    "AppDB"
                )
                if success:
                    imported += 1
                else:
                    # Show first failure for debugging
                    if imported == 0:
                        print(f"  First record: {json_data[:100]}")
                        print(f"  Response: {msg[:100]}")
            
            print(f"  [OK] {imported} records imported")
            return imported
        except Exception as e:
            print(f"  [ERROR] {e}")
            return 0
    
    @staticmethod
    def _is_int(value):
        try:
            int(value)
            return True
        except:
            return False


if __name__ == "__main__":
    print("Fixing columns table import...")
    importer = SurrealDBImport()
    total = importer.import_columns()
    print(f"\nCompleted: {total} records in columns table")
