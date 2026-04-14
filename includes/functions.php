<?php
session_start();

/**
 * Get current logged in user (Mock implementation for demo)
 */
function getCurrentUser($pdo)
{
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    // Default to first user if not set (for hassle-free demo)
    // In real app, redirect to login
    $stmt = $pdo->query("SELECT TOP 1 * FROM users");
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        return $user;
    }
    return null;
}

/**
 * Format date friendly
 */
function formatDate($dateString)
{
    return date('M j, Y', strtotime($dateString));
}

/**
 * Helper to render badge class based on status
 */
function getStatusClass($status)
{
    switch (strtolower($status)) {
        case 'approved':
            return 'status-approved';
        case 'rejected':
            return 'status-rejected';
        default:
            return 'status-pending';
    }
}

/**
 * Set RLS Context for the session
 */
function setRLSContext($pdo, $userId)
{
    try {
        $stmt = $pdo->prepare("EXEC sp_set_session_context @key = N'UserId', @value = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        // Silently fail or log if secondary DB is down, strictly depends on requirements. 
        // For now, allow it to pass as it might be called on the main DB which doesn't have the Proc.
    }
}

?>