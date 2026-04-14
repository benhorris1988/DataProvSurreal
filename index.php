<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$pageTitle = 'Home';
require_once 'includes/header.php';

// --- KPI QUERIES ---

// 1. Total Datasets
$datasetCount = $pdo->query("SELECT COUNT(*) FROM datasets")->fetchColumn();

// 2. My Active Assets (Approved access)
$myActiveCount = $pdo->prepare("SELECT COUNT(DISTINCT dataset_id) FROM access_requests WHERE user_id = ? AND status = 'Approved'");
$myActiveCount->execute([$currentUser['id']]);
$myActive = $myActiveCount->fetchColumn();

// 3. My Pending Requests
$myPendingCount = $pdo->prepare("SELECT COUNT(*) FROM access_requests WHERE user_id = ? AND status = 'Pending'");
$myPendingCount->execute([$currentUser['id']]);
$myPending = $myPendingCount->fetchColumn();

// 4. Actions Required (For IAO/Admin: Pending requests for their groups)
$actionsRequired = 0;
if ($currentUser['role'] == 'IAO' || $currentUser['role'] == 'Admin') {
    // Check pending requests for datasets owned by groups this user owns
    $sqlActions = "
        SELECT COUNT(*) 
        FROM access_requests ar
        JOIN datasets d ON ar.dataset_id = d.id
        JOIN virtual_groups vg ON d.owner_group_id = vg.id
        WHERE vg.owner_id = ? AND ar.status = 'Pending'
    ";
    $stmtAct = $pdo->prepare($sqlActions);
    $stmtAct->execute([$currentUser['id']]);
    $actionsRequired = $stmtAct->fetchColumn();
}


// --- CHART QUERIES ---

// 1. Activity Trend (Last 30 Days) - SVG Line Chart
// Get count of requests per day for last 30 days
$sqlTrend = "
    SELECT FORMAT(created_at, 'yyyy-MM-dd') as date, COUNT(*) as count
    FROM access_requests
    WHERE created_at >= DATEADD(day, -30, GETDATE())
    GROUP BY FORMAT(created_at, 'yyyy-MM-dd')
    ORDER BY date ASC
";
$trendData = $pdo->query($sqlTrend)->fetchAll(PDO::FETCH_KEY_PAIR);

// Fill in missing days
$chartData = [];
$labels = [];
$maxVal = 0;
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $val = $trendData[$date] ?? 0;
    $chartData[] = $val;
    $labels[] = date('d M', strtotime($date));
    if ($val > $maxVal)
        $maxVal = $val;
}
if ($maxVal == 0)
    $maxVal = 5; // Prevent division by zero

// Build SVG Points
$svgPoints = "";
$width = 1000;
$height = 200;
$stepX = $width / (count($chartData) - 1);
foreach ($chartData as $i => $val) {
    $x = $i * $stepX;
    $y = $height - (($val / $maxVal) * $height * 0.8); // 80% height usage
    $svgPoints .= "$x,$y ";
}


// 2. Top Datasets (CSS Bar Chart)
// Top 5 most requested datasets
$sqlTop = "
    SELECT TOP 5 d.name, COUNT(*) as req_count
    FROM access_requests ar
    JOIN datasets d ON ar.dataset_id = d.id
    GROUP BY d.name
    ORDER BY req_count DESC
";
$topDatasets = $pdo->query($sqlTop)->fetchAll();
$maxReq = 0;
foreach ($topDatasets as $td) {
    if ($td['req_count'] > $maxReq)
        $maxReq = $td['req_count'];
}
if ($maxReq == 0)
    $maxReq = 1;


// 3. Data Composition (Pie Chart) - Fact vs Dimension vs Staging
$sqlTypes = "SELECT type, COUNT(*) as count FROM datasets GROUP BY type";
$typeData = $pdo->query($sqlTypes)->fetchAll(PDO::FETCH_KEY_PAIR);
$totalTypes = array_sum($typeData);


// 4. Request Status Breakdown (Pie Chart)
$sqlStatus = "SELECT status, COUNT(*) as count FROM access_requests GROUP BY status";
$statusData = $pdo->query($sqlStatus)->fetchAll(PDO::FETCH_KEY_PAIR);
$totalStatus = array_sum($statusData);


// --- LIST QUERIES ---

// 1. Recently Added Datasets (Top 6 for 2-column layout)
$recentDatasets = $pdo->query("SELECT TOP 6 * FROM datasets ORDER BY created_at DESC")->fetchAll();

// 2. My Recent Access (Approved requests)
$recentAccess = $pdo->prepare("
    SELECT TOP 5 d.id, d.name, ar.reviewed_at
    FROM access_requests ar 
    JOIN datasets d ON ar.dataset_id = d.id 
    WHERE ar.user_id = ? AND ar.status = 'Approved' 
    ORDER BY ar.reviewed_at DESC
");
$recentAccess->execute([$currentUser['id']]);
$myRecentAccess = $recentAccess->fetchAll();

?>

<style>
    @keyframes drawLine {
        to {
            stroke-dashoffset: 0;
        }
    }

    @keyframes pulse {
        0% {
            r: 4;
            stroke-width: 2;
            opacity: 1;
        }

        50% {
            r: 6;
            stroke-width: 3;
            opacity: 0.8;
        }

        100% {
            r: 4;
            stroke-width: 2;
            opacity: 1;
        }
    }

    .line-anim {
        stroke-dasharray: 2000;
        stroke-dashoffset: 2000;
        animation: drawLine 2.5s ease-out forwards;
        filter: drop-shadow(0 0 4px var(--accent-primary));
    }

    .dot-anim {
        animation: pulse 2s infinite ease-in-out;
    }

    .fade-up-item {
        opacity: 0;
        transform: translateY(10px);
        animation: fadeUp 0.5s ease-out forwards;
    }

    @keyframes fadeUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="header-section"
    style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
    <div>
        <h1 class="animate-fade-in" style="margin-bottom: 0.5rem;">Dashboard</h1>
        <p style="color: var(--text-secondary); margin: 0;">Welcome back,
            <?php echo htmlspecialchars($currentUser['name']); ?>.
        </p>
    </div>
    <div>
        <a href="catalog.php" class="btn btn-primary">Browse Catalog</a>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid-container"
    style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
    <!-- Active Assets -->
    <div class="card glass-panel animate-fade-in"
        style="animation-delay: 0.1s; border-left: 4px solid var(--accent-success);">
        <h3 style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">My Active Assets</h3>
        <div style="font-size: 2.5rem; font-weight: 800; color: var(--accent-success);">
            <?php echo $myActive; ?>
        </div>
    </div>

    <!-- Pending Requests -->
    <div class="card glass-panel animate-fade-in" style="animation-delay: 0.2s; border-left: 4px solid #ffc107;">
        <h3 style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Pending Requests</h3>
        <div style="font-size: 2.5rem; font-weight: 800; color: #ffc107;">
            <?php echo $myPending; ?>
        </div>
        <?php if ($myPending > 0): ?>
            <a href="my_requests.php" style="font-size: 0.8rem; color: #ffc107; text-decoration: none;">View Requests
                &rarr;</a>
        <?php endif; ?>
    </div>

    <!-- Actions Required (IAO Only) -->
    <?php if ($currentUser['role'] != 'User'): ?>
        <div class="card glass-panel animate-fade-in"
            style="animation-delay: 0.3s; border-left: 4px solid var(--accent-danger);">
            <h3 style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Actions Required</h3>
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--accent-danger);">
                <?php echo $actionsRequired; ?>
            </div>
            <?php if ($actionsRequired > 0): ?>
                <a href="manage.php" style="font-size: 0.8rem; color: var(--accent-danger); text-decoration: none;">Review
                    Requests &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Total Datasets -->
    <div class="card glass-panel animate-fade-in"
        style="animation-delay: 0.4s; border-left: 4px solid var(--accent-primary);">
        <h3 style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Total Datasets</h3>
        <div style="font-size: 2.5rem; font-weight: 800; color: var(--accent-primary);">
            <?php echo $datasetCount; ?>
        </div>
    </div>
</div>

<!-- TOP CHARTS ROW: Activity, Inventory, Request Outcome -->
<div class="grid-container"
    style="grid-template-columns: minmax(400px, 2fr) minmax(280px, 1fr) minmax(280px, 1fr); gap: 1.5rem; margin-bottom: 3rem;">
    <!-- CHART: Activity Trend -->
    <div class="card glass-panel">
        <h3 style="margin-bottom: 1.5rem;">Request Activity (30 Days)</h3>
        <div style="width: 100%; height: 200px; position: relative;">
            <svg viewBox="0 0 1000 200" preserveAspectRatio="none"
                style="width: 100%; height: 100%; overflow: visible;">
                <!-- Defs for Gradient -->
                <defs>
                    <linearGradient id="lineGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--accent-primary)" stop-opacity="0.5" />
                        <stop offset="100%" stop-color="var(--accent-primary)" stop-opacity="0" />
                    </linearGradient>
                </defs>

                <!-- Grid Lines -->
                <line x1="0" y1="200" x2="1000" y2="200" stroke="rgba(255,255,255,0.1)" stroke-width="1" />
                <line x1="0" y1="100" x2="1000" y2="100" stroke="rgba(255,255,255,0.05)" stroke-width="1"
                    stroke-dasharray="5,5" />
                <line x1="0" y1="0" x2="1000" y2="0" stroke="rgba(255,255,255,0.05)" stroke-width="1"
                    stroke-dasharray="5,5" />

                <!-- The Chart Line -->
                <polyline points="<?php echo $svgPoints; ?>" fill="none" stroke="var(--accent-primary)" stroke-width="3"
                    vector-effect="non-scaling-stroke" stroke-linejoin="round" class="line-anim" />

                <!-- Area Fill (using a polygon) -->
                <?php
                $polyPoints = "0,200 " . $svgPoints . " 1000,200";
                ?>
                <polygon points="<?php echo $polyPoints; ?>" fill="url(#lineGradient)" stroke="none" opacity="0.3"
                    class="fade-up-item" style="animation-delay: 1s;" />

                <!-- Dots on points -->
                <?php
                $pointsArr = explode(' ', trim($svgPoints));
                foreach ($pointsArr as $idx => $pt) {
                    if ($idx % 5 === 0 || $idx === count($pointsArr) - 1) {
                        list($cx, $cy) = explode(',', $pt);
                        // Add animation delay staggered
                        $delay = 1.5 + ($idx * 0.05);
                        echo "<circle cx='$cx' cy='$cy' r='4' fill='var(--bg-dark)' stroke='var(--accent-primary)' stroke-width='2' class='fade-up-item dot-anim' style='animation-delay: {$delay}s;' />";
                    }
                }
                ?>
            </svg>

            <!-- Simple Labels -->
            <div style="position: absolute; bottom: -25px; left: 0; font-size: 0.75rem; color: var(--text-secondary);">
                <?php echo $labels[0]; ?>
            </div>
            <div
                style="position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--text-secondary);">
                <?php echo $labels[15]; ?>
            </div>
            <div style="position: absolute; bottom: -25px; right: 0; font-size: 0.75rem; color: var(--text-secondary);">
                <?php echo $labels[29]; ?>
            </div>
        </div>
    </div>

    <!-- CHART: Data Composition (Pie) -->
    <div class="card glass-panel" style="padding: 2rem;">
        <h3 style="margin-bottom: 1.5rem;">Inventory</h3>
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;">
            <?php
            // Calculate percentages for Conic Gradient
            $factCount = $typeData['Fact'] ?? 0;
            $dimCount = $typeData['Dimension'] ?? 0;
            $stgCount = $typeData['Staging'] ?? 0;

            $pFact = ($totalTypes > 0) ? ($factCount / $totalTypes) * 100 : 0;
            $pDim = ($totalTypes > 0) ? ($dimCount / $totalTypes) * 100 : 0;
            $pStg = ($totalTypes > 0) ? ($stgCount / $totalTypes) * 100 : 0;

            // Cumulative for gradient stops
            $stop1 = $pFact;
            $stop2 = $stop1 + $pDim;
            ?>
            <div class="fade-up-item" style="
                width: 140px; 
                height: 140px; 
                border-radius: 50%;
                background: conic-gradient(
                    var(--accent-primary) 0% <?php echo $stop1; ?>%,
                    var(--accent-secondary) <?php echo $stop1; ?>% <?php echo $stop2; ?>%,
                    var(--text-tertiary) <?php echo $stop2; ?>% 100%
                );
                position: relative;
                margin-bottom: 1rem;
            ">
                <!-- Inner Circle for Donut Effect -->
                <div style="
                    position: absolute; 
                    top: 50%; left: 50%; 
                    transform: translate(-50%, -50%); 
                    width: 70%; height: 70%; 
                    background: #13151a; 
                    border-radius: 50%;
                    display: flex; align-items: center; justify-content: center;
                    font-weight: bold; font-size: 1.2rem;
                ">
                    <?php echo $datasetCount; ?>
                </div>
            </div>

            <!-- Legend -->
            <div style="width: 100%; font-size: 0.8rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="display: flex; align-items: center;"><span
                            style="width: 8px; height: 8px; background: var(--accent-primary); border-radius: 50%; margin-right: 6px;"></span>
                        Fact</span>
                    <span><?php echo number_format($pFact, 0); ?>%</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="display: flex; align-items: center;"><span
                            style="width: 8px; height: 8px; background: var(--accent-secondary); border-radius: 50%; margin-right: 6px;"></span>
                        Dim</span>
                    <span><?php echo number_format($pDim, 0); ?>%</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="display: flex; align-items: center;"><span
                            style="width: 8px; height: 8px; background: var(--text-tertiary); border-radius: 50%; margin-right: 6px;"></span>
                        Staging</span>
                    <span><?php echo number_format($pStg, 0); ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART: Request Status (Pie) -->
    <div class="card glass-panel" style="padding: 2rem;">
        <h3 style="margin-bottom: 1.5rem;">Request Outcome</h3>
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;">
            <?php
            $appCount = $statusData['Approved'] ?? 0;
            $penCount = $statusData['Pending'] ?? 0;
            $rejCount = $statusData['Rejected'] ?? 0;
            $totalReqs = $totalStatus;

            $pApp = ($totalReqs > 0) ? ($appCount / $totalReqs) * 100 : 0;
            $pPen = ($totalReqs > 0) ? ($penCount / $totalReqs) * 100 : 0;
            $pRej = ($totalReqs > 0) ? ($rejCount / $totalReqs) * 100 : 0;

            $s1 = $pApp;
            $s2 = $s1 + $pPen;
            ?>
            <div class="fade-up-item" style="
                width: 140px; 
                height: 140px; 
                border-radius: 50%;
                background: conic-gradient(
                    var(--accent-success) 0% <?php echo $s1; ?>%,
                    var(--accent-warning) <?php echo $s1; ?>% <?php echo $s2; ?>%,
                    var(--accent-danger) <?php echo $s2; ?>% 100%
                );
                position: relative;
                margin-bottom: 1rem;
            ">
                <div style="
                    position: absolute; 
                    top: 50%; left: 50%; 
                    transform: translate(-50%, -50%); 
                    width: 70%; height: 70%; 
                    background: #13151a; 
                    border-radius: 50%;
                    display: flex; align-items: center; justify-content: center;
                    font-weight: bold; font-size: 1.2rem;
                ">
                    <?php echo $totalReqs; ?>
                </div>
            </div>

            <!-- Legend -->
            <div style="width: 100%; font-size: 0.8rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="display: flex; align-items: center;"><span
                            style="width: 8px; height: 8px; background: var(--accent-success); border-radius: 50%; margin-right: 6px;"></span>
                        Approved</span>
                    <span><?php echo number_format($pApp, 0); ?>%</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="display: flex; align-items: center;"><span
                            style="width: 8px; height: 8px; background: var(--accent-warning); border-radius: 50%; margin-right: 6px;"></span>
                        Pending</span>
                    <span><?php echo number_format($pPen, 0); ?>%</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="display: flex; align-items: center;"><span
                            style="width: 8px; height: 8px; background: var(--accent-danger); border-radius: 50%; margin-right: 6px;"></span>
                        Rejected</span>
                    <span><?php echo number_format($pRej, 0); ?>%</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MIDDLE ROW: Popular | My Recent Access -->
<div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem;">
    <!-- CHART: Top Datasets -->
    <div class="card glass-panel">
        <h3 style="margin-bottom: 1.5rem;">Popular</h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php if (empty($topDatasets)): ?>
                <p style="color: var(--text-secondary);">No data yet.</p>
            <?php else: ?>
                <?php foreach ($topDatasets as $td):
                    $pct = ($td['req_count'] / $maxReq) * 100;
                    ?>
                    <div class="fade-up-item" style="animation-delay: <?php echo 0.5 + ($pct / 200); ?>s;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.25rem;">
                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;"
                                title="<?php echo htmlspecialchars($td['name']); ?>"><?php echo htmlspecialchars($td['name']); ?></span>
                            <span style="color: var(--text-secondary);"><?php echo $td['req_count']; ?></span>
                        </div>
                        <div
                            style="width: 100%; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                            <div
                                style="width: <?php echo $pct; ?>%; height: 100%; background: var(--accent-secondary); border-radius: 3px;">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Recent Access -->
    <div class="card glass-panel" style="padding: 1.5rem;">
        <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem;">My Recent
            Access</h3>
        <?php if (empty($myRecentAccess)): ?>
            <p style="color: var(--text-secondary);">You haven't been granted access to any datasets yet.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($myRecentAccess as $ra): ?>
                    <li
                        style="padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div style="min-width: 0; flex: 1;">
                            <a href="details.php?id=<?php echo $ra['id']; ?>"
                                style="color: var(--text-primary); text-decoration: none; font-weight: 500; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                title="<?php echo htmlspecialchars($ra['name']); ?>">
                                <?php echo htmlspecialchars($ra['name']); ?>
                            </a>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--accent-success); white-space: nowrap;">Current</div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- BOTTOM ROW: New to Catalog (Full Width, 2 Columns) -->
<div class="grid-container" style="grid-template-columns: 1fr; gap: 2rem;">
    <!-- Recently Added Datasets -->
    <div class="card glass-panel" style="padding: 1.5rem;">
        <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem;">New to
            Catalog</h3>
        <?php if (empty($recentDatasets)): ?>
            <p style="color: var(--text-secondary);">No datasets available.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <?php foreach ($recentDatasets as $ds): ?>
                    <li
                        style="padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div style="min-width: 0; flex: 1;">
                            <a href="details.php?id=<?php echo $ds['id']; ?>"
                                style="color: var(--text-primary); text-decoration: none; font-weight: 500; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                title="<?php echo htmlspecialchars($ds['name']); ?>">
                                <?php echo htmlspecialchars($ds['name']); ?>
                            </a>
                            <div style="margin-top: 2px;">
                                <span class="badge <?php echo $ds['type'] == 'Fact' ? 'badge-fact' : 'badge-dim'; ?>"
                                    style="font-size: 0.7rem; padding: 2px 6px;">
                                    <?php echo htmlspecialchars($ds['type']); ?>
                                </span>
                            </div>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap;">
                            <?php echo date('d M', strtotime($ds['created_at'])); ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>