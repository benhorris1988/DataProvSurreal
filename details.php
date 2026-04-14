<?php
$pageTitle = 'Dataset Details';
require_once 'includes/header.php';

$id = $_GET['id'] ?? 0;
// Fetch Dataset Details
$stmt = $pdo->prepare("
    SELECT d.*, g.name as group_name, u.name as owner_name 
    FROM datasets d 
    LEFT JOIN virtual_groups g ON d.owner_group_id = g.id 
    LEFT JOIN users u ON g.owner_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$dataset = $stmt->fetch();

if (!$dataset) {
    echo "<h1>Dataset not found</h1>";
    require_once 'includes/footer.php';
    exit;
}

// Fetch Global Approvers for Pending Request display
$stmtApprovers = $pdo->query("SELECT name, role FROM users WHERE role IN ('IAA', 'Admin') ORDER BY name ASC");
$globalApprovers = $stmtApprovers->fetchAll();

// Fetch Columns
$stmtCols = $pdo->prepare("SELECT * FROM columns WHERE dataset_id = ?");
$stmtCols->execute([$id]);
$columns = $stmtCols->fetchAll();

// Check if user already has access or pending request
$stmtReq = $pdo->prepare("SELECT TOP 1 * FROM access_requests WHERE user_id = ? AND dataset_id = ? ORDER BY created_at DESC");
$stmtReq->execute([$currentUser['id'], $id]);
$existingRequest = $stmtReq->fetch();
?>

<div style="margin-bottom: 2rem;">
    <a href="catalog.php" class="btn btn-secondary" style="margin-bottom: 1rem;">&larr; Back to Catalog</a>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">

    <!-- Left Column: Details & Schema -->
    <div>
        <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <h1 style="margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($dataset['name']); ?>
                    </h1>
                    <span class="badge <?php echo $dataset['type'] == 'Fact' ? 'badge-fact' : 'badge-dim'; ?>">
                        <?php echo htmlspecialchars($dataset['type']); ?>
                    </span>
                </div>
            </div>
            <p style="margin-top: 1.5rem; font-size: 1.1rem;">
                <?php echo nl2br(htmlspecialchars($dataset['description'])); ?>
            </p>
        </div>

        <!-- Columns Section -->
        <h2 style="margin-bottom: 1rem;">Data Schema</h2>
        <div class="glass-panel table-container">
            <table>
                <thead>
                    <tr>
                        <th>Column Name</th>
                        <th>Type</th>
                        <th>Definition</th>
                        <th>Sample</th>
                        <th>Security</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($columns as $col): ?>
                        <tr>
                            <td style="font-weight: 500; color: var(--accent-primary);">
                                <?php echo htmlspecialchars($col['name']); ?>
                            </td>
                            <td><code><?php echo htmlspecialchars($col['data_type']); ?></code></td>
                            <td>
                                <?php echo htmlspecialchars($col['definition']); ?>
                            </td>
                            <td style="font-family: monospace; font-size: 0.85rem; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($col['sample_data']); ?>
                            </td>
                            <td>
                                <?php if ($col['is_pii']): ?>
                                    <span class="badge"
                                        style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.2);">Confidential</span>
                                <?php else: ?>
                                    <span style="color: var(--accent-success); font-size: 0.8rem;">Open</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Request Access -->
    <div>
        <div class="glass-panel" style="padding: 1.5rem; position: sticky; top: 100px;">
            <h3 style="margin-top: 0;">Access Status</h3>

            <?php
            // Fetch ALL requests/access for this user
            $stmtReqs = $pdo->prepare("
                SELECT ar.*, g.name as policy_name, rev.name as reviewer_name 
                FROM access_requests ar 
                LEFT JOIN asset_policy_groups g ON ar.policy_group_id = g.id 
                LEFT JOIN users rev ON ar.reviewed_by = rev.id
                WHERE ar.user_id = ? AND ar.dataset_id = ? 
                ORDER BY ar.created_at DESC
            ");
            $stmtReqs->execute([$currentUser['id'], $id]);
            $allRequests = $stmtReqs->fetchAll();

            $hasFullAccess = false;
            $activePolicyIds = [];
            $pendingPolicyIds = [];

            if (!empty($allRequests)) {
                echo "<div style='margin-bottom: 1.5rem;'>";
                foreach ($allRequests as $req) {
                    if ($req['status'] == 'Approved' && empty($req['policy_group_id']))
                        $hasFullAccess = true;
                    if ($req['status'] == 'Approved')
                        $activePolicyIds[] = $req['policy_group_id'];
                    if ($req['status'] == 'Pending')
                        $pendingPolicyIds[] = $req['policy_group_id'];

                    $statusClass = getStatusClass($req['status']);
                    $policyName = $req['policy_name'] ?: 'Full Dataset';
                    echo "<div style='margin-bottom: 0.5rem; padding: 0.75rem; background: rgba(255,255,255,0.03); border-radius: 4px; border-left: 3px solid var(--text-secondary);'>";
                    echo "<strong>$policyName</strong>";
                    echo "<div style='display:flex; justify-content:space-between; align-items:center; margin-top:0.25rem;'>";
                    echo "<span class='$statusClass' style='font-size: 0.75rem; padding: 2px 6px;'>{$req['status']}</span>";

                    if ($req['status'] === 'Pending') {
                        echo "<form action='cancel_request.php' method='post' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to cancel this request?\");'>";
                        echo "<input type='hidden' name='request_id' value='{$req['id']}'>";
                        echo "<input type='hidden' name='dataset_id' value='$id'>";
                        echo "<button type='submit' style='background:none; border:none; color: var(--accent-danger); font-size: 0.75rem; cursor: pointer; text-decoration: underline; margin-left: 1rem;'>Cancel Request</button>";
                        echo "</form>";
                    } elseif ($req['status'] === 'Approved') {
                        echo "<form action='cancel_request.php' method='post' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to remove your access? You will need to request it again if you change your mind.\");'>";
                        echo "<input type='hidden' name='request_id' value='{$req['id']}'>";
                        echo "<input type='hidden' name='dataset_id' value='$id'>";
                        echo "<button type='submit' style='background:none; border:none; color: var(--text-secondary); font-size: 0.75rem; cursor: pointer; text-decoration: underline; margin-left: 1rem; opacity: 0.7;'>Remove Access</button>";
                        echo "</form>";
                    }

                    echo "<span style='font-size: 0.75rem; color: var(--text-secondary);'>" . formatDate($req['created_at']) . "</span>";
                    echo "</div>";

                    // Additional Info based on status
                    echo "<div style='margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.8rem;'>";
                    if ($req['status'] === 'Pending') {
                        // Fetch Approvers (Owner + Members)
                        // Use a SortOrder column to ensure Owner is always first
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
                        $stmtGroupInfo->execute([$id, $id]);
                        $approvers = $stmtGroupInfo->fetchAll();

                        echo "<div style='color: var(--text-secondary); margin-bottom: 0.25rem;'>Waiting on:</div>";

                        if (empty($approvers)) {
                            echo "<div style='color: var(--text-tertiary); font-style: italic;'>No specific approvers assigned.</div>";
                        } else {
                            foreach ($approvers as $app) {
                                echo "<div style='margin-bottom: 0.1rem;'>";
                                echo "<span style='color: var(--accent-primary); font-weight: bold;'>" . htmlspecialchars($app['name']) . "</span> ";
                                echo "<span style='color: var(--text-secondary); font-size: 0.7rem;'>(" . htmlspecialchars($app['type']) . ")</span>";
                                echo "</div>";
                            }
                        }

                        echo "<div style='color: var(--text-secondary); margin-top: 0.5rem; margin-bottom: 0.2rem;'>" . htmlspecialchars($dataset['group_name'] ?? '-') . " <span style='font-size: 0.7rem;'>(Group)</span></div>";

                        if (!empty($globalApprovers)) {
                            echo "<div style='color: var(--text-tertiary); font-size: 0.75rem; margin-top: 0.5rem;'>Also approvable by:</div>";
                            foreach ($globalApprovers as $ga) {
                                echo "<div style='font-size: 0.75rem; color: var(--text-secondary);'>";
                                echo htmlspecialchars($ga['name']) . " <span style='opacity: 0.7;'>(" . htmlspecialchars($ga['role']) . ")</span>";
                                echo "</div>";
                            }
                        }
                    } elseif ($req['status'] === 'Rejected') {
                        echo "<div style='color: var(--accent-danger); font-weight: bold;'>Rejected by: " . htmlspecialchars($req['reviewer_name'] ?? 'Unknown') . "</div>";
                    }
                    echo "</div>"; // End status specific info
            
                    echo "</div>"; // End request card
                }
                echo "</div>"; // End request loop container
            }
            ?>

            <?php if (!$hasFullAccess): ?>
                <?php if (empty($allRequests)): ?>
                    <div
                        style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid var(--accent-primary); padding: 1rem; margin-bottom: 1.5rem;">
                        <p style="margin: 0; font-size: 0.9rem;">You do not have access to this dataset.</p>
                    </div>
                <?php endif; ?>

                <hr style="border-color: var(--border-color); margin: 1.5rem 0;">
                <h4 style="margin-top: 0; margin-bottom: 1rem;">Request New Access</h4>

                <form action="request_access.php" method="post">
                    <input type="hidden" name="dataset_id" value="<?php echo $id; ?>">

                    <div class="form-group">
                        <label class="form-label">Justification</label>
                        <textarea name="justification" class="form-control" placeholder="Why do you need this data?"
                            required></textarea>
                    </div>

                    <?php if (empty($dataset['owner_group_id'])): ?>
                        <div
                            style="background: rgba(255, 152, 0, 0.1); border-left: 4px solid var(--accent-warning); padding: 1rem; margin-bottom: 1rem;">
                            <strong style="color: var(--accent-warning);">Request Disabled</strong>
                            <p style="margin: 0.5rem 0 0; font-size: 0.9rem;">This dataset does not have an assigned Owner
                                Group. Please contact an Administrator.</p>
                        </div>
                        <button type="button" class="btn btn-secondary" style="width: 100%; opacity: 0.5; cursor: not-allowed;"
                            disabled>Request Access</button>
                    <?php else: ?>
                        <div class="form-group">
                            <label class="form-label">Access Policy</label>
                            <select name="policy_group_id" class="form-control" required>
                                <option value="">-- Select Access Policy --</option>
                                <?php
                                $groupsStmt = $pdo->prepare("SELECT * FROM asset_policy_groups WHERE dataset_id = ?");
                                $groupsStmt->execute([$id]);
                                $groups = $groupsStmt->fetchAll();

                                // Filter out already requested groups
                                $availableGroups = 0;
                                foreach ($groups as $g):
                                    if (in_array($g['id'], $activePolicyIds) || in_array($g['id'], $pendingPolicyIds))
                                        continue;
                                    $availableGroups++;
                                    ?>
                                    <option value="<?php echo $g['id']; ?>">
                                        <?php echo htmlspecialchars($g['name']); ?>
                                        (<?php echo htmlspecialchars(substr($g['description'], 0, 30)); ?>...)
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!in_array(0, $activePolicyIds) && !in_array(0, $pendingPolicyIds) && !in_array(NULL, $activePolicyIds)): ?>
                                    <option value="0">Full Dataset (Requires Admin Approval)</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">Request Access</button>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <div
                    style="background: rgba(34, 197, 94, 0.1); padding: 1rem; border-radius: 4px; text-align: center; color: var(--accent-success);">
                    <strong>Fully Authorized</strong><br>
                    You have full access to this dataset.
                </div>
            <?php endif; ?>

            <!-- Linked Reports Section -->
            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <h4 style="margin-top: 0; margin-bottom: 1rem;">Used In Reports</h4>
                <?php
                $stmtRep = $pdo->prepare("SELECT r.* FROM reports r JOIN report_datasets rd ON r.id = rd.report_id WHERE rd.dataset_id = ?");
                $stmtRep->execute([$id]);
                $reports = $stmtRep->fetchAll();

                if (empty($reports)):
                    ?>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">No reports linked yet.</p>
                <?php else: ?>
                    <ul style="padding-left: 1.25rem; margin: 0; font-size: 0.9rem;">
                        <?php foreach ($reports as $rep): ?>
                            <li style="margin-bottom: 0.5rem;">
                                <a href="<?php echo htmlspecialchars($rep['url']); ?>" target="_blank"
                                    style="color: var(--accent-primary); text-decoration: none;">
                                    <?php echo htmlspecialchars($rep['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($currentUser['role'] == 'IAO' || $currentUser['role'] == 'Admin'): ?>
                    <button onclick="document.getElementById('addReportModal').style.display='block'"
                        class="btn btn-secondary" style="width: 100%; margin-top: 1rem; font-size: 0.8rem;">+ Link
                        Report</button>

                    <!-- Modal for Adding Report -->
                    <div id="addReportModal" class="modal"
                        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;">
                        <div class="glass-panel" style="width: 90%; max-width: 400px; margin: 100px auto; padding: 1.5rem;">
                            <h3 style="margin-top: 0;">Link Report</h3>
                            <form action="add_report.php" method="post">
                                <input type="hidden" name="dataset_id" value="<?php echo $id; ?>">
                                <div class="form-group">
                                    <label class="form-label">Report Name</label>
                                    <input type="text" name="report_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Report URL</label>
                                    <input type="text" name="report_url" class="form-control" placeholder="http://..."
                                        required>
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                                    <button type="button"
                                        onclick="document.getElementById('addReportModal').style.display='none'"
                                        class="btn btn-secondary">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <strong>Owner Group:</strong><br>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span><?php echo htmlspecialchars($dataset['group_name'] ?? 'Unknown'); ?></span>
                    <?php if (!empty($dataset['owner_group_id'])): ?>
                        <button onclick="openGroupInfo(<?php echo $dataset['owner_group_id']; ?>)"
                            style="background: none; border: none; color: var(--accent-primary); cursor: pointer; font-size: 1.2rem;"
                            title="View Group Owner & Members">&#9432;</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Group Info -->
<div id="groupInfoModal" class="modal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1100;">
    <div class="glass-panel" style="width: 90%; max-width: 500px; margin: 100px auto; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
            <h3 style="margin: 0;" id="modalGroupName">Group Info</h3>
            <button onclick="document.getElementById('groupInfoModal').style.display='none'"
                style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div id="groupInfoContent">Loading...</div>
    </div>
</div>

<script>
    function openGroupInfo(groupId) {
        document.getElementById('groupInfoModal').style.display = 'block';

        // AJAX Fetch
        const contentDiv = document.getElementById('groupInfoContent');
        contentDiv.innerHTML = '<p>Loading group details...</p>';

        // We can use a small inline PHP script or a new endpoint. 
        // Since we don't have a dedicated API, let's create a quick fetch script logic or use existing pages?
        // Let's create 'get_group_info.php' quickly.
        fetch('get_group_info.php?id=' + groupId)
            .then(response => response.text())
            .then(html => {
                contentDiv.innerHTML = html;
            })
            .catch(err => {
                contentDiv.innerHTML = '<p style="color:red">Failed to load info.</p>';
            });
    }
</script>