<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400 * 30);
    session_set_cookie_params(array(
        'lifetime' => 86400 * 30,
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    session_start();
}

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header('Location: login.php');
    exit;
}

// Release session write lock immediately — pages only READ session
// This allows concurrent requests and speeds up navigation
session_write_close();

// Prevent browsers, proxies, and service workers from caching authenticated pages
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
?>