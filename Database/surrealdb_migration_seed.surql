-- SurrealDB Migration Graph Seed Script
-- Defines schema and initial nodes and edges for transitioning from SQL Server to SurrealDB Graph

-- Let's set our Namespace and Database

-- ==========================================
-- 1. DEFINE NODES (Tables/Vertices)
-- ==========================================
DEFINE TABLE user SCHEMAFULL;
DEFINE FIELD name ON user TYPE string;
DEFINE FIELD email ON user TYPE string;
DEFINE FIELD role ON user TYPE string ASSERT $value IN ["User", "Approver", "Admin"];
DEFINE FIELD avatar ON user TYPE string;
DEFINE FIELD created_at ON user TYPE datetime DEFAULT time::now();
DEFINE INDEX userEmailIndex ON user COLUMNS email UNIQUE;

DEFINE TABLE group SCHEMAFULL;
DEFINE FIELD name ON group TYPE string;
DEFINE FIELD description ON group TYPE option<string>;
DEFINE FIELD created_at ON group TYPE datetime DEFAULT time::now();

DEFINE TABLE dataset SCHEMAFULL;
DEFINE FIELD name ON dataset TYPE string;
DEFINE FIELD type ON dataset TYPE string ASSERT $value IN ["Table", "View", "Query"];
DEFINE FIELD description ON dataset TYPE option<string>;
DEFINE FIELD created_at ON dataset TYPE datetime DEFAULT time::now();

DEFINE TABLE policy_group SCHEMAFULL;
DEFINE FIELD name ON policy_group TYPE string;
DEFINE FIELD description ON policy_group TYPE option<string>;
DEFINE FIELD created_at ON policy_group TYPE datetime DEFAULT time::now();

-- ==========================================
-- 2. DEFINE EDGES (Graph Relationships)
-- ==========================================

-- Edge: User is a member of a Group
DEFINE TABLE BELONGS_TO TYPE RELATION IN user OUT group;
DEFINE FIELD added_at ON BELONGS_TO TYPE datetime DEFAULT time::now();

-- Edge: Group owns a Dataset (Authorization/Security Node)
DEFINE TABLE OWNS_DATASET TYPE RELATION IN group OUT dataset;

-- Edge: Dataset is governed by a Security Policy
DEFINE TABLE HAS_POLICY TYPE RELATION IN dataset OUT policy_group;

-- Edge: Policy has a condition (Row-Level Security)
DEFINE TABLE HAS_CONDITION TYPE RELATION IN policy_group OUT object;
DEFINE FIELD column_name ON HAS_CONDITION TYPE string;
DEFINE FIELD operator ON HAS_CONDITION TYPE string;
DEFINE FIELD value ON HAS_CONDITION TYPE string;

-- Edge: Policy has a hidden column (Column-Level Security mapping)
DEFINE TABLE HAS_HIDDEN_COLUMN TYPE RELATION IN policy_group OUT object;
DEFINE FIELD column_name ON HAS_HIDDEN_COLUMN TYPE string;

-- Edge: User requests access to a Dataset
DEFINE TABLE REQUESTED_ACCESS TYPE RELATION IN user OUT dataset;
DEFINE FIELD status ON REQUESTED_ACCESS TYPE string ASSERT $value IN ["Pending", "Approved", "Rejected"];
DEFINE FIELD requested_rls_filters ON REQUESTED_ACCESS TYPE option<string>;
DEFINE FIELD justification ON REQUESTED_ACCESS TYPE string;
DEFINE FIELD created_at ON REQUESTED_ACCESS TYPE datetime DEFAULT time::now();
DEFINE FIELD reviewed_by ON REQUESTED_ACCESS TYPE option<record<user>>;
DEFINE FIELD reviewed_at ON REQUESTED_ACCESS TYPE option<datetime>;
DEFINE FIELD policy_group_id ON REQUESTED_ACCESS TYPE option<record<policy_group>>;

-- ==========================================
-- 3. INSERT SEED DATA
-- ==========================================

-- Mock Users Let's create an Admin and some standard users
CREATE user:admin SET name = 'System Administrator', email = 'admin@dmp.local', role = 'Admin', avatar = '/avatars/admin.png';
CREATE user:alice SET name = 'Alice Data', email = 'alice@dmp.local', role = 'User', avatar = '/avatars/alice.png';
CREATE user:bob SET name = 'Bob Approver', email = 'bob@dmp.local', role = 'Approver', avatar = '/avatars/bob.png';
CREATE user:charlie SET name = 'Charlie ReadOnly', email = 'charlie@dmp.local', role = 'User', avatar = '/avatars/charlie.png';

-- Mock Groups
CREATE group:hr_team SET name = 'HR Data Team', description = 'Team responsible for payroll and employees';
CREATE group:finance_team SET name = 'Finance Analysts', description = 'Financial reporting and budgeting';

-- Let's define the Graph Relation of who is in what team
RELATE user:alice->BELONGS_TO->group:hr_team;
RELATE user:bob->BELONGS_TO->group:finance_team;
RELATE user:admin->BELONGS_TO->group:hr_team;
RELATE user:admin->BELONGS_TO->group:finance_team;

-- Mock Datasets
CREATE dataset:employee_records SET name = 'Employee Roster', type = 'Table', description = 'Company wide list of active employees';
CREATE dataset:q1_revenue SET name = 'Q1 Revenue 2026', type = 'View', description = 'Global consolidated revenue stream';

-- Associate Groups giving them Ownership over Datasets
RELATE group:hr_team->OWNS_DATASET->dataset:employee_records;
RELATE group:finance_team->OWNS_DATASET->dataset:q1_revenue;

-- Create Security Policy Graphs targeting Data Masking & RLS
CREATE policy_group:hr_managers SET name = 'HR Managers Only', description = 'Limits row access to Management branch';
CREATE policy_group:finance_restricted SET name = 'Finance Restricted', description = 'Hides PII Data';

-- Edges tying the datasets to their operational security policies 
RELATE dataset:employee_records->HAS_POLICY->policy_group:hr_managers;
RELATE dataset:q1_revenue->HAS_POLICY->policy_group:finance_restricted;

-- Complex graph rules dictating WHAT the policy limits through nested graph relations
RELATE policy_group:hr_managers->HAS_CONDITION->{ } SET column_name = 'Department', operator = '=', value = 'Management';
RELATE policy_group:finance_restricted->HAS_HIDDEN_COLUMN->{ } SET column_name = 'CreditCardNumber';

-- User Access Requests acting as Edges querying permissions
RELATE user:charlie->REQUESTED_ACCESS->dataset:q1_revenue SET status = 'Pending', justification = 'Need for quarterly compliance report';
RELATE user:alice->REQUESTED_ACCESS->dataset:employee_records SET status = 'Approved', justification = 'HR Audit review', reviewed_by = user:admin, policy_group_id = policy_group:hr_managers;

-- ==========================================
-- Example Queries mapping the relationships:
-- 'Get all datasets user Alice has access to via her groups'
-- SELECT ->BELONGS_TO->group->OWNS_DATASET->dataset FROM user:alice
-- 
-- 'Get the security conditions protecting employee records dataset'
-- SELECT ->HAS_POLICY->policy_group->HAS_CONDITION FROM dataset:employee_records
-- ==========================================
