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
?>
