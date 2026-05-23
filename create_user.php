<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

if (($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    die('ไม่มีสิทธิ์');
}

$msg = '';
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password_hash, full_name, role, is_active, created_at) VALUES (?, ?, ?, 'user', 1, NOW())");
            mysqli_stmt_bind_param($stmt, 'sss', $username, $hash, $fullName);

            if (mysqli_stmt_execute($stmt)) {
                $newUserId = (int)mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                mysqli_query($conn, "
                    INSERT INTO categories (name, import_alias, type, sort_order, is_active, created_at, updated_at, user_id)
                    SELECT name, import_alias, type, sort_order, is_active, NOW(), NULL, {$newUserId}
                    FROM categories
                    WHERE user_id = {$userId}
                      AND is_active = 1
                ");

                $msg = 'สร้างผู้ใช้ใหม่สำเร็จ';
            } else {
                mysqli_stmt_close($stmt);
                $msg = 'สร้างผู้ใช้ไม่สำเร็จ';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มผู้ใช้</title>
    <style>
        body{font-family:Tahoma,sans-serif;background:#eef2f7;margin:0;padding:24px}
        .wrap{max-width:460px;margin:40px auto}
        .card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(0,0,0,.08)}
        input{width:100%;padding:12px 14px;margin:8px 0 14px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box}
        button{width:100%;padding:12px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer}
        .msg{background:#eff6ff;padding:10px;border-radius:10px;margin-bottom:12px}
        a{color:#2563eb;text-decoration:none}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h2>เพิ่มผู้ใช้</h2>
        <?php if ($msg !== ''): ?><div class="msg"><?php echo h($msg); ?></div><?php endif; ?>
        <form method="post" autocomplete="off">
            <label>ชื่อจริง</label>
            <input type="text" name="full_name">
            <label>ชื่อผู้ใช้</label>
            <input type="text" name="username" required>
            <label>รหัสผ่าน</label>
            <input type="password" name="password" required>
            <button type="submit">สร้างผู้ใช้</button>
        </form>
        <p><a href="index.php">กลับหน้า dashboard</a></p>
    </div>
</div>
</body>
</html>
