<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$entry_date = isset($_POST['entry_date']) ? trim($_POST['entry_date']) : '';
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

if ($id <= 0 || $category_id <= 0 || !valid_date($entry_date) || $amount <= 0) {
    die('ข้อมูลไม่ครบ');
}

$stmt = mysqli_prepare($conn, 'SELECT id FROM categories WHERE id = ? AND user_id = ? AND is_active = 1 LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $category_id, $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$categoryOk = $result && mysqli_num_rows($result) > 0;
mysqli_stmt_close($stmt);

if (!$categoryOk) {
    die('หมวดหมู่ไม่ถูกต้อง');
}

$stmt = mysqli_prepare($conn, 'UPDATE entries SET category_id = ?, entry_date = ?, amount = ?, note = ?, updated_at = NOW() WHERE id = ? AND user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'isdsii', $category_id, $entry_date, $amount, $note, $id, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

redirect('entries.php');
?>
