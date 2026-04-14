<?php
$pageTitle = 'Manage Groups';
require_once 'includes/header.php';

if ($currentUser['role'] != 'IAO' && $currentUser['role'] != 'Admin') {
    die("Unauthorized");
}

// Handle Add Member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_member') {
        $groupId = $_POST['group_id'];
        $userId = $_POST['user_id'];

        $userId = $_POST['user_id'];

        // SQL Server doesn't support INSERT IGNORE, use existence check
        $check = $pdo->prepare("SELECT 1 FROM virtual_group_members WHERE group_id = ? AND user_id = ?");
        $check->execute([$groupId, $userId]);
        if (!$check->fetch()) {
            $stmtInsert = $pdo->prepare("INSERT INTO virtual_group_members (group_id, user_id) VALUES (?, ?)");
            $stmtInsert->execute([$groupId, $userId]);
            $msg = "User added successfully.";
        } else {
            $msg = "User is already a member.";
        }
    }
    // Handle Create Group
    elseif ($_POST['action'] == 'create_group') {
        $name = $_POST['group_name'];
        $desc = $_POST['description'];
        $ownerId = ($currentUser['role'] == 'Admin' && isset($_POST['owner_id'])) ? $_POST['owner_id'] : $currentUser['id'];

        $stmtCreate = $pdo->prepare("INSERT INTO virtual_groups (name, description, owner_id) VALUES (?, ?, ?)");
        $stmtCreate->execute([$name, $desc, $ownerId]);
        $msg = "Group created.";
    }
    // Handle Update Group
    elseif ($_POST['action'] == 'update_group') {
        $groupId = $_POST['group_id'];
        $name = $_POST['group_name'];
        $desc = $_POST['description'];

        // Owner Update Logic (Admin only)
        if ($currentUser['role'] == 'Admin' && isset($_POST['owner_id'])) {
            $ownerId = $_POST['owner_id'];
            $stmtUpdate = $pdo->prepare("UPDATE virtual_groups SET name = ?, description = ?, owner_id = ? WHERE id = ?");
            $stmtUpdate->execute([$name, $desc, $ownerId, $groupId]);
        } else {
            // IAO can only update details, not owner
            // Ensure they own it first
            $checkOwner = $pdo->prepare("SELECT id FROM virtual_groups WHERE id = ? AND owner_id = ?");
            $checkOwner->execute([$groupId, $currentUser['id']]);
            if ($checkOwner->fetch()) {
                $stmtUpdate = $pdo->prepare("UPDATE virtual_groups SET name = ?, description = ? WHERE id = ?");
                $stmtUpdate->execute([$name, $desc, $groupId]);
            }
        }
        $msg = "Group updated.";
    }
}

// Fetch groups (Admin sees all, IAO/IAA/User sees own + member of)
if ($currentUser['role'] == 'Admin') {
    $stmtGroups = $pdo->query("SELECT g.*, u.name as owner_name FROM virtual_groups g JOIN users u ON g.owner_id = u.id ORDER BY g.name ASC");
    $myGroups = $stmtGroups->fetchAll();
} else {
    // Fetch if Owner OR Member
    $stmtGroups = $pdo->prepare("
        SELECT DISTINCT g.*, u.name as owner_name 
        FROM virtual_groups g 
        JOIN users u ON g.owner_id = u.id 
        LEFT JOIN virtual_group_members gm ON g.id = gm.group_id
        WHERE g.owner_id = ? OR gm.user_id = ?
        ORDER BY g.name ASC
    ");
    $stmtGroups->execute([$currentUser['id'], $currentUser['id']]);
    $myGroups = $stmtGroups->fetchAll();
}
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1>Virtual Groups</h1>
        <button onclick="document.getElementById('createGroupModal').style.display='block'" class="btn btn-primary">
            + Create Group
        </button>
    </div>

    <?php if (isset($msg)): ?>
        <div
            style="background: rgba(34, 197, 94, 0.1); color: var(--accent-success); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div
            style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="grid-container">
        <?php foreach ($myGroups as $group):
            $isOwner = ($group['owner_id'] == $currentUser['id']);
            $canEdit = ($isOwner || $currentUser['role'] == 'Admin');
            ?>
            <div class="card glass-panel">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h3>
                        <?php echo htmlspecialchars($group['name']); ?>
                        <?php if (!$isOwner && $currentUser['role'] != 'Admin'): ?>
                            <span class="badge"
                                style="font-size: 0.7rem; background: rgba(255,255,255,0.1); color: var(--text-secondary); margin-left: 0.5rem;">Member
                                View</span>
                        <?php endif; ?>
                    </h3>
                    <?php if ($canEdit): ?>
                        <button onclick='openEditModal(<?php echo json_encode($group); ?>)' class="btn btn-sm btn-secondary"
                            style="font-size: 0.8rem;">Edit</button>
                    <?php endif; ?>
                </div>
                <p>
                    <?php echo htmlspecialchars($group['description']); ?>
                </p>

                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
                    <strong>Data Owner:</strong> <?php echo htmlspecialchars($group['owner_name']); ?>
                </div>

                <div style="margin-top: 1rem; font-size: 0.9rem;">
                    <strong>Controlled Datasets:</strong>
                    <?php
                    $stmtDS = $pdo->prepare("SELECT name FROM datasets WHERE owner_group_id = ? ORDER BY name ASC");
                    $stmtDS->execute([$group['id']]);
                    $datasets = $stmtDS->fetchAll(PDO::FETCH_COLUMN);

                    if ($datasets) {
                        echo "<div style='display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.25rem;'>";
                        foreach ($datasets as $dsName) {
                            echo "<span class='badge' style='background:rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); font-size: 0.8rem;'>" . htmlspecialchars($dsName) . "</span>";
                        }
                        echo "</div>";
                    } else {
                        echo "<span style='color:var(--text-secondary); font-size:0.85rem; display:block; margin-top:0.25rem;'> none</span>";
                    }
                    ?>
                </div>

                <hr style="border-color: var(--border-color); margin: 1rem 0;">

                <h4>IAO/IAA</h4>
                <ul style="list-style: none; padding: 0; margin-bottom: 1rem;">
                    <?php
                    $stmtMem = $pdo->prepare("SELECT u.name, u.email FROM virtual_group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ?");
                    $stmtMem->execute([$group['id']]);
                    $members = $stmtMem->fetchAll();

                    foreach ($members as $m):
                        ?>
                        <li
                            style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span>
                                <?php echo htmlspecialchars($m['name']); ?>
                            </span>
                            <span style="color: var(--text-secondary); font-size: 0.8rem;">
                                <?php echo htmlspecialchars($m['email']); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($members))
                        echo "<li style='color: var(--text-secondary); font-style: italic;'>No members yet</li>"; ?>
                </ul>

                <?php if ($canEdit): ?>
                    <form method="post" style="display: flex; gap: 0.5rem;">
                        <input type="hidden" name="action" value="add_member">
                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                        <?php
                        // Fetch all users for dropdown
                        $allUsers = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC")->fetchAll();
                        ?>
                        <select name="user_id" class="form-control" style="font-size: 0.8rem; padding: 0.5rem;" required>
                            <option value="">Select User...</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?php echo $u['id']; ?>">
                                    <?php echo htmlspecialchars($u['name'] . ' (' . $u['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Add</button>
                    </form>
                <?php else: ?>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); font-style: italic; text-align: center;">
                        Contact the Group Owner to add members.
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Simple Modal for Create Group -->
<div id="createGroupModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;">
    <div class="glass-panel" style="width: 400px; margin: 100px auto; padding: 2rem;">
        <h2>Create New Group</h2>
        <form method="post">
            <input type="hidden" name="action" value="create_group">
            <div class="form-group">
                <label class="form-label">Group Name</label>
                <input type="text" name="group_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" required></textarea>
            </div>

            <?php if ($currentUser['role'] == 'Admin'): ?>
                <div class="form-group">
                    <label class="form-label">Group Owner (IAO)</label>
                    <select name="owner_id" class="form-control">
                        <?php
                        $iaos = $pdo->query("SELECT id, name FROM users WHERE role IN ('IAO', 'Admin') ORDER BY name ASC")->fetchAll();
                        foreach ($iaos as $iao) {
                            echo "<option value='{$iao['id']}' " . ($iao['id'] == $currentUser['id'] ? 'selected' : '') . ">{$iao['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('createGroupModal').style.display='none'"
                    class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Group Modal -->
<div id="editGroupModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;">
    <div class="glass-panel" style="width: 400px; margin: 100px auto; padding: 2rem;">
        <h2>Edit Group</h2>
        <form method="post">
            <input type="hidden" name="action" value="update_group">
            <input type="hidden" name="group_id" id="edit_group_id">

            <div class="form-group">
                <label class="form-label">Group Name</label>
                <input type="text" name="group_name" id="edit_group_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_group_desc" class="form-control" required></textarea>
            </div>

            <?php if ($currentUser['role'] == 'Admin'): ?>
                <div class="form-group">
                    <label class="form-label">Group Owner (IAO)</label>
                    <select name="owner_id" id="edit_group_owner" class="form-control">
                        <?php
                        // Reuse IAO list
                        if (isset($iaos)) {
                            foreach ($iaos as $iao) {
                                echo "<option value='{$iao['id']}'>{$iao['name']}</option>";
                            }
                        } else {
                            $iaos = $pdo->query("SELECT id, name FROM users WHERE role IN ('IAO', 'Admin') ORDER BY name ASC")->fetchAll();
                            foreach ($iaos as $iao) {
                                echo "<option value='{$iao['id']}'>{$iao['name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('editGroupModal').style.display='none'"
                    class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(group) {
        document.getElementById('edit_group_id').value = group.id;
        document.getElementById('edit_group_name').value = group.name;
        document.getElementById('edit_group_desc').value = group.description;

        // Set Owner if Admin
        var ownerSelect = document.getElementById('edit_group_owner');
        if (ownerSelect) {
            ownerSelect.value = group.owner_id;
        }

        document.getElementById('editGroupModal').style.display = 'block';
    }
</script>

<?php require_once 'includes/footer.php'; ?>