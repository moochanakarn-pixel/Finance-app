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

mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set('Asia/Bangkok');

// One-time schema migration: add budget_amount column if missing
@mysqli_query($conn, "ALTER TABLE categories ADD COLUMN budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0");

// Create notes table (utf8mb4 supports emoji)
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    category VARCHAR(20) NOT NULL DEFAULT 'other',
    note_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notes_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Upgrade existing table charset if needed
@mysqli_query($conn, "ALTER TABLE notes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Create vocab table (utf8mb4 for consistency)
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS vocab (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    word VARCHAR(255) NOT NULL,
    meaning TEXT NOT NULL,
    example TEXT,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vocab_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
@mysqli_query($conn, "ALTER TABLE vocab CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Upgrade users + other tables to utf8mb4 if still on utf8/latin1
// Uses MODIFY on full_name to re-cast bytes as utf8mb4 without double-encoding
@mysqli_query($conn, "ALTER TABLE users
    MODIFY full_name VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
?>
