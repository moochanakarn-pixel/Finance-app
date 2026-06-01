<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(0);
ini_set('display_errors', 0);

include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$month      = isset($_GET['month'])       ? (int)$_GET['month']       : 0;
$yearBE     = isset($_GET['year'])        ? (int)$_GET['year']        : 0;

if ($yearBE > 2400) {
    $yearAD = $yearBE - 543;
} else {
    $yearAD = $yearBE;
    $yearBE = $yearAD + 543;
}

if ($categoryId <= 0 || $month < 1 || $month > 12 || $yearAD <= 0) {
    echo '<div style="color:#dc2626;font-weight:700;">ข้อมูลไม่ถูกต้อง</div>';
    exit;
}

$dateFrom = sprintf('%04d-%02d-01', $yearAD, $month);
$dateTo   = date('Y-m-t', strtotime($dateFrom));

$stmt = mysqli_prepare($conn, "
    SELECT
        e.id,
        e.entry_date,
        e.amount,
        e.note,
        c.name AS category_name,
        c.type AS category_type
    FROM entries e
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE e.category_id = ?
      AND e.entry_date BETWEEN ? AND ?
      AND e.user_id = ?
      AND c.user_id = ?
    ORDER BY e.entry_date ASC, e.id ASC
");
mysqli_stmt_bind_param($stmt, 'issii', $categoryId, $dateFrom, $dateTo, $userId, $userId);
mysqli_stmt_execute($stmt);
$rsEntries = mysqli_stmt_get_result($stmt);

$entries = array();
$totalAmount = 0;

if ($rsEntries) {
    while ($row = mysqli_fetch_assoc($rsEntries)) {
        $entries[] = $row;
        $totalAmount += (float)$row['amount'];
    }
}

echo '<div class="detail-summary">';
echo '<div class="detail-box">';
echo '<div class="detail-label">จำนวนรายการ</div>';
echo '<div class="detail-value">' . count($entries) . '</div>';
echo '</div>';

echo '<div class="detail-box">';
echo '<div class="detail-label">รวมทั้งเดือน</div>';
echo '<div class="detail-value">' . h(baht($totalAmount)) . '</div>';
echo '</div>';

echo '<div class="detail-box">';
echo '<div class="detail-label">ปี/เดือน</div>';
echo '<div class="detail-value">' . h($month . '/' . $yearBE) . '</div>';
echo '</div>';
echo '</div>';

echo '<div class="entry-card">';
echo '<form method="post" action="save_entry.php" class="inline-form">';
echo '<input type="hidden" name="action" value="add">';
echo '<input type="hidden" name="category_id" value="' . (int)$categoryId . '">';
echo '<input type="hidden" name="month" value="' . (int)$month . '">';
echo '<input type="hidden" name="year_be" value="' . (int)$yearBE . '">';
echo '<input type="hidden" name="return_url" value="index.php?year=' . (int)$yearBE . '">';

$todayAD = date('Y-m-d');
$defaultDate = ((int)date('Y') === $yearAD && (int)date('n') === $month)
    ? $todayAD
    : ($yearAD . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01');
echo '<div class="inline-row"><label>วันที่</label><input type="date" name="entry_date" value="' . h($defaultDate) . '" required></div>';
echo '<small style="color:#94a3b8;font-size:11px;grid-column:2">ปีเป็น ค.ศ. (' . $yearAD . ')</small>';
echo '<div class="inline-row"><label>จำนวนเงิน</label><input type="number" name="amount" step="0.01" min="0.01" required></div>';
echo '<div class="inline-row"><label>หมายเหตุ</label><textarea name="note"></textarea></div>';
echo '<div class="entry-actions"><button type="submit" class="btn btn-success">+ เพิ่มรายการ</button></div>';
echo '</form>';
echo '</div>';

if (empty($entries)) {
    echo '<div style="color:#9ca3af;font-weight:700;">ยังไม่มีรายการในเดือนนี้</div>';
    exit;
}

foreach ($entries as $item) {
    echo '<div class="entry-card">';
    echo '<div class="entry-head">';
    $d = strtotime($item['entry_date']);
    echo '<strong>' . h(date('d/m/', $d) . ((int)date('Y', $d) + 543)) . '</strong>';
    echo '<strong>' . h(baht($item['amount'])) . '</strong>';
    echo '</div>';

    echo '<form method="post" action="save_entry.php" class="inline-form">';
    echo '<input type="hidden" name="action" value="update">';
    echo '<input type="hidden" name="entry_id" value="' . (int)$item['id'] . '">';
    echo '<input type="hidden" name="category_id" value="' . (int)$categoryId . '">';
    echo '<input type="hidden" name="month" value="' . (int)$month . '">';
    echo '<input type="hidden" name="year_be" value="' . (int)$yearBE . '">';
    echo '<input type="hidden" name="return_url" value="index.php?year=' . (int)$yearBE . '">';

    echo '<div class="inline-row"><label>วันที่</label><input type="date" name="entry_date" value="' . h($item['entry_date']) . '" required></div>';
    echo '<small style="color:#94a3b8;font-size:11px;grid-column:2">ปีเป็น ค.ศ. (' . $yearAD . ')</small>';
    echo '<div class="inline-row"><label>จำนวนเงิน</label><input type="number" name="amount" step="0.01" min="0.01" value="' . h($item['amount']) . '" required></div>';
    echo '<div class="inline-row"><label>หมายเหตุ</label><textarea name="note">' . h($item['note']) . '</textarea></div>';
    echo '<div class="entry-actions">';
    echo '<button type="submit" class="btn btn-primary">บันทึก</button>';
    echo '</div>';
    echo '</form>';

    echo '<form method="post" action="save_entry.php" class="js-delete-form" style="margin-top:8px;">';
    echo '<input type="hidden" name="action" value="delete">';
    echo '<input type="hidden" name="entry_id" value="' . (int)$item['id'] . '">';
    echo '<input type="hidden" name="category_id" value="' . (int)$categoryId . '">';
    echo '<input type="hidden" name="month" value="' . (int)$month . '">';
    echo '<input type="hidden" name="year_be" value="' . (int)$yearBE . '">';
    echo '<input type="hidden" name="return_url" value="index.php?year=' . (int)$yearBE . '">';
    echo '<div class="js-delete-stage1"><button type="button" class="btn btn-danger js-delete-confirm-btn" style="font-size:12px;padding:6px 12px">ลบรายการนี้</button></div>';
    echo '<div class="js-delete-stage2" style="display:none;gap:6px;align-items:center"><span style="font-size:12px;color:#dc2626;font-weight:700">ยืนยันลบ?</span><button type="submit" class="btn btn-danger" style="font-size:12px;padding:6px 12px">ลบเลย</button><button type="button" class="btn js-delete-cancel-btn" style="font-size:12px;padding:6px 12px;background:#f1f5f9">ยกเลิก</button></div>';
    echo '</form>';

    echo '</div>';
}
?>
<script>
document.querySelectorAll('.js-delete-confirm-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        var form = btn.closest('.js-delete-form');
        form.querySelector('.js-delete-stage1').style.display='none';
        form.querySelector('.js-delete-stage2').style.display='flex';
    });
});
document.querySelectorAll('.js-delete-cancel-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        var form = btn.closest('.js-delete-form');
        form.querySelector('.js-delete-stage1').style.display='';
        form.querySelector('.js-delete-stage2').style.display='none';
    });
});
</script>