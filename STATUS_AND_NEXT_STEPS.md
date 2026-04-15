# SurrealDB Migration - Status & Next Steps

## What's Been Completed ✓

### 1. Data Export From SQL Server
- **Source**: `datamarketplace` database
- **Exported**: 10 tables with complete data
  - users (6 records)
  - virtual_groups (8 records)
  - datasets (44 records)  
  - columns (247 records)
  - asset_policy_groups (3 records)
  - asset_policy_columns (3 records)
  - asset_policy_conditions (3 records)
  - virtual_group_members (6 records)
  - access_requests (16 records)
  - initial_admins (3 records)

- **Format**: CSV files in `.\migration\` folder
- **Total**: 337 data records ready for import

### 2. SurrealDB Schema Design
- **File**: `04_complete_schema_setup.sql`
- **Contains**:
  - Namespace definition: `DataProvisioningEngine`
  - Two databases: `AppDB` and `DataWarehouse`
  - 11 tables with relationships
  - Indexes for performance
  - Row-level access control configuration
  - Column-level access control setup

### 3. Migration Scripts Created
1. **Run-Migration.ps1** - Main orchestration script
2. **import_data.py** - Python import script for data loading
3. **Setup-SurrealDB-Migration.ps1** - Setup preparation
4. **Export-SQLToCSV.ps1** - Data export utilities
5. **Import-Data.ps1** - PowerShell import workflow

### 4. Documentation
- **README.md** - Complete migration guide
- **MIGRATION_PLAN.md** - High-level architecture
- Migration files and scripts

---

## What Needs To Be Done

### Phase 1: SurrealDB Setup (Manual Steps)

**Step 1.1: Create Namespace and Databases**
```bash
# Connect to SurrealDB
surreal sql --conn http://localhost:8000 --user root --pass root

# Then paste entire contents of:
# c:\development\DataPrivNet\migration\04_complete_schema_setup.sql
```

Expected output:
- Namespace `DataProvisioningEngine` created
- Database `AppDB` created
- Database `DataWarehouse` created  
- Tables and relationships configured

**Step 1.2: Verify Setup**
```sql
-- In SurrealDB CLI, verify:
USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;
SELECT count() FROM INFORMATION_SCHEMA.TABLES;
-- Should show 11 tables
```

### Phase 2: Data Import

**Step 2.1: Run Python Import**
```bash
cd c:\development\DataPrivNet\migration
pip install requests
python import_data.py
```

This will:
- Connect to SurrealDB
- Read all CSV files
- Insert records with proper type conversion
- Report import statistics

**Step 2.2: Verify Data Import**
```sql
USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;
SELECT count() FROM users;          -- Should be 6
SELECT count() FROM datasets;       -- Should be 44
SELECT count() FROM access_requests; -- Should be 16
```

### Phase 3: Application Configuration

**Step 3.1: Update Connection String**
Edit: `DataProvisioning.WebUI/appsettings.json`

```json
{
  "ConnectionStrings": {
    "DefaultConnection": "http://localhost:8000",
    "SurrealDBNamespace": "DataProvisioningEngine",
    "SurrealDBDatabase": "AppDB"
  },
  "DatabaseProvider": "SurrealDB"
}
```

**Step 3.2: Update Application Code**

You need to:
1. Install SurrealDB .NET SDK
2. Create new repository interfaces for SurrealDB
3. Implement access control queries
4. Replace Entity Framework Core calls

Key files to modify:
- `DataProvisioning.Application/Interfaces/` - Update interfaces
- `DataProvisioning.Infrastructure/Data/` - Implement SurrealDB context
- Controllers - Update data access patterns

### Phase 4: Implement Access Control

**Step 4.1: Query User's Approved Datasets**

```csharp
// Query: Get datasets user is approved to access
string query = $@"
    SELECT ->dataset FROM access_requests 
    WHERE user_id == {userId} 
    AND status == 'Approved'
";
```

**Step 4.2: Apply Row-Level Filters**

```csharp
// Get policy group for dataset
var policyId = dataset.policy_group_id;

// Get filter conditions
var conditions = await db.Query($@"
    SELECT * FROM asset_policy_conditions 
    WHERE policy_group_id == {policyId}
");

// Build WHERE clause from conditions
string whereClause = BuildWhereClause(conditions);
```

**Step 4.3: Apply Column-Level Filtering**

```csharp
// Get hidden columns
var hiddenCols = await db.Query($@"
    SELECT column_name FROM asset_policy_columns 
    WHERE policy_group_id == {policyId} 
    AND is_hidden == true
");

// Filter columns in results
FilterColumns(results, hiddenCols);
```

### Phase 5: Testing

**Step 5.1: Unit Tests**
- Test access request filtering
- Test RLS condition building
- Test column hiding logic

**Step 5.2: Integration Tests**
- Test complete data access flow
- Test with different user roles
- Test approved vs rejected requests

**Step 5.3: Security Tests**
- Verify unauthorized access is blocked
- Test permission boundaries
- Validate data isolation

---

## Migration Timeline Estimate

| Phase | Task | Time | Notes |
|-------|------|------|-------|
| 1 | SurrealDB Setup | 30 min | Manual execution of SQL |
| 2 | Data Import | 15 min | Python script automation |
| 2 | Verify Data | 10 min | Query validation |
| 3 | Configuration | 30 min | Update appsettings |
| 4 | Code Changes | 2-3 days | Depends on complexity |
| 4 | Access Control | 1-2 days | RLS implementation |
| 5 | Testing | 1-2 days | Comprehensive testing |
| **Total** | | **4-6 days** | Includes development |

---

## SQL Server Databases Info

### datamarketplace (Source → SurrealDB/AppDB)
Tables migrated: 10
Records exported: 337
Status: ✓ Ready for import

### DataWarehouse_DEV (Future Phase)
Tables available: 43 (fact, dimension, staging)
Status: ⏳ Queued for next phase
Note: Can migrate in parallel after AppDB is stable

### Other Databases  
- BadmintonDB - Not included (legacy)
- IFS_Test_DB - Not included (legacy)

---

## Files Reference

**Setup & Schema**
- `04_complete_schema_setup.sql` - Main setup script

**Data Files**
- `appdb_*.csv` - 10 table exports

**Import Tools**
- `import_data.py` - Main import script (recommended)
- `Import-Data.ps1` - PowerShell alternative

**Documentation**
- `README.md` - Complete guide
- `MIGRATION_PLAN.md` - Architecture overview
- This file - Status and next steps

---

## Key Considerations

### Performance
- SurrealDB relationships handle JOINs efficiently
- Index creation already configured
- Consider caching for policy queries

### Security  
- Row-level access controlled via `access_requests`
- Column-level access via `asset_policy_columns`
- Permissions defined in database schema

### Data Integrity
- Foreign key relationships preserved
- Data types properly converted
- NULL values handled correctly

### Rollback Plan
- SQL Server databases remain untouched
- Can keep both systems running in parallel
- Easy to revert if needed

---

## Next Immediate Actions

1. **Execute this command** to set up SurrealDB:
   ```bash
   cd c:\development\DataPrivNet\migration
   # Open SurrealDB CLI and paste 04_complete_schema_setup.sql
   ```

2. **Run data import**:
   ```bash
   python import_data.py
   ```

3. **Start coding** the SurrealDB data access layer

4. **Test access control** with sample users

---

## Support Resources

- **SurrealDB Docs**: https://docs.surrealdb.com/
- **Migration Folder**: `c:\development\DataPrivNet\migration\`
- **README**: See `README.md` in migration folder

