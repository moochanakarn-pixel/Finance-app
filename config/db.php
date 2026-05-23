<?php
$_env_file = __DIR__ . '/../.env';
if (file_exists($_env_file)) {
    foreach (file($_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_ENV[trim($_k)] = trim($_v);
    }
}

$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? 'finance';
$DB_PORT = (int)($_ENV['DB_PORT'] ?? 3306);

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if (!$conn) {
    http_response_code(500);
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ');
}

mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set('Asia/Bangkok');
?>
