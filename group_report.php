<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];
$page_title = 'รายงานตามกลุ่ม';

$thaiMonths = [
    1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
    5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
    9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
];

// All available group tags for this user
$allTags = [];
$rsTags = mysqli_query($conn, "
    SELECT DISTINCT group_tag FROM categories
    WHERE user_id = {$userId} AND is_active = 1
      AND group_tag IS NOT NULL AND group_tag != ''
    ORDER BY group_tag ASC
");
if ($rsTags) {
    while ($row = mysqli_fetch_assoc($rsTags)) $allTags[] = $row['group_tag'];
}

// Available years
$yearOptions = [];
$rsYears = mysqli_query($conn, "SELECT DISTINCT YEAR(entry_date) AS y FROM entries WHERE user_id = {$userId} ORDER BY y DESC");
if ($rsYears) {
    while ($row = mysqli_fetch_assoc($rsYears)) $yearOptions[] = (int)$row['y'] + 543;
}
if (empty($yearOptions)) $yearOptions[] = (int)date('Y') + 543;

// Parse filters from GET
$selectedTags = [];
if (isset($_GET['groups']) && is_array($_GET['groups'])) {
    foreach ($_GET['groups'] as $t) {
        $t = trim((string)$t);
        if ($t !== '' && in_array($t, $allTags, true)) $selectedTags[] = $t;
    }
}

$requestedYear = isset($_GET['year']) ? (int)$_GET['year'] : -1;
if ($requestedYear > 2400) {
    $yearBE = $requestedYear; $yearAD = $requestedYear - 543;
} elseif ($requestedYear > 1900) {
    $yearAD = $requestedYear; $yearBE = $requestedYear + 543;
} else {
    $yearAD = (int)date('Y'); $yearBE = $yearAD + 543;
}
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
if ($month < 0 || $month > 12) $month = 0;

// Query data when tags are selected
$summary  = ['income' => 0.0, 'expense' => 0.0, 'saving' => 0.0];
$monthly  = [];  // [1..12] => [income, expense, saving]
$daily    = [];  // ['YYYY-MM-DD'] => [income, expense, saving]
$hasData  = false;

for ($m = 1; $m <= 12; $m++) {
    $monthly[$m] = ['income' => 0.0, 'expense' => 0.0, 'saving' => 0.0];
}

if (!empty($selectedTags)) {
    if ($month >= 1 && $month <= 12) {
        $dateFrom = sprintf('%04d-%02d-01', $yearAD, $month);
        $dateTo   = date('Y-m-t', strtotime($dateFrom));
    } else {
        $dateFrom = $yearAD . '-01-01';
        $dateTo   = $yearAD . '-12-31';
    }

    $placeholders = implode(',', array_fill(0, count($selectedTags), '?'));
    $bindStr = str_repeat('s', count($selectedTags)) . 'ssii';

    $stmt = mysqli_prepare($conn, "
        SELECT e.entry_date, c.type, SUM(e.amount) AS total
        FROM entries e
        INNER JOIN categories c ON e.category_id = c.id
        WHERE c.group_tag IN ({$placeholders})
          AND e.entry_date BETWEEN ? AND ?
          AND e.user_id = ? AND c.user_id = ?
          AND c.is_active = 1
        GROUP BY e.entry_date, c.type
        ORDER BY e.entry_date ASC
    ");
    $bindArgs = array_merge($selectedTags, [$dateFrom, $dateTo, $userId, $userId]);
    mysqli_stmt_bind_param($stmt, $bindStr, ...$bindArgs);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $hasData = true;
            $d = $row['entry_date'];
            $m2 = (int)date('n', strtotime($d));
            $tp = $row['type'];
            $amt = (float)$row['total'];

            if (isset($summary[$tp])) $summary[$tp] += $amt;
            if (isset($monthly[$m2][$tp])) $monthly[$m2][$tp] += $amt;
            if (!isset($daily[$d])) $daily[$d] = ['income'=>0.0,'expense'=>0.0,'saving'=>0.0];
            if (isset($daily[$d][$tp])) $daily[$d][$tp] += $amt;
        }
    }
    krsort($daily);
}

$net = $summary['income'] - $summary['expense'] - $summary['saving'];

include 'partials/header.php';
?>
<style>
.grp-tag-chip {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .5rem 1rem; border-radius: 999px; cursor: pointer;
    border: 2px solid #e2e8f0; background: #f8fafc; color: #475569;
    font-weight: 700; font-size: .92rem; transition: all .15s; user-select: none;
}
.grp-tag-chip input[type=checkbox] { display: none; }
.grp-tag-chip:hover { border-color: #6366f1; color: #4338ca; background: #eef2ff; }
.grp-tag-chip.checked { border-color: #6366f1; background: #6366f1; color: #fff; }
.grp-tag-chip .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; opacity: .7; }
.grp-summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.grp-card { border-radius: 18px; padding: 1.25rem 1.35rem; border: 1px solid rgba(15,23,42,.06); }
.grp-card .lbl { font-size: .88rem; font-weight: 700; color: #64748b; margin-bottom: .4rem; }
.grp-card .val { font-size: 1.55rem; font-weight: 800; line-height: 1.15; }
.grp-card.c-income  { background: linear-gradient(160deg,#f0fdf4,#fff); }
.grp-card.c-expense { background: linear-gradient(160deg,#fef2f2,#fff); }
.grp-card.c-saving  { background: linear-gradient(160deg,#f5f3ff,#fff); }
.grp-card.c-net     { background: linear-gradient(160deg,#eff6ff,#fff); }
.grp-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.grp-table th, .grp-table td { padding: .75rem 1rem; border-bottom: 1px solid #eef2f7; }
.grp-table th { background: #f8fafc; font-size: .8rem; text-transform: uppercase; letter-spacing:.04em; font-weight:800; color:#475569; white-space:nowrap; }
.grp-table tbody tr:hover { background: #fafcff; }
.grp-table .today-row { background: #fffbeb !important; }
.grp-table .total-row td { background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1; border-bottom: none; }
.text-income { color: #15803d !important; }
.text-expense { color: #dc2626 !important; }
.text-saving  { color: #6d28d9 !important; }
.text-net-pos { color: #1d4ed8 !important; }
.text-net-neg { color: #dc2626 !important; }
.no-tag-hint { border: 2px dashed #e2e8f0; border-radius: 18px; padding: 3rem; text-align: center; }
@media(max-width:767.98px) {
    .grp-summary-grid { grid-template-columns: repeat(2,1fr); }
    .grp-card .val { font-size: 1.2rem; }
}
@media(max-width:479.98px) {
    .grp-summary-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-hero">
    <div>
        <div class="page-hero-icon"><i class="bi bi-layers-fill"></i></div>
        <div class="page-hero-title">รายงานตามกลุ่ม</div>
        <div class="page-hero-sub">เลือกหลายกลุ่มพร้อมกัน แล้วดูยอดรายเดือน รายวัน และสุทธิ</div>
    </div>
</div>

<?php if (empty($allTags)): ?>
<div class="no-tag-hint">
    <div class="mb-2" style="font-size:2.5rem">🗂️</div>
    <div class="fw-bold fs-5 mb-1">ยังไม่มีหมวดหมู่ที่มีกลุ่ม</div>
    <div class="text-muted mb-3">ไปที่จัดการหมวดหมู่ แล้วใส่ชื่อ "กลุ่ม" ให้แต่ละหมวดก่อน เช่น "ธุรกิจ" หรือ "ส่วนตัว"</div>
    <a href="categories.php" class="btn btn-primary">ไปจัดการหมวดหมู่</a>
</div>
<?php else: ?>

<div class="card card-soft mb-4" style="border:1px solid rgba(15,23,42,.06)">
    <div class="card-body p-3 p-lg-4">
        <form method="get">
            <div class="mb-3">
                <div class="fw-bold mb-2">เลือกกลุ่มที่ต้องการดู <span class="text-muted fw-normal">(เลือกได้หลายกลุ่ม)</span></div>
                <div class="d-flex flex-wrap gap-2" id="tag-chips">
                    <?php foreach ($allTags as $tag): ?>
                        <?php $checked = in_array($tag, $selectedTags, true); ?>
                        <label class="grp-tag-chip <?php echo $checked ? 'checked' : ''; ?>">
                            <input type="checkbox" name="groups[]" value="<?php echo h($tag); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                            <span class="dot"></span>
                            <?php echo h($tag); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label fw-semibold">ปี</label>
                    <select name="year" class="form-select">
                        <?php foreach ($yearOptions as $yo): ?>
                            <option value="<?php echo (int)$yo; ?>" <?php echo $yearBE === (int)$yo ? 'selected' : ''; ?>><?php echo (int)$yo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label fw-semibold">เดือน</label>
                    <select name="month" class="form-select">
                        <option value="0" <?php echo $month === 0 ? 'selected' : ''; ?>>ทั้งปี</option>
                        <?php foreach ($thaiMonths as $mn => $ml): ?>
                            <option value="<?php echo (int)$mn; ?>" <?php echo $month === (int)$mn ? 'selected' : ''; ?>><?php echo h($ml); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3 col-xl-2 d-grid">
                    <button type="submit" class="btn btn-primary">แสดงผล</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($selectedTags)): ?>

<?php
// Header label
$periodLabel = $month > 0
    ? $thaiMonths[$month] . ' ' . $yearBE
    : 'ทั้งปี ' . $yearBE;
$tagBadges = implode(' + ', array_map('h', $selectedTags));
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="fw-bold"><?php echo $tagBadges; ?></div>
    <span class="badge-soft"><?php echo h($periodLabel); ?></span>
    <?php if (!$hasData): ?>
        <span class="text-muted small">— ไม่มีข้อมูล</span>
    <?php endif; ?>
</div>

<div class="grp-summary-grid mb-4">
    <div class="grp-card c-income">
        <div class="lbl"><i class="bi bi-arrow-down-circle-fill text-success me-1"></i>รายรับ</div>
        <div class="val text-income"><?php echo baht($summary['income']); ?></div>
    </div>
    <div class="grp-card c-expense">
        <div class="lbl"><i class="bi bi-arrow-up-circle-fill text-danger me-1"></i>รายจ่าย</div>
        <div class="val text-expense"><?php echo baht($summary['expense']); ?></div>
    </div>
    <div class="grp-card c-saving">
        <div class="lbl"><i class="bi bi-piggy-bank-fill" style="color:#7c3aed"></i> เงินออม</div>
        <div class="val text-saving"><?php echo baht($summary['saving']); ?></div>
    </div>
    <div class="grp-card c-net">
        <div class="lbl"><i class="bi bi-calculator-fill text-primary me-1"></i>คงเหลือสุทธิ</div>
        <div class="val <?php echo $net >= 0 ? 'text-net-pos' : 'text-net-neg'; ?>">
            <?php echo ($net >= 0 ? '+' : '') . baht($net); ?>
        </div>
    </div>
</div>

<?php if ($hasData): ?>

<div class="card card-soft" style="border:1px solid rgba(15,23,42,.06);border-radius:20px;overflow:hidden">
    <?php if ($month === 0): ?>
    <!-- ── Monthly breakdown ── -->
    <div class="p-3 p-lg-4 border-bottom" style="background:linear-gradient(180deg,#fff,#f8fafc)">
        <div class="fw-bold">สรุปรายเดือน <?php echo (int)$yearBE; ?></div>
    </div>
    <div style="overflow-x:auto">
        <table class="grp-table mb-0">
            <thead>
                <tr>
                    <th>เดือน</th>
                    <th class="text-end">รายรับ</th>
                    <th class="text-end">รายจ่าย</th>
                    <th class="text-end">เงินออม</th>
                    <th class="text-end">คงเหลือ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totInc = $totExp = $totSav = 0;
                foreach ($thaiMonths as $mn => $ml):
                    $r = $monthly[$mn];
                    $mNet = $r['income'] - $r['expense'] - $r['saving'];
                    $hasRow = ($r['income'] > 0 || $r['expense'] > 0 || $r['saving'] > 0);
                    $totInc += $r['income']; $totExp += $r['expense']; $totSav += $r['saving'];
                    $mUrl = h('group_report.php?' . http_build_query(['groups' => $selectedTags, 'year' => $yearBE, 'month' => $mn]));
                ?>
                <tr style="<?php echo !$hasRow ? 'opacity:.4' : ''; ?>">
                    <td>
                        <a href="<?php echo $mUrl; ?>" class="fw-semibold text-decoration-none" style="color:#0f172a">
                            <?php echo h($ml); ?>
                        </a>
                    </td>
                    <td class="text-end <?php echo $r['income'] > 0 ? 'text-income fw-semibold' : 'text-muted'; ?>"><?php echo $r['income'] > 0 ? baht($r['income']) : '-'; ?></td>
                    <td class="text-end <?php echo $r['expense'] > 0 ? 'text-expense fw-semibold' : 'text-muted'; ?>"><?php echo $r['expense'] > 0 ? baht($r['expense']) : '-'; ?></td>
                    <td class="text-end <?php echo $r['saving'] > 0 ? 'text-saving fw-semibold' : 'text-muted'; ?>"><?php echo $r['saving'] > 0 ? baht($r['saving']) : '-'; ?></td>
                    <td class="text-end fw-bold <?php echo $mNet >= 0 ? 'text-net-pos' : 'text-net-neg'; ?>"><?php echo $hasRow ? ($mNet >= 0 ? '+' : '') . baht($mNet) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td>รวมทั้งปี</td>
                    <td class="text-end text-income"><?php echo baht($totInc); ?></td>
                    <td class="text-end text-expense"><?php echo baht($totExp); ?></td>
                    <td class="text-end text-saving"><?php echo baht($totSav); ?></td>
                    <?php $totNet = $totInc - $totExp - $totSav; ?>
                    <td class="text-end <?php echo $totNet >= 0 ? 'text-net-pos' : 'text-net-neg'; ?>"><?php echo ($totNet >= 0 ? '+' : '') . baht($totNet); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <!-- ── Daily breakdown for selected month ── -->
    <?php
    $today = date('Y-m-d');
    $dayInc = $dayExp = $daySav = 0;
    foreach ($daily as $d => $r) { $dayInc += $r['income']; $dayExp += $r['expense']; $daySav += $r['saving']; }
    $dayNet = $dayInc - $dayExp - $daySav;
    ?>
    <div class="p-3 p-lg-4 border-bottom" style="background:linear-gradient(180deg,#fff,#f8fafc)">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="fw-bold"><?php echo h($thaiMonths[$month]); ?> <?php echo (int)$yearBE; ?> — รายวัน</div>
            <a href="<?php echo h('group_report.php?' . http_build_query(['groups' => $selectedTags, 'year' => $yearBE, 'month' => 0])); ?>" class="btn btn-sm btn-outline-secondary">← ดูทั้งปี</a>
        </div>
    </div>
    <div style="overflow-x:auto">
        <table class="grp-table mb-0">
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th class="text-end">รายรับ</th>
                    <th class="text-end">รายจ่าย</th>
                    <th class="text-end">เงินออม</th>
                    <th class="text-end">คงเหลือวันนั้น</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily as $d => $r):
                    $dNet = $r['income'] - $r['expense'] - $r['saving'];
                    $ts = strtotime($d);
                    $dateBE = date('d/m/', $ts) . ((int)date('Y', $ts) + 543);
                    $isToday = ($d === $today);
                ?>
                <tr <?php echo $isToday ? 'class="today-row"' : ''; ?>>
                    <td class="fw-semibold">
                        <?php echo h($dateBE); ?>
                        <?php if ($isToday): ?><span class="badge-soft ms-1" style="font-size:.72rem">วันนี้</span><?php endif; ?>
                    </td>
                    <td class="text-end <?php echo $r['income'] > 0 ? 'text-income fw-semibold' : 'text-muted'; ?>"><?php echo $r['income'] > 0 ? baht($r['income']) : '-'; ?></td>
                    <td class="text-end <?php echo $r['expense'] > 0 ? 'text-expense fw-semibold' : 'text-muted'; ?>"><?php echo $r['expense'] > 0 ? baht($r['expense']) : '-'; ?></td>
                    <td class="text-end <?php echo $r['saving'] > 0 ? 'text-saving fw-semibold' : 'text-muted'; ?>"><?php echo $r['saving'] > 0 ? baht($r['saving']) : '-'; ?></td>
                    <td class="text-end fw-bold <?php echo $dNet >= 0 ? 'text-net-pos' : 'text-net-neg'; ?>"><?php echo ($dNet >= 0 ? '+' : '') . baht($dNet); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td>รวม <?php echo h($thaiMonths[$month]); ?></td>
                    <td class="text-end text-income"><?php echo baht($dayInc); ?></td>
                    <td class="text-end text-expense"><?php echo baht($dayExp); ?></td>
                    <td class="text-end text-saving"><?php echo baht($daySav); ?></td>
                    <td class="text-end <?php echo $dayNet >= 0 ? 'text-net-pos' : 'text-net-neg'; ?>"><?php echo ($dayNet >= 0 ? '+' : '') . baht($dayNet); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="card card-soft">
    <div class="card-body text-center py-5">
        <div class="fw-bold fs-5 mb-1">ไม่มีข้อมูลในช่วงที่เลือก</div>
        <div class="text-muted">ลองเปลี่ยนปีหรือเดือน หรือตรวจสอบว่าหมวดหมู่ในกลุ่มนี้มีรายการบันทึกไว้แล้ว</div>
    </div>
</div>
<?php endif; ?>

<?php endif; // selectedTags not empty ?>
<?php endif; // allTags not empty ?>

<script>
document.querySelectorAll('.grp-tag-chip').forEach(function(label) {
    label.addEventListener('change', function() {
        label.classList.toggle('checked', label.querySelector('input').checked);
    });
});
</script>

<?php include 'partials/footer.php'; ?>
