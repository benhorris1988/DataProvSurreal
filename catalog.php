<?php
$pageTitle = 'Catalog';
require_once 'includes/header.php';

// Simple search logic
// Enhanced search and status logic
$search = $_GET['q'] ?? '';
$currentUser = $currentUser ?? null; // Ensure $currentUser is available (usually from header)
$currentUserId = $currentUser['id'] ?? 0;

// Fetch datasets with aggregated access status
// Status priority: Approved > Pending > None
$sql = "
    SELECT 
        d.id, d.name, d.type, d.description, d.owner_group_id,
        g.name as group_name, g.owner_id as group_owner_id,
        (
            SELECT TOP 1 status 
            FROM access_requests ar 
            WHERE ar.dataset_id = d.id AND ar.user_id = :uid 
            ORDER BY 
                CASE status WHEN 'Approved' THEN 1 WHEN 'Pending' THEN 2 ELSE 3 END
        ) as access_status,
        (
            SELECT CASE WHEN EXISTS (
                SELECT 1 FROM virtual_group_members vgm 
                WHERE vgm.group_id = d.owner_group_id AND vgm.user_id = :uid2
            ) THEN 1 ELSE 0 END
        ) as is_member
    FROM datasets d 
    LEFT JOIN virtual_groups g ON d.owner_group_id = g.id 
";

if ($search) {
    $sql .= " WHERE d.name LIKE :search OR d.description LIKE :search";
}
$sql .= " ORDER BY d.name ASC";

$stmt = $pdo->prepare($sql);
$params = ['uid' => $currentUserId, 'uid2' => $currentUserId];
if ($search) {
    $params['search'] = "%$search%";
}
$stmt->execute($params);
$datasets = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Data Catalog</h1>
    <div style="display: flex; gap: 1rem;">
        <?php if ($currentUser['role'] == 'Admin' || $currentUser['role'] == 'IAO'): ?>
            <a href="sync_catalog.php" class="btn btn-secondary"
                onclick="return confirm('Scan Data Warehouse for new tables?')">
                &#x21bb; Sync Catalog
            </a>
        <?php endif; ?>
        <form action="" method="get" style="width: 300px; display: flex;">
            <input type="text" name="q" id="searchInput" class="form-control" placeholder="Search datasets..."
                value="<?php echo htmlspecialchars($search); ?>">
        </form>
    </div>
</div>

<div class="glass-panel" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="data-table" id="catalogTable" style="width: 100%; border-collapse: collapse; min-width: 800px;">
            <thead style="background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid var(--border-color);">
                <tr>
                    <th style="padding: 1rem; text-align: left;">Name</th>
                    <th style="padding: 1rem; text-align: left; width: 100px;">Type</th>
                    <th style="padding: 1rem; text-align: left;">Description</th>
                    <th style="padding: 1rem; text-align: left; width: 200px;">Owner</th>
                    <th style="padding: 1rem; text-align: center; width: 100px;">Access</th>
                    <th style="padding: 1rem; text-align: right; width: 100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datasets as $ds): ?>
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;">
                        <td style="padding: 1rem; font-weight: 500;">
                            <a href="details.php?id=<?php echo $ds['id']; ?>"
                                style="text-decoration: none; color: var(--text-primary);">
                                <?php echo htmlspecialchars($ds['name']); ?>
                            </a>
                        </td>
                        <td style="padding: 1rem;">
                            <span class="badge <?php echo $ds['type'] == 'Fact' ? 'badge-fact' : 'badge-dim'; ?>"
                                style="font-size: 0.8rem;">
                                <?php echo htmlspecialchars($ds['type']); ?>
                            </span>
                        </td>
                        <td style="padding: 1rem; color: var(--text-secondary); font-size: 0.95rem;">
                            <?php
                            $desc = htmlspecialchars($ds['description']);
                            echo strlen($desc) > 80 ? substr($desc, 0, 80) . '...' : $desc;
                            ?>
                        </td>
                        <td style="padding: 1rem; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($ds['group_name'] ?? 'Unassigned'); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            <?php if ($ds['access_status'] === 'Approved'): ?>
                                <span title="Access Granted" style="color: #4caf50; font-size: 1.2rem;">&#10004;</span>
                            <?php elseif ($ds['access_status'] === 'Pending'): ?>
                                <span title="Request Pending" style="color: #ff9800; font-size: 1.2rem;">&#8987;</span>
                            <?php else: ?>
                                <span title="No Access" style="color: var(--text-tertiary); font-size: 1.2rem;">&bull;</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem; text-align: right;">
                            <?php
                            // Check Edit Permission: Admin OR Group Owner OR Group Member
                            $canEdit = false;
                            if ($currentUser['role'] == 'Admin') {
                                $canEdit = true;
                            } elseif (!empty($ds['owner_group_id'])) {
                                if ($ds['group_owner_id'] == $currentUserId || $ds['is_member'] == 1) {
                                    $canEdit = true;
                                }
                            }
                            ?>
                            <?php if ($canEdit): ?>
                                <a href="edit_dataset.php?id=<?php echo $ds['id']; ?>" class="btn btn-sm btn-secondary"
                                    style="font-size: 0.8rem;">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr id="noResultsRow" style="display: none;">
                    <td colspan="6" style="padding: 3rem; text-align: center; color: var(--text-secondary);">
                        No datasets found matching your search.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
    .data-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }

    .btn-sm {
        padding: 0.4rem 0.8rem;
        font-size: 0.85rem;
    }

    .btn-outline {
        border: 1px solid var(--border-color);
        background: transparent;
        color: var(--text-primary);
    }

    .btn-outline:hover {
        border-color: var(--accent-primary);
        color: var(--accent-primary);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('catalogTable');
        const rows = table.getElementsByTagName('tr');
        const noResultsRow = document.getElementById('noResultsRow');

        searchInput.addEventListener('keyup', function () {
            const filter = searchInput.value.toLowerCase();
            let visibleCount = 0;

            // Start loop from 1 to skip header row
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                if (row.id === 'noResultsRow') continue; // Skip our utility row

                // Columns to search: Name (0), Type (1), Description (2), Owner (3)
                const nameCol = row.getElementsByTagName('td')[0];
                const typeCol = row.getElementsByTagName('td')[1];
                const descCol = row.getElementsByTagName('td')[2];
                const ownerCol = row.getElementsByTagName('td')[3];

                if (nameCol && typeCol && descCol && ownerCol) {
                    const textValue = (nameCol.textContent + " " + typeCol.textContent + " " + descCol.textContent + " " + ownerCol.textContent).toLowerCase();

                    if (textValue.indexOf(filter) > -1) {
                        row.style.display = "";
                        visibleCount++;
                    } else {
                        row.style.display = "none";
                    }
                }
            }

            // Show/Hide "No Results" message
            if (visibleCount === 0) {
                noResultsRow.style.display = "";
            } else {
                noResultsRow.style.display = "none";
            }
        });

        // Prevent form submission on Enter to keep it dynamic
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>