-- CHECK SECURITY STATE
USE datawarehouse_DEV;
GO

PRINT '=== 1. CHECK IF USER EXISTS ==='
SELECT name, type_desc FROM sys.database_principals WHERE name = 'john@example.com';

PRINT '=== 2. CHECK ROLES FOR USER ==='
SELECT 
    r.name as RoleName, 
    m.name as MemberName
FROM sys.database_role_members rm
JOIN sys.database_principals r ON rm.role_principal_id = r.principal_id
JOIN sys.database_principals m ON rm.member_principal_id = m.principal_id
WHERE m.name = 'john@example.com';

PRINT '=== 3. CHECK PERMISSIONS FOR ROLES ==='
SELECT 
    pr.name as Grantee,
    p.permission_name,
    p.state_desc,
    o.name as ObjectName,
    c.name as ColumnName
FROM sys.database_permissions p
JOIN sys.database_principals pr ON p.grantee_principal_id = pr.principal_id
LEFT JOIN sys.objects o ON p.major_id = o.object_id
LEFT JOIN sys.columns c ON p.major_id = c.object_id AND p.minor_id = c.column_id
WHERE o.name = 'DimCustomer' OR o.name = 'FactSales';

PRINT '=== 4. CHECK RLS MAP DETAILED ==='
SELECT 
    ID, 
    UserID, 
    TableName, 
    ColumnID, 
    AuthorizedValue,
    -- Debug Comparison against Policy Definition ('Name')
    CASE WHEN ColumnID = 'Name' THEN 'Match' ELSE 'Mismatch Case/Value' END as ColNameCheck
FROM AppAdmin.PermissionsMap 
WHERE UserID = 'john@example.com';

PRINT '=== 5. CHECK POLICY PRESENCE ==='
SELECT * FROM sys.security_policies WHERE name = 'rls_DimCustomer';

