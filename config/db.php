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

// Fall back to utf8 if server doesn't support utf8mb4
if (!mysqli_set_charset($conn, 'utf8mb4')) {
    mysqli_set_charset($conn, 'utf8');
}
date_default_timezone_set('Asia/Bangkok');

// One-time schema migration: add budget_amount column if missing
@mysqli_query($conn, "ALTER TABLE categories ADD COLUMN budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0");

// Create notes table
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    category VARCHAR(20) NOT NULL DEFAULT 'other',
    note_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notes_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

// Create vocab table
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS vocab (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    word VARCHAR(255) NOT NULL,
    meaning TEXT NOT NULL,
    example TEXT,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vocab_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
?>