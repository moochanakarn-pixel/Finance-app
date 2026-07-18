<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(0);
ini_set('display_errors', 0);

include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];
$yearAD = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$month  = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$gids   = isset($_GET['groups']) && is_array($_GET['groups']) ? $_GET['groups'] : [];

if ($yearAD < 1900 || $month < 1 || $month > 12) {
    echo '<div class="text-danger small py-2 ps-3">ข้อมูลไม่ถูกต้อง</div>';
    exit;
}

$groupIds = [];
foreach ($gids as $g) {
    $g = (int)$g;
    if ($g > 0) $groupIds[] = $g;
}
if (empty($groupIds)) {
    echo '<div class="text-muted small py-2 ps-3">ไม่มีกลุ่มที่เลือก</div>';
    exit;
}

$dateFrom     = sprintf('%04d-%02d-01', $yearAD, $month);
$dateTo       = date('Y-m-t', strtotime($dateFrom));
$placeholders = implode(',', array_fill(0, count($groupIds), '?'));
$bindStr      = str_repeat('i', count($groupIds)) . 'ssii';

$stmt = mysqli_prepare($conn, "
    SELECT e.entry_date, c.type, SUM(e.amount) AS total
    FROM entries e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE c.group_id IN ({$placeholders})
      AND e.entry_date BETWEEN ? AND ?
      AND e.user_id = ? AND c.user_id = ?
      AND c.is_active = 1
    GROUP BY e.entry_date, c.type
    ORDER BY e.entry_date DESC
");
if (!$stmt) {
    echo '<div class="text-danger small py-2 ps-3">เกิดข้อผิดพลาด</div>';
    exit;
}
$bindArgs = array_merge($groupIds, [$dateFrom, $dateTo, $userId, $userId]);
mysqli_stmt_bind_param($stmt, $bindStr, ...$bindArgs);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

$daily = [];
if ($rs) while ($row = mysqli_fetch_assoc($rs)) {
    $d  = $row['entry_date'];
    $tp = $row['type'];
    if (!isset($daily[$d])) $daily[$d] = ['income' => 0.0, 'expense' => 0.0, 'saving' => 0.0];
    if (isset($daily[$d][$tp])) $daily[$d][$tp] += (float)$row['total'];
}
mysqli_stmt_close($stmt);

if (empty($daily)) {
    echo '<div class="text-muted small py-3 px-3">ไม่มีข้อมูลในเดือนนี้</div>';
    exit;
}

$today        = date('Y-m-d');
$groupsParam  = h(http_build_query(['groups' => $groupIds]));
$totInc = $totExp = $totSav = 0;

echo '<table class="grp-sub-table grp-daily-inline">';
echo '<thead><tr>';
echo '<th>วันที่</th>';
echo '<th class="text-end">รายรับ</th>';
echo '<th class="text-end">รายจ่าย</th>';
echo '<th class="text-end">เงินออม</th>';
echo '<th class="text-end">คงเหลือ</th>';
echo '</tr></thead>';
echo '<tbody>';

foreach ($daily as $d => $r) {
    $dNet    = $r['income'] - $r['expense'] - $r['saving'];
    $ts      = strtotime($d);
    $dateBE  = date('d/m/', $ts) . ((int)date('Y', $ts) + 543);
    $isToday = ($d === $today);
    $totInc += $r['income']; $totExp += $r['expense']; $totSav += $r['saving'];

    $rowClass = 'grp-day-row' . ($isToday ? ' today-row' : '');
    echo '<tr class="' . $rowClass . '"'
        . ' data-type="day"'
        . ' data-date="' . h($d) . '"'
        . ' data-groups="' . $groupsParam . '">';
    echo '<td class="fw-semibold"><i class="bi bi-chevron-right ci me-1"></i>' . h($dateBE);
    if ($isToday) echo '<span class="badge-soft ms-1" style="font-size:.7rem">วันนี้</span>';
    echo '</td>';
    echo '<td class="text-end ' . ($r['income'] > 0 ? 'text-income fw-semibold' : 'text-muted') . '">'
        . ($r['income'] > 0 ? baht($r['income']) : '-') . '</td>';
    echo '<td class="text-end ' . ($r['expense'] > 0 ? 'text-expense fw-semibold' : 'text-muted') . '">'
        . ($r['expense'] > 0 ? baht($r['expense']) : '-') . '</td>';
    echo '<td class="text-end ' . ($r['saving'] > 0 ? 'text-saving fw-semibold' : 'text-muted') . '">'
        . ($r['saving'] > 0 ? baht($r['saving']) : '-') . '</td>';
    echo '<td class="text-end fw-bold ' . ($dNet >= 0 ? 'text-net-pos' : 'text-net-neg') . '">'
        . ($dNet >= 0 ? '+' : '') . baht($dNet) . '</td>';
    echo '</tr>';
}

$totNet = $totInc - $totExp - $totSav;
echo '<tr class="total-row">';
echo '<td>รวมเดือนนี้</td>';
echo '<td class="text-end text-income">' . baht($totInc) . '</td>';
echo '<td class="text-end text-expense">' . baht($totExp) . '</td>';
echo '<td class="text-end text-saving">' . baht($totSav) . '</td>';
echo '<td class="text-end ' . ($totNet >= 0 ? 'text-net-pos' : 'text-net-neg') . '">'
    . ($totNet >= 0 ? '+' : '') . baht($totNet) . '</td>';
echo '</tr>';
echo '</tbody></table>';

?>
