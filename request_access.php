<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$currentUser = getCurrentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['dataset_id'])) {
    $dataset_id = $_POST['dataset_id'];
    $user_id = $currentUser['id'];
    $justification = $_POST['justification'];
    $policy_group_id = $_POST['policy_group_id'] ?? null;
    if ($policy_group_id === '0' || $policy_group_id === '') {
        $policy_group_id = null;
    }

    $stmt = $pdo->prepare("INSERT INTO access_requests (user_id, dataset_id, justification, policy_group_id, status) VALUES (?, ?, ?, ?, 'Pending')");

    try {
        $stmt->execute([$user_id, $dataset_id, $justification, $policy_group_id]);
        header("Location: details.php?id=$dataset_id&success=1");
    } catch (PDOException $e) {
        die("Error submitting request: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
}
?>