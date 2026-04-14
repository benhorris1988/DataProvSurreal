<?php
echo "Drivers: " . implode(", ", PDO::getAvailableDrivers()) . "\n";
try {
    echo "Attempting connection to sqlsrv:Server=localhost;Database=datamarketplace\n";
    $pdo = new PDO("sqlsrv:Server=localhost;Database=datamarketplace", "dmp", "dmp1234");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected Successfully!\n";
} catch (PDOException $e) {
    echo "Connection Failed: " . $e->getMessage() . "\n";
}
?>