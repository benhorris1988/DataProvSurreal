<?php
$pageTitle = 'Manage Access';
require_once 'includes/header.php';

// Ensure user is IAO, IAA, or Admin
if (!in_array($currentUser['role'], ['IAO', 'IAA', 'Admin'])) {
    echo "<div class='container'><h2>Access Denied</h2><p>You must be an Information Asset Owner, Administrator, or IAA to view this page.</p></div>";
    require_once 'includes/footer.php';
    exit;
}

// Fetch Pending Requests
$sqlRequests = "
    SELECT r.*, u.name as requestor_name, d.name as dataset_name, d.type 
    FROM access_requests r
    JOIN users u ON r.user_id = u.id
    JOIN datasets d ON r.dataset_id = d.id
    JOIN virtual_groups g ON d.owner_group_id = g.id
    WHERE r.status = 'Pending'
";

// If IAO, restrict to their owned groups OR groups they are a member of. IAAs and Admins see all.
$paramsReq = [];
if ($currentUser['role'] === 'IAO') {
    $sqlRequests .= " AND (g.owner_id = ? OR EXISTS (SELECT 1 FROM virtual_group_members gm WHERE gm.group_id = g.id AND gm.user_id = ?))";
    $paramsReq[] = $currentUser['id'];
    $paramsReq[] = $currentUser['id'];
}

$sqlRequests .= " ORDER BY r.created_at DESC";

$stmtReq = $pdo->prepare($sqlRequests);
$stmtReq->execute($paramsReq);
$pendingRequests = $stmtReq->fetchAll();

// ... (History query logic remains similar if desired, or skip history for members?)
// Let's assume history should also follow approval logic.
$sqlHistory = "
    SELECT TOP 10 r.*, u.name as requestor_name, d.name as dataset_name 
    FROM access_requests r
    JOIN users u ON r.user_id = u.id
    JOIN datasets d ON r.dataset_id = d.id
    JOIN virtual_groups g ON d.owner_group_id = g.id
    WHERE r.status != 'Pending'
";

$paramsHist = [];
if ($currentUser['role'] === 'IAO') {
    $sqlHistory .= " AND (g.owner_id = ? OR EXISTS (SELECT 1 FROM virtual_group_members gm WHERE gm.group_id = g.id AND gm.user_id = ?))";
    $paramsHist[] = $currentUser['id'];
    $paramsHist[] = $currentUser['id'];
}

$sqlHistory .= " ORDER BY r.reviewed_at DESC";

$stmtHist = $pdo->prepare($sqlHistory);
$stmtHist->execute($paramsHist);
$history = $stmtHist->fetchAll();
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Access Management</h1>
        <a href="apply_rls.php" class="btn btn-secondary"
            onclick="return confirm('Re-apply RLS Schema? This will update security policies.')">
            &#x2699; Apply RLS Schema
        </a>
    </div>
    <p>Manage pending requests and configure security policies for your Datasets.</p>

    <!-- My Datasets Section -->
    <div style="margin-top: 2rem; margin-bottom: 3rem;">
        <h2 style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">My Datasets & Policies</h2>

        <?php
        // Fetch datasets owned by groups this user manages, with counts
        // Count 1: Number of Policy Groups created
        // Count 2: Number of UNIQUE users who have 'Approved' access to this dataset (any policy or null)
        $myDatasets = $pdo->prepare("
            SELECT DISTINCT 
                d.id, d.name, d.type, d.description,
                (SELECT COUNT(*) FROM asset_policy_groups pg WHERE pg.dataset_id = d.id) as policy_count,
                (SELECT COUNT(DISTINCT user_id) FROM access_requests ar WHERE ar.dataset_id = d.id AND ar.status = 'Approved') as user_count
            FROM datasets d
            JOIN virtual_groups vg ON d.owner_group_id = vg.id
            WHERE vg.owner_id = ?
        ");
        $myDatasets->execute([$currentUser['id']]);
        $datasets = $myDatasets->fetchAll();

        // Check for Missing Tables in DataWarehouse
        $missingTables = [];
        if (isset($pdo_dw) && $pdo_dw) {
            try {
                $actualTables = $pdo_dw->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($datasets as $ds) {
                    if (!in_array($ds['name'], $actualTables)) {
                        $missingTables[$ds['id']] = true;
                    }
                }
            } catch (Exception $e) {
                // Ignore errors here to avoid breaking the page
            }
        }
        ?>

        <div class="glass-panel" style="padding: 0; overflow: hidden; margin-top: 1rem;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead style="background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem; text-align: left;">Dataset Name</th>
                        <th style="padding: 1rem; text-align: left; width: 100px;">Type</th>
                        <th style="padding: 1rem; text-align: center; width: 120px;">Policies</th>
                        <th style="padding: 1rem; text-align: center; width: 120px;">Users with Access</th>
                        <th style="padding: 1rem; text-align: right; width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datasets as $ds): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem; font-weight: 500;">
                                <?php echo htmlspecialchars($ds['name']); ?>

                                <?php if (isset($missingTables[$ds['id']])): ?>
                                    <span class="badge"
                                        style="background: var(--accent-danger); color: white; margin-left: 0.5rem; font-size: 0.7rem;">Table
                                        Not Found</span>
                                <?php endif; ?>

                                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                    <?php echo htmlspecialchars(substr($ds['description'], 0, 50)) . '...'; ?>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <span class="badge badge-dim"><?php echo htmlspecialchars($ds['type']); ?></span>
                            </td>
                            <td style="padding: 1rem; text-align: center; font-size: 1.1rem; font-weight: bold;">
                                <?php echo $ds['policy_count']; ?>
                            </td>
                            <td
                                style="padding: 1rem; text-align: center; font-size: 1.1rem; font-weight: bold; color: var(--accent-success);">
                                <?php echo $ds['user_count']; ?>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <a href="manage_policy.php?id=<?php echo $ds['id']; ?>" class="btn btn-sm btn-primary">
                                    Manage Policies
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($datasets)): ?>
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center;">You do not own any datasets.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending Requests Section -->
    <h2 style="margin-top: 3rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;">
        Pending Requests
        <?php if (count($pendingRequests) > 0): ?>
            <span class="badge" style="background: var(--accent-danger); color: white;">
                <?php echo count($pendingRequests); ?>
            </span>
        <?php endif; ?>
    </h2>

    <?php if (empty($pendingRequests)): ?>
        <div class="glass-panel" style="padding: 2rem; text-align: center; color: var(--text-secondary);">
            No pending requests at this time.
        </div>
    <?php else: ?>
        <div class="grid-container" style="grid-template-columns: 1fr;">
            <?php foreach ($pendingRequests as $req): ?>
                <div class="card glass-panel" style="border-left: 4px solid var(--accent-primary);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <div>
                            <h3 style="margin: 0; display: inline-block; margin-right: 1rem;">
                                <?php echo htmlspecialchars($req['requestor_name']); ?>
                            </h3>
                            <span style="color: var(--text-secondary);">requests access to</span>
                            <strong>
                                <?php echo htmlspecialchars($req['dataset_name']); ?>
                            </strong>
                        </div>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">
                            <?php echo formatDate($req['created_at']); ?>
                        </span>
                    </div>

                    <!-- Additional Approver Context -->
                    <?php
                    // Fetch Group Owner & Members for context
                    $dsId = $req['dataset_id'];
                    $stmtGroupInfo = $pdo->prepare("
                            SELECT u.name, u.role, 'Owner' as type 
                            FROM datasets d 
                            JOIN virtual_groups vg ON d.owner_group_id = vg.id 
                            JOIN users u ON vg.owner_id = u.id 
                            WHERE d.id = ?
                            UNION
                            SELECT u.name, u.role, 'Member' as type 
                            FROM datasets d 
                            JOIN virtual_groups vg ON d.owner_group_id = vg.id 
                            JOIN virtual_group_members vgm ON vg.id = vgm.group_id 
                            JOIN users u ON vgm.user_id = u.id 
                            WHERE d.id = ?
                        ");
                    $stmtGroupInfo->execute([$dsId, $dsId]);
                    $approvers = $stmtGroupInfo->fetchAll();
                    ?>
                    <div
                        style="margin-bottom: 1rem; border: 1px dashed rgba(255,255,255,0.1); padding: 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                        <strong style="color: var(--text-secondary);">Data Owner & Approvers:</strong>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.25rem;">
                            <?php foreach ($approvers as $app): ?>
                                <span class="badge"
                                    style="background: <?php echo ($app['type'] == 'Owner') ? 'var(--accent-primary)' : 'rgba(255,255,255,0.1)'; ?>; font-weight: normal;">
                                    <?php echo htmlspecialchars($app['name']); ?>
                                    <span style="opacity: 0.7; font-size: 0.7rem;">(<?php echo $app['type']; ?>)</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                        <strong>Justification:</strong><br>
                        <?php echo htmlspecialchars($req['justification']); ?>

                        <?php
                        $filters = json_decode($req['requested_rls_filters'], true);
                        if (!empty($filters)):
                            ?>
                            <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.1);">
                                <strong>Requested RLS:</strong>
                                <?php echo htmlspecialchars($filters['Sector'] ?? 'N/A'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form action="process_request.php" method="post" style="display: flex; gap: 1rem; align-items: center;">
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">

                        <div style="flex-grow: 1;">
                            <label class="form-label" style="font-size: 0.8rem;">Assign Policy Group</label>
                            <select name="policy_group_id" class="form-control" style="padding: 0.5rem;">
                                <option value="">Full Access (No Policy)</option>
                                <?php
                                $pGroups = $pdo->prepare("SELECT * FROM asset_policy_groups WHERE dataset_id = ?");
                                $pGroups->execute([$req['dataset_id']]);
                                foreach ($pGroups->fetchAll() as $pg) {
                                    $selected = ($pg['id'] == $req['policy_group_id']) ? 'selected' : '';
                                    echo "<option value='{$pg['id']}' $selected>" . htmlspecialchars($pg['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <button type="submit" name="action" value="approve" class="btn btn-primary"
                            style="background: var(--accent-success); border-color: transparent;">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-secondary"
                            style="color: var(--accent-danger); border-color: var(--accent-danger);">Reject</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- History Section -->
    <h2 style="margin-top: 3rem;">Recent Decisions</h2>
    <div class="glass-panel table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Dataset</th>
                    <th>Status</th>
                    <th>Applied Controls</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td>
                            <?php echo formatDate($h['reviewed_at']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($h['requestor_name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($h['dataset_name']); ?>
                        </td>
                        <td class="<?php echo getStatusClass($h['status']); ?>">
                            <?php echo $h['status']; ?>
                        </td>
                        <td style="font-size: 0.85rem;">
                            <?php
                            if ($h['status'] == 'Approved') {
                                // Fetch policy name if linked
                                if (!empty($h['policy_group_id'])) {
                                    $pgQuery = $pdo->prepare("SELECT name FROM asset_policy_groups WHERE id = ?");
                                    $pgQuery->execute([$h['policy_group_id']]);
                                    $pgName = $pgQuery->fetchColumn();
                                    echo "Policy: " . htmlspecialchars($pgName);
                                } else {
                                    echo "Full Access";
                                }
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>