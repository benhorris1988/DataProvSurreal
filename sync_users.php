<?php
require_once 'includes/header.php';

// Ensure Admin
if ($currentUser['role'] !== 'Admin') {
    die("Access Denied");
}

echo "<div class='container'>";
echo "<h1>Synchronize SQL Users</h1>";
echo "<div class='card glass-panel'>";
echo "<pre>";

try {
    // 1. Fetch all web users
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll();

    $pdo_dw->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach ($users as $u) {
        // Sanitize username for SQL (remove spaces, etc if needed)
        // For simplicity, let's use Email as the Login Name to be unique, or just Name if guaranteed simple.
        // User asked for "SQL users for each of the users".
        // Let's use the 'Name' or 'Email'. Email is safer for uniqueness but Name is friendlier.
        // Let's use Email for the Login to avoid collisions.
        $loginName = $u['email'];
        $userName = $u['name']; // DB User name
        $password = "1234";

        echo "Processing: $loginName ($userName)...\n";

        // 2. Create Login (Server Level) - Requires High Privileges
        // Check if login exists
        try {
            $stmtChkCookie = $pdo_dw->prepare("SELECT name FROM master.sys.sql_logins WHERE name = ?");
            $stmtChkCookie->execute([$loginName]);
            if (!$stmtChkCookie->fetch()) {
                // Create Login
                $pdo_dw->exec("CREATE LOGIN [$loginName] WITH PASSWORD = '$password', CHECK_POLICY = OFF");
                echo "  - Login created.\n";
            } else {
                echo "  - Login exists.\n";
            }
        } catch (PDOException $e) {
            echo "  ! Error checking/creating Login (Might lack permissions): " . $e->getMessage() . "\n";
            // If we can't create Login, we probably can't proceed for this user unless Contained DB.
            continue;
        }

        // 3. Create User in DataWarehouse (Database Level)
        // Check if user exists in DB
        // Switch context is handled by initial connection to datawarehouse_DEV
        $stmtChkUser = $pdo_dw->prepare("SELECT name FROM sys.database_principals WHERE name = ?");
        $stmtChkUser->execute([$loginName]); // Use LoginName as UserName for consistency

        if (!$stmtChkUser->fetch()) {
            $pdo_dw->exec("CREATE USER [$loginName] FOR LOGIN [$loginName]");
            echo "  - DB User created.\n";
        } else {
            echo "  - DB User exists.\n";
        }

        // 4. Grant Connect
        $pdo_dw->exec("GRANT CONNECT TO [$loginName]");
        echo "  - CONNECT granted.\n";

        echo "---------------------------------------------------\n";
    }

    echo "\nDone.";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage();
}

echo "</pre>";
echo "<a href='manage_users.php' class='btn btn-primary'>Return to Users</a>";
echo "</div></div>";
require_once 'includes/footer.php';
?>