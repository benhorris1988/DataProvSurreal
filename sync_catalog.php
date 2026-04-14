<?php
$pageTitle = 'Sync Catalog';
require_once 'includes/header.php';

// Ensure user is authorized
if (!isset($_SESSION['user_id']) || ($currentUser['role'] != 'Admin' && $currentUser['role'] != 'IAO')) {
    echo "<div class='container'><h2>Access Denied</h2><p>You must be an Admin or IAO to perform this action.</p></div>";
    require_once 'includes/footer.php';
    exit;
}
?>

<div style="max-width: 800px; margin: 0 auto;">
    <h1>Catalog Synchronization</h1>

    <div class="glass-panel" style="padding: 2rem;">
        <?php
        if (!$pdo_dw) {
            echo "<div style='color: var(--accent-danger); margin-bottom: 1rem;'><strong>Connection Error:</strong> Could not connect to datawarehouse_DEV.</div>";
            if (isset($dw_connection_error)) {
                echo "<div style='background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 4px; font-family: monospace; font-size: 0.9rem; margin-bottom: 1rem;'>" . htmlspecialchars($dw_connection_error) . "</div>";
            }
            echo "<p>Please ensure you have run <code>grant_permissions.sql</code> and the database 'datawarehouse_DEV' exists.</p>";
            echo "<a href='catalog.php' class='btn btn-secondary'>Return to Catalog</a>";
            require_once 'includes/footer.php';
            exit;
        }

        echo "<p>Scanning <strong>datawarehouse_DEV</strong> for new tables...</p>";
        echo "<ul style='list-style: none; padding: 0; margin-top: 1rem; margin-bottom: 1.5rem;'>";

        try {
            // 1. Fetch all tables from DataWarehouse
            $dwTablesStmt = $pdo_dw->query("
                SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA NOT IN ('AppAdmin', 'Security', 'sys') 
                  AND TABLE_NAME <> 'sysdiagrams'
            ");
            $dwTables = $dwTablesStmt->fetchAll();

            // 2. Fetch existing datasets/tables in local catalog
            $localStmt = $pdo->query("SELECT name FROM datasets");
            $localDatasets = $localStmt->fetchAll(PDO::FETCH_COLUMN);

            $addedCount = 0;

            foreach ($dwTables as $t) {
                $tableName = $t['TABLE_NAME'];

                // Determine type logic
                $lowerName = strtolower($tableName);
                $type = 'Dimension';
                if (strpos($lowerName, 'fact') === 0) {
                    $type = 'Fact';
                } elseif (strpos($lowerName, 'stg_') === 0) {
                    $type = 'Staging';
                }

                if (!in_array($tableName, $localDatasets)) {
                    // New table found!
                    echo "<li style='padding: 0.5rem; border-bottom: 1px solid var(--border-color); color: var(--accent-success);'>&#43; Found new table: <strong>" . htmlspecialchars($tableName) . "</strong> ($type). Added to catalog.</li>";

                    // Insert into datasets
                    $sql = "INSERT INTO datasets (name, type, description, owner_group_id) VALUES (?, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([
                        $tableName,
                        $type,
                        "Imported from Data Warehouse. Please update description.",
                        null
                    ]);
                    $newId = $pdo->lastInsertId();
                    $addedCount++;
                } else {
                    // Existing table - Check if type needs update?
                    // Let's force update the type for consistency based on naming convention
                    // Verify if current type matches
                    $chk = $pdo->prepare("SELECT type FROM datasets WHERE name = ?");
                    $chk->execute([$tableName]);
                    $currType = $chk->fetchColumn();

                    if ($currType !== $type) {
                        $pdo->prepare("UPDATE datasets SET type = ? WHERE name = ?")->execute([$type, $tableName]);
                        echo "<li style='padding: 0.5rem; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);'>&#10227; Updated type for <strong>" . htmlspecialchars($tableName) . "</strong> to $type.</li>";
                    }
                }

                if (!in_array($tableName, $localDatasets)) {
                    // Only fetch columns for NEW datasets to avoid overhead, or should we sync columns too?
                    // For now, let's keep original flow: only fetch columns for new ones.
                    // (Code continues below for column sync)
        
                    // 3. Sync Columns
                    $colsStmt = $pdo_dw->prepare("
                        SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?
                    ");
                    $colsStmt->execute([$tableName, $t['TABLE_SCHEMA']]);
                    $columns = $colsStmt->fetchAll();

                    foreach ($columns as $c) {
                        $cName = $c['COLUMN_NAME'];
                        $cType = $c['DATA_TYPE'];
                        if ($c['CHARACTER_MAXIMUM_LENGTH']) {
                            $cType .= "(" . $c['CHARACTER_MAXIMUM_LENGTH'] . ")";
                        }

                        $cSql = "INSERT INTO columns (dataset_id, name, data_type, definition, is_pii, sample_data) VALUES (?, ?, ?, ?, 0, '')";
                        $pdo->prepare($cSql)->execute([$newId, $cName, $cType, "Imported column"]);
                    }
                }
            }

            echo "</ul>";

            if ($addedCount == 0) {
                echo "<div style='padding: 1rem; background: rgba(59, 130, 246, 0.1); border-left: 4px solid var(--accent-primary);'><strong>Catalog is up to date.</strong> No new tables found in Data Warehouse.</div>";
            } else {
                echo "<div style='padding: 1rem; background: rgba(34, 197, 94, 0.1); border-left: 4px solid var(--accent-success);'><strong>Success!</strong> Added $addedCount new datasets to the catalog.</div>";
            }

        } catch (Exception $e) {
            echo "<div style='color: var(--accent-danger);'><strong>Sync Failed:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>

        <div style="margin-top: 2rem;">
            <a href="catalog.php" class="btn btn-primary">Return to Catalog</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>