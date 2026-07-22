<?php
header('Content-Type: text/html; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    $__sp = __DIR__ . '/sessions';
    if (!is_dir($__sp)) @mkdir($__sp, 0700, true);
    session_save_path($__sp);
    ini_set('session.gc_maxlifetime', 86400 * 30);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor',     200);
    session_set_cookie_params(array(
        'lifetime' => 86400 * 30,
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

            // Always run password_verify (even on unknown user) to prevent timing attacks
            $hash = $user ? $user['password_hash'] : '$2y$10$invalidhashpadding000000000000000000000000000000000000000';
            if (password_verify($password, $hash) && $user) {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="finance-icon-dark.svg">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:"Noto Sans Thai",system-ui,sans-serif;
            min-height:100vh;
            background:linear-gradient(135deg,#312e81 0%,#4f46e5 50%,#7c3aed 100%);
            display:flex;align-items:center;justify-content:center;
            padding:1.5rem;
        }
        .card{
            width:100%;max-width:400px;
            background:#fff;border-radius:24px;
            padding:2.25rem 2rem;
            box-shadow:0 24px 60px rgba(49,46,129,.35);
        }
        .logo{display:flex;align-items:center;gap:.75rem;margin-bottom:1.75rem}
        .logo img{width:44px;height:44px;border-radius:12px}
        .logo-text .title{font-size:1.15rem;font-weight:800;color:#0f172a;line-height:1.1}
        .logo-text .sub{font-size:.8rem;color:#64748b;margin-top:2px}
        h2{font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:.35rem}
        .hint{font-size:.88rem;color:#64748b;margin-bottom:1.5rem}
        .field{margin-bottom:1rem}
        label{display:block;font-size:.85rem;font-weight:700;color:#374151;margin-bottom:.4rem}
        input{
            width:100%;padding:.78rem .9rem;
            border:1.5px solid #e0e7ff;border-radius:14px;
            font-size:.98rem;font-family:inherit;color:#0f172a;
            background:#fafbff;outline:none;
            transition:border-color .15s,box-shadow .15s;
        }
        input:focus{border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,.15);background:#fff}
        .btn{
            width:100%;padding:.9rem;
            background:linear-gradient(135deg,#6366f1,#4f46e5);
            color:#fff;border:none;border-radius:14px;
            font-size:1rem;font-weight:800;font-family:inherit;
            cursor:pointer;margin-top:.5rem;
            box-shadow:0 4px 16px rgba(99,102,241,.38);
            transition:opacity .15s,transform .1s;
        }
        .btn:hover{opacity:.92;transform:translateY(-1px)}
        .btn:active{transform:translateY(0);opacity:.85}
        .err{
            background:#fef2f2;color:#991b1b;
            border:1px solid #fecaca;
            padding:.75rem 1rem;border-radius:12px;
            font-size:.9rem;font-weight:700;margin-bottom:1rem;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <img src="finance-icon-dark.svg" alt="Finance App">
        <div class="logo-text">
            <div class="title">Finance App</div>
            <div class="sub">บันทึกรายรับรายจ่าย</div>
        </div>
    </div>

    <h2>เข้าสู่ระบบ</h2>
    <div class="hint">ระบบจำการเข้าสู่ระบบ 30 วัน</div>

    <?php if ($error !== ''): ?>
        <div class="err"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="field">
            <label for="username">ชื่อผู้ใช้</label>
            <input type="text" id="username" name="username" autocomplete="username" autofocus required
                   value="<?php echo isset($_POST['username']) ? h($_POST['username']) : ''; ?>">
        </div>
        <div class="field">
            <label for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn">เข้าสู่ระบบ</button>
    </form>
</div>
</body>
</html>
