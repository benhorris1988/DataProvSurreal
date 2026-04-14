<?php
require_once 'includes/db.php';
try {
    $stmt = $pdo->query("SELECT name FROM sys.databases WHERE name = 'datawarehouse_DEV'");
    if ($stmt->fetch()) {
        echo "Database 'datawarehouse_DEV' EXISTS.\n";
    } else {
        echo "Database 'datawarehouse_DEV' DOES NOT EXIST.\n";
    }
} catch (Exception $e) {
    echo "Could not check databases: " . $e->getMessage() . "\n";
}
?>