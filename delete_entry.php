<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId  = (int)$_SESSION['user_id'];
$entryId = isset($_POST['entry_id']) ? (int)$_POST['entry_id'] : 0;
$yearBE  = isset($_POST['year_be'])  ? (int)$_POST['year_be']  : ((int)date('Y') + 543);

if ($entryId > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM entries WHERE id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $entryId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: entries.php?year=' . $yearBE);
exit;
