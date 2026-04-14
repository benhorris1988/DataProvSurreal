<?php
require_once 'includes/db.php';

// Fix Mismatch: dim_Customer -> DimCustomer
try {
    echo "Checking for 'dim_Customer' mismatch...\n";

    // 1. Update Datasets Table (App DB)
    $stmt = $pdo->prepare("SELECT id FROM datasets WHERE name = 'dim_Customer'");
    $stmt->execute();
    if ($row = $stmt->fetch()) {
        echo "Found dataset 'dim_Customer' (ID: {$row['id']}). Renaming to 'DimCustomer'...\n";
        $upd = $pdo->prepare("UPDATE datasets SET name = 'DimCustomer' WHERE id = ?");
        $upd->execute([$row['id']]);
        echo " - Dataset renamed.\n";
    } else {
        echo "No dataset named 'dim_Customer' found.\n";
    }

    // 2. Update Permissions Map (Data Warehouse)
    // RLS will fail if Map has 'dim_Customer' but Policy passes 'DimCustomer'
    echo "Checking PermissionsMap for 'dim_Customer'...\n";
    $dw_stmt = $pdo_dw->query("SELECT COUNT(*) FROM AppAdmin.PermissionsMap WHERE TableName = 'dim_Customer'");
    $count = $dw_stmt->fetchColumn();

    if ($count > 0) {
        echo "Found $count entries in PermissionsMap with 'dim_Customer'. Updating to 'DimCustomer'...\n";
        $pdo_dw->exec("UPDATE AppAdmin.PermissionsMap SET TableName = 'DimCustomer' WHERE TableName = 'dim_Customer'");
        echo " - PermissionsMap updated.\n";
    } else {
        echo "No 'dim_Customer' entries in PermissionsMap.\n";
    }

    echo "\n------------------------------------------------------\n";
    echo "Recommendation:\n";
    echo "1. Run 'Sync Security' again in Manage Users (to ensure GRANT SELECT uses the new name).\n";
    echo "2. Run 'test_impersonation.sql' again.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>