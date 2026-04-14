<?php
$pageTitle = 'Manage Policy';
require_once 'includes/header.php';

// Ensure user is IAO or Admin
if ($currentUser['role'] !== 'IAO' && $currentUser['role'] !== 'Admin') {
    die("Access Denied");
}

$datasetId = $_GET['id'] ?? null;
if (!$datasetId) {
    header("Location: manage.php");
    exit;
}

// Fetch Dataset Details
$stmt = $pdo->prepare("SELECT * FROM datasets WHERE id = ?");
$stmt->execute([$datasetId]);
$dataset = $stmt->fetch();

if (!$dataset) {
    die("Dataset not found");
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_group'])) {
        $name = $_POST['name'];
        $desc = $_POST['description'];
        $ownerId = $_POST['owner_id'];

        $sql = "INSERT INTO asset_policy_groups (dataset_id, owner_id, name, description) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$datasetId, $ownerId, $name, $desc]);
    } elseif (isset($_POST['add_condition'])) {
        $groupId = $_POST['policy_group_id'];
        $col = $_POST['column'];
        $op = $_POST['operator'];
        $val = $_POST['value'];

        $sql = "INSERT INTO asset_policy_conditions (policy_group_id, column_name, operator, value) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$groupId, $col, $op, $val]);
    } elseif (isset($_POST['delete_condition'])) {
        $id = $_POST['condition_id'];
        $pdo->prepare("DELETE FROM asset_policy_conditions WHERE id = ?")->execute([$id]);
    } elseif (isset($_POST['toggle_cls'])) {
        $groupId = $_POST['policy_group_id'];
        $col = $_POST['column'];
        // Checkbox sent 'is_visible' if checked. If not sent, it means unchecked (Hidden).
        $isHidden = isset($_POST['is_visible']) ? 0 : 1;

        // Check if exists
        $check = $pdo->prepare("SELECT id FROM asset_policy_columns WHERE policy_group_id = ? AND column_name = ?");
        $check->execute([$groupId, $col]);
        if ($check->fetch()) {
            $sql = "UPDATE asset_policy_columns SET is_hidden = ? WHERE policy_group_id = ? AND column_name = ?";
            $pdo->prepare($sql)->execute([$isHidden, $groupId, $col]);
        } else {
            $sql = "INSERT INTO asset_policy_columns (policy_group_id, column_name, is_hidden) VALUES (?, ?, ?)";
            $pdo->prepare($sql)->execute([$groupId, $col, $isHidden]);
        }
    }
}

// Fetch Existing Groups
$groups = $pdo->prepare("SELECT g.*, u.name as owner_name FROM asset_policy_groups g LEFT JOIN users u ON g.owner_id = u.id WHERE dataset_id = ?");
$groups->execute([$datasetId]);
$groups = $groups->fetchAll();

// Fetch Columns for this dataset
$columns = $pdo->prepare("SELECT * FROM columns WHERE dataset_id = ?");
$columns->execute([$datasetId]);
$datasetColumns = $columns->fetchAll();
?>

<div style="margin-bottom: 2rem;">
    <a href="manage.php" class="btn btn-outline">&larr; Back to Manage</a>
    <h1 style="margin-top: 1rem;">Policies: <?php echo htmlspecialchars($dataset['name']); ?></h1>
    <p>Define Asset Groups (Slices) and their security rules.</p>
</div>

<div class="grid-container" style="grid-template-columns: 1fr 2fr;">
    <!-- Left: Create New Group -->
    <div class="card glass-panel">
        <h3>Create Asset Group</h3>
        <form method="post">
            <input type="hidden" name="create_group" value="1">
            <div class="form-group">
                <label>Group Name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Nuclear Sector">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Assign IAO</label>
                <select name="owner_id" class="form-control">
                    <?php
                    $users = $pdo->query("SELECT * FROM users WHERE role IN ('IAO', 'Admin')")->fetchAll();
                    foreach ($users as $u) {
                        echo "<option value='{$u['id']}'>{$u['name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create Group</button>
        </form>
    </div>

    <!-- Right: List Groups -->
    <div>
        <?php foreach ($groups as $group): ?>
            <div class="card glass-panel" style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between;">
                    <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                    <span class="badge badge-dim"><?php echo htmlspecialchars($group['owner_name']); ?></span>
                </div>
                <p><?php echo htmlspecialchars($group['description']); ?></p>

                <hr style="border-color: var(--border-color); margin: 1rem 0;">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <!-- RLS Section -->
                    <div>
                        <h4>Row Level Security (RLS)</h4>
                        <table style="width: 100%; font-size: 0.9rem; margin-bottom: 1rem;">
                            <?php
                            $conds = $pdo->prepare("SELECT * FROM asset_policy_conditions WHERE policy_group_id = ?");
                            $conds->execute([$group['id']]);
                            foreach ($conds->fetchAll() as $c):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['column_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['operator']); ?></td>
                                    <td><?php echo htmlspecialchars($c['value']); ?></td>
                                    <td style="text-align: right;">
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="delete_condition" value="1">
                                            <input type="hidden" name="condition_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-sm"
                                                style="background:none; border:none; color: var(--danger-color); cursor:pointer;">&times;</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>

                        <form method="post" style="display: flex; gap: 0.5rem;">
                            <input type="hidden" name="add_condition" value="1">
                            <input type="hidden" name="policy_group_id" value="<?php echo $group['id']; ?>">
                            <select name="column" class="form-control" style="width: 100px;" required>
                                <option value="">Col...</option>
                                <?php foreach ($datasetColumns as $dc): ?>
                                    <option value="<?php echo htmlspecialchars($dc['name']); ?>">
                                        <?php echo htmlspecialchars($dc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="operator" class="form-control" style="width: 80px;">
                                <option value="=">=</option>
                                <option value="!=">!=</option>
                                <option value="IN">IN</option>
                                <option value="LIKE">LIKE</option>
                            </select>
                            <input type="text" name="value" class="form-control" placeholder="Value" required>
                            <button type="submit" class="btn btn-sm btn-primary">+</button>
                        </form>
                    </div>

                    <!-- CLS Section -->
                    <!-- CLS Section -->
                    <div>
                        <h4>Column Level Security (CLS)</h4>
                        <div style="max-height: 200px; overflow-y: auto; margin-bottom: 1rem;">
                            <?php foreach ($datasetColumns as $dc):
                                $isFieldHidden = $pdo->prepare("SELECT is_hidden FROM asset_policy_columns WHERE policy_group_id = ? AND column_name = ?");
                                $isFieldHidden->execute([$group['id'], $dc['name']]);
                                $hidden = $isFieldHidden->fetchColumn();
                                ?>
                                <form method="post">
                                    <input type="hidden" name="toggle_cls" value="1">
                                    <input type="hidden" name="policy_group_id" value="<?php echo $group['id']; ?>">
                                    <input type="hidden" name="column" value="<?php echo htmlspecialchars($dc['name']); ?>">
                                    <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 0.25rem;">
                                        <input type="checkbox" name="is_visible" onchange="this.form.submit()" <?php echo !$hidden ? 'checked' : ''; ?>>
                                        <span
                                            style="margin-left: 0.5rem; text-decoration: <?php echo $hidden ? 'line-through' : 'none'; ?>; color: <?php echo $hidden ? 'var(--text-tertiary)' : 'inherit'; ?>; opacity: <?php echo $hidden ? '0.6' : '1'; ?>;">
                                            <?php echo htmlspecialchars($dc['name']); ?>
                                        </span>
                                    </label>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <hr style="border-color: var(--border-color); margin: 1rem 0;">

                <!-- Users List -->
                <div>
                    <h4>Authorised Users</h4>
                    <?php
                    $authUsers = $pdo->prepare("
                        SELECT u.name, u.email, ar.reviewed_at 
                        FROM access_requests ar
                        JOIN users u ON ar.user_id = u.id
                        WHERE ar.policy_group_id = ? AND ar.status = 'Approved'
                     ");
                    $authUsers->execute([$group['id']]);
                    $usersList = $authUsers->fetchAll();
                    ?>
                    <?php if (empty($usersList)): ?>
                        <p style="font-size: 0.9rem; color: var(--text-secondary);">No users assigned to this policy yet.</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem;">
                            <?php foreach ($usersList as $au): ?>
                                <div
                                    style="font-size: 0.9rem; padding: 0.5rem; background: rgba(255,255,255,0.05); border-radius: 4px;">
                                    <strong><?php echo htmlspecialchars($au['name']); ?></strong><br>
                                    <span
                                        style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($au['email']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>