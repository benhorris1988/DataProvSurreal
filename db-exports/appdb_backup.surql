-- ------------------------------
-- OPTION
-- ------------------------------

OPTION IMPORT;

-- ------------------------------
-- ACCESSES
-- ------------------------------

DEFINE ACCESS user_token ON DATABASE TYPE RECORD SIGNIN (SELECT * FROM users WHERE email = $email) WITH JWT ALGORITHM HS512 KEY '[REDACTED]' WITH ISSUER KEY '[REDACTED]' DURATION FOR TOKEN 1h, FOR SESSION 1w;

-- ------------------------------
-- TABLE: access_requests
-- ------------------------------

DEFINE TABLE access_requests TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE user_id = $auth.id OR $auth.role INSIDE ['Admin', 'IAO', 'IAA'], FOR create WHERE $auth != NONE, FOR update WHERE $auth.role INSIDE ['Admin', 'IAO', 'IAA'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD created_at ON access_requests TYPE datetime PERMISSIONS FULL;
DEFINE FIELD dataset_id ON access_requests TYPE record<datasets> PERMISSIONS FULL;
DEFINE FIELD justification ON access_requests TYPE none | string PERMISSIONS FULL;
DEFINE FIELD policy_group_id ON access_requests TYPE none | record<asset_policy_groups> PERMISSIONS FULL;
DEFINE FIELD requested_rls_filters ON access_requests TYPE none | string PERMISSIONS FULL;
DEFINE FIELD reviewed_at ON access_requests TYPE none | datetime PERMISSIONS FULL;
DEFINE FIELD reviewed_by ON access_requests TYPE none | record<users> PERMISSIONS FULL;
DEFINE FIELD status ON access_requests TYPE string PERMISSIONS FULL;
DEFINE FIELD user_id ON access_requests TYPE record<users> PERMISSIONS FULL;

DEFINE INDEX ar_dataset ON access_requests FIELDS dataset_id;
DEFINE INDEX ar_status ON access_requests FIELDS status;
DEFINE INDEX ar_user ON access_requests FIELDS user_id;


-- ------------------------------
-- TABLE DATA: access_requests
-- ------------------------------

INSERT [ { created_at: d'2026-01-27T17:35:47.420Z', dataset_id: datasets:1, id: access_requests:6, justification: 'need access', requested_rls_filters: '[]', reviewed_at: d'2026-01-28T11:39:35.860Z', reviewed_by: users:2, status: 'Approved', user_id: users:3 }, { created_at: d'2026-01-28T11:23:46.103Z', dataset_id: datasets:1, id: access_requests:7, justification: 'need some access please', requested_rls_filters: '{"Sector":"Marine"}', reviewed_at: d'2026-02-17T12:20:01.407Z', reviewed_by: users:2, status: 'Approved', user_id: users:2 }, { created_at: d'2026-01-28T11:39:09.123Z', dataset_id: datasets:3, id: access_requests:8, justification: 'need some access please!', reviewed_at: d'2026-02-17T12:19:57.977Z', reviewed_by: users:2, status: 'Approved', user_id: users:2 }, { created_at: d'2026-02-17T12:34:13.047Z', dataset_id: datasets:14, id: access_requests:1008, justification: 'Need some land data', reviewed_at: d'2026-02-17T12:34:43.717Z', reviewed_by: users:3, status: 'Approved', user_id: users:1 }, { created_at: d'2026-02-17T16:50:23.497Z', dataset_id: datasets:14, id: access_requests:1011, justification: 'need access', reviewed_at: d'2026-02-17T16:52:13.447Z', reviewed_by: users:3, status: 'Rejected', user_id: users:4 }, { created_at: d'2026-02-17T19:55:37.770Z', dataset_id: datasets:18, id: access_requests:1015, justification: 'ok', reviewed_at: d'2026-02-17T19:56:25.737Z', reviewed_by: users:5, status: 'Rejected', user_id: users:5 }, { created_at: d'2026-02-17T19:55:44.893Z', dataset_id: datasets:18, id: access_requests:1016, justification: 'ok', reviewed_at: d'2026-02-17T19:56:25.063Z', reviewed_by: users:5, status: 'Rejected', user_id: users:5 }, { created_at: d'2026-02-17T19:55:47.937Z', dataset_id: datasets:18, id: access_requests:1017, justification: 'ok', reviewed_at: d'2026-02-17T19:56:24.293Z', reviewed_by: users:5, status: 'Rejected', user_id: users:5 }, { created_at: d'2026-02-17T19:58:41.857Z', dataset_id: datasets:18, id: access_requests:1019, justification: 'ok', reviewed_at: d'2026-02-17T19:58:50.467Z', reviewed_by: users:5, status: 'Approved', user_id: users:5 }, { created_at: d'2026-02-17T19:58:45.517Z', dataset_id: datasets:18, id: access_requests:1020, justification: 'ok', reviewed_at: d'2026-02-17T19:58:49.517Z', reviewed_by: users:5, status: 'Approved', user_id: users:5 }, { created_at: d'2026-02-18T08:59:24Z', dataset_id: datasets:14, id: access_requests:2013, justification: 'ok', reviewed_at: d'2026-02-26T13:25:28.977Z', reviewed_by: users:3, status: 'Approved', user_id: users:4 }, { created_at: d'2026-02-18T18:47:20.417Z', dataset_id: datasets:18, id: access_requests:3007, justification: 'ok', status: 'Pending', user_id: users:1 }, { created_at: d'2026-02-18T19:10:14.947Z', dataset_id: datasets:14, id: access_requests:3008, justification: 'ok', status: 'Pending', user_id: users:3 } ];

-- ------------------------------
-- TABLE: asset_policy_columns
-- ------------------------------

DEFINE TABLE asset_policy_columns TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth.role INSIDE ['Admin', 'IAO', 'IAA'], FOR create, update WHERE $auth.role INSIDE ['Admin', 'IAO'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD column_name ON asset_policy_columns TYPE string PERMISSIONS FULL;
DEFINE FIELD is_hidden ON asset_policy_columns TYPE bool PERMISSIONS FULL;
DEFINE FIELD policy_group_id ON asset_policy_columns TYPE record<asset_policy_groups> PERMISSIONS FULL;



-- ------------------------------
-- TABLE DATA: asset_policy_columns
-- ------------------------------


-- ------------------------------
-- TABLE: asset_policy_conditions
-- ------------------------------

DEFINE TABLE asset_policy_conditions TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth.role INSIDE ['Admin', 'IAO', 'IAA'], FOR create, update WHERE $auth.role INSIDE ['Admin', 'IAO'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD column_name ON asset_policy_conditions TYPE string PERMISSIONS FULL;
DEFINE FIELD operator ON asset_policy_conditions TYPE string PERMISSIONS FULL;
DEFINE FIELD policy_group_id ON asset_policy_conditions TYPE record<asset_policy_groups> PERMISSIONS FULL;
DEFINE FIELD `value` ON asset_policy_conditions TYPE string PERMISSIONS FULL;



-- ------------------------------
-- TABLE DATA: asset_policy_conditions
-- ------------------------------


-- ------------------------------
-- TABLE: asset_policy_groups
-- ------------------------------

DEFINE TABLE asset_policy_groups TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth.role INSIDE ['Admin', 'IAO', 'IAA'], FOR create WHERE $auth.role INSIDE ['Admin', 'IAO'], FOR update WHERE $auth.id = owner_id OR $auth.role = 'Admin', FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD created_at ON asset_policy_groups TYPE datetime PERMISSIONS FULL;
DEFINE FIELD dataset_id ON asset_policy_groups TYPE record<datasets> PERMISSIONS FULL;
DEFINE FIELD description ON asset_policy_groups TYPE none | string PERMISSIONS FULL;
DEFINE FIELD name ON asset_policy_groups TYPE string PERMISSIONS FULL;
DEFINE FIELD owner_id ON asset_policy_groups TYPE none | record<users> PERMISSIONS FULL;

DEFINE INDEX apg_dataset ON asset_policy_groups FIELDS dataset_id;


-- ------------------------------
-- TABLE DATA: asset_policy_groups
-- ------------------------------


-- ------------------------------
-- TABLE: columns
-- ------------------------------

DEFINE TABLE columns TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth != NONE, FOR create, update WHERE $auth.role INSIDE ['Admin', 'IAO'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD data_type ON columns TYPE none | string PERMISSIONS FULL;
DEFINE FIELD dataset_id ON columns TYPE record<datasets> PERMISSIONS FULL;
DEFINE FIELD definition ON columns TYPE none | string PERMISSIONS FULL;
DEFINE FIELD is_pii ON columns TYPE bool PERMISSIONS FULL;
DEFINE FIELD name ON columns TYPE string PERMISSIONS FULL;
DEFINE FIELD sample_data ON columns TYPE none | string PERMISSIONS FULL;

DEFINE INDEX columns_dataset ON columns FIELDS dataset_id;


-- ------------------------------
-- TABLE DATA: columns
-- ------------------------------

INSERT [ { data_type: 'INT', dataset_id: datasets:1, definition: 'Unique identifier for the sale', id: columns:1, is_pii: false, name: 'TransactionID', sample_data: '1001, 1002, 1003' }, { data_type: 'DECIMAL(10,2)', dataset_id: datasets:1, definition: 'Total value of the transaction', id: columns:2, is_pii: false, name: 'Amount', sample_data: '$500.00, $120.50' }, { data_type: 'VARCHAR(50)', dataset_id: datasets:1, definition: 'Business sector (Marine, Land, Aviation, Nuclear)', id: columns:3, is_pii: false, name: 'Sector', sample_data: 'Marine, Land' }, { data_type: 'DATETIME', dataset_id: datasets:1, definition: 'Transaction timestamp', id: columns:4, is_pii: false, name: 'Date', sample_data: '2023-01-01 10:00:00' }, { data_type: 'varchar(-1)', dataset_id: datasets:4, definition: 'Imported column', id: columns:1002, is_pii: false, name: 'COMPANY' }, { data_type: 'varchar(-1)', dataset_id: datasets:4, definition: 'Imported column', id: columns:1003, is_pii: false, name: 'VOUCHER_TYPE' }, { data_type: 'bigint', dataset_id: datasets:4, definition: 'Imported column', id: columns:1004, is_pii: false, name: 'VOUCHER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:4, definition: 'Imported column', id: columns:1005, is_pii: false, name: 'ACCOUNT' }, { data_type: 'datetime', dataset_id: datasets:4, definition: 'Imported column', id: columns:1006, is_pii: false, name: 'VOUCHER_DATE' }, { data_type: 'float', dataset_id: datasets:4, definition: 'Imported column', id: columns:1007, is_pii: false, name: 'AMOUNT' }, { data_type: 'varchar(-1)', dataset_id: datasets:4, definition: 'Imported column', id: columns:1008, is_pii: false, name: 'CURRENCY_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:5, definition: 'Imported column', id: columns:1009, is_pii: false, name: 'COMPANY' }, { data_type: 'varchar(-1)', dataset_id: datasets:5, definition: 'Imported column', id: columns:1010, is_pii: false, name: 'ACCOUNT' }, { data_type: 'varchar(-1)', dataset_id: datasets:5, definition: 'Imported column', id: columns:1011, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'varchar(-1)', dataset_id: datasets:5, definition: 'Imported column', id: columns:1012, is_pii: false, name: 'ACC_GROUP' }, { data_type: 'int', dataset_id: datasets:6, definition: 'Imported column', id: columns:1013, is_pii: false, name: 'id' }, { data_type: 'varchar(5)', dataset_id: datasets:6, definition: 'Imported column', id: columns:1014, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(25)', dataset_id: datasets:6, definition: 'Imported column', id: columns:1015, is_pii: false, name: 'PART_NO' }, { data_type: 'varchar(200)', dataset_id: datasets:6, definition: 'Imported column', id: columns:1016, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'varchar(10)', dataset_id: datasets:6, definition: 'Imported column', id: columns:1017, is_pii: false, name: 'UNIT_MEAS' }, { data_type: 'varchar(20)', dataset_id: datasets:6, definition: 'Imported column', id: columns:1018, is_pii: false, name: 'TYPE_CODE' }, { data_type: 'datetime', dataset_id: datasets:6, definition: 'Imported column', id: columns:1019, is_pii: false, name: 'effective_date' }, { data_type: 'datetime', dataset_id: datasets:6, definition: 'Imported column', id: columns:1020, is_pii: false, name: 'expiry_date' }, { data_type: 'bit', dataset_id: datasets:6, definition: 'Imported column', id: columns:1021, is_pii: false, name: 'is_current' }, { data_type: 'bigint', dataset_id: datasets:7, definition: 'Imported column', id: columns:1022, is_pii: false, name: 'TRANSACTION_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:7, definition: 'Imported column', id: columns:1023, is_pii: false, name: 'PART_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:7, definition: 'Imported column', id: columns:1024, is_pii: false, name: 'CONTRACT' }, { data_type: 'float', dataset_id: datasets:7, definition: 'Imported column', id: columns:1025, is_pii: false, name: 'QUANTITY' }, { data_type: 'varchar(-1)', dataset_id: datasets:7, definition: 'Imported column', id: columns:1026, is_pii: false, name: 'DIRECTION' }, { data_type: 'varchar(-1)', dataset_id: datasets:7, definition: 'Imported column', id: columns:1027, is_pii: false, name: 'SOURCE_REF1' }, { data_type: 'varchar(-1)', dataset_id: datasets:7, definition: 'Imported column', id: columns:1028, is_pii: false, name: 'SOURCE_REF2' }, { data_type: 'varchar(-1)', dataset_id: datasets:7, definition: 'Imported column', id: columns:1029, is_pii: false, name: 'SOURCE_REF3' }, { data_type: 'datetime', dataset_id: datasets:7, definition: 'Imported column', id: columns:1030, is_pii: false, name: 'DATE_APPLIED' }, { data_type: 'varchar(-1)', dataset_id: datasets:8, definition: 'Imported column', id: columns:1031, is_pii: false, name: 'ORDER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:8, definition: 'Imported column', id: columns:1032, is_pii: false, name: 'LINE_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:8, definition: 'Imported column', id: columns:1033, is_pii: false, name: 'RELEASE_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:8, definition: 'Imported column', id: columns:1034, is_pii: false, name: 'PART_NO' }, { data_type: 'float', dataset_id: datasets:8, definition: 'Imported column', id: columns:1035, is_pii: false, name: 'BUY_QTY_DUE' }, { data_type: 'float', dataset_id: datasets:8, definition: 'Imported column', id: columns:1036, is_pii: false, name: 'BUY_UNIT_PRICE' }, { data_type: 'datetime', dataset_id: datasets:8, definition: 'Imported column', id: columns:1037, is_pii: false, name: 'PLANNED_RECEIPT_DATE' }, { data_type: 'datetime', dataset_id: datasets:8, definition: 'Imported column', id: columns:1038, is_pii: false, name: 'PROMISED_DELIVERY_DATE' }, { data_type: 'datetime', dataset_id: datasets:8, definition: 'Imported column', id: columns:1039, is_pii: false, name: 'ACTUAL_DELIVERY_DATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:8, definition: 'Imported column', id: columns:1040, is_pii: false, name: 'DEMAND_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:8, definition: 'Imported column', id: columns:1041, is_pii: false, name: 'DEMAND_ORDER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:8, definition: 'Imported column', id: columns:1042, is_pii: false, name: 'STATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:9, definition: 'Imported column', id: columns:1043, is_pii: false, name: 'QUOTATION_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:9, definition: 'Imported column', id: columns:1044, is_pii: false, name: 'LINE_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:9, definition: 'Imported column', id: columns:1045, is_pii: false, name: 'REL_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:9, definition: 'Imported column', id: columns:1046, is_pii: false, name: 'PART_NO' }, { data_type: 'float', dataset_id: datasets:9, definition: 'Imported column', id: columns:1047, is_pii: false, name: 'PRICE' }, { data_type: 'float', dataset_id: datasets:9, definition: 'Imported column', id: columns:1048, is_pii: false, name: 'QTY' }, { data_type: 'varchar(-1)', dataset_id: datasets:10, definition: 'Imported column', id: columns:1049, is_pii: false, name: 'QUOTATION_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:10, definition: 'Imported column', id: columns:1050, is_pii: false, name: 'CUSTOMER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:10, definition: 'Imported column', id: columns:1051, is_pii: false, name: 'CONTRACT' }, { data_type: 'datetime', dataset_id: datasets:10, definition: 'Imported column', id: columns:1052, is_pii: false, name: 'DATE_ENTERED' }, { data_type: 'varchar(-1)', dataset_id: datasets:10, definition: 'Imported column', id: columns:1053, is_pii: false, name: 'STATE' }, { data_type: 'bigint', dataset_id: datasets:10, definition: 'Imported column', id: columns:1054, is_pii: false, name: 'PROBABILITY' }, { data_type: 'varchar(-1)', dataset_id: datasets:11, definition: 'Imported column', id: columns:1055, is_pii: false, name: 'COMPANY' }, { data_type: 'varchar(-1)', dataset_id: datasets:11, definition: 'Imported column', id: columns:1056, is_pii: false, name: 'POS_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:11, definition: 'Imported column', id: columns:1057, is_pii: false, name: 'POS_TITLE' }, { data_type: 'varchar(-1)', dataset_id: datasets:12, definition: 'Imported column', id: columns:1058, is_pii: false, name: 'DOC_CLASS' }, { data_type: 'varchar(-1)', dataset_id: datasets:12, definition: 'Imported column', id: columns:1059, is_pii: false, name: 'DOC_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:12, definition: 'Imported column', id: columns:1060, is_pii: false, name: 'TITLE' }, { data_type: 'varchar(-1)', dataset_id: datasets:13, definition: 'Imported column', id: columns:1061, is_pii: false, name: 'DOC_CLASS' }, { data_type: 'varchar(-1)', dataset_id: datasets:13, definition: 'Imported column', id: columns:1062, is_pii: false, name: 'DOC_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:13, definition: 'Imported column', id: columns:1063, is_pii: false, name: 'DOC_SHEET' }, { data_type: 'varchar(-1)', dataset_id: datasets:13, definition: 'Imported column', id: columns:1064, is_pii: false, name: 'DOC_REV' }, { data_type: 'varchar(-1)', dataset_id: datasets:13, definition: 'Imported column', id: columns:1065, is_pii: false, name: 'STATE' }, { data_type: 'datetime', dataset_id: datasets:13, definition: 'Imported column', id: columns:1066, is_pii: false, name: 'DT_CRE' }, { data_type: 'int', dataset_id: datasets:14, definition: 'Imported column', id: columns:1067, is_pii: false, name: 'id' }, { data_type: 'varchar(20)', dataset_id: datasets:14, definition: 'Imported column', id: columns:1068, is_pii: false, name: 'CUSTOMER_NO' }, { data_type: 'varchar(100)', dataset_id: datasets:14, definition: 'Imported column', id: columns:1069, is_pii: false, name: 'NAME' }, { data_type: 'varchar(20)', dataset_id: datasets:14, definition: 'Imported column', id: columns:1070, is_pii: false, name: 'CATEGORY' }, { data_type: 'datetime', dataset_id: datasets:14, definition: 'Imported column', id: columns:1071, is_pii: false, name: 'effective_date' }, { data_type: 'datetime', dataset_id: datasets:14, definition: 'Imported column', id: columns:1072, is_pii: false, name: 'expiry_date' }, { data_type: 'bit', dataset_id: datasets:14, definition: 'Imported column', id: columns:1073, is_pii: false, name: 'is_current' }, { data_type: 'varchar(-1)', dataset_id: datasets:15, definition: 'Imported column', id: columns:1074, is_pii: false, name: 'COMPANY' }, { data_type: 'varchar(-1)', dataset_id: datasets:15, definition: 'Imported column', id: columns:1075, is_pii: false, name: 'EMP_NO' }, { data_type: 'datetime', dataset_id: datasets:15, definition: 'Imported column', id: columns:1076, is_pii: false, name: 'VALID_FROM' }, { data_type: 'float', dataset_id: datasets:15, definition: 'Imported column', id: columns:1077, is_pii: false, name: 'AMOUNT' }, { data_type: 'varchar(-1)', dataset_id: datasets:15, definition: 'Imported column', id: columns:1078, is_pii: false, name: 'CURRENCY_CODE' }, { data_type: 'int', dataset_id: datasets:16, definition: 'Imported column', id: columns:1079, is_pii: false, name: 'id' }, { data_type: 'varchar(5)', dataset_id: datasets:16, definition: 'Imported column', id: columns:1080, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(25)', dataset_id: datasets:16, definition: 'Imported column', id: columns:1081, is_pii: false, name: 'PART_NO' }, { data_type: 'varchar(200)', dataset_id: datasets:16, definition: 'Imported column', id: columns:1082, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'datetime', dataset_id: datasets:16, definition: 'Imported column', id: columns:1083, is_pii: false, name: 'effective_date' }, { data_type: 'datetime', dataset_id: datasets:16, definition: 'Imported column', id: columns:1084, is_pii: false, name: 'expiry_date' }, { data_type: 'bit', dataset_id: datasets:16, definition: 'Imported column', id: columns:1085, is_pii: false, name: 'is_current' }, { data_type: 'varchar(-1)', dataset_id: datasets:17, definition: 'Imported column', id: columns:1086, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:17, definition: 'Imported column', id: columns:1087, is_pii: false, name: 'LOCATION_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:17, definition: 'Imported column', id: columns:1088, is_pii: false, name: 'WAREHOUSE_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:17, definition: 'Imported column', id: columns:1089, is_pii: false, name: 'BAY_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:17, definition: 'Imported column', id: columns:1090, is_pii: false, name: 'ROW_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:17, definition: 'Imported column', id: columns:1091, is_pii: false, name: 'TIER_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:17, definition: 'Imported column', id: columns:1092, is_pii: false, name: 'BIN_ID' }, { data_type: 'int', dataset_id: datasets:18, definition: 'Imported column', id: columns:1093, is_pii: false, name: 'id' }, { data_type: 'varchar(12)', dataset_id: datasets:18, definition: 'Imported column', id: columns:1094, is_pii: false, name: 'ORDER_NO' }, { data_type: 'datetime', dataset_id: datasets:18, definition: 'Imported column', id: columns:1095, is_pii: false, name: 'DATE_ENTERED' }, { data_type: 'datetime', dataset_id: datasets:18, definition: 'Imported column', id: columns:1096, is_pii: false, name: 'effective_date' }, { data_type: 'datetime', dataset_id: datasets:18, definition: 'Imported column', id: columns:1097, is_pii: false, name: 'expiry_date' }, { data_type: 'bit', dataset_id: datasets:18, definition: 'Imported column', id: columns:1098, is_pii: false, name: 'is_current' }, { data_type: 'varchar(-1)', dataset_id: datasets:19, definition: 'Imported column', id: columns:1099, is_pii: false, name: 'NCR_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:19, definition: 'Imported column', id: columns:1100, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'varchar(-1)', dataset_id: datasets:19, definition: 'Imported column', id: columns:1101, is_pii: false, name: 'SEVERITY_ID' }, { data_type: 'datetime', dataset_id: datasets:19, definition: 'Imported column', id: columns:1102, is_pii: false, name: 'TARGET_DATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:19, definition: 'Imported column', id: columns:1103, is_pii: false, name: 'STATE' }, { data_type: 'int', dataset_id: datasets:20, definition: 'Imported column', id: columns:1104, is_pii: false, name: 'fact_id' }, { data_type: 'float', dataset_id: datasets:20, definition: 'Imported column', id: columns:1105, is_pii: false, name: 'BUY_QTY_DUE' }, { data_type: 'datetime', dataset_id: datasets:20, definition: 'Imported column', id: columns:1106, is_pii: false, name: 'load_date' }, { data_type: 'bigint', dataset_id: datasets:21, definition: 'Imported column', id: columns:1107, is_pii: false, name: 'PM_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:21, definition: 'Imported column', id: columns:1108, is_pii: false, name: 'PM_REVISION' }, { data_type: 'varchar(-1)', dataset_id: datasets:21, definition: 'Imported column', id: columns:1109, is_pii: false, name: 'MCH_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:21, definition: 'Imported column', id: columns:1110, is_pii: false, name: 'ACTION_ID' }, { data_type: 'bigint', dataset_id: datasets:21, definition: 'Imported column', id: columns:1111, is_pii: false, name: 'INTERVAL' }, { data_type: 'varchar(-1)', dataset_id: datasets:22, definition: 'Imported column', id: columns:1112, is_pii: false, name: 'COMPANY' }, { data_type: 'bigint', dataset_id: datasets:22, definition: 'Imported column', id: columns:1113, is_pii: false, name: 'INVOICE_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:22, definition: 'Imported column', id: columns:1114, is_pii: false, name: 'PARTY_TYPE' }, { data_type: 'varchar(-1)', dataset_id: datasets:22, definition: 'Imported column', id: columns:1115, is_pii: false, name: 'IDENTITY' }, { data_type: 'datetime', dataset_id: datasets:22, definition: 'Imported column', id: columns:1116, is_pii: false, name: 'INVOICE_DATE' }, { data_type: 'float', dataset_id: datasets:22, definition: 'Imported column', id: columns:1117, is_pii: false, name: 'GROSS_AMOUNT' }, { data_type: 'varchar(-1)', dataset_id: datasets:23, definition: 'Imported column', id: columns:1118, is_pii: false, name: 'BUYER_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:23, definition: 'Imported column', id: columns:1119, is_pii: false, name: 'NAME' }, { data_type: 'varchar(-1)', dataset_id: datasets:24, definition: 'Imported column', id: columns:1120, is_pii: false, name: 'REQUISITION_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:24, definition: 'Imported column', id: columns:1121, is_pii: false, name: 'LINE_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:24, definition: 'Imported column', id: columns:1122, is_pii: false, name: 'RELEASE_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:24, definition: 'Imported column', id: columns:1123, is_pii: false, name: 'PART_NO' }, { data_type: 'float', dataset_id: datasets:24, definition: 'Imported column', id: columns:1124, is_pii: false, name: 'ORIGINAL_QTY' }, { data_type: 'datetime', dataset_id: datasets:24, definition: 'Imported column', id: columns:1125, is_pii: false, name: 'LATEST_ORDER_DATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:24, definition: 'Imported column', id: columns:1126, is_pii: false, name: 'VENDOR_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:24, definition: 'Imported column', id: columns:1127, is_pii: false, name: 'STATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:25, definition: 'Imported column', id: columns:1128, is_pii: false, name: 'PART_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:25, definition: 'Imported column', id: columns:1129, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:25, definition: 'Imported column', id: columns:1130, is_pii: false, name: 'VENDOR_NO' }, { data_type: 'float', dataset_id: datasets:25, definition: 'Imported column', id: columns:1131, is_pii: false, name: 'LIST_PRICE' }, { data_type: 'varchar(-1)', dataset_id: datasets:25, definition: 'Imported column', id: columns:1132, is_pii: false, name: 'CURRENCY_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:25, definition: 'Imported column', id: columns:1133, is_pii: false, name: 'PRIMARY_VENDOR_DB' }, { data_type: 'varchar(-1)', dataset_id: datasets:26, definition: 'Imported column', id: columns:1134, is_pii: false, name: 'AUDIT_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:26, definition: 'Imported column', id: columns:1135, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'datetime', dataset_id: datasets:26, definition: 'Imported column', id: columns:1136, is_pii: false, name: 'AUDIT_DATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:26, definition: 'Imported column', id: columns:1137, is_pii: false, name: 'AUDITOR_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:26, definition: 'Imported column', id: columns:1138, is_pii: false, name: 'RESULT' }, { data_type: 'varchar(-1)', dataset_id: datasets:27, definition: 'Imported column', id: columns:1139, is_pii: false, name: 'REQUISITION_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:27, definition: 'Imported column', id: columns:1140, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:27, definition: 'Imported column', id: columns:1141, is_pii: false, name: 'REQUISITIONER_CODE' }, { data_type: 'datetime', dataset_id: datasets:27, definition: 'Imported column', id: columns:1142, is_pii: false, name: 'DATE_ENTERED' }, { data_type: 'varchar(-1)', dataset_id: datasets:27, definition: 'Imported column', id: columns:1143, is_pii: false, name: 'STATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:28, definition: 'Imported column', id: columns:1144, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:28, definition: 'Imported column', id: columns:1145, is_pii: false, name: 'WAREHOUSE_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:28, definition: 'Imported column', id: columns:1146, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'varchar(-1)', dataset_id: datasets:29, definition: 'Imported column', id: columns:1147, is_pii: false, name: 'WO_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:29, definition: 'Imported column', id: columns:1148, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:29, definition: 'Imported column', id: columns:1149, is_pii: false, name: 'MCH_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:29, definition: 'Imported column', id: columns:1150, is_pii: false, name: 'ERR_DESCR' }, { data_type: 'varchar(-1)', dataset_id: datasets:29, definition: 'Imported column', id: columns:1151, is_pii: false, name: 'PLAN_S_DATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:29, definition: 'Imported column', id: columns:1152, is_pii: false, name: 'STATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:30, definition: 'Imported column', id: columns:1153, is_pii: false, name: 'ORDER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:30, definition: 'Imported column', id: columns:1154, is_pii: false, name: 'LINE_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:30, definition: 'Imported column', id: columns:1155, is_pii: false, name: 'REL_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:30, definition: 'Imported column', id: columns:1156, is_pii: false, name: 'PART_NO' }, { data_type: 'float', dataset_id: datasets:30, definition: 'Imported column', id: columns:1157, is_pii: false, name: 'BUY_QTY_DUE' }, { data_type: 'float', dataset_id: datasets:30, definition: 'Imported column', id: columns:1158, is_pii: false, name: 'SALE_UNIT_PRICE' }, { data_type: 'datetime', dataset_id: datasets:30, definition: 'Imported column', id: columns:1159, is_pii: false, name: 'PLANNED_DELIVERY_DATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:31, definition: 'Imported column', id: columns:1160, is_pii: false, name: 'ORDER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:31, definition: 'Imported column', id: columns:1161, is_pii: false, name: 'CUSTOMER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:31, definition: 'Imported column', id: columns:1162, is_pii: false, name: 'CONTRACT' }, { data_type: 'datetime', dataset_id: datasets:31, definition: 'Imported column', id: columns:1163, is_pii: false, name: 'DATE_ENTERED' }, { data_type: 'varchar(-1)', dataset_id: datasets:31, definition: 'Imported column', id: columns:1164, is_pii: false, name: 'STATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:31, definition: 'Imported column', id: columns:1165, is_pii: false, name: 'CURRENCY_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:32, definition: 'Imported column', id: columns:1166, is_pii: false, name: 'COMPANY' }, { data_type: 'varchar(-1)', dataset_id: datasets:32, definition: 'Imported column', id: columns:1167, is_pii: false, name: 'VOUCHER_TYPE' }, { data_type: 'bigint', dataset_id: datasets:32, definition: 'Imported column', id: columns:1168, is_pii: false, name: 'VOUCHER_NO' }, { data_type: 'bigint', dataset_id: datasets:32, definition: 'Imported column', id: columns:1169, is_pii: false, name: 'ROW_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:32, definition: 'Imported column', id: columns:1170, is_pii: false, name: 'ACCOUNT' }, { data_type: 'float', dataset_id: datasets:32, definition: 'Imported column', id: columns:1171, is_pii: false, name: 'AMOUNT' }, { data_type: 'varchar(-1)', dataset_id: datasets:32, definition: 'Imported column', id: columns:1172, is_pii: false, name: 'TEXT' }, { data_type: 'datetime', dataset_id: datasets:32, definition: 'Imported column', id: columns:1173, is_pii: false, name: 'VOUCHER_DATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:33, definition: 'Imported column', id: columns:1174, is_pii: false, name: 'ORDER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:33, definition: 'Imported column', id: columns:1175, is_pii: false, name: 'SUPPLIER_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:33, definition: 'Imported column', id: columns:1176, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:33, definition: 'Imported column', id: columns:1177, is_pii: false, name: 'BUYER_CODE' }, { data_type: 'datetime', dataset_id: datasets:33, definition: 'Imported column', id: columns:1178, is_pii: false, name: 'DATE_ENTERED' }, { data_type: 'varchar(-1)', dataset_id: datasets:33, definition: 'Imported column', id: columns:1179, is_pii: false, name: 'STATE' }, { data_type: 'float', dataset_id: datasets:33, definition: 'Imported column', id: columns:1180, is_pii: false, name: 'TOTAL_AMT' }, { data_type: 'varchar(-1)', dataset_id: datasets:34, definition: 'Imported column', id: columns:1181, is_pii: false, name: 'ORDER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:34, definition: 'Imported column', id: columns:1182, is_pii: false, name: 'RELEASE_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:34, definition: 'Imported column', id: columns:1183, is_pii: false, name: 'SEQUENCE_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:34, definition: 'Imported column', id: columns:1184, is_pii: false, name: 'PART_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:34, definition: 'Imported column', id: columns:1185, is_pii: false, name: 'CONTRACT' }, { data_type: 'datetime', dataset_id: datasets:34, definition: 'Imported column', id: columns:1186, is_pii: false, name: 'ORG_START_DATE' }, { data_type: 'float', dataset_id: datasets:34, definition: 'Imported column', id: columns:1187, is_pii: false, name: 'ORG_QTY_DUE' }, { data_type: 'varchar(-1)', dataset_id: datasets:34, definition: 'Imported column', id: columns:1188, is_pii: false, name: 'STATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:35, definition: 'Imported column', id: columns:1189, is_pii: false, name: 'COMPANY' }, { data_type: 'varchar(-1)', dataset_id: datasets:35, definition: 'Imported column', id: columns:1190, is_pii: false, name: 'CODE_PART' }, { data_type: 'varchar(-1)', dataset_id: datasets:35, definition: 'Imported column', id: columns:1191, is_pii: false, name: 'CODE_PART_VALUE' }, { data_type: 'varchar(-1)', dataset_id: datasets:35, definition: 'Imported column', id: columns:1192, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'bigint', dataset_id: datasets:36, definition: 'Imported column', id: columns:1193, is_pii: false, name: 'ACTIVITY_SEQ' }, { data_type: 'varchar(-1)', dataset_id: datasets:36, definition: 'Imported column', id: columns:1194, is_pii: false, name: 'PROJECT_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:36, definition: 'Imported column', id: columns:1195, is_pii: false, name: 'SUB_PROJECT_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:36, definition: 'Imported column', id: columns:1196, is_pii: false, name: 'ACTIVITY_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:36, definition: 'Imported column', id: columns:1197, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'varchar(-1)', dataset_id: datasets:37, definition: 'Imported column', id: columns:1198, is_pii: false, name: 'COMPANY' }, { data_type: 'varchar(-1)', dataset_id: datasets:37, definition: 'Imported column', id: columns:1199, is_pii: false, name: 'EMP_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:37, definition: 'Imported column', id: columns:1200, is_pii: false, name: 'PERSON_ID' }, { data_type: 'datetime', dataset_id: datasets:37, definition: 'Imported column', id: columns:1201, is_pii: false, name: 'EXPIRE_DATE' }, { data_type: 'varchar(-1)', dataset_id: datasets:38, definition: 'Imported column', id: columns:1202, is_pii: false, name: 'CUSTOMER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:38, definition: 'Imported column', id: columns:1203, is_pii: false, name: 'NAME' }, { data_type: 'varchar(-1)', dataset_id: datasets:38, definition: 'Imported column', id: columns:1204, is_pii: false, name: 'CATEGORY' }, { data_type: 'varchar(-1)', dataset_id: datasets:38, definition: 'Imported column', id: columns:1205, is_pii: false, name: 'CURRENCY_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:39, definition: 'Imported column', id: columns:1206, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:39, definition: 'Imported column', id: columns:1207, is_pii: false, name: 'MCH_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:39, definition: 'Imported column', id: columns:1208, is_pii: false, name: 'MCH_NAME' }, { data_type: 'varchar(-1)', dataset_id: datasets:39, definition: 'Imported column', id: columns:1209, is_pii: false, name: 'GROUP_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:39, definition: 'Imported column', id: columns:1210, is_pii: false, name: 'SERIAL_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:40, definition: 'Imported column', id: columns:1211, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:40, definition: 'Imported column', id: columns:1212, is_pii: false, name: 'PART_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:40, definition: 'Imported column', id: columns:1213, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'varchar(-1)', dataset_id: datasets:40, definition: 'Imported column', id: columns:1214, is_pii: false, name: 'UNIT_MEAS' }, { data_type: 'varchar(-1)', dataset_id: datasets:40, definition: 'Imported column', id: columns:1215, is_pii: false, name: 'TYPE_CODE' }, { data_type: 'varchar(-1)', dataset_id: datasets:41, definition: 'Imported column', id: columns:1216, is_pii: false, name: 'PROJECT_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:41, definition: 'Imported column', id: columns:1217, is_pii: false, name: 'NAME' }, { data_type: 'varchar(-1)', dataset_id: datasets:41, definition: 'Imported column', id: columns:1218, is_pii: false, name: 'MANAGER' }, { data_type: 'varchar(-1)', dataset_id: datasets:41, definition: 'Imported column', id: columns:1219, is_pii: false, name: 'STATE' }, { data_type: 'datetime', dataset_id: datasets:41, definition: 'Imported column', id: columns:1220, is_pii: false, name: 'PLAN_START' }, { data_type: 'datetime', dataset_id: datasets:41, definition: 'Imported column', id: columns:1221, is_pii: false, name: 'PLAN_FINISH' }, { data_type: 'varchar(-1)', dataset_id: datasets:42, definition: 'Imported column', id: columns:1222, is_pii: false, name: 'SUPPLIER_ID' }, { data_type: 'varchar(-1)', dataset_id: datasets:42, definition: 'Imported column', id: columns:1223, is_pii: false, name: 'NAME' }, { data_type: 'varchar(-1)', dataset_id: datasets:42, definition: 'Imported column', id: columns:1224, is_pii: false, name: 'COUNTRY' }, { data_type: 'varchar(-1)', dataset_id: datasets:43, definition: 'Imported column', id: columns:1225, is_pii: false, name: 'CONTRACT' }, { data_type: 'varchar(-1)', dataset_id: datasets:43, definition: 'Imported column', id: columns:1226, is_pii: false, name: 'WORK_CENTER_NO' }, { data_type: 'varchar(-1)', dataset_id: datasets:43, definition: 'Imported column', id: columns:1227, is_pii: false, name: 'DESCRIPTION' }, { data_type: 'varchar(-1)', dataset_id: datasets:43, definition: 'Imported column', id: columns:1228, is_pii: false, name: 'WORK_CENTER_CODE' }, { data_type: 'int', dataset_id: datasets:44, definition: 'Imported column', id: columns:1229, is_pii: false, name: 'TransactionID' }, { data_type: 'nvarchar(50)', dataset_id: datasets:44, definition: 'Imported column', id: columns:1230, is_pii: false, name: 'Sector' }, { data_type: 'nvarchar(50)', dataset_id: datasets:44, definition: 'Imported column', id: columns:1231, is_pii: false, name: 'CostCenter' }, { data_type: 'nvarchar(50)', dataset_id: datasets:44, definition: 'Imported column', id: columns:1232, is_pii: false, name: 'Region' }, { data_type: 'decimal', dataset_id: datasets:44, definition: 'Imported column', id: columns:1233, is_pii: false, name: 'Amount' }, { data_type: 'datetime', dataset_id: datasets:44, definition: 'Imported column', id: columns:1234, is_pii: false, name: 'Date' }, { data_type: 'int', dataset_id: datasets:45, definition: 'Imported column', id: columns:1235, is_pii: false, name: 'id' }, { data_type: 'varchar(20)', dataset_id: datasets:45, definition: 'Imported column', id: columns:1236, is_pii: false, name: 'CUSTOMER_NO' }, { data_type: 'varchar(100)', dataset_id: datasets:45, definition: 'Imported column', id: columns:1237, is_pii: false, name: 'NAME' }, { data_type: 'varchar(20)', dataset_id: datasets:45, definition: 'Imported column', id: columns:1238, is_pii: false, name: 'CATEGORY' }, { data_type: 'datetime', dataset_id: datasets:45, definition: 'Imported column', id: columns:1239, is_pii: false, name: 'effective_date' }, { data_type: 'datetime', dataset_id: datasets:45, definition: 'Imported column', id: columns:1240, is_pii: false, name: 'expiry_date' }, { data_type: 'bit', dataset_id: datasets:45, definition: 'Imported column', id: columns:1241, is_pii: false, name: 'is_current' } ];

-- ------------------------------
-- TABLE: datasets
-- ------------------------------

DEFINE TABLE datasets TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth != NONE, FOR create, update WHERE $auth.role INSIDE ['Admin', 'IAO'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD created_at ON datasets TYPE datetime PERMISSIONS FULL;
DEFINE FIELD description ON datasets TYPE none | string PERMISSIONS FULL;
DEFINE FIELD name ON datasets TYPE string PERMISSIONS FULL;
DEFINE FIELD owner_group_id ON datasets TYPE none | record<virtual_groups> PERMISSIONS FULL;
DEFINE FIELD type ON datasets TYPE string PERMISSIONS FULL;



-- ------------------------------
-- TABLE DATA: datasets
-- ------------------------------

INSERT [ { created_at: d'2026-01-27T17:16:54.743Z', description: 'Consolidated sales transactions across all sectors.', id: datasets:1, name: 'FactSales', owner_group_id: virtual_groups:1, type: 'Fact' }, { created_at: d'2026-01-27T17:16:54.743Z', description: 'Monthly payroll data.', id: datasets:3, name: 'FactPayroll', owner_group_id: virtual_groups:2, type: 'Fact' }, { created_at: d'2026-02-17T12:14:59.697Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:4, name: 'stg_ACCOUNTING_JOURNAL_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.837Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:5, name: 'stg_ACCOUNT_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.843Z', description: 'Sales parts', id: datasets:6, name: 'dim_SalesPart', type: 'Dimension' }, { created_at: d'2026-02-17T12:14:59.850Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:7, name: 'stg_INVENTORY_TRANSACTION_HIST_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.860Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:8, name: 'stg_PURCHASE_ORDER_LINE_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.870Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:9, name: 'stg_SALES_QUOTATION_LINE_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.873Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:10, name: 'stg_SALES_QUOTATION_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.880Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:11, name: 'stg_COMPANY_POSITION_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.887Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:12, name: 'stg_DOC_TITLE_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.890Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:13, name: 'stg_DOC_ISSUE_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.897Z', description: 'Land Customer dimension', id: datasets:14, name: 'DimCustomer', owner_group_id: virtual_groups:3, type: 'Dimension' }, { created_at: d'2026-02-17T12:14:59.907Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:15, name: 'stg_EMP_SALARY_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.917Z', description: 'All the materials', id: datasets:16, name: 'dim_Part', type: 'Dimension' }, { created_at: d'2026-02-17T12:14:59.927Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:17, name: 'stg_INVENTORY_LOCATION_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.937Z', description: 'Orders dim', id: datasets:18, name: 'dim_Order', owner_group_id: virtual_groups:1, type: 'Dimension' }, { created_at: d'2026-02-17T12:14:59.943Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:19, name: 'stg_NCR_REPORT_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.950Z', description: 'All our customers orders', id: datasets:20, name: 'fact_CustomerOrders', type: 'Fact' }, { created_at: d'2026-02-17T12:14:59.957Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:21, name: 'stg_PM_ACTION_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.963Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:22, name: 'stg_INVOICE_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.973Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:23, name: 'stg_PURCHASE_BUYER_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.977Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:24, name: 'stg_PURCHASE_REQ_LINE_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.983Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:25, name: 'stg_PURCHASE_PART_SUPPLIER_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.990Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:26, name: 'stg_QUALITY_AUDIT_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:14:59.997Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:27, name: 'stg_PURCHASE_REQUISITION_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.003Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:28, name: 'stg_WAREHOUSE_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.007Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:29, name: 'stg_ACTIVE_WORK_ORDER_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.010Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:30, name: 'stg_CUSTOMER_ORDER_LINE_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.017Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:31, name: 'stg_CUSTOMER_ORDER_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.020Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:32, name: 'stg_GEN_LED_VOUCHER_ROW_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.027Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:33, name: 'stg_PURCHASE_ORDER_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.030Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:34, name: 'stg_SHOP_ORD_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.033Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:35, name: 'stg_ACCOUNTING_CODE_PART_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.037Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:36, name: 'stg_ACTIVITY_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.040Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:37, name: 'stg_COMPANY_EMP_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.040Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:38, name: 'stg_CUST_ORD_CUSTOMER_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.043Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:39, name: 'stg_EQUIPMENT_OBJECT_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.047Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:40, name: 'stg_INVENTORY_PART_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.050Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:41, name: 'stg_PROJECT_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.050Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:42, name: 'stg_SUPPLIER_INFO_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:15:00.053Z', description: 'Imported from Data Warehouse. Please update description.', id: datasets:43, name: 'stg_WORK_CENTER_TAB', type: 'Staging' }, { created_at: d'2026-02-17T12:53:59.930Z', description: 'Stock in the warehouse', id: datasets:44, name: 'FactInventory', type: 'Fact' }, { created_at: d'2026-02-17T17:08:08.607Z', description: 'Customer dimension', id: datasets:45, name: 'dim_Customer', owner_group_id: virtual_groups:1, type: 'Dimension' } ];

-- ------------------------------
-- TABLE: governed_by
-- ------------------------------

DEFINE TABLE governed_by TYPE RELATION IN datasets OUT asset_policy_groups SCHEMAFULL PERMISSIONS FOR select WHERE $auth != NONE, FOR create, update WHERE $auth.role INSIDE ['Admin', 'IAO'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD in ON governed_by TYPE record<datasets> PERMISSIONS FULL;
DEFINE FIELD out ON governed_by TYPE record<asset_policy_groups> PERMISSIONS FULL;

DEFINE INDEX governed_by_unique ON governed_by FIELDS in, out UNIQUE;


-- ------------------------------
-- TABLE DATA: governed_by
-- ------------------------------


-- ------------------------------
-- TABLE: has_access
-- ------------------------------

DEFINE TABLE has_access TYPE RELATION IN users OUT datasets SCHEMAFULL PERMISSIONS FOR select WHERE $auth.id = in OR $auth.role INSIDE ['Admin', 'IAO', 'IAA'], FOR create, update WHERE $auth.role INSIDE ['Admin', 'IAO', 'IAA'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD granted_at ON has_access TYPE datetime DEFAULT time::now() PERMISSIONS FULL;
DEFINE FIELD granted_by ON has_access TYPE none | record<users> PERMISSIONS FULL;
DEFINE FIELD in ON has_access TYPE record<users> PERMISSIONS FULL;
DEFINE FIELD out ON has_access TYPE record<datasets> PERMISSIONS FULL;
DEFINE FIELD policy_group_id ON has_access TYPE none | record<asset_policy_groups> PERMISSIONS FULL;
DEFINE FIELD rls_filters ON has_access TYPE none | string PERMISSIONS FULL;

DEFINE INDEX has_access_unique ON has_access FIELDS in, out UNIQUE;


-- ------------------------------
-- TABLE DATA: has_access
-- ------------------------------

INSERT RELATION [ { granted_at: d'2026-04-15T07:58:12.170536900Z', granted_by: users:2, id: has_access:a0449mjktor731c8mep5, in: users:3, out: datasets:1, rls_filters: '[]' }, { granted_at: d'2026-04-15T07:58:24.629262600Z', granted_by: users:3, id: has_access:ele1uf12gsf8rtgy831r, in: users:4, out: datasets:14 }, { granted_at: d'2026-04-15T07:58:14.277951300Z', granted_by: users:2, id: has_access:kkdcp9kik9wvz5nhfmw3, in: users:2, out: datasets:1, rls_filters: '{"Sector":"Marine"}' }, { granted_at: d'2026-04-15T07:58:20.493986100Z', granted_by: users:5, id: has_access:rjkwqmslnboiathru0vr, in: users:5, out: datasets:18 }, { granted_at: d'2026-04-15T07:58:18.428983700Z', granted_by: users:3, id: has_access:wv7ng6mt25mk7e1cnump, in: users:1, out: datasets:14 }, { granted_at: d'2026-04-15T07:58:16.348601300Z', granted_by: users:2, id: has_access:zrhlzaztbx5chmhysg6p, in: users:2, out: datasets:3 } ];

-- ------------------------------
-- TABLE: initial_admins
-- ------------------------------

DEFINE TABLE initial_admins TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select, create, update, delete WHERE $auth.role = 'Admin';

DEFINE FIELD added_at ON initial_admins TYPE none | datetime PERMISSIONS FULL;
DEFINE FIELD username ON initial_admins TYPE string PERMISSIONS FULL;



-- ------------------------------
-- TABLE DATA: initial_admins
-- ------------------------------


-- ------------------------------
-- TABLE: member_of
-- ------------------------------

DEFINE TABLE member_of TYPE RELATION IN users OUT virtual_groups SCHEMAFULL PERMISSIONS FOR select WHERE $auth != NONE, FOR create, delete WHERE $auth.role = 'Admin' OR (SELECT 1 FROM virtual_groups WHERE id = $parent.out AND owner_id = $auth.id LIMIT 1).len() > 0, FOR update NONE;

DEFINE FIELD added_at ON member_of TYPE datetime DEFAULT time::now() PERMISSIONS FULL;
DEFINE FIELD in ON member_of TYPE record<users> PERMISSIONS FULL;
DEFINE FIELD out ON member_of TYPE record<virtual_groups> PERMISSIONS FULL;

DEFINE INDEX member_of_unique ON member_of FIELDS in, out UNIQUE;


-- ------------------------------
-- TABLE DATA: member_of
-- ------------------------------

INSERT RELATION [ { added_at: d'2026-04-15T07:58:05.978849200Z', id: member_of:ezy3kckj3y5cgjlz6wg2, in: users:3, out: virtual_groups:3 }, { added_at: d'2026-04-15T07:58:03.886486200Z', id: member_of:poupuh41vqr51hevamvh, in: users:4, out: virtual_groups:1 }, { added_at: d'2026-04-15T07:58:08.035846400Z', id: member_of:stzrgtpreuiknqqezyrt, in: users:4, out: virtual_groups:3 } ];

-- ------------------------------
-- TABLE: report_datasets
-- ------------------------------

DEFINE TABLE report_datasets TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth != NONE, FOR create, update WHERE $auth.role INSIDE ['Admin', 'IAO'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD dataset_id ON report_datasets TYPE record<datasets> PERMISSIONS FULL;
DEFINE FIELD report_id ON report_datasets TYPE record<reports> PERMISSIONS FULL;



-- ------------------------------
-- TABLE DATA: report_datasets
-- ------------------------------


-- ------------------------------
-- TABLE: reports
-- ------------------------------

DEFINE TABLE reports TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth != NONE, FOR create, update WHERE $auth.role INSIDE ['Admin', 'IAO'], FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD description ON reports TYPE none | string PERMISSIONS FULL;
DEFINE FIELD name ON reports TYPE string PERMISSIONS FULL;
DEFINE FIELD url ON reports TYPE none | string PERMISSIONS FULL;



-- ------------------------------
-- TABLE DATA: reports
-- ------------------------------


-- ------------------------------
-- TABLE: users
-- ------------------------------

DEFINE TABLE users TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth != NONE, FOR create, update, delete WHERE $auth.role = 'Admin';

DEFINE FIELD avatar ON users TYPE none | string PERMISSIONS FULL;
DEFINE FIELD created_at ON users TYPE datetime PERMISSIONS FULL;
DEFINE FIELD email ON users TYPE string PERMISSIONS FULL;
DEFINE FIELD name ON users TYPE string PERMISSIONS FULL;
DEFINE FIELD role ON users TYPE string PERMISSIONS FULL;

DEFINE INDEX users_email_unique ON users FIELDS email UNIQUE;


-- ------------------------------
-- TABLE DATA: users
-- ------------------------------

INSERT [ { created_at: d'2026-01-27T17:16:54.733Z', email: 'john@example.com', id: users:1, name: 'John User', role: 'User' }, { created_at: d'2026-01-27T17:16:54.733Z', email: 'alice@example.com', id: users:2, name: 'Alice Manager', role: 'IAO' }, { created_at: d'2026-01-27T17:16:54.733Z', email: 'admin@example.com', id: users:3, name: 'Ben Admin', role: 'Admin' }, { created_at: d'2026-02-17T12:30:24.247Z', email: 'milly@milly.com', id: users:4, name: 'Milly', role: 'Admin' }, { created_at: d'2026-02-17T12:30:34.307Z', email: 'stu@stu.com', id: users:5, name: 'Stu', role: 'IAO' }, { created_at: d'2026-02-26T13:50:21.263Z', email: 'test@testing.com', id: users:1004, name: 'Test', role: 'User' } ];

-- ------------------------------
-- TABLE: virtual_group_members
-- ------------------------------

DEFINE TABLE virtual_group_members TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select WHERE $auth != NONE, FOR create, delete WHERE $auth.role = 'Admin' OR (SELECT 1 FROM virtual_groups WHERE id = $parent.group_id AND owner_id = $auth.id LIMIT 1).len() > 0, FOR update NONE;

DEFINE FIELD added_at ON virtual_group_members TYPE datetime PERMISSIONS FULL;
DEFINE FIELD group_id ON virtual_group_members TYPE record<virtual_groups> PERMISSIONS FULL;
DEFINE FIELD user_id ON virtual_group_members TYPE record<users> PERMISSIONS FULL;

DEFINE INDEX vgm_group ON virtual_group_members FIELDS group_id;
DEFINE INDEX vgm_user ON virtual_group_members FIELDS user_id;


-- ------------------------------
-- TABLE DATA: virtual_group_members
-- ------------------------------

INSERT [ { added_at: d'2026-02-17T20:02:21.980Z', group_id: virtual_groups:1, id: virtual_group_members:vgm_1_4, user_id: users:4 }, { added_at: d'2026-02-18T19:08:21.630Z', group_id: virtual_groups:3, id: virtual_group_members:vgm_3_3, user_id: users:3 }, { added_at: d'2026-02-18T08:16:20.417Z', group_id: virtual_groups:3, id: virtual_group_members:vgm_3_4, user_id: users:4 } ];

-- ------------------------------
-- TABLE: virtual_groups
-- ------------------------------

DEFINE TABLE virtual_groups TYPE NORMAL SCHEMAFULL PERMISSIONS FOR select, create WHERE $auth != NONE, FOR update WHERE $auth.id = owner_id OR $auth.role = 'Admin', FOR delete WHERE $auth.role = 'Admin';

DEFINE FIELD created_at ON virtual_groups TYPE datetime PERMISSIONS FULL;
DEFINE FIELD description ON virtual_groups TYPE none | string PERMISSIONS FULL;
DEFINE FIELD name ON virtual_groups TYPE string PERMISSIONS FULL;
DEFINE FIELD owner_id ON virtual_groups TYPE record<users> PERMISSIONS FULL;



-- ------------------------------
-- TABLE DATA: virtual_groups
-- ------------------------------

INSERT [ { created_at: d'2026-01-27T17:16:54.740Z', description: 'Owners of global sales data assets', id: virtual_groups:1, name: 'Group Finance', owner_id: users:5 }, { created_at: d'2026-01-27T17:16:54.740Z', description: 'Owners of personnel data', id: virtual_groups:2, name: 'Marine Finance', owner_id: users:4 }, { created_at: d'2026-01-27T17:29:33.810Z', description: 'admin group', id: virtual_groups:3, name: 'Land Finance', owner_id: users:5 }, { created_at: d'2026-02-17T12:23:05.140Z', description: 'P&SC Marine', id: virtual_groups:1002, name: 'Aviation Finance', owner_id: users:3 }, { created_at: d'2026-02-26T13:26:37.613Z', description: 'Testing access', id: virtual_groups:2002, name: 'Test group', owner_id: users:2 } ];

