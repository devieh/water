<?php
// ============================================
// LOGOUT HANDLER
// ============================================

require_once 'config.php';
require_once 'functions.php';

// Log audit trail if user is logged in
if (isLoggedIn()) {
    try {
        $pdo = getDB();
        $audit = $pdo->prepare("INSERT INTO audit_trail (user_id, action_type, module_name, ip_address) VALUES (?, 'logout', 'auth', ?)");
        $audit->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        // Ignore errors
    }
}

// Destroy session
session_destroy();
unset($_SESSION);

// Redirect to login page
redirect('index.php');
?>