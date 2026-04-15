-- SurrealDB Migration Setup Complete Script
-- Creates namespace, databases, and schema for Data Provisioning Engine

-- ========================================
-- STEP 1: Create Namespace
-- ========================================
DEFINE NAMESPACE `DataProvisioningEngine`;

-- ========================================
-- STEP 2: Create Databases within Namespace
-- ========================================
USE NAMESPACE `DataProvisioningEngine`;
DEFINE DATABASE `AppDB`;
DEFINE DATABASE `DataWarehouse`;

-- ========================================
-- STEP 3: Setup AppDB Schema
-- ========================================
USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;

-- Users table
DEFINE TABLE users SCHEMAFULL
    PERMISSIONS
        FOR select WHERE $auth != NONE,
        FOR create WHERE $auth.role == 'admin',
        FOR update WHERE $auth.role == 'admin',
        FOR delete WHERE $auth.role == 'admin';

DEFINE FIELD id ON TABLE users TYPE int PRIMARY KEY;
DEFINE FIELD name ON TABLE users TYPE string;
DEFINE FIELD email ON TABLE users TYPE string;
DEFINE FIELD role ON TABLE users TYPE string;
DEFINE FIELD avatar ON TABLE users TYPE string NULLABLE;
DEFINE FIELD created_at ON TABLE users TYPE datetime;

-- Reports table
DEFINE TABLE reports SCHEMAFULL;
DEFINE FIELD id ON TABLE reports TYPE int PRIMARY KEY;
DEFINE FIELD name ON TABLE reports TYPE string;
DEFINE FIELD url ON TABLE reports TYPE string NULLABLE;
DEFINE FIELD description ON TABLE reports TYPE string NULLABLE;

-- Virtual Groups table
DEFINE TABLE virtual_groups SCHEMAFULL;
DEFINE FIELD id ON TABLE virtual_groups TYPE int PRIMARY KEY;
DEFINE FIELD name ON TABLE virtual_groups TYPE string;
DEFINE FIELD owner_id ON TABLE virtual_groups TYPE record<users>;
DEFINE FIELD owner ON TABLE virtual_groups TYPE record<users>;  -- Relation shorthand
DEFINE FIELD description ON TABLE virtual_groups TYPE string NULLABLE;
DEFINE FIELD created_at ON TABLE virtual_groups TYPE datetime;

-- Datasets table
DEFINE TABLE datasets SCHEMAFULL;
DEFINE FIELD id ON TABLE datasets TYPE int PRIMARY KEY;
DEFINE FIELD name ON TABLE datasets TYPE string;
DEFINE FIELD type ON TABLE datasets TYPE string;
DEFINE FIELD description ON TABLE datasets TYPE string NULLABLE;
DEFINE FIELD owner_group_id ON TABLE datasets TYPE record<virtual_groups> NULLABLE;
DEFINE FIELD owner_group ON TABLE datasets TYPE record<virtual_groups>;  -- Relation
DEFINE FIELD created_at ON TABLE datasets TYPE datetime;

-- Columns table (metadata about dataset columns)
DEFINE TABLE columns SCHEMAFULL;
DEFINE FIELD id ON TABLE columns TYPE int PRIMARY KEY;
DEFINE FIELD dataset_id ON TABLE columns TYPE record<datasets>;
DEFINE FIELD dataset ON TABLE columns TYPE record<datasets>;  -- Relation
DEFINE FIELD name ON TABLE columns TYPE string;
DEFINE FIELD data_type ON TABLE columns TYPE string NULLABLE;
DEFINE FIELD definition ON TABLE columns TYPE string NULLABLE;
DEFINE FIELD is_pii ON TABLE columns TYPE bool;
DEFINE FIELD sample_data ON TABLE columns TYPE string NULLABLE;

-- Asset Policy Groups table
DEFINE TABLE asset_policy_groups SCHEMAFULL;
DEFINE FIELD id ON TABLE asset_policy_groups TYPE int PRIMARY KEY;
DEFINE FIELD dataset_id ON TABLE asset_policy_groups TYPE record<datasets>;
DEFINE FIELD dataset ON TABLE asset_policy_groups TYPE record<datasets>;  -- Relation
DEFINE FIELD owner_id ON TABLE asset_policy_groups TYPE record<users> NULLABLE;
DEFINE FIELD owner ON TABLE asset_policy_groups TYPE record<users>;  -- Relation
DEFINE FIELD name ON TABLE asset_policy_groups TYPE string;
DEFINE FIELD description ON TABLE asset_policy_groups TYPE string NULLABLE;
DEFINE FIELD created_at ON TABLE asset_policy_groups TYPE datetime;

-- Asset Policy Columns table
DEFINE TABLE asset_policy_columns SCHEMAFULL;
DEFINE FIELD id ON TABLE asset_policy_columns TYPE int PRIMARY KEY;
DEFINE FIELD policy_group_id ON TABLE asset_policy_columns TYPE record<asset_policy_groups>;
DEFINE FIELD policy_group ON TABLE asset_policy_columns TYPE record<asset_policy_groups>;  -- Relation
DEFINE FIELD column_name ON TABLE asset_policy_columns TYPE string;
DEFINE FIELD is_hidden ON TABLE asset_policy_columns TYPE bool;

-- Asset Policy Conditions table
DEFINE TABLE asset_policy_conditions SCHEMAFULL;
DEFINE FIELD id ON TABLE asset_policy_conditions TYPE int PRIMARY KEY;
DEFINE FIELD policy_group_id ON TABLE asset_policy_conditions TYPE record<asset_policy_groups>;
DEFINE FIELD policy_group ON TABLE asset_policy_conditions TYPE record<asset_policy_groups>;  -- Relation
DEFINE FIELD column_name ON TABLE asset_policy_conditions TYPE string;
DEFINE FIELD operator ON TABLE asset_policy_conditions TYPE string;
DEFINE FIELD value ON TABLE asset_policy_conditions TYPE string;

-- Virtual Group Members table (Junction/Bridge table)
DEFINE TABLE virtual_group_members SCHEMAFULL;
DEFINE FIELD group_id ON TABLE virtual_group_members TYPE record<virtual_groups>;
DEFINE FIELD group ON TABLE virtual_group_members TYPE record<virtual_groups>;  -- Relation
DEFINE FIELD user_id ON TABLE virtual_group_members TYPE record<users>;
DEFINE FIELD user ON TABLE virtual_group_members TYPE record<users>;  -- Relation
DEFINE FIELD added_at ON TABLE virtual_group_members TYPE datetime;

-- Report Datasets table (Junction/Bridge table)
DEFINE TABLE report_datasets SCHEMAFULL;
DEFINE FIELD dataset_id ON TABLE report_datasets TYPE record<datasets>;
DEFINE FIELD dataset ON TABLE report_datasets TYPE record<datasets>;  -- Relation
DEFINE FIELD report_id ON TABLE report_datasets TYPE record<reports>;
DEFINE FIELD report ON TABLE report_datasets TYPE record<reports>;  -- Relation

-- Access Requests table
DEFINE TABLE access_requests SCHEMAFULL
    PERMISSIONS
        FOR select WHERE user_id = $auth.id OR $auth.role == 'admin' OR $auth.role == 'iaa' OR $auth.role == 'iao',
        FOR create WHERE $auth != NONE,
        FOR update WHERE $auth.role == 'admin' OR $auth.role == 'iaa',
        FOR delete WHERE $auth.role == 'admin';

DEFINE FIELD id ON TABLE access_requests TYPE int PRIMARY KEY;
DEFINE FIELD user_id ON TABLE access_requests TYPE record<users>;
DEFINE FIELD user ON TABLE access_requests TYPE record<users>;  -- Relation
DEFINE FIELD dataset_id ON TABLE access_requests TYPE record<datasets>;
DEFINE FIELD dataset ON TABLE access_requests TYPE record<datasets>;  -- Relation
DEFINE FIELD status ON TABLE access_requests TYPE string;  -- pending, approved, rejected, revoked
DEFINE FIELD requested_rls_filters ON TABLE access_requests TYPE string NULLABLE;
DEFINE FIELD justification ON TABLE access_requests TYPE string NULLABLE;
DEFINE FIELD reviewed_by ON TABLE access_requests TYPE record<users> NULLABLE;
DEFINE FIELD reviewer ON TABLE access_requests TYPE record<users>;  -- Relation
DEFINE FIELD reviewed_at ON TABLE access_requests TYPE datetime NULLABLE;
DEFINE FIELD created_at ON TABLE access_requests TYPE datetime;
DEFINE FIELD policy_group_id ON TABLE access_requests TYPE record<asset_policy_groups> NULLABLE;
DEFINE FIELD policy_group ON TABLE access_requests TYPE record<asset_policy_groups>;  -- Relation

-- Initial Admins table
DEFINE TABLE initial_admins SCHEMAFULL;
DEFINE FIELD id ON TABLE initial_admins TYPE int PRIMARY KEY;
DEFINE FIELD username ON TABLE initial_admins TYPE string;
DEFINE FIELD added_at ON TABLE initial_admins TYPE datetime;

-- ========================================
-- STEP 4: Create Indexes for AppDB
-- ========================================
DEFINE INDEX idx_access_requests_user ON access_requests COLUMNS user_id;
DEFINE INDEX idx_access_requests_dataset ON access_requests COLUMNS dataset_id;
DEFINE INDEX idx_access_requests_status ON access_requests COLUMNS status;
DEFINE INDEX idx_datasets_owner_group ON datasets COLUMNS owner_group_id;
DEFINE INDEX idx_columns_dataset ON columns COLUMNS dataset_id;
DEFINE INDEX idx_asset_policy_groups_dataset ON asset_policy_groups COLUMNS dataset_id;
DEFINE INDEX idx_virtual_group_members_user ON virtual_group_members COLUMNS user_id;
DEFINE INDEX idx_virtual_group_members_group ON virtual_group_members COLUMNS group_id;

-- ========================================
-- STEP 5: Setup DataWarehouse Schema
-- ========================================
USE NAMESPACE `DataProvisioningEngine` DATABASE `DataWarehouse`;

-- Fact Tables
DEFINE TABLE fact_CustomerOrders SCHEMAFULL;
DEFINE TABLE FactInventory SCHEMAFULL;
DEFINE TABLE FactSales SCHEMAFULL;

-- Dimension Tables
DEFINE TABLE dim_Customer SCHEMAFULL;
DEFINE TABLE dim_Order SCHEMAFULL;
DEFINE TABLE dim_Part SCHEMAFULL;
DEFINE TABLE dim_SalesPart SCHEMAFULL;

-- Staging Tables (create as needed)
-- These will be created dynamically when data is imported

-- Permissions Map (for RLS enforcement)
DEFINE TABLE PermissionsMap SCHEMAFULL;
DEFINE FIELD id ON TABLE PermissionsMap TYPE string PRIMARY KEY;
DEFINE FIELD user_id ON TABLE PermissionsMap TYPE string;
DEFINE FIELD dataset_id ON TABLE PermissionsMap TYPE string;
DEFINE FIELD row_filter ON TABLE PermissionsMap TYPE string;
DEFINE FIELD created_at ON TABLE PermissionsMap TYPE datetime;

-- ========================================
-- Complete: Namespaces and Databases Ready
-- ========================================
-- The schema is now prepared for data import
-- Run the data import scripts next
