#!/usr/bin/env python3
"""
Complete SurrealDB Setup and Data Import
Handles schema creation and all data imports
"""

import requests
import json
import csv
from pathlib import Path
from datetime import datetime
import sys

class SurrealDBSetup:
    def __init__(self, url="http://localhost:8000", user="root", password="root"):
        self.url = url
        self.user = user
        self.password = password
        self.session = requests.Session()
        self.session.auth = (user, password)
        self.session.headers.update({'Content-Type': 'application/json'})
        
    def check_connection(self):
        """Check if SurrealDB is running"""
        try:
            response = self.session.get(f"{self.url}/health", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def execute_query(self, query, namespace=None, database=None):
        """Execute a single query"""
        url = f"{self.url}/sql"
        params = {}
        if namespace:
            params['ns'] = namespace
        if database:
            params['db'] = database
        
        try:
            response = self.session.post(
                url,
                params=params,
                data=query
            )
            return response.status_code < 400, response.text
        except Exception as e:
            return False, str(e)
    
    def execute_queries_from_file(self, filepath):
        """Execute all queries from a SQL file"""
        with open(filepath, 'r') as f:
            content = f.read()
        
        # Split by semicolons (simple approach)
        queries = [q.strip() for q in content.split(';') if q.strip()]
        
        success_count = 0
        for i, query in enumerate(queries, 1):
            success, msg = self.execute_query(query)
            if success:
                success_count += 1
            else:
                print(f"  Query {i} result: {msg[:100]}")
        
        return success_count, len(queries)
    
    def setup_namespace_and_db(self):
        """Create namespace and databases"""
        print("Creating namespace and databases...")
        queries = [
            "DEFINE NAMESPACE `DataProvisioningEngine`;",
            "USE NAMESPACE `DataProvisioningEngine`;",
            "DEFINE DATABASE `AppDB`;",
            "DEFINE DATABASE `DataWarehouse`;",
        ]
        
        for query in queries:
            success, msg = self.execute_query(query)
            if not success and "already" not in msg.lower():
                print(f"  Warning: {msg[:100]}")
        
        print("  [OK] Namespace and databases ready")
    
    def setup_schema(self):
        """Create all tables and indexes"""
        print("Creating schema...")
        
        queries = [
            # Users table
            "USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;",
            "DEFINE TABLE users SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE users TYPE int PRIMARY KEY;",
            "DEFINE FIELD name ON TABLE users TYPE string;",
            "DEFINE FIELD email ON TABLE users TYPE string;",
            "DEFINE FIELD role ON TABLE users TYPE string;",
            "DEFINE FIELD avatar ON TABLE users TYPE string NULLABLE;",
            "DEFINE FIELD created_at ON TABLE users TYPE datetime;",
            
            # Virtual Groups
            "DEFINE TABLE virtual_groups SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE virtual_groups TYPE int PRIMARY KEY;",
            "DEFINE FIELD name ON TABLE virtual_groups TYPE string;",
            "DEFINE FIELD owner_id ON TABLE virtual_groups TYPE int;",
            "DEFINE FIELD description ON TABLE virtual_groups TYPE string NULLABLE;",
            "DEFINE FIELD created_at ON TABLE virtual_groups TYPE datetime;",
            
            # Datasets
            "DEFINE TABLE datasets SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE datasets TYPE int PRIMARY KEY;",
            "DEFINE FIELD name ON TABLE datasets TYPE string;",
            "DEFINE FIELD type ON TABLE datasets TYPE string;",
            "DEFINE FIELD description ON TABLE datasets TYPE string NULLABLE;",
            "DEFINE FIELD owner_group_id ON TABLE datasets TYPE int NULLABLE;",
            "DEFINE FIELD created_at ON TABLE datasets TYPE datetime;",
            
            # Columns
            "DEFINE TABLE columns SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE columns TYPE int PRIMARY KEY;",
            "DEFINE FIELD dataset_id ON TABLE columns TYPE int;",
            "DEFINE FIELD name ON TABLE columns TYPE string;",
            "DEFINE FIELD data_type ON TABLE columns TYPE string NULLABLE;",
            "DEFINE FIELD definition ON TABLE columns TYPE string NULLABLE;",
            "DEFINE FIELD is_pii ON TABLE columns TYPE bool;",
            "DEFINE FIELD sample_data ON TABLE columns TYPE string NULLABLE;",
            
            # Asset Policy Groups
            "DEFINE TABLE asset_policy_groups SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE asset_policy_groups TYPE int PRIMARY KEY;",
            "DEFINE FIELD dataset_id ON TABLE asset_policy_groups TYPE int;",
            "DEFINE FIELD owner_id ON TABLE asset_policy_groups TYPE int NULLABLE;",
            "DEFINE FIELD name ON TABLE asset_policy_groups TYPE string;",
            "DEFINE FIELD description ON TABLE asset_policy_groups TYPE string NULLABLE;",
            "DEFINE FIELD created_at ON TABLE asset_policy_groups TYPE datetime;",
            
            # Asset Policy Columns
            "DEFINE TABLE asset_policy_columns SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE asset_policy_columns TYPE int PRIMARY KEY;",
            "DEFINE FIELD policy_group_id ON TABLE asset_policy_columns TYPE int;",
            "DEFINE FIELD column_name ON TABLE asset_policy_columns TYPE string;",
            "DEFINE FIELD is_hidden ON TABLE asset_policy_columns TYPE bool;",
            
            # Asset Policy Conditions
            "DEFINE TABLE asset_policy_conditions SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE asset_policy_conditions TYPE int PRIMARY KEY;",
            "DEFINE FIELD policy_group_id ON TABLE asset_policy_conditions TYPE int;",
            "DEFINE FIELD column_name ON TABLE asset_policy_conditions TYPE string;",
            "DEFINE FIELD operator ON TABLE asset_policy_conditions TYPE string;",
            "DEFINE FIELD value ON TABLE asset_policy_conditions TYPE string;",
            
            # Virtual Group Members
            "DEFINE TABLE virtual_group_members SCHEMAFULL;",
            "DEFINE FIELD group_id ON TABLE virtual_group_members TYPE int;",
            "DEFINE FIELD user_id ON TABLE virtual_group_members TYPE int;",
            "DEFINE FIELD added_at ON TABLE virtual_group_members TYPE datetime;",
            
            # Access Requests
            "DEFINE TABLE access_requests SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE access_requests TYPE int PRIMARY KEY;",
            "DEFINE FIELD user_id ON TABLE access_requests TYPE int;",
            "DEFINE FIELD dataset_id ON TABLE access_requests TYPE int;",
            "DEFINE FIELD status ON TABLE access_requests TYPE string;",
            "DEFINE FIELD requested_rls_filters ON TABLE access_requests TYPE string NULLABLE;",
            "DEFINE FIELD justification ON TABLE access_requests TYPE string NULLABLE;",
            "DEFINE FIELD reviewed_by ON TABLE access_requests TYPE int NULLABLE;",
            "DEFINE FIELD reviewed_at ON TABLE access_requests TYPE datetime NULLABLE;",
            "DEFINE FIELD created_at ON TABLE access_requests TYPE datetime;",
            "DEFINE FIELD policy_group_id ON TABLE access_requests TYPE int NULLABLE;",
            
            # Initial Admins
            "DEFINE TABLE initial_admins SCHEMAFULL;",
            "DEFINE FIELD id ON TABLE initial_admins TYPE int PRIMARY KEY;",
            "DEFINE FIELD username ON TABLE initial_admins TYPE string;",
            "DEFINE FIELD added_at ON TABLE initial_admins TYPE datetime;",
            
            # Indexes
            "DEFINE INDEX idx_access_requests_user ON access_requests COLUMNS user_id;",
            "DEFINE INDEX idx_access_requests_dataset ON access_requests COLUMNS dataset_id;",
            "DEFINE INDEX idx_datasets_owner_group ON datasets COLUMNS owner_group_id;",
            "DEFINE INDEX idx_columns_dataset ON columns COLUMNS dataset_id;",
        ]
        
        success_count = 0
        for query in queries:
            success, msg = self.execute_query(query, "DataProvisioningEngine", "AppDB")
            if success:
                success_count += 1
        
        print(f"  [OK] Created {success_count}/{len(queries)} schema elements")
    
    def import_csv_data(self, csv_file, table_name):
        """Import CSV file into table"""
        if not Path(csv_file).exists():
            return 0
        
        try:
            records = []
            with open(csv_file, 'r', encoding='utf-8') as f:
                reader = csv.DictReader(f)
                if reader.fieldnames is None:
                    return 0
                
                for row in reader:
                    record = {}
                    for key, value in row.items():
                        if value == '' or value is None:
                            continue
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
                query = f"INSERT INTO `{table_name}` {json_data}"
                success, msg = self.execute_query(
                    query,
                    "DataProvisioningEngine",
                    "AppDB"
                )
                if success:
                    imported += 1
            
            return imported
        except Exception as e:
            print(f"    Error: {e}")
            return 0
    
    @staticmethod
    def _is_int(value):
        try:
            int(value)
            return True
        except:
            return False


def main():
    print()
    print("=" * 60)
    print("SurrealDB Setup and Data Import")
    print("=" * 60)
    print()
    
    setup = SurrealDBSetup()
    
    # Check connection
    print("Checking SurrealDB connection...", end=" ")
    if not setup.check_connection():
        print("[ERROR]")
        print("SurrealDB is not accessible. Make sure it's running:")
        print("  surreal start file://./surreal.db")
        sys.exit(1)
    print("[OK]")
    print()
    
    # Setup namespace and databases
    setup.setup_namespace_and_db()
    
    # Setup schema
    setup.setup_schema()
    
    # Import data
    print()
    print("Importing data...")
    
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
        if csv_file.exists():
            print(f"  Importing {table_name}...", end=" ")
            count = setup.import_csv_data(str(csv_file), table_name)
            print(f"[OK] {count} records")
            total_imported += count
        else:
            print(f"  {table_name}... [SKIP] file not found")
    
    print()
    print("=" * 60)
    print(f"Import Complete: {total_imported} records imported")
    print("=" * 60)
    print()
    print("Data is now in SurrealDB!")
    print()


if __name__ == "__main__":
    main()
