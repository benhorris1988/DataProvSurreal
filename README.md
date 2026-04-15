# SurrealDB Migration - Complete Guide

## Overview

You are migrating from SQL Server to SurrealDB while implementing row-level access control based on the Data Provisioning Engine.

**Source Databases:**
- `datamarketplace` (Application DB) - 6 users, 44 datasets, 13 access requests
- `DataWarehouse_DEV` (Operational DW) - 43 tables with fact, dimension, and staging data

**Target SurrealDB Structure:**
- **Namespace**: `DataProvisioningEngine`
- **Database 1**: `AppDB` - Application metadata and access control
- **Database 2**: `DataWarehouse` - Operational data warehouse

---

## Migration Steps

### Step 1: Set Up SurrealDB Namespace and Databases

**File:** `04_complete_schema_setup.sql`

Execute this by connecting to SurrealDB:

```bash
# Start SurrealDB (if not running)
surreal start file://./surreal.db

# Connect to SurrealDB CLI
surreal sql --conn http://localhost:8000 --user root --pass root
```

Then paste the entire contents of `04_complete_schema_setup.sql` into the SurrealDB CLI.

This will:
- Create namespace `DataProvisioningEngine`
- Create databases `AppDB` and `DataWarehouse`
- Create all tables with relationships
- Set up indexes for performance
- Configure permissions for access control

### Step 2: Verify Data Export

All data has been exported to CSV files:
- `appdb_users.csv` (6 users)
- `appdb_virtual_groups.csv` (8 groups)
- `appdb_datasets.csv` (44 datasets)
- `appdb_columns.csv` (dataset columns)
- `appdb_asset_policy_groups.csv` (policy groups)
- `appdb_asset_policy_columns.csv` (hidden columns)
- `appdb_asset_policy_conditions.csv` (RLS conditions)
- `appdb_virtual_group_members.csv` (group memberships)
- `appdb_access_requests.csv` (13 access requests)
- `appdb_initial_admins.csv` (admin users)

### Step 3: Import Data into SurrealDB

**Option A: Using Python (Recommended)**

```bash
# Install required package
pip install requests

# Run the import script
python import_data.py
```

**Option B: Manual Import via CLI**

In the SurrealDB CLI:
```
USE NAMESPACE `DataProvisioningEngine`;
USE DATABASE `AppDB`;

-- Then import each CSV by reading and inserting
-- This is more manual but gives you control
```

### Step 4: Verify Data Import

In SurrealDB CLI, verify the imports:

```surqljs
USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;

SELECT count(*) FROM users;
SELECT count(*) FROM datasets;
SELECT count(*) FROM access_requests;
```

Expected results:
- Users: 6
- Datasets: 44
- Access Requests: 13

### Step 5: Update Application Configuration

Edit `DataProvisioning.WebUI/appsettings.json`:

```json
{
  "ConnectionStrings": {
    "SurrealDB": "http://localhost:8000",
    "SurrealDBNamespace": "DataProvisioningEngine",
    "SurrealDBDatabase": "AppDB",
    "SurrealDBUser": "root",
    "SurrealDBPassword": "root"
  },
  "DatabaseProvider": "SurrealDB"
}
```

### Step 6: Update Data Access Layer

Create new data access classes for SurrealDB:

```csharp
// In DataProvisioning.Infrastructure/Data/
// Create SurrealDbContext.cs
// Create repositories for SurrealDB access
```

Key implementation points:
- Replace Entity Framework Core with SurrealDB client library
- Implement query builder for row-level filters
- Apply access control based on `access_requests` table
- Filter columns based on `asset_policy_columns.is_hidden`

### Step 7: Implement Row-Level Access Control

When a user logs in and requests data:

1. **Identify User**: Get user ID from authentication
2. **Get Approved Datasets**: Query `access_requests` WHERE user_id = $user_id AND status = 'Approved'
3. **Get Access Policies**: Get `asset_policy_groups` linked to those datasets
4. **Build Row Filter**: Construct WHERE clause from `asset_policy_conditions`
5. **Hide Columns**: Filter columns where `asset_policy_columns.is_hidden = true`
6. **Execute Query**: Query the data with filters applied

Example pseudocode:
```csharp
// Get approved datasets for user
var approvedDatasets = await db.Query(
    $"SELECT dataset FROM access_requests WHERE user_id = {userId} AND status = 'Approved'"
);

// For each dataset, get policy conditions
foreach (var dataset in approvedDatasets) {
    var policy = await db.Query(
        $"SELECT * FROM asset_policy_groups WHERE dataset_id = {dataset.id}"
    );
    
    // Build row filter SQL
    var conditions = await db.Query(
        $"SELECT * FROM asset_policy_conditions WHERE policy_group_id = {policy.id}"
    );
    
    // Build query with RLS applied
    var rqlQuery = BuildQueryWithRLS(dataset, conditions);
    var data = await ExecuteQuery(rqlQuery);
}
```

### Step 8: Test the Integration

1. Log in as different users
2. Verify each user sees only their approved datasets
3. Verify row-level filters are applied correctly
4. Verify hidden columns are not displayed
5. Test access request workflow

---

## Troubleshooting

### Issue: "Cannot connect to SurrealDB"
- **Solution**: Ensure SurrealDB is running: `surreal start`

### Issue: "Namespace already exists"
- **Solution**: This is fine, SurrealDB will use the existing namespace

### Issue: "Table not found" during import
- **Solution**: Verify Step 1 (schema setup) completed successfully

### Issue: Data import is slow
- **Solution**: Use batch imports (multiple INSERT values in one query)

---

## Data Migration Checklist

- [ ] Back up SQL Server databases
- [ ] Start SurrealDB server
- [ ] Execute `04_complete_schema_setup.sql` in SurrealDB CLI
- [ ] Verify tables created: SELECT * FROM sys.tables;
- [ ] Import CSV data using Python script or manual CLI
- [ ] Verify record counts match
- [ ] Update `appsettings.json`
- [ ] Create new data access classes for SurrealDB
- [ ] Implement row-level access control
- [ ] Test with multiple users
- [ ] Verify all features work as expected
- [ ] Perform security audit
- [ ] Set up monitoring and logging
- [ ] Deploy to production

---

## Performance Considerations

1. **Indexes**: Schema already includes indexes on foreign keys
2. **Batch Imports**: Use batch INSERT for faster data loading
3. **Caching**: Implement caching for frequently accessed policies
4. **Query Optimization**: Use SurrealDB's query planner analysis

---

## Security Notes

1. **Row-Level Security**: Implemented via `access_requests` filtering
2. **Column-Level Security**: Implemented via `asset_policy_columns.is_hidden`
3. **Authentication**: Integration with Windows AD/Test Mode preserved
4. **Permissions**: SurrealDB table permissions configured in schema

---

## Next Steps After Migration

1. **Refactor Application Code**
   - Replace EF Core with SurrealDB SDK
   - Update all CRUD operations
   - Test thoroughly

2. **Implement Data Warehouse Queries**
   - Connect to `DataWarehouse` database
   - Apply same access control model
   - Query facts and dimensions

3. **Set Up Monitoring**
   - Log all access requests
   - Monitor performance
   - Alert on anomalies

4. **Complete Testing**
   - Unit tests for access control
   - Integration tests for data access
   - Security penetration testing

---

## Support

For issues or questions:
1. Check SurrealDB documentation: https://docs.surrealdb.com/
2. Review migration files in this directory
3. Check application logs for errors

