<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Simple AJAX endpoint to get Group Info
$groupId = $_GET['id'] ?? 0;
if (!$groupId)
    die("Invalid Group");

// Fetch Group & Owner
$stmt = $pdo->prepare("
    SELECT g.name, u.name as owner_name, u.email as owner_email 
    FROM virtual_groups g 
    LEFT JOIN users u ON g.owner_id = u.id 
    WHERE g.id = ?
");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if (!$group)
    die("Group not found");

// Fetch Members
$stmtMem = $pdo->prepare("
    SELECT u.name, u.email 
    FROM virtual_group_members gm 
    JOIN users u ON gm.user_id = u.id 
    WHERE gm.group_id = ?
");
$stmtMem->execute([$groupId]);
$members = $stmtMem->fetchAll();
?>

<div style="margin-bottom: 1.5rem;">
    <h4 style="margin-top:0; color: var(--text-secondary);">Data Owner</h4>
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div
            style="width: 40px; height: 40px; background: var(--accent-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
            <?php echo strtoupper(substr($group['owner_name'], 0, 1)); ?>
        </div>
        <div>
            <div style="font-weight: bold;">
                <?php echo htmlspecialchars($group['owner_name']); ?>
            </div>
            <div style="font-size: 0.9rem; color: var(--text-secondary);">
                <?php echo htmlspecialchars($group['owner_email']); ?>
            </div>
        </div>
    </div>
</div>

<div>
    <h4 style="margin-top:0; color: var(--text-secondary);">IAO/IAA (
        <?php echo count($members); ?>)
    </h4>
    <?php if (empty($members)): ?>
        <p style="font-size: 0.9rem;">No members invited yet.</p>
    <?php else: ?>
        <div style="max-height: 200px; overflow-y: auto;">
            <?php foreach ($members as $m): ?>
                <div
                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; padding: 0.5rem; background: rgba(255,255,255,0.05); border-radius: 4px;">
                    <div
                        style="width: 24px; height: 24px; background: var(--text-secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                        <?php echo strtoupper(substr($m['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem;">
                            <?php echo htmlspecialchars($m['name']); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">
                            <?php echo htmlspecialchars($m['email']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>