<?php
// Simple script to apply the SQL file to datawarehouse_DEV
// Run from CLI: php apply_rls.php

require_once 'includes/db.php';

// Web Execution Context
$isWeb = isset($_SERVER['HTTP_HOST']);

if ($isWeb) {
    $pageTitle = 'Applying Security Schema';
    require_once 'includes/header.php';

    // Simple Admin/IAO check
    if (!isset($_SESSION['user_id'])) {
        echo "<div class='container'><h2>Access Denied</h2><p>Please log in.</p></div>";
        require_once 'includes/footer.php';
        exit;
    }
}

// Start Output
if ($isWeb) {
    echo "<div style='max-width: 800px; margin: 0 auto;'>";
    echo "<h1>Applying Security Schema</h1>";
    echo "<div class='glass-panel' style='padding: 2rem;'>";

    // Connection Check
    if (!$pdo_dw) {
        echo "<div style='color: var(--accent-danger); margin-bottom: 1rem;'><strong>Connection Error:</strong> Could not connect to datawarehouse_DEV.</div>";
        echo "<p>Please ensure you have run <code>grant_permissions.sql</code> as a database administrator.</p>";
        echo "<a href='manage.php' class='btn btn-secondary'>Return to Manage Access</a>";
        echo "</div></div>";
        require_once 'includes/footer.php';
        exit;
    }

    echo "<p>Updating Row-Level Security (RLS) and Dynamic Data Masking (DDM) policies on <strong>datawarehouse_DEV</strong>...</p>";
    echo "<div style='background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 4px; font-family: monospace; font-size: 0.9rem; max-height: 400px; overflow-y: auto; margin: 1.5rem 0;'>";
} else {
    // CLI Output
    if (!$pdo_dw) {
        die("Error: Connection to datawarehouse_DEV failed.\nPlease run 'grant_permissions.sql' as Admin via SSMS.\n");
    }
    echo "Applying RLS Setup to datawarehouse_DEV...\n";
}

// --- Execution Logic ---
$sqlFile = 'setup_datawarehouse_rls.sql';
if (!file_exists($sqlFile)) {
    if ($isWeb)
        echo "Error: $sqlFile not found.<br/>";
    else
        echo "Error: $sqlFile not found.\n";
    exit;
}

$sql = file_get_contents($sqlFile);
$batches = preg_split('/^GO\s*$/mi', $sql);
$successCount = 0;
$errorCount = 0;

foreach ($batches as $i => $batch) {
    $batch = trim($batch);
    if (!empty($batch)) {
        try {
            $pdo_dw->exec($batch);
            if ($isWeb)
                echo "<span style='color: var(--accent-success);'>&#10004; Executed batch " . ($i + 1) . "</span><br/>";
            else
                echo "Executed batch " . ($i + 1) . ".\n";
            $successCount++;
        } catch (PDOException $e) {
            $errMsg = $e->getMessage();
            if ($isWeb) {
                echo "<span style='color: var(--accent-danger);'>&#10008; Error executing batch " . ($i + 1) . ":</span> " . htmlspecialchars($errMsg) . "<br/>";
                echo "<span style='color: #888; font-size: 0.8rem;'>Batch Start: " . htmlspecialchars(substr($batch, 0, 100)) . "...</span><br/>";
            } else {
                echo "Error executing batch " . ($i + 1) . ": " . $errMsg . "\n";
            }
            $errorCount++;
        }
    }
}

// --- Footer / Wrap up ---
if ($isWeb) {
    echo "</div>"; // End console log div

    if ($errorCount == 0) {
        echo "<div style='padding: 1rem; background: rgba(34, 197, 94, 0.1); border-left: 4px solid var(--accent-success);'><strong>Success!</strong> Security schema applied successfully ($successCount batches).</div>";
    } else {
        echo "<div style='padding: 1rem; background: rgba(255, 152, 0, 0.1); border-left: 4px solid var(--accent-warning);'><strong>Completed with Errors.</strong> $successCount batches succeeded, $errorCount failed.</div>";
    }

    echo "<div style='margin-top: 2rem;'>";
    echo "<a href='manage.php' class='btn btn-primary'>Return to Manage Access</a>";
    echo "</div>";

    echo "</div></div>"; // End glass-panel and container
    require_once 'includes/footer.php';
} else {
    echo "Done. Success: $successCount, Errors: $errorCount.\n";
}
?>