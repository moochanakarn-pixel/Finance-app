<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$action       = trim((string)($_POST['action'] ?? ''));
$currentUserId = (int)$_SESSION['user_id'];
$returnUrl    = build_return_url('admin_users.php');

if ($action === 'add') {
    $username  = trim((string)($_POST['username'] ?? ''));
    $fullName  = trim((string)($_POST['full_name'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $role      = in_array($_POST['role'] ?? '', ['admin', 'user'], true) ? $_POST['role'] : 'user';

    if ($username === '' || $password === '') {
        redirect($returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'error=invalid');
    }

    $check = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? LIMIT 1');
    mysqli_stmt_bind_param($check, 's', $username);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    $exists = mysqli_stmt_num_rows($check) > 0;
    mysqli_stmt_close($check);

    if ($exists) {
        redirect($returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'error=duplicate');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, 'INSERT INTO users (username, password_hash, full_name, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())');
    mysqli_stmt_bind_param($stmt, 'ssss', $username, $hash, $fullName, $role);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    redirect($returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'success=added');

} elseif ($action === 'toggle') {
    $targetId = (int)($_POST['target_user_id'] ?? 0);

    if ($targetId <= 0 || $targetId === $currentUserId) {
        redirect($returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'error=invalid');
    }

    $stmt = mysqli_prepare($conn, 'UPDATE users SET is_active = IF(is_active=1, 0, 1), updated_at = NOW() WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $targetId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    redirect($returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'success=toggled');

} elseif ($action === 'set_password') {
    $targetId    = (int)($_POST['target_user_id'] ?? 0);
    $newPassword = (string)($_POST['new_password'] ?? '');

    if ($targetId <= 0 || $newPassword === '') {
        redirect($returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'error=invalid');
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, 'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'si', $hash, $targetId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    redirect($returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'success=password');

} else {
    redirect('admin_users.php');
}