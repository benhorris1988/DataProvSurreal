<?php
$pageTitle = 'Manage Users';
require_once 'includes/header.php';

// Ensure user is Admin
if ($currentUser['role'] != 'Admin') {
    die("Access Denied. Only Administrators can manage users.");
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];

        $stmt = $pdo->prepare("INSERT INTO users (name, email, role) VALUES (?, ?, ?)");
        try {
            $stmt->execute([$name, $email, $role]);
            $msg = "User added successfully.";
        } catch (PDOException $e) {
            $error = "Error adding user: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_role'])) {
        $userId = $_POST['user_id'];
        $newRole = $_POST['role'];

        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);
        $msg = "User role updated.";
    } elseif (isset($_POST['update_name'])) {
        $userId = $_POST['user_id'];
        $newName = $_POST['name'];

        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $userId]);
        $msg = "User name updated successfully.";
    }
}

// Fetch All Users
$users = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll();
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1>Manage Users</h1>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <input type="text" id="searchInput" class="form-control" placeholder="Search users by name, email, role..."
                style="width: 250px;">
            <a href="sync_users.php" class="btn btn-secondary" title="Create SQL Logins for all users"
                onclick="return confirm('Create SQL Logins for all users?')">
                &#x21bb; Sync SQL Users
            </a>
            <a href="sync_security.php" class="btn btn-secondary" title="Update SQL Roles, CLS and RLS"
                onclick="return confirm('Update SQL Roles, CLS and RLS?')">
                &#x1F512; Sync Security
            </a>
            <button onclick="document.getElementById('addUserModal').style.display='block'" class="btn btn-primary">
                + Add User
            </button>
        </div>
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

    <div class="glass-panel table-container">
        <table>
            <thead>
                <tr>
                    <th onclick="sortTable(0)" style="cursor: pointer;" title="Click to sort by Name">Name &#x21D5;</th>
                    <th onclick="sortTable(1)" style="cursor: pointer;" title="Click to sort by Email">Email &#x21D5;
                    </th>
                    <th onclick="sortTable(2)" style="cursor: pointer;" title="Click to sort by Role">Role &#x21D5;</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <?php echo htmlspecialchars($u['name']); ?>
                                <button onclick='openEditUserModal(<?php echo json_encode($u); ?>)'
                                    class="btn btn-sm btn-secondary"
                                    style="padding: 0.2rem 0.5rem; font-size: 0.7rem; margin-left: 1rem;">Edit</button>
                            </div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($u['email']); ?>
                        </td>
                        <td>
                            <span
                                class="badge <?php echo $u['role'] == 'Admin' ? 'badge-fact' : ($u['role'] == 'IAO' || $u['role'] == 'IAA' ? 'badge-dim' : ''); ?>">
                                <?php echo htmlspecialchars($u['role']); ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                <input type="hidden" name="update_role" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <select name="role" class="form-control" style="padding: 0.3rem; font-size: 0.8rem;"
                                    onchange="this.form.submit()">
                                    <option value="User" <?php echo $u['role'] == 'User' ? 'selected' : ''; ?>>User</option>
                                    <option value="IAO" <?php echo $u['role'] == 'IAO' ? 'selected' : ''; ?>>IAO</option>
                                    <option value="IAA" <?php echo $u['role'] == 'IAA' ? 'selected' : ''; ?>>IAA</option>
                                    <option value="Admin" <?php echo $u['role'] == 'Admin' ? 'selected' : ''; ?>>Admin
                                    </option>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;">
    <div class="glass-panel" style="width: 400px; margin: 100px auto; padding: 2rem;">
        <h2>Add New User</h2>
        <form method="post">
            <input type="hidden" name="add_user" value="1">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="User">User</option>
                    <option value="IAO">IAO</option>
                    <option value="IAA">IAA</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('addUserModal').style.display='none'"
                    class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;">
    <div class="glass-panel" style="width: 400px; margin: 100px auto; padding: 2rem;">
        <h2>Edit User</h2>
        <form method="post">
            <input type="hidden" name="update_name" value="1">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Email (Read Only)</label>
                <input type="email" id="edit_email" class="form-control" readonly
                    style="opacity: 0.6; cursor: not-allowed;">
                <small style="color: var(--text-secondary); display: block; margin-top: 0.25rem;">
                    Email cannot be changed as it is linked to database authentication.
                </small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('editUserModal').style.display='none'"
                    class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditUserModal(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_name').value = user.name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('editUserModal').style.display = 'block';
    }

    // Close modals on outside click
    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }

    // --- Search functionality ---
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('.table-container tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            if (row.id === 'noResultsRow') return;
            const name = row.cells[0].textContent.toLowerCase();
            const email = row.cells[1].textContent.toLowerCase();
            const role = row.cells[2].textContent.toLowerCase();
            
            if (name.includes(filter) || email.includes(filter) || role.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Handle "No Users Found" case
        let noResultsRow = document.getElementById('noResultsRow');
        if (!noResultsRow) {
            const tbody = document.querySelector('.table-container tbody');
            noResultsRow = document.createElement('tr');
            noResultsRow.id = 'noResultsRow';
            noResultsRow.style.display = 'none';
            noResultsRow.innerHTML = `<td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-secondary);">No users found matching '${filter}'.</td>`;
            tbody.appendChild(noResultsRow);
        }
        
        if (visibleCount === 0 && filter !== '') {
            noResultsRow.style.display = '';
        } else if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    });

    // --- Sort functionality ---
    let sortDirection = {}; 
    window.sortTable = function(columnIndex) {
        const table = document.querySelector('.table-container table');
        const tbody = table.querySelector('tbody');
        const rowsArray = Array.from(tbody.querySelectorAll('tr:not(#noResultsRow)'));
        
        sortDirection[columnIndex] = !sortDirection[columnIndex];
        const isAscending = sortDirection[columnIndex];

        rowsArray.sort((a, b) => {
            let textA = a.cells[columnIndex].textContent.trim().toLowerCase();
            let textB = b.cells[columnIndex].textContent.trim().toLowerCase();
            
            if (textA < textB) return isAscending ? -1 : 1;
            if (textA > textB) return isAscending ? 1 : -1;
            return 0;
        });
        
        rowsArray.forEach(row => tbody.appendChild(row));
        
        const noResultsRow = document.getElementById('noResultsRow');
        if (noResultsRow) tbody.appendChild(noResultsRow);

        const headers = table.querySelectorAll('th');
        headers.forEach((th, index) => {
            if(index < 3) {
                if (index === columnIndex) {
                    th.innerHTML = th.innerHTML.replace(/&#x21D5;|&#x25B2;|&#x25BC;/g, isAscending ? '&#x25B2;' : '&#x25BC;');
                } else {
                    th.innerHTML = th.innerHTML.replace(/&#x21D5;|&#x25B2;|&#x25BC;/g, '&#x21D5;');
                }
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>