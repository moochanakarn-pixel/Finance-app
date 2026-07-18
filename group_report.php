<?php
error_reporting(0);
ini_set('display_errors', 0);
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId     = (int)$_SESSION['user_id'];
$page_title = 'รายงานตามกลุ่ม';

$thaiMonths = [
    1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
    5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
    9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
];

// All groups for this user
$allGroups = [];
$rsGroups = mysqli_query($conn, "
    SELECT id, name FROM category_groups
    WHERE user_id = {$userId}
    ORDER BY sort_order ASC, id ASC
");
if ($rsGroups) {
    while ($row = mysqli_fetch_assoc($rsGroups)) $allGroups[] = $row;
}
$groupById = [];
foreach ($allGroups as $g) $groupById[(int)$g['id']] = $g['name'];

// Available years
$yearOptions = [];
$rsYears = mysqli_query($conn, "SELECT DISTINCT YEAR(entry_date) AS y FROM entries WHERE user_id = {$userId} ORDER BY y DESC");
if ($rsYears) {
    while ($row = mysqli_fetch_assoc($rsYears)) $yearOptions[] = (int)$row['y'] + 543;
}
if (empty($yearOptions)) $yearOptions[] = (int)date('Y') + 543;

// Parse selected group IDs from GET
$selectedGroupIds = [];
if (isset($_GET['groups']) && is_array($_GET['groups'])) {
    $validIds = array_keys($groupById);
    foreach ($_GET['groups'] as $gid) {
        $gid = (int)$gid;
        if ($gid > 0 && in_array($gid, $validIds, true)) $selectedGroupIds[] = $gid;
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

// Query data when groups are selected
$summary = ['income' => 0.0, 'expense' => 0.0, 'saving' => 0.0];
$monthly = [];
$daily   = [];
$hasData = false;

for ($m = 1; $m <= 12; $m++) {
    $monthly[$m] = ['income' => 0.0, 'expense' => 0.0, 'saving' => 0.0];
}

if (!empty($selectedGroupIds)) {
    if ($month >= 1 && $month <= 12) {
        $dateFrom = sprintf('%04d-%02d-01', $yearAD, $month);
        $dateTo   = date('Y-m-t', strtotime($dateFrom));
    } else {
        $dateFrom = $yearAD . '-01-01';
        $dateTo   = $yearAD . '-12-31';
    }

    $placeholders = implode(',', array_fill(0, count($selectedGroupIds), '?'));
    $bindStr      = str_repeat('i', count($selectedGroupIds)) . 'ssii';

    $stmt = mysqli_prepare($conn, "
        SELECT e.entry_date, c.type, SUM(e.amount) AS total
        FROM entries e
        INNER JOIN categories c ON e.category_id = c.id
        WHERE c.group_id IN ({$placeholders})
          AND e.entry_date BETWEEN ? AND ?
          AND e.user_id = ? AND c.user_id = ?
          AND c.is_active = 1
        GROUP BY e.entry_date, c.type
        ORDER BY e.entry_date ASC
    ");
    $bindArgs = array_merge($selectedGroupIds, [$dateFrom, $dateTo, $userId, $userId]);
    mysqli_stmt_bind_param($stmt, $bindStr, ...$bindArgs);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $hasData = true;
            $d  = $row['entry_date'];
            $m2 = (int)date('n', strtotime($d));
            $tp = $row['type'];
            $amt = (float)$row['total'];
            if (isset($summary[$tp])) $summary[$tp] += $amt;
            if (isset($monthly[$m2][$tp])) $monthly[$m2][$tp] += $amt;
            if ($month >= 1 && $month <= 12) {
                if (!isset($daily[$d])) $daily[$d] = ['income'=>0.0,'expense'=>0.0,'saving'=>0.0];
                if (isset($daily[$d][$tp])) $daily[$d][$tp] += $amt;
            }
        }
    }
    if (!empty($daily)) krsort($daily);
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
/* expandable rows */
.grp-day-row { cursor: pointer; }
.grp-day-row:hover { background: #f0f4ff !important; }
.grp-day-row .ci { transition: transform .2s; font-size: .72rem; color: #94a3b8; display: inline-block; }
.grp-day-row.open .ci { transform: rotate(90deg); }
.grp-detail-row > td { padding: 0 !important; background: #f8fafc; }
.grp-sub-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.grp-sub-table th, .grp-sub-table td { padding: .45rem .9rem .45rem 2.5rem; border-bottom: 1px solid #e2e8f0; }
.grp-sub-table th { background: #f1f5f9; font-size: .75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing:.03em; }
.grp-sub-table tbody tr:last-child td { border-bottom: none; }
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
    <div class="page-hero-actions">
        <a class="btn-hero" href="groups.php"><i class="bi bi-pencil-square me-1"></i>จัดการกลุ่ม</a>
    </div>
</div>

<?php if (empty($allGroups)): ?>
<div class="no-tag-hint">
    <div class="mb-2" style="font-size:2.5rem">🗂️</div>
    <div class="fw-bold fs-5 mb-1">ยังไม่มีกลุ่มรายงาน</div>
    <div class="text-muted mb-3">ไปสร้างกลุ่มและกำหนดหมวดหมู่ที่ต้องการรายงานร่วมกันก่อน</div>
    <a href="groups.php" class="btn btn-primary">ไปจัดการกลุ่มรายงาน</a>
</div>
<?php else: ?>

<div class="card card-soft mb-4" style="border:1px solid rgba(15,23,42,.06)">
    <div class="card-body p-3 p-lg-4">
        <form method="get">
            <div class="mb-3">
                <div class="fw-bold mb-2">เลือกกลุ่มที่ต้องการดู <span class="text-muted fw-normal">(เลือกได้หลายกลุ่ม)</span></div>
                <div class="d-flex flex-wrap gap-2" id="tag-chips">
                    <?php foreach ($allGroups as $grp): ?>
                        <?php $checked = in_array((int)$grp['id'], $selectedGroupIds, true); ?>
                        <label class="grp-tag-chip <?php echo $checked ? 'checked' : ''; ?>">
                            <input type="checkbox" name="groups[]" value="<?php echo (int)$grp['id']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                            <span class="dot"></span>
                            <?php echo h($grp['name']); ?>
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

<?php if (!empty($selectedGroupIds)): ?>

<?php
$periodLabel = $month > 0
    ? $thaiMonths[$month] . ' ' . $yearBE
    : 'ทั้งปี ' . $yearBE;
$groupNames  = array_map(function($gid) use ($groupById) { return h($groupById[$gid] ?? ''); }, $selectedGroupIds);
$tagBadges   = implode(' + ', $groupNames);
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

<div id="grp-report-card" class="card card-soft" style="border:1px solid rgba(15,23,42,.06);border-radius:20px;overflow:hidden">
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
                $groupsParam = h(http_build_query(['groups' => $selectedGroupIds]));
                foreach ($thaiMonths as $mn => $ml):
                    $r    = $monthly[$mn];
                    $mNet = $r['income'] - $r['expense'] - $r['saving'];
                    $hasRow = ($r['income'] > 0 || $r['expense'] > 0 || $r['saving'] > 0);
                    $totInc += $r['income']; $totExp += $r['expense']; $totSav += $r['saving'];
                ?>
                <tr class="grp-day-row<?php echo !$hasRow ? '" style="opacity:.4' : ''; ?>"
                    data-type="month"
                    data-year="<?php echo (int)$yearAD; ?>"
                    data-month="<?php echo (int)$mn; ?>"
                    data-groups="<?php echo $groupsParam; ?>">
                    <td>
                        <i class="bi bi-chevron-right ci me-1"></i>
                        <a href="<?php echo h('group_report.php?' . http_build_query(['groups' => $selectedGroupIds, 'year' => $yearBE, 'month' => $mn])); ?>" class="fw-semibold text-decoration-none" style="color:#0f172a"><?php echo h($ml); ?></a>
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
    $today  = date('Y-m-d');
    $dayInc = $dayExp = $daySav = 0;
    foreach ($daily as $d => $r) { $dayInc += $r['income']; $dayExp += $r['expense']; $daySav += $r['saving']; }
    $dayNet = $dayInc - $dayExp - $daySav;
    ?>
    <div class="p-3 p-lg-4 border-bottom" style="background:linear-gradient(180deg,#fff,#f8fafc)">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="fw-bold"><?php echo h($thaiMonths[$month]); ?> <?php echo (int)$yearBE; ?> — รายวัน</div>
            <a href="<?php echo h('group_report.php?' . http_build_query(['groups' => $selectedGroupIds, 'year' => $yearBE, 'month' => 0])); ?>" class="btn btn-sm btn-outline-secondary">← ดูทั้งปี</a>
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
                <?php
                $groupsParam = h(http_build_query(['groups' => $selectedGroupIds]));
                foreach ($daily as $d => $r):
                    $dNet   = $r['income'] - $r['expense'] - $r['saving'];
                    $ts     = strtotime($d);
                    $dateBE = date('d/m/', $ts) . ((int)date('Y', $ts) + 543);
                    $isToday = ($d === $today);
                ?>
                <tr class="grp-day-row<?php echo $isToday ? ' today-row' : ''; ?>"
                    data-type="day"
                    data-date="<?php echo h($d); ?>"
                    data-groups="<?php echo $groupsParam; ?>">
                    <td class="fw-semibold">
                        <i class="bi bi-chevron-right ci me-1"></i>
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
        <div class="text-muted">ลองเปลี่ยนปีหรือเดือน หรือตรวจสอบว่ากลุ่มนี้มีหมวดหมู่และรายการบันทึกแล้ว</div>
    </div>
</div>
<?php endif; ?>

<?php endif; // selectedGroupIds not empty ?>
<?php endif; // allGroups not empty ?>

<script>
document.querySelectorAll('.grp-tag-chip').forEach(function(label) {
    label.addEventListener('change', function() {
        label.classList.toggle('checked', label.querySelector('input').checked);
    });
});

// Expandable rows — event delegation so dynamically injected rows also work
var grpCard = document.getElementById('grp-report-card');
if (grpCard) {
    grpCard.addEventListener('click', function(e) {
        var row = e.target.closest('.grp-day-row');
        if (!row) return;
        e.preventDefault();

        var type   = row.dataset.type;
        var groups = row.dataset.groups;
        var detailId, url;

        if (type === 'month') {
            var year  = row.dataset.year;
            var month = row.dataset.month;
            detailId  = 'grp-detail-month-' + year + '-' + month;
            url       = 'get_group_month.php?' + groups + '&year=' + encodeURIComponent(year) + '&month=' + encodeURIComponent(month);
        } else {
            var date  = row.dataset.date;
            detailId  = 'grp-detail-day-' + date;
            url       = 'get_group_day.php?' + groups + '&date=' + encodeURIComponent(date);
        }

        var existing = document.getElementById(detailId);
        if (existing) {
            row.classList.toggle('open');
            existing.style.display = row.classList.contains('open') ? '' : 'none';
            return;
        }

        row.classList.add('open');
        var colCount = row.cells.length;
        var detailRow = document.createElement('tr');
        detailRow.id = detailId;
        detailRow.className = 'grp-detail-row';
        var td = document.createElement('td');
        td.colSpan = colCount;
        td.innerHTML = '<div class="py-2 ps-4 text-muted" style="font-size:.85rem">กำลังโหลด...</div>';
        detailRow.appendChild(td);
        row.after(detailRow);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function(html) { td.innerHTML = html; })
            .catch(function() { td.innerHTML = '<div class="text-danger small py-2 ps-3">โหลดไม่สำเร็จ</div>'; });
    });
}
</script>

<?php include 'partials/footer.php'; ?>
