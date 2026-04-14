<?php
session_start();
if (isset($_POST['user_id'])) {
    $_SESSION['user_id'] = $_POST['user_id'];
}
$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $redirect");
exit;
?>