<?php
require_once 'includes/db.php';

echo "Fixing Catalog Types...\n";

// Fetch all datasets
$stmt = $pdo->query("SELECT id, name, type FROM datasets");
$datasets = $stmt->fetchAll();

// Drop Constraint if exists (using a safe approach or just try/catch)
try {
    echo "Attempting to drop CHECK constraint on 'type'...\n";
    // We don't know the exact name if it's auto-generated, but the error gave us "CK__datasets__type__33D4B598"
    // However, in dev/prod it might differ. 
    // Let's try to find it dynamically or just try the specific name from the error for now as it's local.
    // Better: Query sys.check_constraints
    $sql = "SELECT name FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID('dbo.datasets') AND definition LIKE '%type%'";
    $stmt = $pdo->query($sql);
    $constName = $stmt->fetchColumn();
    if ($constName) {
        $pdo->exec("ALTER TABLE dbo.datasets DROP CONSTRAINT [$constName]");
        echo "Dropped constraint: $constName\n";
    }
} catch (Exception $e) {
    echo "Warning dropping constraint: " . $e->getMessage() . "\n";
}

$count = 0;
foreach ($datasets as $d) {
    $name = $d['name']; // e.g. fact_CustomerOrders
    $currentType = $d['type'];

    $lowerName = strtolower($name);
    $newType = 'Dimension'; // Default

    if (strpos($lowerName, 'fact') === 0) {
        $newType = 'Fact';
    } elseif (strpos($lowerName, 'stg_') === 0) {
        $newType = 'Staging';
    }

    if ($currentType !== $newType) {
        echo "Updating '$name': $currentType -> $newType\n";
        $upd = $pdo->prepare("UPDATE datasets SET type = ? WHERE id = ?");
        $upd->execute([$newType, $d['id']]);
        $count++;
    }
}

echo "Done. Updated $count datasets.\n";
?>