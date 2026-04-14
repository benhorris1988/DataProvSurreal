-- Database Schema for Data Marketplace (T-SQL)

-- Check if database exists, if not create (Note: User said DB 'datamarketplace' exists, but good to have)
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'datamarketplace')
BEGIN
    CREATE DATABASE datamarketplace;
END
GO

USE datamarketplace;
GO

-- Users Table
IF OBJECT_ID('users', 'U') IS NULL
CREATE TABLE users (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(100) NOT NULL,
    email NVARCHAR(100) NOT NULL UNIQUE,
    role NVARCHAR(20) DEFAULT 'User' CHECK (role IN ('Admin', 'User', 'IAO')),
    avatar NVARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT GETDATE()
);
GO

-- Virtual Groups (Owned by IAOs)
IF OBJECT_ID('virtual_groups', 'U') IS NULL
CREATE TABLE virtual_groups (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(100) NOT NULL,
    owner_id INT NOT NULL,
    description NVARCHAR(MAX),
    created_at DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (owner_id) REFERENCES users(id)
);
GO

-- Virtual Group Members
IF OBJECT_ID('virtual_group_members', 'U') IS NULL
CREATE TABLE virtual_group_members (
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at DATETIME DEFAULT GETDATE(),
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES virtual_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE NO ACTION -- Cycle prevention in MSSQL often needs NO ACTION + Triggers, or simple NO ACTION
);
GO

-- Datasets (Facts and Dimensions)
IF OBJECT_ID('datasets', 'U') IS NULL
CREATE TABLE datasets (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(150) NOT NULL,
    type NVARCHAR(20) NOT NULL CHECK (type IN ('Fact', 'Dimension', 'Staging')),
    description NVARCHAR(MAX),
    owner_group_id INT,
    created_at DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (owner_group_id) REFERENCES virtual_groups(id)
);
GO

-- Columns within Datasets
IF OBJECT_ID('columns', 'U') IS NULL
CREATE TABLE columns (
    id INT IDENTITY(1,1) PRIMARY KEY,
    dataset_id INT NOT NULL,
    name NVARCHAR(100) NOT NULL,
    data_type NVARCHAR(50),
    definition NVARCHAR(MAX),
    is_pii BIT DEFAULT 0,
    sample_data NVARCHAR(MAX), 
    FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE
);
GO

-- Access Requests
IF OBJECT_ID('access_requests', 'U') IS NULL
CREATE TABLE access_requests (
    id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NOT NULL,
    dataset_id INT NOT NULL,
    status NVARCHAR(20) DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved', 'Rejected')),
    requested_rls_filters NVARCHAR(MAX), 
    justification NVARCHAR(MAX),
    reviewed_by INT,
    reviewed_at DATETIME NULL,
    created_at DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (dataset_id) REFERENCES datasets(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);
GO

-- Reports linking
IF OBJECT_ID('reports', 'U') IS NULL
CREATE TABLE reports (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(200) NOT NULL,
    url NVARCHAR(500),
    description NVARCHAR(MAX)
);
GO

IF OBJECT_ID('report_datasets', 'U') IS NULL
CREATE TABLE report_datasets (
    report_id INT NOT NULL,
    dataset_id INT NOT NULL,
    PRIMARY KEY (report_id, dataset_id),
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE
);
GO

-- Seed Initial Users (Demo)
IF NOT EXISTS (SELECT * FROM users)
BEGIN
    INSERT INTO users (name, email, role) VALUES 
    ('John Doe', 'john@example.com', 'User'),
    ('Alice Manager', 'alice@example.com', 'IAO'),
    ('Bob Admin', 'admin@example.com', 'Admin');
END
GO

-- Seed Initial Groups
IF NOT EXISTS (SELECT * FROM virtual_groups)
BEGIN
    INSERT INTO virtual_groups (name, owner_id, description) VALUES
    ('Sales Data Owners', 2, 'Owners of global sales data assets'),
    ('HR Data Owners', 2, 'Owners of personnel data');
END
GO

-- Seed Initial Datasets
IF NOT EXISTS (SELECT * FROM datasets)
BEGIN
    INSERT INTO datasets (name, type, description, owner_group_id) VALUES
    ('FactSales', 'Fact', 'Consolidated sales transactions across all sectors.', 1),
    ('DimCustomer', 'Dimension', 'Customer attributes and demographics.', 1),
    ('FactPayroll', 'Fact', 'Monthly payroll data.', 2);
END
GO

-- Seed Columns for FactSales
IF NOT EXISTS (SELECT * FROM columns)
BEGIN
    INSERT INTO columns (dataset_id, name, data_type, definition, is_pii, sample_data) VALUES
    (1, 'TransactionID', 'INT', 'Unique identifier for the sale', 0, '1001, 1002, 1003'),
    (1, 'Amount', 'DECIMAL(10,2)', 'Total value of the transaction', 0, '$500.00, $120.50'),
    (1, 'Sector', 'VARCHAR(50)', 'Business sector (Marine, Land, Aviation, Nuclear)', 0, 'Marine, Land'),
    (1, 'Date', 'DATETIME', 'Transaction timestamp', 0, '2023-01-01 10:00:00');
END
GO
