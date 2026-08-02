<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(0);
ini_set('display_errors', 0);

include 'auth.php';
include 'config/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$fullName = isset($_SESSION['full_name']) ? trim((string)$_SESSION['full_name']) : '';
$username = isset($_SESSION['username']) ? trim((string)$_SESSION['username']) : '';
$role = isset($_SESSION['role']) ? trim((string)$_SESSION['role']) : 'user';

function h($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function baht($amount) {
    return '฿' . number_format((float)$amount, 2);
}

function query_or_die($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        die('SQL Error: ' . h(mysqli_error($conn)));
    }
    return $result;
}

$thaiMonths = array(
    1 => 'ม.ค.',
    2 => 'ก.พ.',
    3 => 'มี.ค.',
    4 => 'เม.ย.',
    5 => 'พ.ค.',
    6 => 'มิ.ย.',
    7 => 'ก.ค.',
    8 => 'ส.ค.',
    9 => 'ก.ย.',
    10 => 'ต.ค.',
    11 => 'พ.ย.',
    12 => 'ธ.ค.'
);

$typeLabels = array(
    'income' => 'รายรับ',
    'saving' => 'เงินออม',
    'expense' => 'รายจ่าย'
);

$typeColors = array(
    'income' => 'green',
    'saving' => 'blue',
    'expense' => 'red'
);

$requestedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
if ($requestedYear > 2400) {
    $selectedBE = $requestedYear;
    $selectedAD = $requestedYear - 543;
} elseif ($requestedYear > 1900) {
    $selectedAD = $requestedYear;
    $selectedBE = $requestedYear + 543;
} else {
    $selectedAD = (int)date('Y');
    $selectedBE = $selectedAD + 543;
}

$yearOptions = array();
$rsYears = query_or_die($conn, "
    SELECT DISTINCT YEAR(entry_date) AS y
    FROM entries
    WHERE user_id = {$userId}
    ORDER BY y DESC
");
while ($row = mysqli_fetch_assoc($rsYears)) {
    $adYear = (int)$row['y'];
    $yearOptions[] = array(
        'ad' => $adYear,
        'be' => $adYear + 543
    );
}
if (empty($yearOptions)) {
    $yearOptions[] = array('ad' => $selectedAD, 'be' => $selectedBE);
}

$summary = array(
    'income' => 0,
    'expense' => 0,
    'saving' => 0
);

$yearStart = $selectedAD . '-01-01';
$yearEnd   = $selectedAD . '-12-31';

$rsSummary = query_or_die($conn, "
    SELECT c.type, SUM(e.amount) AS total_amount
    FROM entries e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE e.entry_date BETWEEN '{$yearStart}' AND '{$yearEnd}'
      AND e.user_id = {$userId}
      AND c.user_id = {$userId}
      AND c.is_active = 1
    GROUP BY c.type
");
while ($row = mysqli_fetch_assoc($rsSummary)) {
    $type = (string)$row['type'];
    if (isset($summary[$type])) {
        $summary[$type] = (float)$row['total_amount'];
    }
}
$balance = $summary['income'] - $summary['expense'] - $summary['saving'];

$latestEntry = null;
$latestEntries = array();
$rsLatestEntry = query_or_die($conn, "
    SELECT e.id, e.entry_date, e.amount, e.note, c.name AS category_name, c.type AS category_type
    FROM entries e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE e.user_id = {$userId}
      AND c.user_id = {$userId}
      AND c.is_active = 1
    ORDER BY e.id DESC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($rsLatestEntry)) {
    $latestEntries[] = $row;
}
if (!empty($latestEntries)) {
    $latestEntry = $latestEntries[0];
}

$currentMonthStart = date('Y-m-01');
$currentMonthEnd   = date('Y-m-t');
$currentMonthLabel = $thaiMonths[(int)date('n')] . ' ' . ((int)date('Y') + 543);
$currentMonthEntries = array();
$rsCurrentMonth = query_or_die($conn, "
    SELECT e.id, e.entry_date, e.amount, e.note, c.name AS category_name, c.type AS category_type
    FROM entries e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE e.user_id = {$userId}
      AND c.user_id = {$userId}
      AND c.is_active = 1
      AND e.entry_date BETWEEN '{$currentMonthStart}' AND '{$currentMonthEnd}'
    ORDER BY e.id DESC
    LIMIT 10
");
while ($row = mysqli_fetch_assoc($rsCurrentMonth)) {
    $currentMonthEntries[] = $row;
}

// Today's summary
$today = date('Y-m-d');
$todaySummary = array('income' => 0, 'expense' => 0, 'saving' => 0);
$rsTodaySum = mysqli_query($conn, "
    SELECT c.type, SUM(e.amount) AS t
    FROM entries e INNER JOIN categories c ON e.category_id = c.id
    WHERE e.entry_date = '{$today}'
      AND e.user_id = {$userId}
      AND c.user_id = {$userId}
      AND c.is_active = 1
    GROUP BY c.type
");
if ($rsTodaySum) {
    while ($r = mysqli_fetch_assoc($rsTodaySum)) {
        if (isset($todaySummary[$r['type']])) $todaySummary[$r['type']] = (float)$r['t'];
    }
}
$todayNet = $todaySummary['income'] - $todaySummary['expense'] - $todaySummary['saving'];
$todayHasData = $todaySummary['income'] > 0 || $todaySummary['expense'] > 0 || $todaySummary['saving'] > 0;
$todayDateBE = date('j') . '/' . date('n') . '/' . ((int)date('Y') + 543);

// Budget progress: categories with budget_amount > 0 + their spending this month
$budgetProgress = array();
$rsBudget = mysqli_query($conn, "
    SELECT c.id, c.name, c.type, c.budget_amount,
           COALESCE(SUM(e.amount), 0) AS spent
    FROM categories c
    LEFT JOIN entries e ON e.category_id = c.id
        AND e.user_id = {$userId}
        AND e.entry_date BETWEEN '{$currentMonthStart}' AND '{$currentMonthEnd}'
    WHERE c.user_id = {$userId}
      AND c.is_active = 1
      AND c.budget_amount > 0
    GROUP BY c.id
    ORDER BY spent DESC
    LIMIT 10
");
if ($rsBudget) {
    while ($row = mysqli_fetch_assoc($rsBudget)) {
        $budgetProgress[] = $row;
    }
}

$categories = array(
    'income' => array(),
    'saving' => array(),
    'expense' => array()
);
$rsCategories = query_or_die($conn, "
    SELECT id, name, type, sort_order, budget_amount
    FROM categories
    WHERE is_active = 1
      AND user_id = {$userId}
    ORDER BY FIELD(type,'income','saving','expense'), sort_order ASC, id ASC
");
while ($row = mysqli_fetch_assoc($rsCategories)) {
    $type = (string)$row['type'];
    if (!isset($categories[$type])) {
        $categories[$type] = array();
    }
    $categories[$type][] = $row;
}

$amountMap    = array();
$yearTotalMap = array();
$monthly      = array();
for ($m = 1; $m <= 12; $m++) {
    $monthly[$m] = array('income' => 0, 'expense' => 0, 'saving' => 0);
}

$rsCombined = query_or_die($conn, "
    SELECT
        e.category_id,
        c.type          AS category_type,
        MONTH(e.entry_date) AS month_no,
        SUM(e.amount)   AS total_amount
    FROM entries e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE e.entry_date BETWEEN '{$yearStart}' AND '{$yearEnd}'
      AND e.user_id = {$userId}
      AND c.user_id = {$userId}
      AND c.is_active = 1
    GROUP BY e.category_id, c.type, MONTH(e.entry_date)
");
while ($row = mysqli_fetch_assoc($rsCombined)) {
    $cid     = (int)$row['category_id'];
    $monthNo = (int)$row['month_no'];
    $amount  = (float)$row['total_amount'];
    $type    = (string)$row['category_type'];

    if (!isset($amountMap[$cid]))    $amountMap[$cid]    = array();
    if (!isset($yearTotalMap[$cid])) $yearTotalMap[$cid] = 0;
    $amountMap[$cid][$monthNo] = $amount;
    $yearTotalMap[$cid]       += $amount;

    if (isset($monthly[$monthNo][$type])) {
        $monthly[$monthNo][$type] += $amount;
    }
}

$chartLabels = array_values($thaiMonths);
$chartIncome = array();
$chartExpense = array();
$chartSaving = array();
$monthlyNet = array();

for ($m = 1; $m <= 12; $m++) {
    $chartIncome[] = $monthly[$m]['income'];
    $chartExpense[] = $monthly[$m]['expense'];
    $chartSaving[] = $monthly[$m]['saving'];
    $monthlyNet[] = $monthly[$m]['income'] - $monthly[$m]['expense'] - $monthly[$m]['saving'];
}

$totalCategories = count($categories['income']) + count($categories['saving']) + count($categories['expense']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance App</title>
    <link rel="icon" type="image/svg+xml" href="finance-icon-dark.svg">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Finance">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"></noscript>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<div class="topbar">
    <div class="topbar-inner">
        <div class="brand-wrap">
            <div class="brand">🧾 Budget Tracker</div>
        </div>

        <div class="nav">
            <span class="nav-user"><?php echo h($fullName !== '' ? $fullName : $username); ?></span>
            <a class="nav-link" href="entries.php?year=<?php echo (int)$selectedBE; ?>">รายการ</a>
            <a class="nav-link" href="report.php">รายงาน</a>
            <a class="nav-link" href="categories.php">หมวดหมู่</a>
            <a class="nav-link" href="groups.php">กลุ่ม</a>
            <a class="nav-link primary" href="add.php">+ เพิ่มรายการ</a>
            <a class="nav-link" href="logout.php">ออกจากระบบ</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="hero">
        <div>
            <h1 class="hero-title">ภาพรวมปี <?php echo h($selectedBE); ?></h1>
            <div class="hero-meta">
                <span class="chip">หมวดทั้งหมด <?php echo (int)$totalCategories; ?> หมวด</span>
                <span class="chip">ฐานข้อมูลปี ค.ศ. <?php echo (int)$selectedAD; ?></span>
                <span class="chip">เงินคงเหลือ <?php echo h(baht($balance)); ?></span>
            </div>
            <?php
            $inc = $summary['income'];
            $exp = $summary['expense'];
            $sav = $summary['saving'];
            $expPct = $inc > 0 ? min(100, round($exp / $inc * 100)) : 0;
            $savPct = $inc > 0 ? min(100, round($sav / $inc * 100)) : 0;
            $netPct = $inc > 0 ? min(100, max(0, round(($inc - $exp - $sav) / $inc * 100))) : 0;
            ?>
            <div class="progress-section">
                <div class="progress-row">
                    <div class="progress-label">รายจ่าย</div>
                    <div class="progress-track"><div class="progress-fill" style="width:<?php echo $expPct; ?>%;background:#dc2626"></div></div>
                    <div class="progress-pct red"><?php echo $expPct; ?>%</div>
                </div>
                <div class="progress-row">
                    <div class="progress-label">เงินออม</div>
                    <div class="progress-track"><div class="progress-fill" style="width:<?php echo $savPct; ?>%;background:#7c3aed"></div></div>
                    <div class="progress-pct purple"><?php echo $savPct; ?>%</div>
                </div>
                <div class="progress-row">
                    <div class="progress-label">คงเหลือ</div>
                    <div class="progress-track"><div class="progress-fill" style="width:<?php echo $netPct; ?>%;background:#15803d"></div></div>
                    <div class="progress-pct green"><?php echo $netPct; ?>%</div>
                </div>
            </div>
        </div>

        <div class="hero-side">
            <div class="mini-card">
                <div class="mini-label">รายรับเฉลี่ยต่อเดือน</div>
                <div class="mini-value green"><?php echo baht($summary['income'] / 12); ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">รายจ่ายเฉลี่ยต่อเดือน</div>
                <div class="mini-value red"><?php echo baht($summary['expense'] / 12); ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">เงินออมเฉลี่ยต่อเดือน</div>
                <div class="mini-value blue"><?php echo baht($summary['saving'] / 12); ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">สุทธิ์เฉลี่ยต่อเดือน</div>
                <div class="mini-value purple"><?php echo baht($balance / 12); ?></div>
            </div>
        </div>
    </div>

    <div class="section-heading"><i class="bi bi-bar-chart-line-fill"></i> สรุปยอดทั้งปี พ.ศ. <?php echo h($selectedBE); ?></div>
    <div class="cards">
        <div class="metric-card">
            <div class="label">รายรับรวมปี <?php echo h($selectedBE); ?></div>
            <div class="value green"><?php echo baht($summary['income']); ?></div>
        </div>
        <div class="metric-card">
            <div class="label">รายจ่ายรวม</div>
            <div class="value red"><?php echo baht($summary['expense']); ?></div>
        </div>
        <div class="metric-card">
            <div class="label">เงินออมรวม</div>
            <div class="value blue"><?php echo baht($summary['saving']); ?></div>
        </div>
        <div class="metric-card">
            <div class="label">เงินคงเหลือ</div>
            <div class="value purple"><?php echo baht($balance); ?></div>
        </div>
    </div>

    <div class="section-heading"><i class="bi bi-calendar-day-fill"></i> สรุปวันนี้ — <?php echo h($todayDateBE); ?></div>
    <?php if ($todayHasData): ?>
    <div class="cards">
        <div class="metric-card">
            <div class="label">รายรับวันนี้</div>
            <div class="value green"><?php echo baht($todaySummary['income']); ?></div>
        </div>
        <div class="metric-card">
            <div class="label">รายจ่ายวันนี้</div>
            <div class="value red"><?php echo baht($todaySummary['expense']); ?></div>
        </div>
        <div class="metric-card">
            <div class="label">เงินออมวันนี้</div>
            <div class="value blue"><?php echo baht($todaySummary['saving']); ?></div>
        </div>
        <div class="metric-card">
            <div class="label">สุทธิวันนี้</div>
            <div class="value <?php echo $todayNet >= 0 ? 'green' : 'red'; ?>">
                <?php echo ($todayNet >= 0 ? '+฿' : '-฿') . number_format(abs($todayNet), 2); ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="panel" style="text-align:center;color:#94a3b8;padding:.9rem 1.5rem;font-size:.9rem">
        ยังไม่มีรายการวันนี้ — <a href="add_mobile.php" style="color:#6366f1;font-weight:700;text-decoration:none">+ เพิ่มรายการ</a>
    </div>
    <?php endif; ?>

    <div class="section-heading"><i class="bi bi-funnel-fill"></i> ตัวกรอง &amp; ทางลัด</div>
    <div class="panel filter-panel">
        <form method="get" class="filter-row">
            <div class="filter-title-inline">ตัวกรองและทางลัด</div>
            <div class="form-group">
                <label for="year">เลือกปี</label>
                <select name="year" id="year" onchange="this.form.submit()">
                    <?php foreach ($yearOptions as $yearItem): ?>
                        <option value="<?php echo (int)$yearItem['be']; ?>" <?php echo ($selectedBE === (int)$yearItem['be']) ? 'selected' : ''; ?>>
                            <?php echo h($yearItem['be']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">แสดงข้อมูล</button>
            <a href="add.php" class="btn btn-success">+ เพิ่มรายการ</a>
            <a href="entries.php?year=<?php echo (int)$selectedBE; ?>" class="btn btn-outline">ดูรายการทั้งหมด</a>
            <a href="categories.php" class="btn btn-outline">จัดการหมวดหมู่</a>
        </form>
    </div>

    <div class="section-heading"><i class="bi bi-lightning-charge-fill"></i> เพิ่มรายการด่วน</div>
    <div class="panel quick-add-panel">
        <?php
        $allCats = array_merge($categories['income'], $categories['saving'], $categories['expense']);
        $typeLabelsQA = ['income' => 'รายรับ', 'saving' => 'ออม', 'expense' => 'รายจ่าย'];
        ?>
        <form action="save_entry.php" method="post" class="quick-add-form" id="quick-add-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="year_be" value="<?php echo (int)$selectedBE; ?>">
            <input type="hidden" name="return_url" value="index.php?year=<?php echo (int)$selectedBE; ?>">
            <select name="category_id" required>
                <option value="">เพิ่มรายการด่วน — เลือกหมวด...</option>
                <?php foreach (['income','saving','expense'] as $t): ?>
                    <?php foreach ($categories[$t] as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>">[<?php echo h($typeLabelsQA[$t]); ?>] <?php echo h($cat['name']); ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <input type="number" name="amount" min="0.01" step="0.01" placeholder="จำนวนเงิน (บาท)" required>
            <input type="date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required>
            <input type="text" name="note" placeholder="หมายเหตุ (ไม่บังคับ)">
            <button type="submit" class="btn btn-success">+ บันทึกเลย</button>
        </form>
        <div class="quick-add-saved" style="display:none;margin-top:8px;color:#15803d;font-size:13px;font-weight:700">✓ บันทึกสำเร็จแล้ว</div>
        <div id="quick-add-error" style="display:none;margin-top:8px;color:#dc2626;font-size:13px;font-weight:700">⚠ กรุณาเลือกหมวดหมู่และกรอกจำนวนเงินให้ถูกต้อง</div>
    </div>

    <div class="section-heading"><i class="bi bi-table"></i> ตารางงบประมาณ &amp; ภาพรวม</div>
    <div class="layout">
        <div class="panel budget-panel">
            <div class="panel-header budget-panel-header">
                <div>
                    <h2 class="section-title">ตารางงบประมาณรายปี</h2>
                </div>
            </div>

            <div class="table-wrap">
                <table class="budget-table">
                    <thead>
                        <tr>
                            <th class="sticky-col text-left">รายการ</th>
                            <?php
                            $currentMonth = (int)date('n');
                            $isCurrentYear = ($selectedAD === (int)date('Y'));
                            for ($m = 1; $m <= 12; $m++):
                                $isNow = $isCurrentYear && ($m === $currentMonth);
                            ?>
                                <th class="month-col <?php echo $isNow ? 'month-current-head' : ''; ?>"><?php echo h($thaiMonths[$m]); ?><?php echo $isNow ? ' ▸' : ''; ?></th>
                            <?php endfor; ?>
                            <th class="year-col">รวมทั้งปี</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array('income', 'saving', 'expense') as $type): ?>
                        <?php
                        $groupMonthlyTotals = array();
                        for ($m = 1; $m <= 12; $m++) {
                            $groupMonthlyTotals[$m] = 0;
                        }
                        $groupYearTotal = 0;
                        ?>
                        <tr class="group-row">
                            <td class="sticky-col text-left" colspan="14"><?php echo h($typeLabels[$type]); ?></td>
                        </tr>

                        <?php if (!empty($categories[$type])): ?>
                            <?php foreach ($categories[$type] as $cat): ?>
                                <?php
                                $catId = (int)$cat['id'];
                                $rowYearTotal = isset($yearTotalMap[$catId]) ? (float)$yearTotalMap[$catId] : 0;
                                $groupYearTotal += $rowYearTotal;
                                ?>
                                <tr>
                                    <td class="sticky-col text-left category-cell">
                                        <span
                                            class="editable-category js-edit-category"
                                            data-category-id="<?php echo $catId; ?>"
                                            data-category-name="<?php echo h($cat['name']); ?>"
                                        >
                                            <?php echo h($cat['name']); ?> ✏️
                                        </span>
                                    </td>

                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <?php
                                        $value  = isset($amountMap[$catId][$m]) ? (float)$amountMap[$catId][$m] : 0;
                                        $groupMonthlyTotals[$m] += $value;
                                        $isNowCell = $isCurrentYear && ($m === $currentMonth);
                                        ?>
                                        <td class="<?php echo $isNowCell ? 'month-current' : ''; ?>">
                                            <button
                                                type="button"
                                                class="amount-link js-open-detail"
                                                data-category-id="<?php echo $catId; ?>"
                                                data-category-name="<?php echo h($cat['name']); ?>"
                                                data-month="<?php echo (int)$m; ?>"
                                                data-month-label="<?php echo h($thaiMonths[$m]); ?>"
                                                data-year="<?php echo (int)$selectedBE; ?>"
                                            >
                                                <?php echo $value > 0 ? baht($value) : '<span class="muted">-</span>'; ?>
                                            </button>
                                        </td>
                                    <?php endfor; ?>

                                    <td class="year-total-col">
                                        <?php echo $rowYearTotal > 0 ? baht($rowYearTotal) : '<span class="muted">-</span>'; ?>
                                        <?php
                                        $budgetAnnual = (float)$cat['budget_amount'] * 12;
                                        if ($budgetAnnual > 0):
                                            $pct = min(100, $rowYearTotal > 0 ? (int)round($rowYearTotal / $budgetAnnual * 100) : 0);
                                            $barColor = $pct >= 100 ? '#ef4444' : ($pct >= 80 ? '#f59e0b' : '#10b981');
                                        ?>
                                        <div style="margin-top:5px;height:4px;background:#e5e7eb;border-radius:2px;min-width:50px">
                                            <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $barColor; ?>;border-radius:2px"></div>
                                        </div>
                                        <div style="font-size:10px;color:#94a3b8;margin-top:2px"><?php echo $pct; ?>% จาก <?php echo baht($budgetAnnual); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr class="group-total-row">
                                <td class="sticky-col text-left category-cell">รวมรายเดือน</td>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <td><?php echo $groupMonthlyTotals[$m] > 0 ? baht($groupMonthlyTotals[$m]) : '<span class="muted">-</span>'; ?></td>
                                <?php endfor; ?>
                                <td class="year-total-col"><?php echo $groupYearTotal > 0 ? baht($groupYearTotal) : '<span class="muted">-</span>'; ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="sticky-col text-left muted category-cell" colspan="14">ยังไม่มีข้อมูลหมวดหมู่</td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                    <?php
                    $netMonthly = [];
                    $netYear = 0;
                    for ($m = 1; $m <= 12; $m++) {
                        $n = $monthly[$m]['income'] - $monthly[$m]['expense'] - $monthly[$m]['saving'];
                        $netMonthly[$m] = $n;
                        $netYear += $n;
                    }
                    ?>
                    <tfoot>
                        <tr class="net-row">
                            <td class="sticky-col text-left category-cell" style="background:#f0fdf4;font-weight:800">สุทธิ์รายเดือน</td>
                            <?php for ($m = 1; $m <= 12; $m++):
                                $n = $netMonthly[$m];
                                $cls = $n > 0 ? 'is-pos' : ($n < 0 ? 'is-neg' : 'is-zero');
                                $isNowFoot = $isCurrentYear && ($m === $currentMonth);
                            ?>
                                <td class="<?php echo $cls . ($isNowFoot ? ' month-current' : ''); ?>">
                                    <?php echo $n != 0 ? ($n > 0 ? '+' : '') . number_format($n, 0) : '<span class="muted">-</span>'; ?>
                                </td>
                            <?php endfor; ?>
                            <td class="year-total-col <?php echo $netYear > 0 ? 'is-pos' : ($netYear < 0 ? 'is-neg' : 'is-zero'); ?>" style="font-weight:800">
                                <?php echo ($netYear > 0 ? '+' : '') . number_format($netYear, 0); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="footer-note">
                ปีที่แสดง: พ.ศ. <?php echo h($selectedBE); ?> และฐานข้อมูลเก็บเป็น ค.ศ. <?php echo h($selectedAD); ?>
            </div>
        </div>

        <div class="right-stack">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="section-title">กราฟภาพรวมรายเดือน</h2>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <div class="panel latest-entry-card">
                <div class="panel-header">
                    <div>
                        <h2 class="section-title">รายการล่าสุดที่เพิ่ม</h2>
                        <div class="subtle">แสดง 5 รายการล่าสุดที่บันทึกเข้าไป</div>
                    </div>
                </div>
                <?php if ($latestEntry): ?>
                    <div class="latest-entry-top">
                        <div>
                            <div class="latest-entry-label">รายการล่าสุด</div>
                            <div class="latest-entry-name"><?php echo h($latestEntry['category_name']); ?></div>
                        </div>
                        <div class="latest-entry-amount <?php echo h($typeColors[$latestEntry['category_type']]); ?>"><?php echo baht($latestEntry['amount']); ?></div>
                    </div>
                    <div class="latest-entry-meta">
                        <div class="latest-entry-meta-item">
                            <span class="meta-key">วันที่</span>
                            <span class="meta-value"><?php echo h(date('d/m/', strtotime($latestEntry['entry_date'])) . ((int)date('Y', strtotime($latestEntry['entry_date'])) + 543)); ?></span>
                        </div>
                        <div class="latest-entry-meta-item">
                            <span class="meta-key">ประเภท</span>
                            <span class="meta-value"><span class="badge-soft <?php echo h($latestEntry['category_type']); ?>"><?php echo h($typeLabels[$latestEntry['category_type']]); ?></span></span>
                        </div>
                        <div class="latest-entry-meta-item">
                            <span class="meta-key">หมายเหตุ</span>
                            <span class="meta-value"><?php echo trim((string)$latestEntry['note']) !== '' ? h($latestEntry['note']) : '-'; ?></span>
                        </div>
                    </div>

                    <?php if (count($latestEntries) > 1): ?>
                        <div class="latest-list">
                            <?php foreach ($latestEntries as $index => $item): ?>
                                <?php if ($index === 0) { continue; } ?>
                                <div class="latest-item">
                                    <div class="latest-item-main">
                                        <div class="latest-item-title"><?php echo h($item['category_name']); ?></div>
                                        <div class="latest-item-sub">
                                            <span><?php echo h(date('d/m/', strtotime($item['entry_date'])) . ((int)date('Y', strtotime($item['entry_date'])) + 543)); ?></span>
                                            <span class="badge-soft <?php echo h($item['category_type']); ?>"><?php echo h($typeLabels[$item['category_type']]); ?></span>
                                        </div>
                                        <?php if (trim((string)$item['note']) !== ''): ?>
                                            <div class="latest-item-note">หมายเหตุ: <?php echo h($item['note']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="latest-item-amount <?php echo h($typeColors[$item['category_type']]); ?>"><?php echo baht($item['amount']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="muted">ยังไม่มีรายการล่าสุด</div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="section-title">สรุปแบบเร็ว</h2>
                    </div>
                </div>
                <div class="summary-list">
                    <div class="summary-item">
                        <span class="dot green"></span>
                        <div class="name">รายรับรวมทั้งปี</div>
                        <div class="amount green"><?php echo baht($summary['income']); ?></div>
                    </div>
                    <div class="summary-item">
                        <span class="dot red"></span>
                        <div class="name">รายจ่ายรวมทั้งปี</div>
                        <div class="amount red"><?php echo baht($summary['expense']); ?></div>
                    </div>
                    <div class="summary-item">
                        <span class="dot blue"></span>
                        <div class="name">เงินออมรวมทั้งปี</div>
                        <div class="amount blue"><?php echo baht($summary['saving']); ?></div>
                    </div>
                    <div class="summary-item">
                        <span class="dot" style="background: var(--purple);"></span>
                        <div class="name">สุทธิ์ทั้งปี</div>
                        <div class="amount purple"><?php echo baht($balance); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($budgetProgress)): ?>
    <div class="section-heading"><i class="bi bi-piggy-bank-fill"></i> ติดตามงบประมาณเดือนนี้</div>
    <div class="panel" style="margin-bottom:12px">
        <div class="panel-header">
            <div>
                <h2 class="section-title">งบประมาณเดือน<?php echo h($currentMonthLabel); ?></h2>
                <div class="subtle">ติดตามการใช้จ่ายเทียบกับงบที่ตั้งไว้</div>
            </div>
            <a href="categories.php" class="btn btn-outline" style="font-size:13px">แก้ไขงบ</a>
        </div>
        <div style="display:grid;gap:10px">
            <?php foreach ($budgetProgress as $bp):
                $spent = (float)$bp['spent'];
                $budget = (float)$bp['budget_amount'];
                $pct = $budget > 0 ? min(100, round($spent / $budget * 100)) : 0;
                $remaining = $budget - $spent;
                $barColor = $pct >= 100 ? '#ef4444' : ($pct >= 80 ? '#f59e0b' : '#6366f1');
                $bgColor  = $pct >= 100 ? '#fee2e2' : ($pct >= 80 ? '#fef3c7' : '#eef2ff');
            ?>
            <div style="background:<?php echo $bgColor; ?>;border-radius:14px;padding:12px 14px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;gap:8px">
                    <div>
                        <span style="font-weight:800;font-size:.93rem;color:#0f172a"><?php echo h($bp['name']); ?></span>
                        <?php if ($pct >= 100): ?>
                        <span style="background:#ef4444;color:#fff;font-size:.7rem;font-weight:700;padding:2px 7px;border-radius:99px;margin-left:6px">เกินงบ!</span>
                        <?php elseif ($pct >= 80): ?>
                        <span style="background:#f59e0b;color:#fff;font-size:.7rem;font-weight:700;padding:2px 7px;border-radius:99px;margin-left:6px">ใกล้เต็ม</span>
                        <?php endif; ?>
                    </div>
                    <div style="text-align:right;flex-shrink:0">
                        <span style="font-size:.82rem;color:#64748b">฿<?php echo number_format($spent, 0); ?> / ฿<?php echo number_format($budget, 0); ?></span>
                    </div>
                </div>
                <div style="background:rgba(0,0,0,.08);border-radius:99px;height:8px;overflow:hidden">
                    <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $barColor; ?>;border-radius:99px;transition:width .4s"></div>
                </div>
                <div style="font-size:.78rem;color:#64748b;margin-top:5px">
                    <?php if ($remaining >= 0): ?>เหลือ ฿<?php echo number_format($remaining, 0); ?> (<?php echo 100 - $pct; ?>%)
                    <?php else: ?>เกินงบ ฿<?php echo number_format(abs($remaining), 0); ?><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="section-heading"><i class="bi bi-clock-history"></i> รายการล่าสุดเดือนนี้</div>
    <div class="panel" style="margin-bottom:12px">
        <div class="panel-header">
            <div>
                <h2 class="section-title">รายการล่าสุดในเดือน<?php echo h($currentMonthLabel); ?></h2>
                <div class="subtle">แสดง 10 รายการล่าสุดที่บันทึกในเดือนนี้</div>
            </div>
            <a href="entries.php?year=<?php echo (int)$selectedBE; ?>&month=<?php echo (int)date('n'); ?>" class="btn btn-outline" style="font-size:13px">ดูทั้งหมดของเดือนนี้</a>
        </div>
        <?php if (!empty($currentMonthEntries)): ?>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13.5px">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                            <th style="padding:9px 12px;text-align:left;font-weight:800;color:#64748b;white-space:nowrap">วันที่</th>
                            <th style="padding:9px 12px;text-align:left;font-weight:800;color:#64748b">หมวดหมู่</th>
                            <th style="padding:9px 12px;text-align:left;font-weight:800;color:#64748b">ประเภท</th>
                            <th style="padding:9px 12px;text-align:right;font-weight:800;color:#64748b;white-space:nowrap">จำนวนเงิน</th>
                            <th style="padding:9px 12px;text-align:left;font-weight:800;color:#64748b">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentMonthEntries as $item):
                            $color = $item['category_type'] === 'income' ? '#059669' : ($item['category_type'] === 'expense' ? '#dc2626' : '#7c3aed');
                            $bgBadge = $item['category_type'] === 'income' ? '#dcfce7' : ($item['category_type'] === 'expense' ? '#fee2e2' : '#ede9fe');
                            $colorBadge = $item['category_type'] === 'income' ? '#166534' : ($item['category_type'] === 'expense' ? '#991b1b' : '#6d28d9');
                            $note = trim((string)$item['note']);
                        ?>
                        <tr style="border-bottom:1px solid #f1f5f9">
                            <td style="padding:9px 12px;white-space:nowrap;color:#64748b"><?php echo h(date('d/m/', strtotime($item['entry_date'])) . ((int)date('Y', strtotime($item['entry_date'])) + 543)); ?></td>
                            <td style="padding:9px 12px;font-weight:700"><?php echo h($item['category_name']); ?></td>
                            <td style="padding:9px 12px">
                                <span style="background:<?php echo $bgBadge; ?>;color:<?php echo $colorBadge; ?>;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700">
                                    <?php echo h($typeLabels[$item['category_type']]); ?>
                                </span>
                            </td>
                            <td style="padding:9px 12px;text-align:right;font-weight:800;color:<?php echo $color; ?>;white-space:nowrap"><?php echo baht($item['amount']); ?></td>
                            <td style="padding:9px 12px;color:<?php echo $note !== '' ? '#334155' : '#94a3b8'; ?>"><?php echo $note !== '' ? h($note) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="muted" style="padding:12px 0">ยังไม่มีรายการในเดือน<?php echo h($currentMonthLabel); ?></div>
        <?php endif; ?>
    </div>
</div>

<div id="categoryModal" class="modal">
    <div class="modal-dialog small">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">แก้ชื่อหัวข้อ</h3>
            </div>
            <button type="button" class="modal-close" id="closeCategoryModalBtn">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="save_category.php" class="inline-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="category_id" id="category_id">
                <input type="hidden" name="return_year" value="<?php echo (int)$selectedBE; ?>">

                <div class="inline-row">
                    <label for="category_name">ชื่อหมวด</label>
                    <input type="text" name="category_name" id="category_name" required>
                </div>

                <div class="entry-actions">
                    <button type="submit" class="btn btn-primary">บันทึกชื่อใหม่</button>
                    <button type="button" class="btn btn-outline" id="cancelCategoryBtn">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="detailModal" class="modal">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3 id="modalTitle" class="modal-title">รายละเอียดรายการ</h3>
                <div id="modalSubtitle" class="modal-subtitle"></div>
            </div>
            <button type="button" class="modal-close" id="closeModalBtn">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="loading">กำลังโหลดข้อมูล...</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.min.js"></script>
<script>
(function () {
    const chartCanvas = document.getElementById('monthlyChart');
    if (chartCanvas && typeof Chart !== 'undefined') {
        new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [
                    {
                        label: 'รายรับ',
                        data: <?php echo json_encode($chartIncome); ?>,
                        backgroundColor: 'rgba(5, 150, 105, 0.82)',
                        borderRadius: 6,
                        maxBarThickness: 26
                    },
                    {
                        label: 'รายจ่าย',
                        data: <?php echo json_encode($chartExpense); ?>,
                        backgroundColor: 'rgba(220, 38, 38, 0.82)',
                        borderRadius: 6,
                        maxBarThickness: 26
                    },
                    {
                        label: 'เงินออม',
                        data: <?php echo json_encode($chartSaving); ?>,
                        backgroundColor: 'rgba(37, 99, 235, 0.82)',
                        borderRadius: 6,
                        maxBarThickness: 26
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                layout: { padding: 6 },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ฿' + Number(context.raw || 0).toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, minRotation: 0 }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return '฿' + Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    const detailModal = document.getElementById('detailModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const modalBody = document.getElementById('modalBody');
    const closeModalBtn = document.getElementById('closeModalBtn');

    const categoryModal = document.getElementById('categoryModal');
    const closeCategoryModalBtn = document.getElementById('closeCategoryModalBtn');
    const cancelCategoryBtn = document.getElementById('cancelCategoryBtn');
    const categoryIdInput = document.getElementById('category_id');
    const categoryNameInput = document.getElementById('category_name');

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function () { var dlg = modal.querySelector('.modal-dialog'); (dlg || modal).scrollTop = 0; });
    }

    function resetModalScroll() {
        requestAnimationFrame(function () { if (detailModal) { var dlg = detailModal.querySelector('.modal-dialog'); (dlg || detailModal).scrollTop = 0; } });
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        if (!document.querySelector('.modal.open')) {
            document.body.style.overflow = '';
        }
    }

    var pageNeedsRefresh = false;

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function () {
            closeModal(detailModal);
            if (pageNeedsRefresh) { pageNeedsRefresh = false; location.reload(); }
        });
    }
    if (detailModal) {
        detailModal.addEventListener('click', function (e) {
            if (e.target === detailModal) {
                closeModal(detailModal);
                if (pageNeedsRefresh) { pageNeedsRefresh = false; location.reload(); }
            }
        });
    }

    if (closeCategoryModalBtn) {
        closeCategoryModalBtn.addEventListener('click', function () {
            closeModal(categoryModal);
        });
    }
    if (cancelCategoryBtn) {
        cancelCategoryBtn.addEventListener('click', function () {
            closeModal(categoryModal);
        });
    }
    if (categoryModal) {
        categoryModal.addEventListener('click', function (e) {
            if (e.target === categoryModal) {
                closeModal(categoryModal);
            }
        });
    }

    var currentDetailController = null;

    document.querySelectorAll('.js-open-detail').forEach(function (el) {
        el.addEventListener('click', function () {
            if (currentDetailController) { currentDetailController.abort(); currentDetailController = null; }

            const categoryId = this.dataset.categoryId || '';
            const categoryName = this.dataset.categoryName || '';
            const month = this.dataset.month || '';
            const monthLabel = this.dataset.monthLabel || '';
            const year = this.dataset.year || '';

            modalTitle.textContent = categoryName;
            modalSubtitle.textContent = 'เดือน ' + monthLabel + ' ปี ' + year;
            openModal(detailModal);

            var cacheKey = 'detail_' + categoryId + '_' + month + '_' + year;
            var cached = sessionStorage.getItem(cacheKey);
            if (cached) {
                modalBody.innerHTML = cached;
                resetModalScroll();
            } else {
                modalBody.innerHTML = '<div class="loading">กำลังโหลดข้อมูล...</div>';
                currentDetailController = new AbortController();
                var signal = currentDetailController.signal;
                fetch('get_detail.php?category_id=' + encodeURIComponent(categoryId) + '&month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: signal
                })
                    .then(function (res) {
                        if (!res.ok) { throw new Error('โหลดข้อมูลไม่สำเร็จ'); }
                        return res.text();
                    })
                    .then(function (html) {
                        currentDetailController = null;
                        modalBody.innerHTML = html;
                        resetModalScroll();
                        try { sessionStorage.setItem(cacheKey, html); } catch(e) {}
                    })
                    .catch(function (err) {
                        if (err.name === 'AbortError') return;
                        modalBody.innerHTML = '<div style="color:#dc2626;font-weight:700;">โหลดข้อมูลไม่สำเร็จ</div>';
                    });
            }
        });
    });

    if (modalBody) {
        modalBody.addEventListener('submit', function (e) {
            var form = e.target;
            if (form.tagName !== 'FORM') return;
            e.preventDefault();
            var confirmMsg = form.getAttribute('data-confirm');
            if (confirmMsg && !confirm(confirmMsg)) return;

            var catInput  = form.querySelector('[name="category_id"]');
            var monInput  = form.querySelector('[name="month"]');
            var yearInput = form.querySelector('[name="year_be"]');
            var categoryId = catInput  ? catInput.value  : '';
            var month      = monInput  ? monInput.value  : '';
            var year       = yearInput ? yearInput.value : '';

            // Warn if entry_date is outside the modal's month — the entry will
            // be saved but won't appear in this modal after refresh.
            var dateInput = form.querySelector('[name="entry_date"]');
            if (dateInput && dateInput.value && month && year) {
                var d  = new Date(dateInput.value);
                var yr = parseInt(year) > 2400 ? parseInt(year) - 543 : parseInt(year);
                if (!isNaN(d.getFullYear()) && (d.getFullYear() !== yr || (d.getMonth() + 1) !== parseInt(month))) {
                    if (!confirm('วันที่ที่เลือกอยู่นอกเดือนนี้\nรายการจะถูกบันทึก แต่จะไม่แสดงใน popup นี้\nกดตกลงเพื่อบันทึกต่อ หรือยกเลิกเพื่อแก้ไขวันที่')) return;
                }
            }

            var btn = form.querySelector('[type="submit"]');
            if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }

            fetch(form.getAttribute('action') || 'save_entry.php', {
                method: 'POST',
                body: new FormData(form),
                redirect: 'follow'
            })
            .then(function (res) {
                var finalUrl = res.url || '';
                if (finalUrl.indexOf('save_error=1') !== -1) {
                    if (btn && btn.isConnected) { btn.disabled = false; btn.style.opacity = ''; }
                    modalBody.innerHTML = '<div style="color:#dc2626;font-weight:700;padding:16px 0">บันทึกไม่สำเร็จ — กรุณาตรวจสอบข้อมูลและลองใหม่</div>';
                    return Promise.reject('save_error');
                }
                pageNeedsRefresh = true;
                sessionStorage.removeItem('detail_' + categoryId + '_' + month + '_' + year);
                modalBody.innerHTML = '<div class="loading">กำลังโหลดข้อมูล...</div>';
                return fetch('get_detail.php?category_id=' + encodeURIComponent(categoryId)
                    + '&month=' + encodeURIComponent(month)
                    + '&year=' + encodeURIComponent(year), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
            })
            .then(function (res) {
                if (!res.ok) throw new Error('server_error');
                return res.text();
            })
            .then(function (html) {
                modalBody.innerHTML = html;
                try { sessionStorage.setItem('detail_' + categoryId + '_' + month + '_' + year, html); } catch (ex) {}
            })
            .catch(function (reason) {
                if (reason === 'save_error') return;
                if (btn && btn.isConnected) {
                    btn.disabled = false;
                    btn.style.opacity = '';
                } else {
                    modalBody.innerHTML = '<div style="color:#dc2626;font-weight:700;padding:20px">เกิดข้อผิดพลาด กรุณาปิด popup แล้วลองใหม่</div>';
                }
            });
        });

        // Delegated two-stage delete — replaces <script> in get_detail.php response
        // (innerHTML does not execute injected <script> tags per browser spec)
        modalBody.addEventListener('click', function(e) {
            var confirmBtn = e.target.closest('.js-delete-confirm-btn');
            if (confirmBtn) {
                var form = confirmBtn.closest('.js-delete-form');
                if (form) {
                    form.querySelector('.js-delete-stage1').style.display = 'none';
                    form.querySelector('.js-delete-stage2').style.display = 'flex';
                }
                return;
            }
            var cancelBtn = e.target.closest('.js-delete-cancel-btn');
            if (cancelBtn) {
                var form2 = cancelBtn.closest('.js-delete-form');
                if (form2) {
                    form2.querySelector('.js-delete-stage1').style.display = '';
                    form2.querySelector('.js-delete-stage2').style.display = 'none';
                }
            }
        });
    }

    document.querySelectorAll('.js-edit-category').forEach(function (el) {
        el.addEventListener('click', function () {
            categoryIdInput.value = this.dataset.categoryId || '';
            categoryNameInput.value = this.dataset.categoryName || '';
            openModal(categoryModal);
            categoryNameInput.focus();
            categoryNameInput.select();
        });
    });

    var qaForm = document.getElementById('quick-add-form');
    if (qaForm) {
        qaForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = qaForm.querySelector('[type="submit"]');
            var savedMsg = document.querySelector('.quick-add-saved');
            var errorMsg = document.getElementById('quick-add-error');
            if (btn) { btn.disabled = true; btn.textContent = 'กำลังบันทึก...'; }
            if (savedMsg) savedMsg.style.display = 'none';
            if (errorMsg) errorMsg.style.display = 'none';
            fetch('save_entry.php', {
                method: 'POST',
                body: new FormData(qaForm),
                redirect: 'follow'
            })
            .then(function(res) {
                var ok = res.url && res.url.indexOf('saved=1') !== -1;
                if (ok) {
                    if (savedMsg) { savedMsg.style.display = 'block'; }
                    var amountInput = qaForm.querySelector('[name="amount"]');
                    if (amountInput) amountInput.value = '';
                    var noteInput = qaForm.querySelector('[name="note"]');
                    if (noteInput) noteInput.value = '';
                    // Reload to the year matching the saved entry_date (not the current display year)
                    var dateEl = qaForm.querySelector('[name="entry_date"]');
                    var entryBE = dateEl ? (new Date(dateEl.value).getFullYear() + 543) : NaN;
                    var reloadUrl = (!isNaN(entryBE) && entryBE > 2400)
                        ? 'index.php?year=' + entryBE
                        : location.href;
                    setTimeout(function() { location.href = reloadUrl; }, 900);
                } else {
                    if (errorMsg) { errorMsg.style.display = 'block'; }
                }
            })
            .catch(function() {
                if (errorMsg) { errorMsg.style.display = 'block'; }
            })
            .finally(function() {
                if (btn) { btn.disabled = false; btn.textContent = '+ บันทึกเลย'; }
            });
        });
    }
})();
</script>

<nav class="idx-bottom-nav">
  <a href="index.php" class="idx-mbn-item active">
    <i class="bi bi-grid-1x2-fill"></i><span>หน้าหลัก</span>
  </a>
  <a href="entries.php" class="idx-mbn-item">
    <i class="bi bi-journal-text"></i><span>รายการ</span>
  </a>
  <div class="idx-mbn-center">
    <a href="add_mobile.php" class="idx-mbn-fab">
      <i class="bi bi-plus-lg"></i>
    </a>
  </div>
  <a href="report.php" class="idx-mbn-item">
    <i class="bi bi-bar-chart-fill"></i><span>รายงาน</span>
  </a>
  <a href="categories.php" class="idx-mbn-item">
    <i class="bi bi-tag-fill"></i><span>หมวดหมู่</span>
  </a>
</nav>

<!-- PWA Install Banner -->
<div id="pwa-banner" style="display:none;position:fixed;top:68px;left:12px;right:12px;z-index:1040;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(79,70,229,.22);padding:14px 16px;align-items:center;gap:12px;">
  <img src="finance-icon-dark.svg" width="44" height="44" style="border-radius:10px;flex-shrink:0" alt="">
  <div style="flex:1;min-width:0">
    <div style="font-weight:700;font-size:.95rem;color:#1e1b4b">Finance App</div>
    <div style="font-size:.8rem;color:#6b7280">ติดตั้งแอพบนหน้าจอหลัก</div>
  </div>
  <button id="pwa-install-btn" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:10px;padding:8px 14px;font-size:.85rem;font-weight:600;cursor:pointer;white-space:nowrap">ติดตั้ง</button>
  <button id="pwa-dismiss-btn" style="background:none;border:none;color:#9ca3af;font-size:1.1rem;cursor:pointer;padding:4px;line-height:1">✕</button>
</div>

<!-- iOS Install Hint (shown only when user taps install button) -->
<div id="ios-hint" style="display:none;position:fixed;top:68px;left:12px;right:12px;z-index:1040;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(79,70,229,.22);padding:14px 16px;">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
    <img src="finance-icon-dark.svg" width="40" height="40" style="border-radius:9px;flex-shrink:0" alt="">
    <div style="flex:1">
      <div style="font-weight:700;font-size:.95rem;color:#1e1b4b">ติดตั้งบน iPhone/iPad</div>
    </div>
    <button id="ios-hint-close" style="background:none;border:none;color:#9ca3af;font-size:1.1rem;cursor:pointer;padding:4px;line-height:1">✕</button>
  </div>
  <div style="font-size:.82rem;color:#4b5563;line-height:1.6">
    แตะ <strong>แชร์</strong> <span style="font-size:1rem">⬆️</span> ที่แถบด้านล่าง แล้วเลือก <strong>"เพิ่มไปยังหน้าจอหลัก"</strong>
  </div>
</div>

<!-- iOS install trigger button (visible on iOS only, bottom-right) -->
<button id="ios-install-trigger" style="display:none;position:fixed;bottom:68px;right:14px;z-index:995;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:50px;padding:6px 12px;font-size:.75rem;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(99,102,241,.35);align-items:center;gap:5px">
  <span>⬇</span> ติดตั้ง
</button>

<script src="assets/js/app.js" defer></script>