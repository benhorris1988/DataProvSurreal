<?php
$pageTitle = 'My Requests';
require_once 'includes/header.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$currentUser = getCurrentUser($pdo);

// Fetch My Requests
$sql = "
    SELECT r.*, d.name as dataset_name, u.name as owner_name, vg.name as group_name,
           rev.name as reviewer_name
    FROM access_requests r
    JOIN datasets d ON r.dataset_id = d.id
    LEFT JOIN virtual_groups vg ON d.owner_group_id = vg.id
    LEFT JOIN users u ON vg.owner_id = u.id
    LEFT JOIN users rev ON r.reviewed_by = rev.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$currentUser['id']]);
$requests = $stmt->fetchAll();
// Fetch Global Approvers (IAA/Admin)
$stmtApprovers = $pdo->query("SELECT name, role FROM users WHERE role IN ('IAA', 'Admin') ORDER BY name ASC");
$globalApprovers = $stmtApprovers->fetchAll();
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <h1 class="animate-fade-in" style="margin-bottom: 2rem;">My Access Requests</h1>

    <?php if (empty($requests)): ?>
        <div class="glass-panel animate-fade-in" style="text-align: center; padding: 3rem;">
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">You haven't made any requests yet.</p>
            <a href="catalog.php" class="btn btn-primary">Browse Catalog</a>
        </div>
    <?php else: ?>
        <div class="glass-panel table-container animate-fade-in">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Dataset</th>
                        <th>Owner / Approver</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                        <?php
                        // Fetch Group Owner & Members for this request's dataset
                        $dsId = $req['dataset_id'];
                        $stmtGroupInfo = $pdo->prepare("
                            SELECT u.name, u.role, 'Owner' as type, 1 as sort_order 
                            FROM datasets d 
                            JOIN virtual_groups vg ON d.owner_group_id = vg.id 
                            JOIN users u ON vg.owner_id = u.id 
                            WHERE d.id = ?
                            UNION
                            SELECT u.name, u.role, 'Member' as type, 2 as sort_order 
                            FROM datasets d 
                            JOIN virtual_groups vg ON d.owner_group_id = vg.id 
                            JOIN virtual_group_members vgm ON vg.id = vgm.group_id 
                            JOIN users u ON vgm.user_id = u.id 
                            WHERE d.id = ?
                            ORDER BY sort_order ASC, name ASC
                        ");
                        $stmtGroupInfo->execute([$dsId, $dsId]);
                        $approvers = $stmtGroupInfo->fetchAll();
                        ?>
                        <tr>
                            <td>
                                <?php echo formatDate($req['created_at']); ?>
                            </td>
                            <td style="font-weight: 500;">
                                <a href="details.php?id=<?php echo $req['dataset_id']; ?>"
                                    style="color: var(--text-primary); text-decoration: none;">
                                    <?php echo htmlspecialchars($req['dataset_name']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($req['status'] == 'Rejected'): ?>
                                    <div style="color: var(--accent-danger); font-weight: bold; font-size: 0.9rem;">
                                        Rejected by:
                                        <span style="color: var(--text-primary); font-weight: normal;">
                                            <?php echo htmlspecialchars($req['reviewer_name'] ?? 'Unknown'); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <!-- Display Owner & Members -->
                                    <div style="margin-bottom: 0.5rem;">
                                        <?php if (empty($approvers)): ?>
                                            <div
                                                style="font-size: 0.9rem; font-weight: bold; color: var(--accent-primary); margin-bottom: 0.2rem;">
                                                <?php echo htmlspecialchars($req['owner_name'] ?? 'Unknown'); ?>
                                                <span
                                                    style="font-size: 0.7rem; color: var(--text-secondary); font-weight: normal;">(Owner)</span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($approvers as $app): ?>
                                                <div style="font-size: 0.9rem; margin-bottom: 0.2rem;">
                                                    <span style="font-weight: bold; color: var(--accent-primary);">
                                                        <?php echo htmlspecialchars($app['name']); ?>
                                                    </span>
                                                    <span style="font-size: 0.7rem; color: var(--text-secondary);">
                                                        (<?php echo $app['type']; ?>)
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($req['group_name'] ?? '-'); ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($globalApprovers) && $req['status'] == 'Pending'): ?>
                                        <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.25rem;">
                                            <div style="font-size: 0.7rem; color: var(--text-tertiary); margin-bottom: 0.1rem;">Also
                                                approvable by:</div>
                                            <?php foreach ($globalApprovers as $ga): ?>
                                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                                    <?php echo htmlspecialchars($ga['name']); ?>
                                                    <span style="font-size: 0.7rem; opacity: 0.7;">(<?php echo $ga['role']; ?>)</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo getStatusClass($req['status']); ?>">
                                    <?php echo htmlspecialchars($req['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($req['status'] == 'Pending'): ?>
                                    <a href="cancel_request.php?id=<?php echo $req['id']; ?>" class="btn btn-sm btn-secondary"
                                        style="color: var(--accent-danger); border-color: var(--accent-danger); padding: 0.2rem 0.6rem; font-size: 0.8rem;"
                                        onclick="return confirm('Are you sure you want to cancel this request?');">
                                        Cancel
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-tertiary); font-size: 0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>