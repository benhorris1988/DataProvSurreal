<?php
$pageTitle = 'Edit Dataset';
require_once 'includes/header.php';

// Fetch Dataset First to check permissions
$id = $_GET['id'] ?? 0;
if (!$id) {
    header("Location: catalog.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM datasets WHERE id = ?");
$stmt->execute([$id]);
$dataset = $stmt->fetch();

if (!$dataset) {
    die("Dataset not found");
}

// Security Check
$canEdit = false;
if ($currentUser['role'] == 'Admin') {
    $canEdit = true;
} elseif (!empty($dataset['owner_group_id'])) {
    // Check if user is owner of the group
    $stmtGroup = $pdo->prepare("SELECT owner_id FROM virtual_groups WHERE id = ?");
    $stmtGroup->execute([$dataset['owner_group_id']]);
    $group = $stmtGroup->fetch();

    if ($group && $group['owner_id'] == $currentUser['id']) {
        $canEdit = true;
    } else {
        // Check if user is a member
        $stmtMember = $pdo->prepare("SELECT 1 FROM virtual_group_members WHERE group_id = ? AND user_id = ?");
        $stmtMember->execute([$dataset['owner_group_id'], $currentUser['id']]);
        if ($stmtMember->fetch()) {
            $canEdit = true;
        }
    }
}

if (!$canEdit) {
    echo "<div class='container'><h2>Access Denied</h2><p>You do not have permission to edit this dataset.</p></div>";
    require_once 'includes/footer.php';
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $ownerGroupId = $_POST['owner_group_id'] ?: null; // Handle empty string as NULL

    $updateStmt = $pdo->prepare("UPDATE datasets SET name = ?, description = ?, owner_group_id = ? WHERE id = ?");
    try {
        $updateStmt->execute([$name, $description, $ownerGroupId, $id]);
        echo "<script>window.location.href = 'details.php?id=$id&updated=1';</script>";
        exit;
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch all virtual groups for dropdown
$groups = $pdo->query("SELECT * FROM virtual_groups ORDER BY name ASC")->fetchAll();
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="details.php?id=<?php echo $id; ?>" class="btn btn-secondary">&larr; Back to Details</a>
    </div>

    <h1>Edit Dataset</h1>

    <div class="glass-panel" style="padding: 2rem;">
        <?php if (isset($error)): ?>
            <div style="margin-bottom: 1rem; color: var(--accent-danger);">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Dataset Name</label>
                <input type="text" name="name" class="form-control"
                    value="<?php echo htmlspecialchars($dataset['name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control"
                    rows="4"><?php echo htmlspecialchars($dataset['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Owner Group</label>
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Select the Virtual
                    Group responsible for this dataset.</p>
                <select name="owner_group_id" class="form-control">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo $g['id']; ?>" <?php echo $dataset['owner_group_id'] == $g['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="details.php?id=<?php echo $id; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>