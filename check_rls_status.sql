-- Connect to your database
USE datawarehouse_DEV;
GO

PRINT '=== SECURITY POLICIES ===';

-- 1. View Active Security Policies
SELECT 
    sp.name AS PolicyName,
    sp.is_enabled,
    sp.is_schema_bound,
    s.name AS SchemaName
FROM sys.security_policies sp
JOIN sys.schemas s ON sp.schema_id = s.schema_id;

PRINT '';
PRINT '=== APPLIED PREDICATES (Filters) ===';

-- 2. View Function Predicates applied to tables
SELECT 
    sp.name AS PolicyName,
    t.name AS TargetTable,
    f.name AS SecurityFunction,
    p.predicate_type_desc AS Operation, -- FILTER vs BLOCK
    p.target_service_name
FROM sys.security_predicates p
JOIN sys.security_policies sp ON p.object_id = sp.object_id
JOIN sys.objects t ON p.target_object_id = t.object_id
JOIN sys.objects f ON p.predicate_definition_id = f.object_id
ORDER BY sp.name, t.name;

PRINT '';
PRINT '=== SECURITY FUNCTION DEFINITION ===';

-- 3. View the code of the Security Function
SELECT OBJECT_DEFINITION(OBJECT_ID('Security.fn_DynamicPredicate')) AS FunctionDefinition;
GO
