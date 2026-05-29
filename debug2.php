<?php
// Quick charset debug — delete after use
include 'auth.php';
include 'config/db.php';

header('Content-Type: text/plain; charset=UTF-8');

// 1. Connection charset
$rs = mysqli_query($conn, "SHOW VARIABLES LIKE 'character_set_connection'");
$row = mysqli_fetch_assoc($rs);
echo "Connection charset: " . $row['Value'] . "\n\n";

// 2. categories table charset
$rs = mysqli_query($conn, "SHOW CREATE TABLE categories");
$row = mysqli_fetch_assoc($rs);
echo "categories table DDL (truncated):\n";
preg_match('/CHARSET=\S+/', $row['Create Table'], $m);
echo ($m[0] ?? '(not found)') . "\n\n";

// 3. First 5 category names + hex bytes
$userId = (int)$_SESSION['user_id'];
$rs = mysqli_query($conn, "SELECT id, name, is_active FROM categories WHERE user_id={$userId} LIMIT 5");
echo "Categories (id | is_active | name | hex):\n";
while ($r = mysqli_fetch_assoc($rs)) {
    $hex = bin2hex($r['name']);
    $valid = mb_check_encoding($r['name'], 'UTF-8') ? 'valid-utf8' : 'INVALID-utf8';
    echo "{$r['id']} | is_active={$r['is_active']} | [{$r['name']}] | {$valid} | hex={$hex}\n";
}

echo "\nDone.";
