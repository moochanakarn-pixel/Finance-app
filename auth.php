<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(array(
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
