DEFINE NAMESPACE `DataProvisioningEngine`;
USE NAMESPACE `DataProvisioningEngine`;
DEFINE DATABASE `AppDB`;
DEFINE DATABASE `DataWarehouse`;
USE NAMESPACE `DataProvisioningEngine` DATABASE `AppDB`;

-- Users table
DEFINE TABLE users SCHEMAFULL;
DEFINE FIELD id ON TABLE users TYPE int PRIMARY KEY;
DEFINE FIELD name ON TABLE users TYPE string;
DEFINE FIELD email ON TABLE users TYPE string;
DEFINE FIELD role ON TABLE users TYPE string;
DEFINE FIELD avatar ON TABLE users TYPE string NULLABLE;
DEFINE FIELD created_at ON TABLE users TYPE datetime;

-- Virtual Groups table
DEFINE TABLE virtual_groups SCHEMAFULL;
DEFINE FIELD id ON TABLE virtual_groups TYPE int PRIMARY KEY;
DEFINE FIELD name ON TABLE virtual_groups TYPE string;
DEFINE FIELD owner_id ON TABLE virtual_groups TYPE int;
DEFINE FIELD description ON TABLE virtual_groups TYPE string NULLABLE;
DEFINE FIELD created_at ON TABLE virtual_groups TYPE datetime;

-- Datasets table
DEFINE TABLE datasets SCHEMAFULL;
DEFINE FIELD id ON TABLE datasets TYPE int PRIMARY KEY;
DEFINE FIELD name ON TABLE datasets TYPE string;
DEFINE FIELD type ON TABLE datasets TYPE string;
DEFINE FIELD description ON TABLE datasets TYPE string NULLABLE;
DEFINE FIELD owner_group_id ON TABLE datasets TYPE int NULLABLE;
DEFINE FIELD created_at ON TABLE datasets TYPE datetime;

-- Columns table
DEFINE TABLE columns SCHEMAFULL;
DEFINE FIELD id ON TABLE columns TYPE int PRIMARY KEY;
DEFINE FIELD dataset_id ON TABLE columns TYPE int;
DEFINE FIELD name ON TABLE columns TYPE string;
DEFINE FIELD data_type ON TABLE columns TYPE string NULLABLE;
DEFINE FIELD definition ON TABLE columns TYPE string NULLABLE;
DEFINE FIELD is_pii ON TABLE columns TYPE bool;
DEFINE FIELD sample_data ON TABLE columns TYPE string NULLABLE;

-- Asset Policy Groups table
DEFINE TABLE asset_policy_groups SCHEMAFULL;
DEFINE FIELD id ON TABLE asset_policy_groups TYPE int PRIMARY KEY;
DEFINE FIELD dataset_id ON TABLE asset_policy_groups TYPE int;
DEFINE FIELD owner_id ON TABLE asset_policy_groups TYPE int NULLABLE;
DEFINE FIELD name ON TABLE asset_policy_groups TYPE string;
DEFINE FIELD description ON TABLE asset_policy_groups TYPE string NULLABLE;
DEFINE FIELD created_at ON TABLE asset_policy_groups TYPE datetime;

-- Asset Policy Columns table
DEFINE TABLE asset_policy_columns SCHEMAFULL;
DEFINE FIELD id ON TABLE asset_policy_columns TYPE int PRIMARY KEY;
DEFINE FIELD policy_group_id ON TABLE asset_policy_columns TYPE int;
DEFINE FIELD column_name ON TABLE asset_policy_columns TYPE string;
DEFINE FIELD is_hidden ON TABLE asset_policy_columns TYPE bool;

-- Asset Policy Conditions table
DEFINE TABLE asset_policy_conditions SCHEMAFULL;
DEFINE FIELD id ON TABLE asset_policy_conditions TYPE int PRIMARY KEY;
DEFINE FIELD policy_group_id ON TABLE asset_policy_conditions TYPE int;
DEFINE FIELD column_name ON TABLE asset_policy_conditions TYPE string;
DEFINE FIELD operator ON TABLE asset_policy_conditions TYPE string;
DEFINE FIELD value ON TABLE asset_policy_conditions TYPE string;

-- Virtual Group Members table
DEFINE TABLE virtual_group_members SCHEMAFULL;
DEFINE FIELD group_id ON TABLE virtual_group_members TYPE int;
DEFINE FIELD user_id ON TABLE virtual_group_members TYPE int;
DEFINE FIELD added_at ON TABLE virtual_group_members TYPE datetime;

-- Access Requests table
DEFINE TABLE access_requests SCHEMAFULL;
DEFINE FIELD id ON TABLE access_requests TYPE int PRIMARY KEY;
DEFINE FIELD user_id ON TABLE access_requests TYPE int;
DEFINE FIELD dataset_id ON TABLE access_requests TYPE int;
DEFINE FIELD status ON TABLE access_requests TYPE string;
DEFINE FIELD requested_rls_filters ON TABLE access_requests TYPE string NULLABLE;
DEFINE FIELD justification ON TABLE access_requests TYPE string NULLABLE;
DEFINE FIELD reviewed_by ON TABLE access_requests TYPE int NULLABLE;
DEFINE FIELD reviewed_at ON TABLE access_requests TYPE datetime NULLABLE;
DEFINE FIELD created_at ON TABLE access_requests TYPE datetime;
DEFINE FIELD policy_group_id ON TABLE access_requests TYPE int NULLABLE;

-- Initial Admins table
DEFINE TABLE initial_admins SCHEMAFULL;
DEFINE FIELD id ON TABLE initial_admins TYPE int PRIMARY KEY;
DEFINE FIELD username ON TABLE initial_admins TYPE string;
DEFINE FIELD added_at ON TABLE initial_admins TYPE datetime;

-- Create indexes
DEFINE INDEX idx_access_requests_user ON access_requests COLUMNS user_id;
DEFINE INDEX idx_access_requests_dataset ON access_requests COLUMNS dataset_id;
DEFINE INDEX idx_access_requests_status ON access_requests COLUMNS status;
DEFINE INDEX idx_datasets_owner_group ON datasets COLUMNS owner_group_id;
DEFINE INDEX idx_columns_dataset ON columns COLUMNS dataset_id;
DEFINE INDEX idx_asset_policy_groups_dataset ON asset_policy_groups COLUMNS dataset_id;
DEFINE INDEX idx_virtual_group_members_user ON virtual_group_members COLUMNS user_id;
