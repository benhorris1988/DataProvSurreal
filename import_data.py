#!/usr/bin/env python3
"""
SurrealDB Data Import Script
Imports CSV data into SurrealDB with proper data type handling
"""

import csv
import json
import sys
import requests
from pathlib import Path
from typing import Dict, Any, List
from datetime import datetime

class SurrealDBImporter:
    def __init__(self, url: str, user: str, password: str):
        self.url = url
        self.user = user
        self.password = password
        self.session = self._create_session()
        
    def _create_session(self) -> requests.Session:
        """Create authenticated session"""
        session = requests.Session()
        session.headers.update({
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        })
        # Note: SurrealDB auth needs to be configured appropriately
        return session
    
    def execute_query(self, namespace: str, database: str, query: str) -> Dict[str, Any]:
        """Execute a query against SurrealDB"""
        try:
            url = f"{self.url}/sql"
            params = {
                'ns': namespace,
                'db': database
            }
            
            response = self.session.post(
                url,
                params=params,
                data=query,
                auth=(self.user, self.password)
            )
            
            if response.status_code >= 400:
                print(f"Error: {response.status_code} - {response.text}")
                return None
            
            return response.json()
        except Exception as e:
            print(f"Query execution error: {e}")
            return None
    
    def import_csv(self, table_name: str, csv_file_path: str, 
                   namespace: str, database: str) -> int:
        """Import CSV file into SurrealDB table"""
        
        if not Path(csv_file_path).exists():
            print(f"  [SKIP] File not found: {csv_file_path}")
            return 0
        
        print(f"  Importing {table_name}...", end=" ", flush=True)
        
        try:
            records = []
            with open(csv_file_path, 'r', encoding='utf-8') as f:
                reader = csv.DictReader(f)
                
                if reader.fieldnames is None:
                    print("[EMPTY]")
                    return 0
                
                for row in reader:
                    # Convert data types appropriately
                    record = self._convert_row_types(row)
                    records.append(record)
            
            if not records:
                print("[EMPTY]")
                return 0
            
            # Build INSERT statements
            inserted_count = 0
            for record in records:
                # Remove null values
                record = {k: v for k, v in record.items() if v is not None}
                
                # Build SurrealQL INSERT
                json_str = json.dumps(record, default=str)
                query = f"INSERT INTO `{table_name}` {json_str}"
                
                response = self.execute_query(namespace, database, query)
                if response:
                    inserted_count += 1
            
            print(f"[OK] ({inserted_count} records)")
            return inserted_count
            
        except Exception as e:
            print(f"[ERROR] {e}")
            return 0
    
    def _convert_row_types(self, row: Dict[str, str]) -> Dict[str, Any]:
        """Convert CSV string values to appropriate types"""
        converted = {}
        
        for key, value in row.items():
            if value == '' or value is None:
                converted[key] = None
            elif value.lower() == 'true':
                converted[key] = True
            elif value.lower() == 'false':
                converted[key] = False
            elif self._is_datetime(value):
                converted[key] = value  # Keep as ISO string
            elif self._is_int(value):
                converted[key] = int(value)
            elif self._is_float(value):
                converted[key] = float(value)
            else:
                converted[key] = value
        
        return converted
    
    @staticmethod
    def _is_int(value: str) -> bool:
        try:
            int(value)
            return True
        except ValueError:
            return False
    
    @staticmethod
    def _is_float(value: str) -> bool:
        try:
            float(value)
            return '.' in value
        except ValueError:
            return False
    
    @staticmethod
    def _is_datetime(value: str) -> bool:
        datetime_formats = [
            '%Y-%m-%d',
            '%Y-%m-%dT%H:%M:%S',
            '%Y-%m-%d %H:%M:%S',
            '%Y-%m-%dT%H:%M:%S.%f',
        ]
        for fmt in datetime_formats:
            try:
                datetime.strptime(value, fmt)
                return True
            except ValueError:
                continue
        return False


def main():
    # Configuration
    SURREAL_URL = "http://localhost:8000"
    SURREAL_USER = "root"
    SURREAL_PASS = "root"
    NAMESPACE = "DataProvisioningEngine"
    DATABASE = "AppDB"
    MIGRATION_FOLDER = "."
    
    # Tables to import (in dependency order)
    TABLES = [
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
    
    print()
    print("=" * 60)
    print("SurrealDB Data Import")
    print("=" * 60)
    print()
    
    # Initialize importer
    importer = SurrealDBImporter(SURREAL_URL, SURREAL_USER, SURREAL_PASS)
    
    # Test connection
    print(f"Connecting to {SURREAL_URL}...", end=" ", flush=True)
    try:
        requests.get(f"{SURREAL_URL}/health", timeout=5)
        print("[OK]")
    except:
        print("[ERROR] Cannot connect to SurrealDB")
        print("Make sure SurrealDB is running: surreal start")
        sys.exit(1)
    
    # Import each table
    print()
    print(f"Importing data to {NAMESPACE}/{DATABASE}...")
    print()
    
    total_imported = 0
    for table_name in TABLES:
        csv_file = Path(MIGRATION_FOLDER) / f"appdb_{table_name}.csv"
        count = importer.import_csv(table_name, str(csv_file), NAMESPACE, DATABASE)
        total_imported += count
    
    print()
    print("=" * 60)
    print(f"Import Complete: {total_imported} records imported")
    print("=" * 60)
    print()
    print("Next steps:")
    print("1. Verify data in SurrealDB")
    print("2. Update application configuration")
    print("3. Test the data access control")
    print()


if __name__ == "__main__":
    main()
