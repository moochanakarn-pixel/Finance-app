<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(0);
ini_set('display_errors', 0);

include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];
$date   = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$gids   = isset($_GET['groups']) && is_array($_GET['groups']) ? $_GET['groups'] : [];

if (!valid_date($date)) {
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

$placeholders = implode(',', array_fill(0, count($groupIds), '?'));
$bindStr = str_repeat('i', count($groupIds)) . 'sii';

$stmt = mysqli_prepare($conn, "
    SELECT e.amount, e.note, c.name AS category_name, c.type AS category_type
    FROM entries e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE c.group_id IN ({$placeholders})
      AND e.entry_date = ?
      AND e.user_id = ? AND c.user_id = ?
      AND c.is_active = 1
    ORDER BY c.type ASC, e.id ASC
");
$bindArgs = array_merge($groupIds, [$date, $userId, $userId]);
mysqli_stmt_bind_param($stmt, $bindStr, ...$bindArgs);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

$entries = [];
while ($row = mysqli_fetch_assoc($rs)) $entries[] = $row;
mysqli_stmt_close($stmt);

if (empty($entries)) {
    echo '<div class="text-muted small py-2 px-3">ไม่มีรายการในวันนี้</div>';
    exit;
}

$typeLabel = ['income' => 'รายรับ', 'expense' => 'รายจ่าย', 'saving' => 'เงินออม'];
$typeClass = ['income' => 'text-income', 'expense' => 'text-expense', 'saving' => 'text-saving'];

echo '<table class="grp-sub-table">';
echo '<thead><tr><th>หมวดหมู่</th><th>ประเภท</th><th class="text-end">จำนวน</th><th>หมายเหตุ</th></tr></thead>';
echo '<tbody>';
foreach ($entries as $e) {
    $cls = $typeClass[$e['category_type']] ?? '';
    $lbl = $typeLabel[$e['category_type']] ?? $e['category_type'];
    echo '<tr>';
    echo '<td class="fw-semibold">' . h($e['category_name']) . '</td>';
    echo '<td><span class="' . $cls . '" style="font-size:.8rem;font-weight:700">' . h($lbl) . '</span></td>';
    echo '<td class="text-end ' . $cls . ' fw-bold">' . h(baht($e['amount'])) . '</td>';
    echo '<td class="text-muted" style="font-size:.85rem">' . h($e['note'] ?: '—') . '</td>';
    echo '</tr>';
}
echo '</tbody></table>';
?>
