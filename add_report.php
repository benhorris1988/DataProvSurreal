<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$currentUser = getCurrentUser($pdo);

if ($currentUser['role'] != 'IAO' && $currentUser['role'] != 'Admin') {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_name'])) {
    $datasetId = $_POST['dataset_id'];
    $name = $_POST['report_name'];
    $url = $_POST['report_url'];

    try {
        // Insert report
        $stmt = $pdo->prepare("INSERT INTO reports (name, url) VALUES (?, ?)");
        $stmt->execute([$name, $url]);
        $reportId = $pdo->lastInsertId();

        // Link to dataset
        $stmtLink = $pdo->prepare("INSERT INTO report_datasets (report_id, dataset_id) VALUES (?, ?)");
        $stmtLink->execute([$reportId, $datasetId]);

        header("Location: details.php?id=$datasetId&success=1");
    } catch (PDOException $e) {
        die("Error adding report: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
}
?>