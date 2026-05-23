<?php
header('Content-Type: text/html; charset=UTF-8');
include 'config/db.php';
include 'config/functions.php';

$msg = '';
$adminExists = false;
$checkAdmin = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if ($checkAdmin && mysqli_num_rows($checkAdmin) > 0) {
    $adminExists = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');

    if ($username === '' || $password === '') {
        $msg = 'กรอกข้อมูลไม่ครบ';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt);
        $hasUser = $exists && mysqli_num_rows($exists) > 0;
        mysqli_stmt_close($stmt);

        if ($hasUser) {
            $msg = 'มีชื่อผู้ใช้นี้แล้ว';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password_hash, full_name, role, is_active, created_at) VALUES (?, ?, ?, 'admin', 1, NOW())");
            mysqli_stmt_bind_param($stmt, 'sss', $username, $hash, $fullName);

            if (mysqli_stmt_execute($stmt)) {
                $userId = (int)mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                mysqli_query($conn, "UPDATE categories SET user_id = {$userId} WHERE user_id IS NULL");
                mysqli_query($conn, "UPDATE entries SET user_id = {$userId} WHERE user_id IS NULL");
                $msg = 'สร้าง admin สำเร็จ และย้ายข้อมูลเดิมให้แล้ว';
                $adminExists = true;
            } else {
                mysqli_stmt_close($stmt);
                $msg = 'สร้าง admin ไม่สำเร็จ';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สร้างผู้ใช้แรก</title>
    <style>
        body{font-family:Tahoma,sans-serif;background:#eef2f7;margin:0;padding:24px}
        .wrap{max-width:460px;margin:40px auto}
        .card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(0,0,0,.08)}
        input{width:100%;padding:12px 14px;margin:8px 0 14px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box}
        button{width:100%;padding:12px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer}
        .msg{background:#eff6ff;padding:10px;border-radius:10px;margin-bottom:12px}
        .warn{background:#fff7ed;color:#9a3412;padding:10px;border-radius:10px;margin-bottom:12px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h2>สร้างผู้ใช้แรก</h2>

        <?php if ($msg !== ''): ?>
            <div class="msg"><?php echo h($msg); ?></div>
        <?php endif; ?>

        <?php if ($adminExists): ?>
            <div class="warn">ระบบมีผู้ดูแลอยู่แล้ว ควรลบหรือเปลี่ยนชื่อไฟล์นี้หลังติดตั้งเสร็จ</div>
            <p><a href="login.php">ไปหน้าเข้าสู่ระบบ</a></p>
        <?php else: ?>
            <form method="post" autocomplete="off">
                <label>ชื่อจริง</label>
                <input type="text" name="full_name">

                <label>ชื่อผู้ใช้</label>
                <input type="text" name="username" required>

                <label>รหัสผ่าน</label>
                <input type="password" name="password" required>

                <button type="submit">สร้างผู้ใช้</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
