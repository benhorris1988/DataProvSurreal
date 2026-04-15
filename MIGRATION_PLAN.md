// Migration instructions file
// This file documents the complete SurrealDB migration process

## Overview
The migration is from SQL Server to SurrealDB across two main databases:
1. **datamarketplace** (AppDB) - The Data Provisioning Engine metadata
2. **DataWarehouse_DEV** (DataWarehouse) - The operational data warehouse

## Target SurrealDB Structure
- **Namespace**: `DataProvisioningEngine`
- **Database 1**: `AppDB` - Contains application metadata and access control
- **Database 2**: `DataWarehouse` - Contains fact tables, dimensions, and staging tables

## Migration Steps

### Step 1: Create Namespace and Databases in SurrealDB
Use surreal CLI to execute:
```
DEFINE NAMESPACE `DataProvisioningEngine`;
USE NAMESPACE `DataProvisioningEngine`;
DEFINE DATABASE `AppDB`;
DEFINE DATABASE `DataWarehouse`;
```

### Step 2: Create Schema in AppDB
Tables to migrate from datamarketplace:
- users
- datasets
- access_requests
- asset_policy_groups
- asset_policy_columns
- asset_policy_conditions
- virtual_groups
- virtual_group_members
- columns
- report_datasets (if reports table exists)
- initial_admins

### Step 3: Create Schema in DataWarehouse
Tables to migrate from DataWarehouse_DEV:
- Fact Tables: fact_CustomerOrders, FactInventory, FactSales
- Dimension Tables: dim_Customer, dim_Order, dim_Part, dim_SalesPart
- Staging Tables: 36 stg_* tables
- Permissions: PermissionsMap

### Step 4: Data Migration
Execute bulk data import using SurrealDB's API or CLI

### Step 5: Set Up Relationships
- Link users to datasets (via owner_group_id)
- Link users to access_requests
- Link datasets to columns
- Link dimensions to facts (in DataWarehouse)

### Step 6: Application Configuration
Update appsettings.json to point to SurrealDB instead of SQL Server
Update connection strings and implement DAL layer for SurrealDB

##Access Control Implementation
When users log in, the application will:
1. Query SurrealDB for the logged-in user's ID
2. Filter datasets based on access_requests where status='Approved'
3. Apply row-level filters from asset_policy_conditions
4. Return only the permitted columns (per asset_policy_columns.is_hidden)
