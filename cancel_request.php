<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['request_id'] ?? 0;
    $datasetId = $_POST['dataset_id'] ?? 0;

    // Verify ownership (allow canceling pending or removing approved/rejected)
    $stmt = $pdo->prepare("SELECT id FROM access_requests WHERE id = ? AND user_id = ?");
    $stmt->execute([$requestId, $_SESSION['user_id']]);
    $request = $stmt->fetch();

    if ($request) {
        $del = $pdo->prepare("DELETE FROM access_requests WHERE id = ?");
        $del->execute([$requestId]);
        // Could enable flash message here if we had a system for it
    }

    header("Location: details.php?id=" . $datasetId);
    exit;
}
header("Location: catalog.php");
exit;
?>