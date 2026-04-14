-- =============================================================
-- TEST RLS & CLS IMPERSONATION
-- =============================================================
-- Usage:
-- 1. Ensure you have run 'grant_permissions.sql' (as Admin/SA)
-- 2. Ensure you have run 'Sync SQL Users' & 'Sync Security' in the Web App directly.
-- 3. Run this script in SSMS to test access.
-- =============================================================

USE datawarehouse_DEV;
GO

-- 1. ADD ADDITIONAL SAMPLE DATA TO DimCustomer
IF NOT EXISTS (SELECT 1 FROM dbo.DimCustomer WHERE CustomerID = 4)
BEGIN
    INSERT INTO dbo.DimCustomer (CustomerID, Name, Region) VALUES
    (4, 'Delta Dynamics', 'West'),
    (5, 'Epsilon Energy', 'North'),
    (6, 'Zeta Zone', 'South'),
    (7, 'Omega Ops', 'East');
    PRINT 'Added sample data to DimCustomer.';
END
GO

-- 2. CHECK CURRENT USER (Should be dbo/admin)
SELECT 'Current User (Admin)' as Context, SUSER_NAME() as Login, USER_NAME() as DBUser;
SELECT * FROM dbo.DimCustomer; -- Should see all rows
GO

-- 3. IMPERSONATE A USER
-- REPLACE 'john@example.com' with a real email from your Users table that you have Synced.
PRINT '---------------------------------------------------';
PRINT 'Impersonating john@example.com...';
PRINT '---------------------------------------------------';

BEGIN TRY
    EXECUTE AS USER = 'john@example.com'; 
    -- NOTE: If this fails, it means the user does not exist in the DB. 
    -- Run "Sync SQL Users" in the web app first.

    SELECT 'Impersonated Context' as Context, SUSER_NAME() as Login, USER_NAME() as DBUser;

    -- TEST RLS: Should only see rows where Region/Sector/etc matches their permissions
    PRINT 'Querying FactSales (RLS Check):';
    SELECT * FROM dbo.FactSales;

    PRINT 'Querying DimCustomer (RLS Check):';
    SELECT * FROM dbo.DimCustomer;

    -- TEST CLS: Try to select a potentially HIDDEN column
    -- If you have hidden 'Amount' for this user's group, this might return NULL/Masked or Error (depending on DENY vs Masking)
    -- Our Sync Script uses DENY for CLS, so this simple SELECT * might FAIL if a column is denied!
    -- If SELECT * fails, that proves CLS is working (Deny Select).
    
    REVERT;
END TRY
BEGIN CATCH
    PRINT 'Error during impersonation test: ' + ERROR_MESSAGE();
    IF (SUSER_NAME() <> 'dmp') REVERT; -- Safety revert
END CATCH
GO
