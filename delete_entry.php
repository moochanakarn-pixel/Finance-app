<?php
header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'auth.php';
include 'config/db.php';
mysqli_set_charset($conn, 'utf8');

$userId = (int)$_SESSION['user_id'];
$entryId = isset($_POST['entry_id']) ? (int)$_POST['entry_id'] : 0;
$yearBE = isset($_POST['year_be']) ? (int)$_POST['year_be'] : ((int)date('Y') + 543);

if ($entryId > 0) {
    mysqli_query($conn, "
        DELETE FROM entries
        WHERE id = {$entryId}
          AND user_id = {$userId}
        LIMIT 1
    ");
}

header('Location: entries.php?year=' . $yearBE);
exit;