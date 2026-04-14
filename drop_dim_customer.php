<?php
require_once 'includes/db.php';
try {
    echo "Dropping Security Policy rls_DimCustomer...\n";
    $pdo_dw->exec("DROP SECURITY POLICY IF EXISTS Security.rls_DimCustomer");

    echo "Dropping dbo.DimCustomer...\n";
    $pdo_dw->exec("DROP TABLE IF EXISTS dbo.DimCustomer");

    echo "Table dropped successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>