<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser($pdo);
$pageTitle = isset($pageTitle) ? $pageTitle : 'Data Marketplace';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($pageTitle); ?>
    </title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

    <nav class="navbar glass-panel">
        <a href="index.php" class="nav-brand">
            <span>Data</span>Provisioning Engine
        </a>

        <ul class="nav-links">
            <li><a href="index.php" class="nav-link <?php echo $pageTitle == 'Home' ? 'active' : ''; ?>">Dashboard</a>
            </li>
            <li><a href="catalog.php"
                    class="nav-link <?php echo $pageTitle == 'Catalog' ? 'active' : ''; ?>">Catalog</a></li>
            <li><a href="my_requests.php" class="nav-link <?php echo $pageTitle == 'My Requests' ? 'active' : ''; ?>">My
                    Requests</a></li>
            <?php if (in_array($currentUser['role'], ['IAO', 'IAA', 'Admin'])): ?>
                <li><a href="manage.php"
                        class="nav-link <?php echo $pageTitle == 'Manage Access' ? 'active' : ''; ?>">Manage Access</a></li>
                <li><a href="groups.php"
                        class="nav-link <?php echo $pageTitle == 'Manage Groups' ? 'active' : ''; ?>">Virtual Groups</a>
                </li>
            <?php endif; ?>
            <?php if ($currentUser['role'] == 'Admin'): ?>
                <li><a href="manage_users.php"
                        class="nav-link <?php echo $pageTitle == 'Manage Users' ? 'active' : ''; ?>">Users</a></li>
                <li><a href="admin_config.php"
                        class="nav-link <?php echo $pageTitle == 'Admin Configuration' ? 'active' : ''; ?>"
                        style="color: var(--accent-warning);">Admin Centre</a></li>
            <?php endif; ?>
        </ul>

        <div class="user-selector">
            <span>Logged in as:</span>
            <form method="post" action="switch_user.php" id="userSwitchForm">
                <select name="user_id" onchange="document.getElementById('userSwitchForm').submit()">
                    <?php
                    $users = $pdo->query("SELECT * FROM users")->fetchAll();
                    foreach ($users as $u):
                        ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $currentUser['id'] == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']) . ' (' . $u['role'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </nav>

    <div class="container animate-fade-in">