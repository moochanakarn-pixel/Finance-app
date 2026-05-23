<?php
if (!isset($page_title)) {
    $page_title = 'Finance App';
}
$current_file = basename($_SERVER['PHP_SELF'] ?? '');
$display_name = '';
if (isset($_SESSION['full_name']) && trim((string)$_SESSION['full_name']) !== '') {
    $display_name = trim((string)$_SESSION['full_name']);
} elseif (isset($_SESSION['username'])) {
    $display_name = trim((string)$_SESSION['username']);
}
$role = isset($_SESSION['role']) ? trim((string)$_SESSION['role']) : '';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Thai Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="finance-icon-dark.svg">
</head>
<body>

<nav class="navbar navbar-expand-lg app-navbar">
    <div class="container-fluid app-shell px-3 px-lg-4">
        <a class="navbar-brand app-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-wallet2 fs-4 text-primary" style="display:none"></i>
            <img src="finance-icon-dark.svg" width="36" height="36" alt="Finance App" style="border-radius:9px">
            <div>
                <div class="app-brand-title">Finance App</div>
                <small class="app-brand-subtitle">บันทึกรายรับรายจ่าย</small>
            </div>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1 mb-3 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'index.php' ? 'active' : ''; ?>" href="index.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'entries.php' ? 'active' : ''; ?>" href="entries.php">รายการทั้งหมด</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'add.php' ? 'active' : ''; ?>" href="add.php">เพิ่มรายการ</a></li>
                <li class="nav-item d-lg-none"><a class="nav-link <?php echo $current_file === 'add_mobile.php' ? 'active' : ''; ?>" href="add_mobile.php">เพิ่ม (มือถือ)</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'categories.php' ? 'active' : ''; ?>" href="categories.php">หมวดหมู่</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'report.php' ? 'active' : ''; ?>" href="report.php">รายงาน</a></li>
            </ul>

            <?php if ($display_name !== ''): ?>
                <div class="app-user-chip ms-lg-3 d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle"></i>
                    <span><?php echo htmlspecialchars($display_name); ?></span>
                    <a href="logout.php" class="app-user-logout">ออกจากระบบ</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container-fluid app-shell px-3 px-lg-4 py-3 py-lg-4">
