<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = 'pospwnet';
$DB_NAME = 'finance';
$DB_PORT = 3307;

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if (!$conn) {
    http_response_code(500);
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ');
}

mysqli_set_charset($conn, 'utf8');
date_default_timezone_set('Asia/Bangkok');

// One-time schema migration: add budget_amount column if missing
@mysqli_query($conn, "ALTER TABLE categories ADD COLUMN budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
?>
