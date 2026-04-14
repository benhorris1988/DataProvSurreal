-- RUN THIS SCRIPT AS ADMIN / SA (e.g., in SSMS)

USE datawarehouse_DEV;
GO

-- Create the user for the login 'dmp' if it doesn't exist
IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = 'dmp')
BEGIN
    CREATE USER [dmp] FOR LOGIN [dmp];
END
GO

-- Grant permissions (db_owner is easiest for dev, or db_datareader + db_datawriter + SHOWPLAN)
-- For RLS administration (creating schemas/policies), db_owner or specific SECURITY ADMIN rights are needed.
ALTER ROLE [db_owner] ADD MEMBER [dmp];
GO

-- REQUIRED FOR SYNC_USERS.PHP:
-- The 'dmp' user needs permission to CREATE LOGINS on the server instance.
-- Use 'securityadmin' server role for this.
ALTER SERVER ROLE [securityadmin] ADD MEMBER [dmp];
GO
