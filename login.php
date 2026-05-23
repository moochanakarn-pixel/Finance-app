<?php
header('Content-Type: text/html; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(array(
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    session_start();
}

include 'config/db.php';
include 'config/functions.php';

if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, username, password_hash, full_name, role FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                redirect('index.php');
            }
        }

        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <style>
        body{font-family:Tahoma,sans-serif;background:#eef2f7;margin:0;padding:24px}
        .wrap{max-width:420px;margin:60px auto}
        .card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(0,0,0,.08)}
        h1{margin-top:0}
        input{width:100%;padding:12px 14px;margin:8px 0 14px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box}
        button{width:100%;padding:12px 14px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer}
        .err{background:#fef2f2;color:#991b1b;padding:10px;border-radius:10px;margin-bottom:14px}
        .note{color:#6b7280;font-size:14px;margin-top:12px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>เข้าสู่ระบบ</h1>

        <?php if ($error !== ''): ?>
            <div class="err"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <label>ชื่อผู้ใช้</label>
            <input type="text" name="username" required>

            <label>รหัสผ่าน</label>
            <input type="password" name="password" required>

            <button type="submit">เข้าสู่ระบบ</button>
        </form>

        <div class="note"></div>
    </div>
</div>
</body>
</html>
