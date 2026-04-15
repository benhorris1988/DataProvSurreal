# SurrealDB Migration Checklist

## Phase 1: Preparation ✓ COMPLETE

- [x] Analyze source SQL Server databases
- [x] Design SurrealDB schema
- [x] Extract data from datamarketplace
- [x] Convert to CSV format (10 tables, 337 records)
- [x] Create SurrealDB setup script
- [x] Create import scripts (Python + PowerShell)
- [x] Create comprehensive documentation

---

## Phase 2: SurrealDB Setup (TODO - ~30 minutes)

### 2.1 Create Namespace and Databases
- [ ] Start SurrealDB: `surreal start file://./surreal.db`
- [ ] Open SurrealDB CLI: `surreal sql --conn http://localhost:8000`
- [ ] Execute `04_complete_schema_setup.sql`
  - [ ] Verify namespace created
  - [ ] Verify databases created
  - [ ] Verify tables created
  - [ ] Verify indexes created

### 2.2 Verify Schema
- [ ] Query table count
- [ ] Check relationships are defined
- [ ] Verify field types
- [ ] Check indexes exist

---

## Phase 3: Data Import (TODO - ~15 minutes)

### 3.1 Prepare Environment
- [ ] Navigate to migration folder
- [ ] Verify all CSV files present (10 files)
- [ ] Check CSV file sizes are not empty

### 3.2 Run Import
- [ ] Install Python: `pip install requests`
- [ ] Run: `python import_data.py`
- [ ] Monitor for errors
- [ ] Collect import statistics

### 3.3 Verify Import
- [ ] Count users: should be 6
- [ ] Count datasets: should be 44
- [ ] Count access_requests: should be 16
- [ ] Verify relationships (check foreign keys)
- [ ] Sample query results

---

## Phase 4: Application Integration (TODO - 2-3 days)

### 4.1 Configuration Updates
- [ ] Edit `appsettings.json`
  - [ ] Add SurrealDB connection string
  - [ ] Set namespace: `DataProvisioningEngine`
  - [ ] Set database: `AppDB`
- [ ] Remove SQL Server connection string

### 4.2 Dependency Installation
- [ ] Install SurrealDB .NET SDK
  - [ ] Via NuGet: `Install-Package SurrealDB`
  - [ ] Or download from https://github.com/surrealdb/surrealdb.net
- [ ] Add using statements

### 4.3 Data Access Layer
- [ ] Create `SurrealDbContext` class
- [ ] Create repository interfaces
  - [ ] IUserRepository
  - [ ] IDatasetRepository
  - [ ] IAccessRequestRepository
  - [ ] IPolicyRepository
- [ ] Implement repositories
- [ ] Add dependency injection configuration

### 4.4 Query Methods
- [ ] GetUserApprovedDatasets(userId)
- [ ] GetDatasetWithRLS(datasetId, userId)
- [ ] GetAccessRequest(userId, datasetId)
- [ ] GetPolicyConditions(policyId)
- [ ] GetVisibleColumns(policyId)

---

## Phase 5: Access Control Implementation (TODO - 1-2 days)

### 5.1 Catalog Service Updates
- [ ] Update CatalogService to use SurrealDB
- [ ] Implement dataset filtering per user
- [ ] Apply RLS from access_requests
- [ ] Filter columns per policy
- [ ] Test with sample queries

### 5.2 Access Request Service
- [ ] Update to query SurrealDB
- [ ] Implement request tracking
- [ ] Apply policy group linking
- [ ] Generate audit logs

### 5.3 Administration Service
- [ ] Update admin queries for SurrealDB
- [ ] Implement user management
- [ ] Dataset ownership tracking
- [ ] Policy management

---

## Phase 6: Testing (TODO - 1-2 days)

### 6.1 Unit Tests
- [ ] Test repository methods
- [ ] Test RLS condition building
- [ ] Test column filtering logic
- [ ] Test relationship queries
- [ ] Test null handling

### 6.2 Integration Tests
- [ ] Test full data access flow
- [ ] Test with Admin role
- [ ] Test with IAO role
- [ ] Test with IAA role
- [ ] Test with regular User role
- [ ] Test denied access scenarios

### 6.3 Security Tests
- [ ] Verify no unauthorized data leaks
- [ ] Test column-level security
- [ ] Test row-level security
- [ ] Verify policy enforcement
- [ ] Test permission boundaries

### 6.4 Performance Tests
- [ ] Measure query response times
- [ ] Test with realistic data volumes
- [ ] Check index usage
- [ ] Identify bottlenecks

---

## Phase 7: Validation (TODO - ~1 day)

### 7.1 Feature Validation
- [ ] Login works correctly
- [ ] Catalog displays correct datasets
- [ ] Access requests process correctly
- [ ] Approvals/rejections work
- [ ] Admin functions work
- [ ] Reports generate correctly

### 7.2 Data Validation
- [ ] All 44 datasets visible/managed
- [ ] All 6 users can log in
- [ ] All access requests preserved
- [ ] All policies applied
- [ ] No data loss

### 7.3 Integration Validation
- [ ] Windows Auth still works (if enabled)
- [ ] Test Mode still works
- [ ] Email notifications work
- [ ] Logging works
- [ ] Error handling works

---

## Phase 8: Deployment Preparation (TODO)

### 8.1 Pre-Deployment Checks
- [ ] All tests passing
- [ ] No compiler warnings
- [ ] Security review complete
- [ ] Performance validated
- [ ] Documentation updated

### 8.2 Rollback Plan
- [ ] Document rollback procedure
- [ ] Test rollback process
- [ ] Backup SurrealDB
- [ ] Backup appsettings
- [ ] Backup application code

### 8.3 Deployment
- [ ] Deploy to staging
- [ ] Run full test suite
- [ ] Deploy to production
- [ ] Monitor closely
- [ ] Document any issues

---

## Phase 9: Post-Deployment (TODO)

### 9.1 Monitoring Setup
- [ ] Set up query logging
- [ ] Configure performance alerts
- [ ] Set up error tracking
- [ ] Monitor access patterns
- [ ] Track security events

### 9.2 Documentation
- [ ] Update system architecture docs
- [ ] Document SurrealDB setup
- [ ] Create backup procedures
- [ ] Write troubleshooting guide
- [ ] Update API documentation

### 9.3 Decommission SQL Server
- [ ] Archive old database backups
- [ ] Remove development databases
- [ ] Update connection docs
- [ ] Notify users of change
- [ ] Remove old code references

---

## Optional: Future Work

### Data Warehouse Migration  
- [ ] Migrate remaining DataWarehouse_DEV tables
- [ ] Set up fact/dimension relationships
- [ ] Implement aggregate queries
- [ ] Create BI layer

### Advanced Features
- [ ] Add caching layer for permissions
- [ ] Implement policy templates
- [ ] Add audit trail dashboards
- [ ] Advanced RLS conditions

---

## Quick Reference

**Key Commands:**
```bash
# Start SurrealDB
surreal start file://./surreal.db

# Connect to SurrealDB CLI
surreal sql --conn http://localhost:8000 --user root --pass root

# Run import
cd c:\development\DataPrivNet\migration
python import_data.py
```

**Key Files:**
- Schema: `04_complete_schema_setup.sql`
- Import: `import_data.py`
- Data: `appdb_*.csv`
- Docs: `README.md`, `STATUS_AND_NEXT_STEPS.md`

**Key Emails to Notify (when complete):**
- IT Infrastructure Team
- Data Governance Team
- End Users

---

## Notes

- Expected completion: 4-6 days
- All SQL Server data preserved
- Parallel running possible for safety
- No breaking changes to API
- Full rollback capability

---

**Last Updated**: April 14, 2026
**Status**: PREPARATION COMPLETE - READY FOR IMPLEMENTATION
**Next Step**: Execute Phase 2.1 - Create SurrealDB Namespace

