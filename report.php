<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId   = (int)$_SESSION['user_id'];
$page_title = 'รายงานภาพรวม';

$requestedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
if ($requestedYear > 2400) {
    $selectedBE = $requestedYear;
    $selectedAD = $requestedYear - 543;
} elseif ($requestedYear > 1900) {
    $selectedAD = $requestedYear;
    $selectedBE = $requestedYear + 543;
} else {
    $selectedAD = (int)date('Y');
    $selectedBE = $selectedAD + 543;
}
