<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'auth.php';
include 'config/db.php';

$userId = (int)$_SESSION['user_id'];
$categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

if ($categoryId <= 0) {
    header('Location: categories.php');
    exit;
}

$rsCheck = mysqli_query($conn, "
    SELECT COUNT(*) AS total_rows
    FROM entries
    WHERE category_id = {$categoryId}
      AND user_id = {$userId}
");
$hasEntries = 0;

if ($rsCheck) {
    $row = mysqli_fetch_assoc($rsCheck);
    $hasEntries = (int)$row['total_rows'];
}

if ($hasEntries > 0) {
    mysqli_query($conn, "
        UPDATE categories
        SET is_active = 0,
            updated_at = NOW()
        WHERE id = {$categoryId}
          AND user_id = {$userId}
        LIMIT 1
    ");
} else {
    mysqli_query($conn, "
        DELETE FROM categories
        WHERE id = {$categoryId}
          AND user_id = {$userId}
        LIMIT 1
    ");
}

header('Location: categories.php?success=deleted');
exit;