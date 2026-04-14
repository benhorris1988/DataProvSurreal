<?php
require_once 'includes/header.php';

if ($currentUser['role'] !== 'Admin') {
    die("Access Denied");
}

echo "<div class='container'>";
echo "<h1>Synchronize Security (Roles, CLS, RLS)</h1>";
echo "<div class='card glass-panel'>";
echo "<pre>";

try {
    $pdo_dw->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================================
    // 0. AUTO-REGISTER MISSING TABLES (Self-Healing)
    // ==========================================
    // For this PoC, we need FactSales and DimCustomer to be in the datasets table
    // so that we can create Policy Groups for them.
    // If they aren't there, the user can't create policies, and Sync won't find them.

    $tablesToRegister = ['FactSales', 'DimCustomer'];
    foreach ($tablesToRegister as $tbl) {
        $chk = $pdo->prepare("SELECT id FROM datasets WHERE name = ?");
        $chk->execute([$tbl]);
        if (!$chk->fetch()) {
            $pdo->prepare("INSERT INTO datasets (name, type, description, created_at) VALUES (?, 'Table', 'Auto-registered by Sync', GETDATE())")->execute([$tbl]);
            echo "  - Auto-registered dataset: $tbl\n";
        }
    }

    // ==========================================
    // 1. SYNC ROLES (from asset_policy_groups)
    // ==========================================
    echo "<strong>1. Syncing Roles...</strong>\n";
    $groups = $pdo->query("SELECT * FROM asset_policy_groups")->fetchAll();

    foreach ($groups as $g) {
        $roleName = "Role_" . preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $g['name'])) . "_" . $g['id'];

        // Check if role exists
        $chk = $pdo_dw->prepare("SELECT name FROM sys.database_principals WHERE name = ? AND type = 'R'");
        $chk->execute([$roleName]);
        if (!$chk->fetch()) {
            $pdo_dw->exec("CREATE ROLE [$roleName]");
            echo "  - Created Role: $roleName\n";
        }

        // Store the role name back in the policy group table for reference? 
        // Or just map dynamically. Mapping dynamically is fine for now.

        // ==========================================
        // 2. SYNC MEMBERS (Approved Users -> Role)
        // ==========================================
        $members = $pdo->prepare("SELECT u.email FROM access_requests ar JOIN users u ON ar.user_id = u.id WHERE ar.policy_group_id = ? AND ar.status = 'Approved'");
        $members->execute([$g['id']]);
        while ($user = $members->fetch()) {
            $userName = $user['email']; // Matching sync_users.php logic
            // Add member to role
            // Need to check if user exists in DB first? sync_users.php should have run.
            try {
                $pdo_dw->exec("ALTER ROLE [$roleName] ADD MEMBER [$userName]");
                // echo "    - Added $userName to $roleName\n"; 
            } catch (PDOException $e) {
                // User might not exist or already Member
            }
        }

        // ==========================================
        // 3. SYNC CLS (Deny Select on Hidden Cols)
        // ==========================================
        // First, Grant SELECT on the Table to the Role? 
        // Or assume 'public' has select? Usually explicit grant is better.
        // Let's find the table for this group
        $stmtDs = $pdo->prepare("SELECT d.name as dataset_name, d.name as table_name FROM datasets d WHERE id = ?");
        $stmtDs->execute([$g['dataset_id']]);
        $ds = $stmtDs->fetch();
        $tableName = $ds['table_name']; // Use actual table name if available, else dataset Name?
        // Actually earlier codebase implied dataset Name ~ Table Name or mapped. 
        // `setup_datawarehouse_rls.sql` uses 'FactSales', 'DimCustomer'.
        // Let's assume dataset name matches.

        if ($tableName) {
            // GRANT SELECT ON Table TO Role
            // GRANT SELECT ON Table TO Role
            try {
                $pdo_dw->exec("GRANT SELECT ON dbo.[$tableName] TO [$roleName]");
                // echo "    - Granted SELECT on $tableName to $roleName\n";
            } catch (Exception $e) {
                echo "    ! Failed to GRANT SELECT on $tableName: " . $e->getMessage() . "\n";
            }

            // DENY HIDDEN COLUMNS
            $hiddenCols = $pdo->prepare("SELECT column_name FROM asset_policy_columns WHERE policy_group_id = ? AND is_hidden = 1");
            $hiddenCols->execute([$g['id']]);
            $deniedCount = 0;
            while ($col = $hiddenCols->fetch()) {
                $colName = $col['column_name'];
                try {
                    $pdo_dw->exec("DENY SELECT ON dbo.[$tableName] ([$colName]) TO [$roleName]");
                    $deniedCount++;
                } catch (Exception $e) {
                    echo "    ! Failed to DENY $colName: " . $e->getMessage() . "\n";
                }
            }
            if ($deniedCount > 0)
                echo "  - Denied $deniedCount columns on $tableName for $roleName\n";
        }
    }

    // ==========================================
    // 4. SYNC RLS (PermissionsMap)
    // ==========================================
    echo "\n<strong>2. Syncing RLS Rules...</strong>\n";

    // Clear PermissionsMap? Or just upsert?
    // Clearing and rebuilding is safer for sync script.
    $pdo_dw->exec("TRUNCATE TABLE AppAdmin.PermissionsMap");

    $reqs = $pdo->query("SELECT ar.id, u.email, ar.policy_group_id FROM access_requests ar JOIN users u ON ar.user_id = u.id WHERE ar.status = 'Approved'")->fetchAll();

    $rlsCount = 0;
    foreach ($reqs as $r) {
        $userEmail = $r['email'];
        $pgId = $r['policy_group_id'];

        if ($pgId == 0)
            continue; // Full Access? Handle separately if needed.

        // Get RLS conditions for this group
        $conds = $pdo->prepare("SELECT * FROM asset_policy_conditions WHERE policy_group_id = ?");
        $conds->execute([$pgId]);
        $conditions = $conds->fetchAll();

        // Get TableName
        $stmtPg = $pdo->prepare("SELECT d.name as table_name FROM asset_policy_groups g JOIN datasets d ON g.dataset_id = d.id WHERE g.id = ?");
        $stmtPg->execute([$pgId]);
        $tbl = $stmtPg->fetch();
        $tableName = $tbl['table_name'];

        if (count($conditions) === 0) {
            // No RLS Conditions -> FULL ROW ACCESS (1=1)
            // (CLS columns are still hidden via DENY SELECT)
            $ins = $pdo_dw->prepare("INSERT INTO AppAdmin.PermissionsMap (UserID, TableName, ColumnID, AuthorizedValue) VALUES (?, ?, ?, ?)");
            $ins->execute([$userEmail, $tableName, 'Access', 'ALL']);
            $rlsCount++;
        } else {
            foreach ($conditions as $c) {
                // Insert into PermissionsMap
                $ins = $pdo_dw->prepare("INSERT INTO AppAdmin.PermissionsMap (UserID, TableName, ColumnID, AuthorizedValue) VALUES (?, ?, ?, ?)");
                // We use Email as the UserID in the map to match SUSER_NAME()
                $ins->execute([$userEmail, $tableName, $c['column_name'], $c['value']]);
                $rlsCount++;
            }
        }
    }
    echo "  - Synced $rlsCount RLS Text rules.\n";

    echo "\nDone.";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage();
}

echo "</pre>";
echo "<a href='manage_users.php' class='btn btn-primary'>Return to Users</a>";
echo "</div></div>";
require_once 'includes/footer.php';
?>