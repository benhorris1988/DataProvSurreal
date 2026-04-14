-- Connect to the Data Warehouse Database
USE datawarehouse_DEV;
GO

-- 1. Create necessary Schemas
IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'AppAdmin')
BEGIN
    EXEC('CREATE SCHEMA [AppAdmin]');
END
GO
IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'Security')
BEGIN
    EXEC('CREATE SCHEMA [Security]');
END
GO

-- 2. Create the Permissions Map Table
IF OBJECT_ID('AppAdmin.PermissionsMap', 'U') IS NULL
BEGIN
    CREATE TABLE AppAdmin.PermissionsMap (
        ID INT IDENTITY(1,1) PRIMARY KEY,
        UserID NVARCHAR(100) NOT NULL,
        TableName NVARCHAR(128) NOT NULL,
        ColumnID NVARCHAR(128) NOT NULL,
        AuthorizedValue NVARCHAR(MAX) NOT NULL,
        CreatedDate DATETIME DEFAULT GETDATE()
    );
    CREATE INDEX IX_PermissionsMap_UserTable ON AppAdmin.PermissionsMap(UserID, TableName);
END
GO

-- 3. Cleanup Existing Policies (Required to update Function)
IF EXISTS (SELECT * FROM sys.security_policies WHERE name = 'rls_FactSales')
BEGIN
    DROP SECURITY POLICY Security.rls_FactSales;
END
GO
IF EXISTS (SELECT * FROM sys.security_policies WHERE name = 'rls_DimCustomer')
BEGIN
    DROP SECURITY POLICY Security.rls_DimCustomer;
END
GO

-- 4. Create/Update Generic Predicate Function
-- Supports up to 3 columns dynamically defined by the Policy
CREATE OR ALTER FUNCTION Security.fn_DynamicPredicate(
    @Val1 SQL_VARIANT, @ColName1 NVARCHAR(128),
    @Val2 SQL_VARIANT, @ColName2 NVARCHAR(128),
    @Val3 SQL_VARIANT, @ColName3 NVARCHAR(128),
    @TableName sysname
)
RETURNS TABLE
WITH SCHEMABINDING
AS
RETURN SELECT 1 AS result
WHERE 
    IS_MEMBER('db_owner') = 1 
    OR IS_SRVROLEMEMBER('sysadmin') = 1 
    OR SUSER_NAME() = 'dmp'
    OR EXISTS (
        SELECT 1 
        FROM AppAdmin.PermissionsMap 
        WHERE UserID = COALESCE(CAST(SESSION_CONTEXT(N'UserId') AS NVARCHAR(100)), SUSER_NAME())
          AND TableName = @TableName
          AND (
              (AuthorizedValue = 'ALL')
              OR
              (@ColName1 IS NOT NULL AND ColumnID = @ColName1 AND AuthorizedValue = CAST(@Val1 AS NVARCHAR(MAX)))
              OR 
              (@ColName2 IS NOT NULL AND ColumnID = @ColName2 AND AuthorizedValue = CAST(@Val2 AS NVARCHAR(MAX)))
              OR
              (@ColName3 IS NOT NULL AND ColumnID = @ColName3 AND AuthorizedValue = CAST(@Val3 AS NVARCHAR(MAX)))
          )
    );
GO

-- 5. Create Tables (FactSales & DimCustomer)
-- FactSales
IF OBJECT_ID('dbo.FactSales', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.FactSales (
        TransactionID INT PRIMARY KEY,
        Sector NVARCHAR(50),
        CostCenter NVARCHAR(50),
        Region NVARCHAR(50),
        Amount DECIMAL(18,2),
        [Date] DATETIME
    );
     INSERT INTO dbo.FactSales (TransactionID, Sector, CostCenter, Region, Amount) VALUES 
    (1, 'Marine', 'CC100', 'North', 1000.00),
    (2, 'Aviation', 'CC200', 'South', 5000.00),
    (3, 'Marine', 'CC105', 'North', 1500.00),
    (4, 'Land', 'CC300', 'East', 2000.00);
END
GO
-- DimCustomer
IF OBJECT_ID('dbo.DimCustomer', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.DimCustomer (
        ID INT IDENTITY(1,1) PRIMARY KEY,
        CUSTOMER_NO NVARCHAR(50),
        NAME NVARCHAR(100),
        CATEGORY NVARCHAR(50),
        effective_date DATETIME,
        expiry_date DATETIME,
        is_current BIT
    );
    INSERT INTO dbo.DimCustomer (CUSTOMER_NO, NAME, CATEGORY, effective_date, is_current) VALUES
    ('C001', 'Alpha Corp', 'Industrial', '2023-01-01', 1),
    ('C002', 'Beta Ltd', 'Retail', '2023-01-01', 1),
    ('C003', 'Gamma Inc', 'Industrial', '2023-01-01', 1),
    ('C004', 'Land', 'Land', '2023-01-01', 1); -- Matching User's RLS Value 'Land'
END
GO

-- 6. Apply Security Policies
-- FactSales (Secures Sector, CostCenter, Region)
CREATE SECURITY POLICY Security.rls_FactSales
ADD FILTER PREDICATE Security.fn_DynamicPredicate(
    Sector, 'Sector', 
    CostCenter, 'CostCenter', 
    Region, 'Region', 
    'FactSales'
) 
ON dbo.FactSales
WITH (STATE = ON);
GO

-- DimCustomer (Secures Name)
CREATE SECURITY POLICY Security.rls_DimCustomer
ADD FILTER PREDICATE Security.fn_DynamicPredicate(
    Name, 'NAME', 
    NULL, NULL, 
    NULL, NULL, 
    'DimCustomer'
) 
ON dbo.DimCustomer
WITH (STATE = ON);
GO

-- 7. Apply Dynamic Data Masking (DDM)
-- Check if masking exists on Amount column before Adding (to avoid error if rerun)
IF NOT EXISTS (SELECT 1 FROM sys.masked_columns WHERE object_id = OBJECT_ID('dbo.FactSales') AND name = 'Amount')
BEGIN
    ALTER TABLE dbo.FactSales 
    ALTER COLUMN Amount ADD MASKED WITH (FUNCTION = 'default()');
END
GO
