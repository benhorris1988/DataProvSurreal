<?php
require_once 'includes/db.php';

$sql = "
-- Asset Policy Groups (Slices of data with specific owners)
IF OBJECT_ID('asset_policy_groups', 'U') IS NULL
CREATE TABLE asset_policy_groups (
    id INT IDENTITY(1,1) PRIMARY KEY,
    dataset_id INT NOT NULL,
    owner_id INT NOT NULL,
    name NVARCHAR(100) NOT NULL,
    description NVARCHAR(MAX),
    created_at DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- RLS Conditions (Rows to include)
IF OBJECT_ID('asset_policy_conditions', 'U') IS NULL
CREATE TABLE asset_policy_conditions (
    id INT IDENTITY(1,1) PRIMARY KEY,
    policy_group_id INT NOT NULL,
    column_name NVARCHAR(100) NOT NULL,
    operator NVARCHAR(20) DEFAULT '=', -- =, !=, IN, LIKE
    value NVARCHAR(MAX),
    FOREIGN KEY (policy_group_id) REFERENCES asset_policy_groups(id) ON DELETE CASCADE
);

-- CLS Rules (Columns to hide/show)
IF OBJECT_ID('asset_policy_columns', 'U') IS NULL
CREATE TABLE asset_policy_columns (
    id INT IDENTITY(1,1) PRIMARY KEY,
    policy_group_id INT NOT NULL,
    column_name NVARCHAR(100) NOT NULL,
    is_hidden BIT DEFAULT 0,
    FOREIGN KEY (policy_group_id) REFERENCES asset_policy_groups(id) ON DELETE CASCADE
);

-- Update Access Requests to link to policy groups optionally
-- We'll add a column 'policy_group_id' to access_requests
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('access_requests') AND name = 'policy_group_id')
BEGIN
    ALTER TABLE access_requests ADD policy_group_id INT NULL;
    ALTER TABLE access_requests ADD CONSTRAINT FK_Request_PolicyGroup FOREIGN KEY (policy_group_id) REFERENCES asset_policy_groups(id);
END
";

try {
    $pdo->exec($sql);
    echo "Schema updated successfully.\n";
} catch (PDOException $e) {
    die("Schema update failed: " . $e->getMessage());
}
?>