USE [master]
GO
IF NOT EXISTS (SELECT * FROM sys.server_principals WHERE name = N'dmp')
BEGIN
    CREATE LOGIN [dmp] WITH PASSWORD=N'dmp1234', DEFAULT_DATABASE=[datamarketplace], CHECK_EXPIRATION=OFF, CHECK_POLICY=OFF
END
GO
USE [datamarketplace]
GO
IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = N'dmp')
BEGIN
    CREATE USER [dmp] FOR LOGIN [dmp]
END
GO
ALTER ROLE [db_owner] ADD MEMBER [dmp]
GO
