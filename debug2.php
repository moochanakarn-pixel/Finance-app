<?php
// Quick charset debug — delete after use
include 'auth.php';
include 'config/db.php';

header('Content-Type: text/plain; charset=UTF-8');

// 1. Connection charset
$rs = mysqli_query($conn, "SHOW VARIABLES LIKE 'character_set_connection'");
$row = mysqli_fetch_assoc($rs);
echo "Connection charset: " . $row['Value'] . "\n";

// 2. Session user_id
$userId = (int)($_SESSION['user_id'] ?? 0);
echo "Session user_id: " . $userId . "\n\n";

// 3. categories table charset
$rs = mysqli_query($conn, "SHOW CREATE TABLE categories");
$row = mysqli_fetch_assoc($rs);
echo "categories table DDL (truncated):\n";
preg_match('/CHARSET=\S+/', $row['Create Table'], $m);
echo ($m[0] ?? '(not found)') . "\n\n";

// 4. All categories for this user (no is_active filter)
$rs = mysqli_query($conn, "SELECT id, name, is_active FROM categories WHERE user_id={$userId} LIMIT 10");
echo "Categories for user_id={$userId}:\n";
$count = 0;
if ($rs) {
    while ($r = mysqli_fetch_assoc($rs)) {
        $count++;
        $hex   = bin2hex($r['name']);
        $valid = mb_check_encoding($r['name'], 'UTF-8') ? 'valid-utf8' : 'INVALID-utf8';
        echo "{$r['id']} | is_active={$r['is_active']} | [{$r['name']}] | {$valid} | hex={$hex}\n";
    }
}
if ($count === 0) echo "(no rows)\n";

// 5. Total rows in categories table (all users)
$rs = mysqli_query($conn, "SELECT COUNT(*) AS n FROM categories");
$row = mysqli_fetch_assoc($rs);
echo "\nTotal rows in categories table (all users): " . $row['n'] . "\n";

echo "\nDone.";
