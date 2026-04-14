<?php
$config = require __DIR__ . '/config.php';

$serverName = $config['db_host'];
$uid = $config['db_user'];
$pwd = $config['db_pass'];
$database = $config['db_name'];

try {
    // Check for driver presence to give a better error than [2002] connection refused (if falling back to default)
    if (!in_array('sqlsrv', PDO::getAvailableDrivers())) {
        throw new Exception("The 'pdo_sqlsrv' driver is not installed or enabled in php.ini. Please install the Microsoft Drivers for PHP for SQL Server.");
    }

    $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $uid, $pwd);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Secondary Connection to Data Warehouse for RLS
    // Using Data Warehouse credentials from config
    try {
        $dwServer = $config['dw_host'] ?? $serverName;
        $dwDb = $config['dw_name'] ?? 'datawarehouse_DEV';
        $dwUser = $config['dw_user'] ?? $uid;
        $dwPass = $config['dw_pass'] ?? $pwd;

        $pdo_dw = new PDO("sqlsrv:Server=$dwServer;Database=$dwDb", $dwUser, $dwPass);
        $pdo_dw->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo_dw->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pdo_dw = null;
        $dw_connection_error = $e->getMessage(); // Capture error for debugging
    }

} catch (PDOException $e) {

    die("Database Connection Failed: " . $e->getMessage());
} catch (Exception $e) {
    die("System Error: " . $e->getMessage());
}
?>