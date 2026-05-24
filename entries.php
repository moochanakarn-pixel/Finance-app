<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';
mysqli_set_charset($conn, 'utf8');

$userId = (int)$_SESSION['user_id'];
$page_title = 'รายการทั้งหมด';

$yearBE = isset($_GET['year']) ? (int)$_GET['year'] : ((int)date('Y') + 543);
$yearAD = ($yearBE > 2400) ? ($yearBE - 543) : $yearBE;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';


$typeLabels = array('income' => 'รายรับ', 'expense' => 'รายจ่าย', 'saving' => 'เงินออม');
$typeTextClass = array('income' => 'text-income', 'expense' => 'text-expense', 'saving' => 'text-saving');

$latestEntry = null;
$rsLatestEntry = mysqli_query($conn, "
    SELECT e.id, e.entry_date, e.amount, e.note, c.name AS category_name, c.type AS category_type
    FROM entries e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE e.user_id = {$userId}
      AND c.user_id = {$userId}
      AND c.is_active = 1
    ORDER BY e.id DESC
    LIMIT 1
");
if ($rsLatestEntry && mysqli_num_rows($rsLatestEntry) > 0) {
    $latestEntry = mysqli_fetch_assoc($rsLatestEntry);
}
$thaiMonths = array(
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
);

$yearOptions = array();
$rsYears = mysqli_query($conn, "
    SELECT DISTINCT YEAR(entry_date) AS y
    FROM entries
    WHERE user_id = {$userId}
    ORDER BY y DESC
");
if ($rsYears) {
    while ($row = mysqli_fetch_assoc($rsYears)) {
        $yearOptions[] = (int)$row['y'] + 543;
    }
}
if (empty($yearOptions)) {
    $yearOptions[] = $yearBE;
}

$yearStart = $yearAD . '-01-01';
$yearEnd   = $yearAD . '-12-31';

$where   = array();
$where[] = "e.entry_date BETWEEN '{$yearStart}' AND '{$yearEnd}'";
$where[] = "e.user_id = {$userId}";
$where[] = "c.user_id = {$userId}";
$where[] = "c.is_active = 1";
if ($month >= 1 && $month <= 12) {
    $monthFrom = sprintf('%04d-%02d-01', $yearAD, $month);
    $monthTo   = date('Y-m-t', strtotime($monthFrom));
    $where[0]  = "e.entry_date BETWEEN '{$monthFrom}' AND '{$monthTo}'";
}
if ($categoryId > 0) {
    $where[] = "e.category_id = {$categoryId}";
}
if (in_array($type, array('income', 'expense', 'saving'), true)) {
    $safeType = mysqli_real_escape_string($conn, $type);
    $where[] = "c.type = '{$safeType}'";
}
$whereSql = implode(' AND ', $where);

$entries = array();
$summary = array(
    'count' => 0,
    'income' => 0,
    'expense' => 0,
    'saving' => 0,
);
$entriesByMonth = array();
$rsEntries = mysqli_query($conn, "
    SELECT e.id, e.entry_date, e.amount, e.note, c.id AS category_id, c.name AS category_name, c.type AS category_type
    FROM entries e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE {$whereSql}
    ORDER BY e.entry_date DESC, e.id DESC
");
if ($rsEntries) {
    while ($row = mysqli_fetch_assoc($rsEntries)) {
        $entries[] = $row;
        $amount = (float)$row['amount'];
        $entryMonth = (int)date('n', strtotime($row['entry_date']));
        $entryYearBE = (int)date('Y', strtotime($row['entry_date'])) + 543;
        $groupKey = sprintf('%04d-%02d', $entryYearBE, $entryMonth);

        if (!isset($entriesByMonth[$groupKey])) {
            $entriesByMonth[$groupKey] = array(
                'month' => $entryMonth,
                'year_be' => $entryYearBE,
                'label' => $thaiMonths[$entryMonth] . ' ' . $entryYearBE,
                'items' => array(),
                'count' => 0,
                'income' => 0,
                'expense' => 0,
                'saving' => 0,
            );
        }

        $entriesByMonth[$groupKey]['items'][] = $row;
        $entriesByMonth[$groupKey]['count']++;
        if (isset($entriesByMonth[$groupKey][$row['category_type']])) {
            $entriesByMonth[$groupKey][$row['category_type']] += $amount;
        }
        if (isset($summary[$row['category_type']])) {
            $summary[$row['category_type']] += $amount;
        }
        $summary['count']++;
    }
}

$categories = array();
$rsCategories = mysqli_query($conn, "
    SELECT id, name, type
    FROM categories
    WHERE is_active = 1
      AND user_id = {$userId}
    ORDER BY FIELD(type,'income','saving','expense'), sort_order ASC, id ASC
");
if ($rsCategories) {
    while ($row = mysqli_fetch_assoc($rsCategories)) {
        $categories[] = $row;
    }
}

$displayTotal = $summary['income'] - $summary['expense'] - $summary['saving'];
$hasFilter = ($month > 0 || $categoryId > 0 || $type !== '');

include 'partials/header.php';
?>
<style>
.entries-toolbar-card,
.entries-month-card,
.entries-summary-card,
.entries-empty-card {
    border: 1px solid rgba(15, 23, 42, 0.06);
}
.entries-summary-card {
    border-radius: 18px;
}
.entries-summary-card .summary-label {
    color: #6b7280;
    font-size: .92rem;
    font-weight: 600;
    margin-bottom: .35rem;
}
.entries-summary-card .summary-value {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.2;
}
.entries-summary-card.is-income {
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
}
.entries-summary-card.is-expense {
    background: linear-gradient(180deg, #fef2f2 0%, #ffffff 100%);
}
.entries-summary-card.is-saving {
    background: linear-gradient(180deg, #f5f3ff 0%, #ffffff 100%);
}
.entries-summary-card.is-net {
    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
}
.text-income { color: #15803d !important; }
.text-expense { color: #dc2626 !important; }
.text-saving { color: #6d28d9 !important; }
.month-switcher {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
}
.month-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 84px;
    padding: .58rem .85rem;
    border-radius: 999px;
    text-decoration: none;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-weight: 700;
    font-size: .92rem;
}
.month-chip:hover { background: #eef2ff; color: #334155; }
.month-chip.active {
    background: #111827;
    border-color: #111827;
    color: #fff;
}
.entries-month-card {
    border-radius: 20px;
    overflow: hidden;
}
.entries-month-header {
    padding: 1rem 1.1rem;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-bottom: 1px solid #edf2f7;
}
.entries-month-title {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 800;
    color: #0f172a;
}
.entries-month-meta {
    color: #64748b;
    font-size: .92rem;
    font-weight: 600;
}
.entries-month-totals {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-top: .8rem;
}
.entries-total-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .46rem .75rem;
    border-radius: 999px;
    font-size: .88rem;
    font-weight: 700;
}
.entries-total-pill.income { background: #dcfce7; color: #166534; }
.entries-total-pill.expense { background: #fee2e2; color: #991b1b; }
.entries-total-pill.saving { background: #ede9fe; color: #6d28d9; }
.entries-total-pill.count { background: #e2e8f0; color: #334155; }
.entries-table-wrap {
    overflow-x: auto;
}
.entries-table {
    width: 100%;
    min-width: 920px;
    border-collapse: separate;
    border-spacing: 0;
}
.entries-table th,
.entries-table td {
    padding: .95rem .95rem;
    border-bottom: 1px solid #eef2f7;
    vertical-align: top;
}
.entries-table th {
    background: #f8fafc;
    color: #475569;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    font-weight: 800;
    white-space: nowrap;
}
.entries-table tbody tr:hover { background: #fafcff; }
.entries-date strong {
    display: block;
    color: #0f172a;
}
.entries-date span {
    color: #64748b;
    font-size: .86rem;
}
.entries-note {
    color: #475569;
    white-space: pre-line;
    min-width: 220px;
}
.entries-note.is-empty { color: #94a3b8; }
.entry-actions {
    display: flex;
    justify-content: center;
    gap: .5rem;
    flex-wrap: wrap;
}
.entry-mobile-list {
    display: none;
    padding: .85rem;
}
.entry-mobile-item {
    border: 1px solid #edf2f7;
    border-radius: 16px;
    padding: .9rem;
    background: #fff;
}
.entry-mobile-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .75rem;
}
.entry-mobile-title {
    font-weight: 800;
    color: #0f172a;
    margin-bottom: .2rem;
}
.entry-mobile-date {
    color: #64748b;
    font-size: .87rem;
}
.entry-mobile-note {
    color: #475569;
    white-space: pre-line;
    border-top: 1px dashed #e2e8f0;
    margin-top: .75rem;
    padding-top: .75rem;
}
.entry-mobile-note.is-empty { color: #94a3b8; }
.latest-entry-inline {
    display: grid;
    gap: .85rem;
}
.latest-entry-row {
    display: flex;
    justify-content: space-between;
    gap: .75rem;
    align-items: center;
    padding: .75rem .9rem;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
}
.latest-entry-key {
    color: #64748b;
    font-size: .9rem;
    font-weight: 700;
}
.latest-entry-value {
    font-size: .96rem;
    font-weight: 800;
    text-align: right;
}
.latest-entry-name {
    font-size: 1.02rem;
    font-weight: 800;
    color: #0f172a;
}
.latest-entry-amount {
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1.1;
}
.latest-entry-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: .35rem .65rem;
    border-radius: 999px;
    font-size: .82rem;
    font-weight: 800;
}
.latest-entry-tag.income { background: #dcfce7; color: #166534; }
.latest-entry-tag.expense { background: #fee2e2; color: #991b1b; }
.latest-entry-tag.saving { background: #ede9fe; color: #6d28d9; }
.batch-add-card { border: 1px solid rgba(15,23,42,.06); border-radius: 20px; }
.batch-grid-head, .batch-grid-row { display: grid; grid-template-columns: 160px 1.2fr 1fr 1.4fr auto; gap: .75rem; align-items: start; }
.batch-grid-head { padding: .75rem .9rem; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; font-weight: 800; color: #475569; font-size: .85rem; }
.batch-grid-wrap { display: grid; gap: .75rem; }
.batch-grid-row { padding: .85rem; border: 1px solid #e5e7eb; border-radius: 16px; background: #fff; }
.batch-row-actions { display: flex; gap: .4rem; flex-wrap: wrap; }
.batch-summary-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; padding: .9rem 1rem; }
.batch-total-amount { font-size: 1.15rem; font-weight: 800; color: #0f172a; }
.preset-chip.secondary { background:#fff; border:1px solid #dbe2ea; color:#334155; }
@media (max-width: 991.98px) { .batch-grid-head { display:none; } .batch-grid-row { grid-template-columns: 1fr; } .batch-row-actions { justify-content:flex-start; } }
@media (max-width: 767.98px) {
    .entries-summary-card .summary-value { font-size: 1.3rem; }
    .month-chip { min-width: unset; flex: 1 1 calc(33.333% - .5rem); }
    .entries-table-wrap { display: none; }
    .entry-mobile-list { display: grid; gap: .75rem; }
    .entries-month-header { padding: .95rem; }
}
</style>

<div class="page-hero">
    <div class="d-flex justify-content-between align-items-flex-start gap-3 flex-wrap">
        <div>
            <div class="page-hero-icon"><i class="bi bi-journal-text"></i></div>
            <div class="page-hero-title">รายการทั้งหมด</div>
            <div class="page-hero-sub">ดูแบบรายเดือน คัดกรองได้ง่าย</div>
        </div>
    </div>
    <div class="page-hero-actions">
        <a href="add_mobile.php" class="btn-hero btn-hero-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มรายการ</a>
        <a href="add.php" class="btn-hero"><i class="bi bi-grid me-1"></i>เพิ่มหลายรายการ</a>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success mb-3">บันทึกรายการสำเร็จ</div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="alert alert-success mb-3">แก้ไขรายการสำเร็จ</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success mb-3">ลบรายการสำเร็จ</div><?php endif; ?>
<?php if (isset($_GET['batch_saved'])): ?><div class="alert alert-success mb-3">บันทึกหลายรายการสำเร็จ<?php echo isset($_GET['batch_count']) ? ' (' . (int)$_GET['batch_count'] . ' รายการ)' : ''; ?></div><?php endif; ?>

<div class="card card-soft entries-toolbar-card mb-4">
    <div class="card-body p-3 p-lg-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label fw-semibold">ปี</label>
                <select name="year" class="form-select">
                    <?php foreach ($yearOptions as $yearOpt): ?>
                        <option value="<?php echo (int)$yearOpt; ?>" <?php echo $yearBE === (int)$yearOpt ? 'selected' : ''; ?>><?php echo (int)$yearOpt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label fw-semibold">เดือน</label>
                <select name="month" class="form-select">
                    <option value="0">ทั้งปี</option>
                    <?php foreach ($thaiMonths as $monthNo => $monthName): ?>
                        <option value="<?php echo (int)$monthNo; ?>" <?php echo $month === (int)$monthNo ? 'selected' : ''; ?>><?php echo h($monthName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label fw-semibold">หมวดหมู่</label>
                <select name="category_id" class="form-select">
                    <option value="0">ทุกหมวดหมู่</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>" <?php echo $categoryId === (int)$cat['id'] ? 'selected' : ''; ?>>
                            [<?php echo strtoupper(h($cat['type'])); ?>] <?php echo h($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label fw-semibold">ประเภท</label>
                <select name="type" class="form-select">
                    <option value="">ทุกประเภท</option>
                    <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>รายรับ</option>
                    <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>รายจ่าย</option>
                    <option value="saving" <?php echo $type === 'saving' ? 'selected' : ''; ?>>เงินออม</option>
                </select>
            </div>
            <div class="col-12 col-xl-2 d-grid d-xl-block">
                <button type="submit" class="btn btn-primary w-100">แสดงผล</button>
            </div>
        </form>

        <div class="mt-3 pt-3 border-top">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" id="entry-search" class="form-control border-start-0 ps-0" placeholder="ค้นหาหมวดหมู่หรือหมายเหตุ..." autocomplete="off">
                <span id="search-count" class="input-group-text bg-white text-muted" style="font-size:.82rem;display:none"></span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-3 pt-3 border-top">
            <div class="month-switcher w-100">
                <a href="entries.php?<?php echo h(http_build_query(array('year' => $yearBE, 'category_id' => $categoryId, 'type' => $type, 'month' => 0))); ?>" class="month-chip <?php echo $month === 0 ? 'active' : ''; ?>">ทั้งปี</a>
                <?php foreach ($thaiMonths as $monthNo => $monthName): ?>
                    <a href="entries.php?<?php echo h(http_build_query(array('year' => $yearBE, 'category_id' => $categoryId, 'type' => $type, 'month' => $monthNo))); ?>" class="month-chip <?php echo $month === (int)$monthNo ? 'active' : ''; ?>">
                        <?php echo h(thai_month_short($monthNo)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <?php if ($hasFilter): ?>
                    <a href="entries.php?year=<?php echo (int)$yearBE; ?>" class="btn btn-outline-secondary">ล้างตัวกรอง</a>
                <?php endif; ?>
                <a href="add.php" class="btn btn-dark">+ เพิ่มรายการ</a>
            </div>
        </div>
    </div>
</div>

<div class="card card-soft batch-add-card mb-4" id="batch-entry-form">
    <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h5 class="mb-1">เพิ่มหลายรายการพร้อมกัน</h5>
                <div class="text-muted">เหมาะกับการคีย์ย้อนหลังหลายรายการ แล้วกดบันทึกทีเดียว</div>
            </div>
            <a href="#batch-entry-form" class="btn btn-outline-secondary">ไปที่ฟอร์มนี้</a>
        </div>
        <form method="post" action="save_entry.php" id="batch-add-form">
            <input type="hidden" name="action" value="batch_add">
            <input type="hidden" name="year_be" value="<?php echo (int)$yearBE; ?>">
            <input type="hidden" name="return_url" value="<?php echo h('entries.php?' . http_build_query(array('year' => $yearBE, 'month' => $month, 'category_id' => $categoryId, 'type' => $type))); ?>">

            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="preset-chip secondary" id="batch-add-row">+ เพิ่ม 1 แถว</button>
                <button type="button" class="preset-chip secondary" id="batch-add-5">+ เพิ่ม 5 แถว</button>
                <button type="button" class="preset-chip secondary" id="batch-copy-date">คัดลอกวันที่แถวแรกลงทุกแถว</button>
                <button type="button" class="preset-chip secondary" id="batch-copy-category">คัดลอกหมวดแถวแรกลงทุกแถว</button>
            </div>

            <div class="batch-summary-box mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                    <div>จำนวนแถวทั้งหมด: <strong id="batch-row-count">3</strong></div>
                    <div class="batch-total-amount">รวมทั้งหมด: <span id="batch-grand-total">0.00</span> บาท</div>
                </div>
            </div>

            <div class="batch-grid-head">
                <div>วันที่</div>
                <div>หมวดหมู่</div>
                <div>จำนวนเงิน</div>
                <div>หมายเหตุ</div>
                <div class="text-center">จัดการ</div>
            </div>
            <div class="batch-grid-wrap mt-2" id="batch-rows"></div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-lg">บันทึกทั้งหมด</button>
            </div>
        </form>
    </div>
</div>

<script>
window.batchCategoryOptionsHtml = <?php echo json_encode(implode('', array_map(function($cat) use ($typeLabels) { $label = isset($typeLabels[$cat['type']]) ? $typeLabels[$cat['type']] : strtoupper($cat['type']); return '<option value="' . (int)$cat['id'] . '">[' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '] ' . htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') . '</option>'; }, $categories)), JSON_UNESCAPED_UNICODE); ?>;
window.batchDefaultDate = <?php echo json_encode(date('Y-m-d')); ?>;
</script>
<script>
(function(){
  function normalizeAmountExpression(value){ value=(value||'').toString().replace(/,/g,'').replace(/\s+/g,''); if(!value) return ''; if(!/^[0-9+\-.]+$/.test(value)) return value; return value; }
  function evaluateAmountExpression(value){ value=normalizeAmountExpression(value); if(!value) return 0; if(!/^[0-9+\-.]+$/.test(value)) return NaN; var tokens=value.match(/[+\-]?\d+(?:\.\d+)?/g); if(!tokens) return NaN; var total=0; for(var i=0;i<tokens.length;i++){ total+=parseFloat(tokens[i]); } return total; }
  var wrap=document.getElementById('batch-rows'); if(!wrap) return;
  function rowTemplate(index){
    return '<div class="batch-grid-row" data-row>' +
      '<div><label class="form-label fw-semibold d-lg-none">วันที่</label><input type="date" class="form-control" name="batch['+index+'][entry_date]" value="'+window.batchDefaultDate+'"></div>' +
      '<div><label class="form-label fw-semibold d-lg-none">หมวดหมู่</label><select class="form-select" name="batch['+index+'][category_id]"><option value="">เลือกหมวดหมู่</option>'+window.batchCategoryOptionsHtml+'</select></div>' +
      '<div><label class="form-label fw-semibold d-lg-none">จำนวนเงิน</label><input type="text" class="form-control js-batch-amount" name="batch['+index+'][amount]" placeholder="เช่น 100+50+20"><div class="small text-muted mt-1">รวม: <span class="js-row-total">0.00</span> บาท</div></div>' +
      '<div><label class="form-label fw-semibold d-lg-none">หมายเหตุ</label><textarea class="form-control" name="batch['+index+'][note]" rows="2" placeholder="หมายเหตุ (ถ้ามี)"></textarea></div>' +
      '<div class="batch-row-actions"><button type="button" class="btn btn-outline-secondary btn-sm js-duplicate-row">คัดลอก</button><button type="button" class="btn btn-outline-danger btn-sm js-remove-row">ลบ</button></div>' +
      '</div>';
  }
  function refreshSummary(){
    var rows=wrap.querySelectorAll('[data-row]');
    document.getElementById('batch-row-count').textContent=rows.length;
    var grand=0;
    rows.forEach(function(row){ var input=row.querySelector('.js-batch-amount'); var totalEl=row.querySelector('.js-row-total'); var total=evaluateAmountExpression(input.value); if(isNaN(total)){ totalEl.textContent='-'; } else { totalEl.textContent=total.toFixed(2); grand+=total; } });
    document.getElementById('batch-grand-total').textContent=grand.toFixed(2);
  }
  function addRows(count){ for(var i=0;i<count;i++){ wrap.insertAdjacentHTML('beforeend', rowTemplate(Date.now()+Math.floor(Math.random()*100000)+i)); } refreshSummary(); }
  addRows(3);
  document.getElementById('batch-add-row').addEventListener('click', function(){ addRows(1); });
  document.getElementById('batch-add-5').addEventListener('click', function(){ addRows(5); });
  document.getElementById('batch-copy-date').addEventListener('click', function(){ var first=wrap.querySelector('[name$="[entry_date]"]'); if(!first) return; wrap.querySelectorAll('[name$="[entry_date]"]').forEach(function(el,idx){ if(idx>0) el.value=first.value; }); });
  document.getElementById('batch-copy-category').addEventListener('click', function(){ var first=wrap.querySelector('[name$="[category_id]"]'); if(!first) return; wrap.querySelectorAll('[name$="[category_id]"]').forEach(function(el,idx){ if(idx>0) el.value=first.value; }); });
  wrap.addEventListener('input', function(e){ if(e.target.classList.contains('js-batch-amount')) refreshSummary(); });
  wrap.addEventListener('click', function(e){
    var btn=e.target.closest('button'); if(!btn) return;
    var row=e.target.closest('[data-row]');
    if(btn.classList.contains('js-remove-row')){ if(wrap.querySelectorAll('[data-row]').length>1){ row.remove(); refreshSummary(); } }
    if(btn.classList.contains('js-duplicate-row')){ var clone=row.cloneNode(true); clone.querySelectorAll('input, textarea, select').forEach(function(el){ if(el.tagName==='SELECT'){ } }); wrap.insertBefore(clone, row.nextSibling); refreshSummary(); }
  });
  document.getElementById('batch-add-form').addEventListener('submit', function(){ wrap.querySelectorAll('.js-batch-amount').forEach(function(input){ var total=evaluateAmountExpression(input.value); if(!isNaN(total) && input.value.trim()!==''){ input.value=total.toFixed(2).replace(/\.00$/,''); } }); });
})();
</script>

<div class="stat-row mb-4">
    <div class="stat-card sc-indigo">
        <div class="stat-card-icon ic-indigo"><i class="bi bi-list-ul"></i></div>
        <div class="stat-card-label">รายการทั้งหมด</div>
        <div class="stat-card-value indigo"><?php echo number_format($summary['count']); ?></div>
    </div>
    <div class="stat-card sc-green">
        <div class="stat-card-icon ic-green"><i class="bi bi-arrow-down-circle-fill"></i></div>
        <div class="stat-card-label">รายรับ</div>
        <div class="stat-card-value green">฿<?php echo number_format($summary['income'], 0); ?></div>
    </div>
    <div class="stat-card sc-red">
        <div class="stat-card-icon ic-red"><i class="bi bi-arrow-up-circle-fill"></i></div>
        <div class="stat-card-label">รายจ่าย</div>
        <div class="stat-card-value red">฿<?php echo number_format($summary['expense'], 0); ?></div>
    </div>
    <div class="stat-card sc-purple">
        <div class="stat-card-icon ic-purple"><i class="bi bi-piggy-bank-fill"></i></div>
        <div class="stat-card-label">เงินออม</div>
        <div class="stat-card-value purple">฿<?php echo number_format($summary['saving'], 0); ?></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-4">
        <div class="card card-soft entries-summary-card h-100">
            <div class="card-body p-3 p-lg-4">
                <div class="summary-label mb-3">รายการล่าสุดที่เพิ่ม</div>
                <?php if ($latestEntry): ?>
                    <div class="latest-entry-inline">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <div class="latest-entry-name"><?php echo h($latestEntry['category_name']); ?></div>
                                <div class="text-secondary small mt-1">ล่าสุดจากรายการที่เพิ่มเข้ามา</div>
                            </div>
                            <div class="latest-entry-amount <?php echo h($typeTextClass[$latestEntry['category_type']]); ?>"><?php echo baht($latestEntry['amount']); ?></div>
                        </div>
                        <div class="latest-entry-row">
                            <div class="latest-entry-key">วันที่</div>
                            <div class="latest-entry-value"><?php echo h(date('d/m/', strtotime($latestEntry['entry_date'])) . ((int)date('Y', strtotime($latestEntry['entry_date'])) + 543)); ?></div>
                        </div>
                        <div class="latest-entry-row">
                            <div class="latest-entry-key">ประเภท</div>
                            <div class="latest-entry-value"><span class="latest-entry-tag <?php echo h($latestEntry['category_type']); ?>"><?php echo h($typeLabels[$latestEntry['category_type']]); ?></span></div>
                        </div>
                        <div class="latest-entry-row">
                            <div class="latest-entry-key">หมายเหตุ</div>
                            <div class="latest-entry-value"><?php echo trim((string)$latestEntry['note']) !== '' ? h($latestEntry['note']) : '-'; ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-secondary">ยังไม่มีรายการล่าสุด</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="row g-3">
            <div class="col-12 col-lg-8">
        <div class="card card-soft entries-summary-card is-net h-100">
            <div class="card-body p-3 p-lg-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="summary-label">คงเหลือตามรายการที่แสดง</div>
                    <div class="summary-value <?php echo $displayTotal >= 0 ? 'text-primary' : 'text-expense'; ?>"><?php echo baht($displayTotal); ?></div>
                </div>
                <div class="text-secondary small">
                    คำนวณจาก รายรับ - รายจ่าย - เงินออม
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card card-soft h-100 entries-summary-card">
            <div class="card-body p-3 p-lg-4">
                <div class="summary-label mb-2">ตัวกรองปัจจุบัน</div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge-soft"><?php echo h((string)$yearBE); ?></span>
                    <span class="badge-soft"><?php echo $month > 0 ? h($thaiMonths[$month]) : 'ทั้งปี'; ?></span>
                    <?php if ($type !== ''): ?>
                        <span class="badge-soft <?php echo $type === 'income' ? 'badge-income' : ($type === 'expense' ? 'badge-expense' : 'badge-saving'); ?>">
                            <?php echo $type === 'income' ? 'รายรับ' : ($type === 'expense' ? 'รายจ่าย' : 'เงินออม'); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($categoryId > 0): ?>
                        <?php foreach ($categories as $cat): ?>
                            <?php if ((int)$cat['id'] === $categoryId): ?>
                                <span class="badge-soft"><?php echo h($cat['name']); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($entriesByMonth)): ?>
    <?php foreach ($entriesByMonth as $group): ?>
        <div class="card card-soft entries-month-card mb-4">
            <div class="entries-month-header">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="entries-month-title"><?php echo h($group['label']); ?></h2>
                        <div class="entries-month-meta"><?php echo number_format($group['count']); ?> รายการ</div>
                    </div>
                    <div class="entries-month-totals">
                        <span class="entries-total-pill count">ทั้งหมด <?php echo number_format($group['count']); ?></span>
                        <span class="entries-total-pill income">รับ <?php echo baht($group['income']); ?></span>
                        <span class="entries-total-pill expense">จ่าย <?php echo baht($group['expense']); ?></span>
                        <span class="entries-total-pill saving">ออม <?php echo baht($group['saving']); ?></span>
                    </div>
                </div>
            </div>

            <div class="entries-table-wrap">
                <table class="entries-table mb-0">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>หมวดหมู่</th>
                            <th>ประเภท</th>
                            <th class="text-end">จำนวนเงิน</th>
                            <th>หมายเหตุ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['items'] as $entry): ?>
                            <?php $typeClass = $entry['category_type'] === 'income' ? 'income' : ($entry['category_type'] === 'expense' ? 'expense' : 'saving'); ?>
                            <tr>
                                <td class="entries-date">
                                    <strong><?php echo h(date('d/m/', strtotime($entry['entry_date'])) . ((int)date('Y', strtotime($entry['entry_date'])) + 543)); ?></strong>
                                    <span><?php echo h($thaiMonths[(int)date('n', strtotime($entry['entry_date']))]); ?></span>
                                </td>
                                <td class="fw-semibold"><?php echo h($entry['category_name']); ?></td>
                                <td>
                                    <span class="badge-soft <?php echo $typeClass === 'income' ? 'badge-income' : ($typeClass === 'expense' ? 'badge-expense' : 'badge-saving'); ?>">
                                        <?php echo $typeClass === 'income' ? 'รายรับ' : ($typeClass === 'expense' ? 'รายจ่าย' : 'เงินออม'); ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold <?php echo $typeClass === 'income' ? 'text-income' : ($typeClass === 'expense' ? 'text-expense' : 'text-saving'); ?>">
                                    <?php echo baht($entry['amount']); ?>
                                </td>
                                <td>
                                    <?php $note = trim((string)$entry['note']); ?>
                                    <div class="entries-note <?php echo $note === '' ? 'is-empty' : ''; ?>"><?php echo $note !== '' ? nl2br(h($note)) : '-'; ?></div>
                                </td>
                                <td class="text-center">
                                    <div class="entry-actions">
                                        <a href="edit.php?entry_id=<?php echo (int)$entry['id']; ?>" class="btn btn-sm btn-outline-secondary">แก้ไข</a>
                                        <form method="post" action="save_entry.php" class="m-0" onsubmit="return confirm('ลบรายการนี้ใช่ไหม?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="entry_id" value="<?php echo (int)$entry['id']; ?>">
                                            <input type="hidden" name="year_be" value="<?php echo (int)$yearBE; ?>">
                                            <input type="hidden" name="return_url" value="entries.php?year=<?php echo (int)$yearBE; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="entry-mobile-list">
                <?php foreach ($group['items'] as $entry): ?>
                    <?php $typeClass = $entry['category_type'] === 'income' ? 'income' : ($entry['category_type'] === 'expense' ? 'expense' : 'saving'); ?>
                    <?php $note = trim((string)$entry['note']); ?>
                    <div class="entry-mobile-item">
                        <div class="entry-mobile-top">
                            <div>
                                <div class="entry-mobile-title"><?php echo h($entry['category_name']); ?></div>
                                <div class="entry-mobile-date"><?php echo h(thai_date($entry['entry_date'])); ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold <?php echo $typeClass === 'income' ? 'text-income' : ($typeClass === 'expense' ? 'text-expense' : 'text-saving'); ?>">
                                    <?php echo baht($entry['amount']); ?>
                                </div>
                                <span class="badge-soft <?php echo $typeClass === 'income' ? 'badge-income' : ($typeClass === 'expense' ? 'badge-expense' : 'badge-saving'); ?> mt-1">
                                    <?php echo $typeClass === 'income' ? 'รายรับ' : ($typeClass === 'expense' ? 'รายจ่าย' : 'เงินออม'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="entry-mobile-note <?php echo $note === '' ? 'is-empty' : ''; ?>"><?php echo $note !== '' ? nl2br(h($note)) : 'ไม่มีหมายเหตุ'; ?></div>
                        <div class="entry-actions mt-3 justify-content-start">
                            <a href="edit.php?entry_id=<?php echo (int)$entry['id']; ?>" class="btn btn-sm btn-outline-secondary">แก้ไข</a>
                            <form method="post" action="save_entry.php" class="m-0" onsubmit="return confirm('ลบรายการนี้ใช่ไหม?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="entry_id" value="<?php echo (int)$entry['id']; ?>">
                                <input type="hidden" name="year_be" value="<?php echo (int)$yearBE; ?>">
                                <input type="hidden" name="return_url" value="entries.php?year=<?php echo (int)$yearBE; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card card-soft entries-empty-card">
        <div class="card-body text-center py-5">
            <div class="mb-2 fw-bold fs-5">ยังไม่มีรายการตามเงื่อนไขที่เลือก</div>
            <div class="text-muted mb-3">ลองเปลี่ยนปี เดือน หรือหมวดหมู่ดูอีกครั้ง</div>
            <a href="add.php" class="btn btn-primary">+ เพิ่มรายการแรก</a>
        </div>
    </div>
<?php endif; ?>
<script>
(function () {
  var searchInput  = document.getElementById('entry-search');
  var searchCount  = document.getElementById('search-count');
  if (!searchInput) return;

  searchInput.addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    var tableRows   = document.querySelectorAll('.entries-table tbody tr');
    var mobileItems = document.querySelectorAll('.entry-mobile-item');
    var monthCards  = document.querySelectorAll('.entries-month-card');
    var visible = 0;

    if (q === '') {
      tableRows.forEach(function (r) { r.style.display = ''; });
      mobileItems.forEach(function (r) { r.style.display = ''; });
      monthCards.forEach(function (c) { c.style.display = ''; });
      searchCount.style.display = 'none';
      return;
    }

    // Desktop table rows & mobile items share the same data, match by index
    tableRows.forEach(function (row, i) {
      var text = (row.textContent || '').toLowerCase();
      var show = text.indexOf(q) !== -1;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    mobileItems.forEach(function (item) {
      var text = (item.textContent || '').toLowerCase();
      item.style.display = text.indexOf(q) !== -1 ? '' : 'none';
    });

    // Hide month card if all rows hidden
    monthCards.forEach(function (card) {
      var visRows   = card.querySelectorAll('.entries-table tbody tr:not([style*="none"])');
      var visItems  = card.querySelectorAll('.entry-mobile-item:not([style*="none"])');
      card.style.display = (visRows.length + visItems.length > 0) ? '' : 'none';
    });

    searchCount.textContent = visible + ' รายการ';
    searchCount.style.display = 'inline-flex';
  });
})();
</script>
<?php include 'partials/footer.php'; ?>
