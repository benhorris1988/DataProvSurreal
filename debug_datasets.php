<?php
require_once 'includes/db.php';
try {
    echo "Querying Datasets...\n";
    $stmt = $pdo->query("SELECT * FROM datasets");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>