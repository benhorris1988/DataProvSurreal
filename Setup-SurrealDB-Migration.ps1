#!/usr/bin/env pwsh
# SurrealDB Migration Setup Script
# This script migrates data from SQL Server to SurrealDB

param(
    [string]$SqlServer = "localhost",
    [string]$SurrealUrl = "http://localhost:8000",
    [string]$SurrealUser = "root",
    [string]$SurrealPass = "root"
)

# Define the namespace and databases in SurrealDB
$namespaceSetup = @"
DEFINE NAMESPACE `DataProvisioningEngine`;
"@

Write-Host "Setting up SurrealDB Namespace..."
Write-Host $namespaceSetup

# Define the AppDB schema
$appDbSchema = @"
USE NAMESPACE `DataProvisioningEngine`;
DEFINE DATABASE `AppDB`;
USE DATABASE `AppDB`;

-- Users table
DEFINE TABLE users SCHEMAFULL;
DEFINE FIELD id ON users TYPE int;
DEFINE FIELD name ON users TYPE string;
DEFINE FIELD email ON users TYPE string;
DEFINE FIELD role ON users TYPE string;
DEFINE FIELD avatar ON users TYPE string;
DEFINE FIELD created_at ON users TYPE datetime;

-- Reports table
DEFINE TABLE reports SCHEMAFULL;
DEFINE FIELD id ON reports TYPE int;
DEFINE FIELD name ON reports TYPE string;
DEFINE FIELD url ON reports TYPE string;
DEFINE FIELD description ON reports TYPE string;

-- Virtual Groups table
DEFINE TABLE virtual_groups SCHEMAFULL;
DEFINE FIELD id ON virtual_groups TYPE int;
DEFINE FIELD name ON virtual_groups TYPE string;
DEFINE FIELD owner_id ON virtual_groups TYPE record<users>;
DEFINE FIELD description ON virtual_groups TYPE string;
DEFINE FIELD created_at ON virtual_groups TYPE datetime;

-- Datasets table
DEFINE TABLE datasets SCHEMAFULL;
DEFINE FIELD id ON datasets TYPE int;
DEFINE FIELD name ON datasets TYPE string;
DEFINE FIELD type ON datasets TYPE string;
DEFINE FIELD description ON datasets TYPE string;
DEFINE FIELD owner_group_id ON datasets TYPE record<virtual_groups>;
DEFINE FIELD created_at ON datasets TYPE datetime;

-- Columns table
DEFINE TABLE columns SCHEMAFULL;
DEFINE FIELD id ON columns TYPE int;
DEFINE FIELD dataset_id ON columns TYPE record<datasets>;
DEFINE FIELD name ON columns TYPE string;
DEFINE FIELD data_type ON columns TYPE string;
DEFINE FIELD definition ON columns TYPE string;
DEFINE FIELD is_pii ON columns TYPE bool;
DEFINE FIELD sample_data ON columns TYPE string;

-- Asset Policy Groups table
DEFINE TABLE asset_policy_groups SCHEMAFULL;
DEFINE FIELD id ON asset_policy_groups TYPE int;
DEFINE FIELD dataset_id ON asset_policy_groups TYPE record<datasets>;
DEFINE FIELD owner_id ON asset_policy_groups TYPE record<users>;
DEFINE FIELD name ON asset_policy_groups TYPE string;
DEFINE FIELD description ON asset_policy_groups TYPE string;
DEFINE FIELD created_at ON asset_policy_groups TYPE datetime;

-- Asset Policy Columns table
DEFINE TABLE asset_policy_columns SCHEMAFULL;
DEFINE FIELD id ON asset_policy_columns TYPE int;
DEFINE FIELD policy_group_id ON asset_policy_columns TYPE record<asset_policy_groups>;
DEFINE FIELD column_name ON asset_policy_columns TYPE string;
DEFINE FIELD is_hidden ON asset_policy_columns TYPE bool;

-- Asset Policy Conditions table
DEFINE TABLE asset_policy_conditions SCHEMAFULL;
DEFINE FIELD id ON asset_policy_conditions TYPE int;
DEFINE FIELD policy_group_id ON asset_policy_conditions TYPE record<asset_policy_groups>;
DEFINE FIELD column_name ON asset_policy_conditions TYPE string;
DEFINE FIELD operator ON asset_policy_conditions TYPE string;
DEFINE FIELD value ON asset_policy_conditions TYPE string;

-- Virtual Group Members table (junction)
DEFINE TABLE virtual_group_members SCHEMAFULL;
DEFINE FIELD group_id ON virtual_group_members TYPE record<virtual_groups>;
DEFINE FIELD user_id ON virtual_group_members TYPE record<users>;
DEFINE FIELD added_at ON virtual_group_members TYPE datetime;

-- Report Datasets table (junction)
DEFINE TABLE report_datasets SCHEMAFULL;
DEFINE FIELD dataset_id ON report_datasets TYPE record<datasets>;
DEFINE FIELD report_id ON report_datasets TYPE record<reports>;

-- Access Requests table
DEFINE TABLE access_requests SCHEMAFULL;
DEFINE FIELD id ON access_requests TYPE int;
DEFINE FIELD user_id ON access_requests TYPE record<users>;
DEFINE FIELD dataset_id ON access_requests TYPE record<datasets>;
DEFINE FIELD status ON access_requests TYPE string;
DEFINE FIELD requested_rls_filters ON access_requests TYPE string;
DEFINE FIELD justification ON access_requests TYPE string;
DEFINE FIELD reviewed_by ON access_requests TYPE record<users>;
DEFINE FIELD reviewed_at ON access_requests TYPE datetime;
DEFINE FIELD created_at ON access_requests TYPE datetime;
DEFINE FIELD policy_group_id ON access_requests TYPE record<asset_policy_groups>;
"@

Write-Host "AppDB Schema Definition prepared"

# Save for later execution
$namespaceSetup | Out-File -FilePath "$PSScriptRoot\02_namespace_setup.sql" -Encoding UTF8 -Force
$appDbSchema | Out-File -FilePath "$PSScriptRoot\03_appdb_schema.sql" -Encoding UTF8 -Force

Write-Host "Migration files created:"
Write-Host "- 02_namespace_setup.sql"
Write-Host "- 03_appdb_schema.sql"
