<?php
include 'auth.php';
include 'config/db.php';

$results = [];

// 1. Check group_tag column
$r = @mysqli_query($conn, "SHOW COLUMNS FROM categories LIKE 'group_tag'");
$colExists = $r && mysqli_num_rows($r) > 0;
$results[] = 'group_tag column: ' . ($colExists ? '✅ exists' : '❌ MISSING');

// 2. Try to add it if missing
if (!$colExists) {
    $ok = @mysqli_query($conn, "ALTER TABLE categories ADD COLUMN group_tag VARCHAR(50) CHARACTER SET utf8 NULL DEFAULT NULL");
    $err = mysqli_error($conn);
    if ($ok) {
        $results[] = 'ALTER TABLE: ✅ success — column added';
    } else {
        $results[] = 'ALTER TABLE: ❌ failed — ' . $err;
    }
    $r2 = @mysqli_query($conn, "SHOW COLUMNS FROM categories LIKE 'group_tag'");
    $results[] = 'Re-check after ALTER: ' . ($r2 && mysqli_num_rows($r2) > 0 ? '✅ column now exists' : '❌ still missing');
}

// 3. Check _dbver
$rv = @mysqli_query($conn, "SELECT k FROM _dbver WHERE k = 'group_tag_col'");
$results[] = '_dbver group_tag_col: ' . ($rv && mysqli_num_rows($rv) > 0 ? '✅ marked done' : '❌ not marked');

// 4. Test prepare() with group_tag
$stmt = mysqli_prepare($conn, "SELECT id, group_tag FROM categories WHERE user_id = 1 LIMIT 1");
$results[] = 'prepare() with group_tag: ' . ($stmt ? '✅ success' : '❌ failed — ' . mysqli_error($conn));
if ($stmt) mysqli_stmt_close($stmt);

// 5. MySQL version
$ver = mysqli_get_server_info($conn);
$results[] = 'MySQL version: ' . $ver;

// 6. PHP version
$results[] = 'PHP version: ' . PHP_VERSION;

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $results) . "\n";
