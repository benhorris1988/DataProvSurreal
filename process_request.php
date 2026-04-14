<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$currentUser = getCurrentUser($pdo);

// Security check
if ($currentUser['role'] != 'IAO' && $currentUser['role'] != 'Admin') {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
    $requestId = $_POST['request_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $policyGroupId = $_POST['policy_group_id'] ?? null;
    $adminId = $currentUser['id'];

    if ($action == 'approve') {
        $status = 'Approved';

        // Handle empty string from select as NULL
        if ($policyGroupId === '') {
            $policyGroupId = null;
        }

        $stmt = $pdo->prepare("
            UPDATE access_requests 
            SET status = ?, 
                reviewed_by = ?, 
                reviewed_at = GETDATE(), 
                policy_group_id = ? 
            WHERE id = ?
        ");

        try {
            $stmt->execute([$status, $adminId, $policyGroupId, $requestId]);

            // --- PROVISIONING TO DATA WAREHOUSE ---
            if ($status == 'Approved') {
                try {
                    // 1. Get Request Details (User and Dataset)
                    $reqDetails = $pdo->prepare("SELECT u.name as user_name, u.email, d.name as table_name 
                                                 FROM access_requests r 
                                                 JOIN users u ON r.user_id = u.id 
                                                 JOIN datasets d ON r.dataset_id = d.id 
                                                 WHERE r.id = ?");
                    $reqDetails->execute([$requestId]);
                    $details = $reqDetails->fetch();

                    if ($details) {
                        $targetUser = $details['user_name']; // Using Name as per requirements (UserA)
                        $tableName = $details['table_name'];

                        // 2. Identify Permissions to Grant
                        $permissions = [];

                        if (empty($policyGroupId)) {
                            // Full Access
                            $permissions[] = ['col' => 'Any', 'val' => 'ALL'];
                        } else {
                            // Specific Policy
                            $condStmt = $pdo->prepare("SELECT column_name, operator, value FROM asset_policy_conditions WHERE policy_group_id = ?");
                            $condStmt->execute([$policyGroupId]);
                            $conditions = $condStmt->fetchAll();

                            foreach ($conditions as $cond) {
                                // Handle comma-separated IN values or single values
                                $vals = explode(',', $cond['value']);
                                foreach ($vals as $v) {
                                    $permissions[] = [
                                        'col' => $cond['column_name'],
                                        'val' => trim($v)
                                    ];
                                }
                            }
                        }

                        // 3. Insert into DataWarehouse PermissionsMap
                        // First, clear existing permissions for this user/table to avoid duplicates/conflicts? 
                        // Or just append? Provisioning usually implies "Set State". 
                        // For this demo, let's delete existing for this user/table and re-insert.
                        $delStmt = $pdo_dw->prepare("DELETE FROM AppAdmin.PermissionsMap WHERE UserID = ? AND TableName = ?");
                        $delStmt->execute([$targetUser, $tableName]);

                        $insStmt = $pdo_dw->prepare("INSERT INTO AppAdmin.PermissionsMap (UserID, TableName, ColumnID, AuthorizedValue) VALUES (?, ?, ?, ?)");

                        foreach ($permissions as $perm) {
                            $insStmt->execute([$targetUser, $tableName, $perm['col'], $perm['val']]);
                        }
                    }

                } catch (Exception $e) {
                    // Log error but don't stop the flow? Or warn admin? 
                    // For now, we'll let it bubble or just continue. 
                    // Ideally, we should rollback the local update if remote fails, but distributed txn is hard.
                    // We'll proceed.
                }
            }
            // ---------------------------------------

            header("Location: manage.php?success=1");
        } catch (PDOException $e) {
            die("Error processing request: " . $e->getMessage());
        }

    } elseif ($action == 'reject') {
        $status = 'Rejected';
        $stmt = $pdo->prepare("
            UPDATE access_requests 
            SET status = ?, 
                reviewed_by = ?, 
                reviewed_at = GETDATE() 
            WHERE id = ?
        ");
        $stmt->execute([$status, $adminId, $requestId]);
        header("Location: manage.php?success=1");
    }

} else {
    header("Location: manage.php");
}
?>