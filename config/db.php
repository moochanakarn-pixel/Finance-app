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

// Connection charset: utf8mb4 if available, else utf8
if (!mysqli_set_charset($conn, 'utf8mb4')) {
    mysqli_set_charset($conn, 'utf8');
}
date_default_timezone_set('Asia/Bangkok');

// ── Migration tracker ────────────────────────────────────────────
// Stores which one-time migrations have been applied.
// Using utf8 so the table itself is charset-safe on any server.
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS _dbver (
    k VARCHAR(60) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

function _ran($conn, $k) {
    $r = @mysqli_query($conn, "SELECT 1 FROM _dbver WHERE k='" . $k . "'");
    return $r && mysqli_num_rows($r) > 0;
}
function _done($conn, $k) {
    @mysqli_query($conn, "INSERT IGNORE INTO _dbver (k) VALUES ('" . $k . "')");
}

// ── M1: add budget_amount column ───────────────────────────────────
if (!_ran($conn, 'budget_amount_col')) {
    @mysqli_query($conn, "ALTER TABLE categories ADD COLUMN budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
    _done($conn, 'budget_amount_col');
}

// ── M2: set DEFAULT CHARSET=utf8 on all tables ───────────────────────
// ALTER TABLE … DEFAULT CHARACTER SET changes the default for NEW columns only.
// It does NOT re-encode existing column data — completely safe.
// Critical: prevents server's default TIS-620 charset from applying to new columns.
if (!_ran($conn, 'all_tables_utf8')) {
    $tables = ['categories', 'entries', 'users'];
    foreach ($tables as $t) {
        @mysqli_query($conn, "ALTER TABLE `{$t}` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci");
    }
    _done($conn, 'all_tables_utf8');
}

?>
