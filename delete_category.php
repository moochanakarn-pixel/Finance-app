<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId     = (int)$_SESSION['user_id'];
$categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

if ($categoryId <= 0) {
    header('Location: categories.php');
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total_rows FROM entries WHERE category_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $categoryId, $userId);
mysqli_stmt_execute($stmt);
$row        = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$hasEntries = (int)$row['total_rows'];
mysqli_stmt_close($stmt);

if ($hasEntries > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE categories SET is_active = 0, updated_at = NOW() WHERE id = ? AND user_id = ? LIMIT 1");
} else {
    $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ? AND user_id = ? LIMIT 1");
}
mysqli_stmt_bind_param($stmt, 'ii', $categoryId, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: categories.php?success=deleted');
exit;
