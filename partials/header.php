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

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"></noscript>

    <!-- Thai Font (non-render-blocking) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>

    <link href="assets/css/style.css" rel="stylesheet">
    <?php if (!empty($page_css)): ?><link rel="stylesheet" href="<?php echo htmlspecialchars($page_css, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="finance-icon-dark.svg">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Finance">
</head>
<body>

<nav class="navbar navbar-expand-lg app-navbar">
    <div class="container-fluid app-shell px-3 px-lg-4">
        <a class="navbar-brand app-brand d-flex align-items-center gap-2" href="index.php">
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
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'index.php' ? 'active' : ''; ?>" href="index.php"><i class="bi bi-grid-1x2-fill me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'entries.php' ? 'active' : ''; ?>" href="entries.php"><i class="bi bi-journal-text me-1"></i>รายการทั้งหมด</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'add.php' ? 'active' : ''; ?>" href="add.php"><i class="bi bi-plus-circle-fill me-1"></i>เพิ่มรายการ</a></li>
                <li class="nav-item d-lg-none"><a class="nav-link <?php echo $current_file === 'add_mobile.php' ? 'active' : ''; ?>" href="add_mobile.php"><i class="bi bi-phone me-1"></i>เพิ่ม (มือถือ)</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'categories.php' ? 'active' : ''; ?>" href="categories.php"><i class="bi bi-tags-fill me-1"></i>หมวดหมู่</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'report.php' ? 'active' : ''; ?>" href="report.php"><i class="bi bi-bar-chart-fill me-1"></i>รายงาน</a></li>
                <li class="nav-item"><a class="nav-link <?php echo in_array($current_file, ['groups.php', 'group_report.php']) ? 'active' : ''; ?>" href="groups.php" title="กลุ่มรายงาน"><i class="bi bi-collection-fill me-1"></i><span class="d-xl-none">กลุ่ม</span><span class="d-none d-xl-inline">กลุ่มรายงาน</span></a></li>
                <?php if ($role === 'admin'): ?>
                <li class="nav-item"><a class="nav-link <?php echo $current_file === 'admin_users.php' ? 'active' : ''; ?>" href="admin_users.php"><i class="bi bi-people-fill"></i>จัดการ User</a></li>
                <?php endif; ?>
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