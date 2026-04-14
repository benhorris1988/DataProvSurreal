-- Database Schema for Data Marketplace

CREATE DATABASE IF NOT EXISTS data_marketplace;
USE data_marketplace;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('Admin', 'User', 'IAO') DEFAULT 'User',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Virtual Groups (Owned by IAOs)
CREATE TABLE IF NOT EXISTS virtual_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    owner_id INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Virtual Group Members
CREATE TABLE IF NOT EXISTS virtual_group_members (
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES virtual_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Datasets (Facts and Dimensions)
CREATE TABLE IF NOT EXISTS datasets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('Fact', 'Dimension', 'Staging') NOT NULL,
    description TEXT,
    owner_group_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_group_id) REFERENCES virtual_groups(id)
);

-- Columns within Datasets
CREATE TABLE IF NOT EXISTS columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dataset_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    data_type VARCHAR(50),
    definition TEXT,
    is_pii BOOLEAN DEFAULT FALSE,
    sample_data TEXT, -- JSON or string examples
    FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE
);

-- Access Requests
CREATE TABLE IF NOT EXISTS access_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    dataset_id INT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    requested_rls_filters TEXT, -- JSON storing requested filters e.g. {"Sector": "Marine"}
    justification TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (dataset_id) REFERENCES datasets(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Reports linking
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    url VARCHAR(500),
    description TEXT
);

CREATE TABLE IF NOT EXISTS report_datasets (
    report_id INT NOT NULL,
    dataset_id INT NOT NULL,
    PRIMARY KEY (report_id, dataset_id),
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE
);

-- Seed Initial Users (Demo)
INSERT INTO users (name, email, role) VALUES 
('John Doe', 'john@example.com', 'User'), -- Regular Requestor
('Alice Manager', 'alice@example.com', 'IAO'), -- Information Asset Owner
('Bob Admin', 'admin@example.com', 'Admin');

-- Seed Initial Groups
INSERT INTO virtual_groups (name, owner_id, description) VALUES
('Sales Data Owners', 2, 'Owners of global sales data assets'),
('HR Data Owners', 2, 'Owners of personnel data');

-- Seed Initial Datasets
INSERT INTO datasets (name, type, description, owner_group_id) VALUES
('FactSales', 'Fact', 'Consolidated sales transactions across all sectors.', 1),
('DimCustomer', 'Dimension', 'Customer attributes and demographics.', 1),
('FactPayroll', 'Fact', 'Monthly payroll data.', 2);

-- Seed Columns for FactSales
INSERT INTO columns (dataset_id, name, data_type, definition, is_pii, sample_data) VALUES
(1, 'TransactionID', 'INT', 'Unique identifier for the sale', 0, '1001, 1002, 1003'),
(1, 'Amount', 'DECIMAL(10,2)', 'Total value of the transaction', 0, '$500.00, $120.50'),
(1, 'Sector', 'VARCHAR(50)', 'Business sector (Marine, Land, Aviation, Nuclear)', 0, 'Marine, Land'),
(1, 'Date', 'DATETIME', 'Transaction timestamp', 0, '2023-01-01 10:00:00');

