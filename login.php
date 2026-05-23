<?php
header('Content-Type: text/html; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
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
            $user   = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
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
    <title>เข้าสู่ระบบ — Finance App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="finance-icon-dark.svg">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

<div class="w-100" style="max-width:420px;padding:1.5rem">
    <div class="text-center mb-4">
        <img src="finance-icon-dark.svg" width="56" height="56" alt="Finance App" style="border-radius:14px">
        <h1 class="mt-3 mb-0 fw-800" style="font-size:1.6rem;font-weight:800">Finance App</h1>
        <p class="text-muted mb-0" style="font-size:.9rem">บันทึกรายรับรายจ่าย</p>
    </div>

    <div class="card-soft p-4">
        <h2 class="mb-4 fw-bold" style="font-size:1.2rem">เข้าสู่ระบบ</h2>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="alert-success-soft mb-3" style="background:#fef9c3;border-color:#fde68a;color:#92400e">
                Session หมดอายุ กรุณาเข้าสู่ระบบใหม่
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert-success-soft mb-3" style="background:#fef2f2;border-color:#fecaca;color:#991b1b">
                <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label fw-600">ชื่อผู้ใช้</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label fw-600">รหัสผ่าน</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold py-2">เข้าสู่ระบบ</button>
        </form>
    </div>
</div>

</body>
</html>
