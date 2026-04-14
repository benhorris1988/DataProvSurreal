<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get current user
$currentUser = getCurrentUser($pdo);

// Check if user is Admin
if (!$currentUser || $currentUser['role'] !== 'Admin') {
    die("Access Denied. You must be an Administrator to view this page.");
}

$configFile = __DIR__ . '/includes/config.php';
$config = require $configFile;

$message = '';
$messageType = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update config array with posted values
    $newConfig = $config;

    // DB Settings
    $newConfig['db_host'] = $_POST['db_host'];
    $newConfig['db_name'] = $_POST['db_name'];
    $newConfig['db_user'] = $_POST['db_user'];
    // Only update password if provided (don't overwrite with empty if they left it blank)
    if (!empty($_POST['db_pass'])) {
        $newConfig['db_pass'] = $_POST['db_pass'];
    }

    // Data Warehouse Settings
    $newConfig['dw_host'] = $_POST['dw_host'];
    $newConfig['dw_name'] = $_POST['dw_name'];
    $newConfig['dw_user'] = $_POST['dw_user'];
    if (!empty($_POST['dw_pass'])) {
        $newConfig['dw_pass'] = $_POST['dw_pass'];
    }

    // AD Settings
    $newConfig['ad_enabled'] = isset($_POST['ad_enabled']);
    $newConfig['ad_domain'] = $_POST['ad_domain'];
    $newConfig['ad_server'] = $_POST['ad_server'];
    $newConfig['ad_base_dn'] = $_POST['ad_base_dn'];

    // Entra Settings
    $newConfig['entra_enabled'] = isset($_POST['entra_enabled']);
    $newConfig['entra_tenant_id'] = $_POST['entra_tenant_id'];
    $newConfig['entra_client_id'] = $_POST['entra_client_id'];
    if (!empty($_POST['entra_client_secret'])) {
        $newConfig['entra_client_secret'] = $_POST['entra_client_secret'];
    }

    // Save to file
    $content = "<?php\nreturn " . var_export($newConfig, true) . ";\n";

    if (file_put_contents($configFile, $content)) {
        $message = "Settings saved successfully! Database changes will take effect on next page load.";
        $messageType = "success";
        $config = $newConfig; // Update current view
    } else {
        $message = "Failed to save settings. Please check file permissions.";
        $messageType = "danger";
    }
}

require_once 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="animate-fade-in">Admin Centre</h1>
        <p class="text-secondary">Manage system configuration and connectivity.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> animate-fade-in">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <form method="POST" action="admin_config.php">

            <!-- Database Settings (App DB) -->
            <div class="card glass-panel mb-4 animate-fade-in" style="animation-delay: 0.1s;">
                <h3 class="mb-3" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Database
                    Connection (App)</h3>
                <div class="mb-3">
                    <label class="form-label">Server Host</label>
                    <input type="text" name="db_host" class="form-control"
                        value="<?php echo htmlspecialchars($config['db_host']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database Name</label>
                    <input type="text" name="db_name" class="form-control"
                        value="<?php echo htmlspecialchars($config['db_name']); ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="db_user" class="form-control"
                            value="<?php echo htmlspecialchars($config['db_user']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="db_pass" class="form-control"
                            placeholder="Leave blank to keep current password">
                    </div>
                </div>
            </div>

            <!-- Data Warehouse Settings (Scanning DB) -->
            <div class="card glass-panel mb-4 animate-fade-in" style="animation-delay: 0.15s;">
                <h3 class="mb-3" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Data
                    Warehouse (Scanning Target)</h3>
                <div class="mb-3">
                    <label class="form-label">Server Host</label>
                    <input type="text" name="dw_host" class="form-control"
                        value="<?php echo htmlspecialchars($config['dw_host'] ?? 'localhost'); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database Name</label>
                    <input type="text" name="dw_name" class="form-control"
                        value="<?php echo htmlspecialchars($config['dw_name'] ?? 'datawarehouse_DEV'); ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="dw_user" class="form-control"
                            value="<?php echo htmlspecialchars($config['dw_user'] ?? 'dmp'); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="dw_pass" class="form-control"
                            placeholder="Leave blank to keep current password">
                    </div>
                </div>
            </div>

            <!-- AD / LDAP Settings -->
            <div class="card glass-panel mb-4 animate-fade-in" style="animation-delay: 0.2s;">
                <div class="d-flex justify-content-between align-items-center mb-3"
                    style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <h3 class="m-0">Active Directory (LDAP)</h3>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ad_enabled" id="ad_enabled" <?php echo $config['ad_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ad_enabled">Enable</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Domain</label>
                    <input type="text" name="ad_domain" class="form-control"
                        value="<?php echo htmlspecialchars($config['ad_domain']); ?>" placeholder="example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Server URL</label>
                    <input type="text" name="ad_server" class="form-control"
                        value="<?php echo htmlspecialchars($config['ad_server']); ?>"
                        placeholder="ldap://dc.example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Base DN</label>
                    <input type="text" name="ad_base_dn" class="form-control"
                        value="<?php echo htmlspecialchars($config['ad_base_dn']); ?>" placeholder="DC=example,DC=com">
                </div>
            </div>

            <!-- Entra ID Settings -->
            <div class="card glass-panel mb-4 animate-fade-in" style="animation-delay: 0.3s;">
                <div class="d-flex justify-content-between align-items-center mb-3"
                    style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <h3 class="m-0">Microsoft Entra ID (Azure AD)</h3>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="entra_enabled" id="entra_enabled" <?php echo $config['entra_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="entra_enabled">Enable</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tenant ID</label>
                    <input type="text" name="entra_tenant_id" class="form-control"
                        value="<?php echo htmlspecialchars($config['entra_tenant_id']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Client ID</label>
                    <input type="text" name="entra_client_id" class="form-control"
                        value="<?php echo htmlspecialchars($config['entra_client_id']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Client Secret</label>
                    <input type="password" name="entra_client_secret" class="form-control"
                        placeholder="Leave blank to keep current secret">
                </div>
            </div>

            <div class="mb-5">
                <button type="submit" class="btn btn-primary btn-lg">Save Configuration</button>
            </div>

        </form>
    </div>

    <div class="col-md-4">
        <div class="card glass-panel animate-fade-in" style="animation-delay: 0.4s;">
            <h3 class="mb-3">System Info</h3>
            <p><strong>PHP Version:</strong>
                <?php echo phpversion(); ?>
            </p>
            <p><strong>Server Software:</strong>
                <?php echo $_SERVER['SERVER_SOFTWARE']; ?>
            </p>
            <p><strong>Driver:</strong>
                <?php echo in_array('sqlsrv', PDO::getAvailableDrivers()) ? '<span class="text-success">SQL Server (sqlsrv) Installed</span>' : '<span class="text-danger">SQL Server Driver Missing</span>'; ?>
            </p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>