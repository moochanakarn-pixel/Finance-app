<?php
include 'auth.php';
include 'config/db.php';
header('Content-Type: text/plain; charset=UTF-8');

echo "=== Session ===\n";
echo "full_name in session: " . ($_SESSION['full_name'] ?? '(empty)') . "\n";
echo "username in session: " . ($_SESSION['username'] ?? '(empty)') . "\n";
echo "HEX of session full_name: " . bin2hex($_SESSION['full_name'] ?? '') . "\n\n";

echo "=== DB Query ===\n";
$r = mysqli_query($conn, "SELECT id, username, full_name, HEX(full_name) as hex_name FROM users WHERE id = " . (int)$_SESSION['user_id']);
$row = mysqli_fetch_assoc($r);
echo "full_name from DB: " . $row['full_name'] . "\n";
echo "HEX from DB: " . $row['hex_name'] . "\n\n";

echo "=== Charset Check ===\n";
$r2 = mysqli_query($conn, "SELECT CHARSET(full_name) as cs, COLLATION(full_name) as co FROM users WHERE id = " . (int)$_SESSION['user_id']);
$row2 = mysqli_fetch_assoc($r2);
echo "Column charset: " . $row2['cs'] . "\n";
echo "Column collation: " . $row2['co'] . "\n\n";

echo "=== Connection Charset ===\n";
$r3 = mysqli_query($conn, "SHOW VARIABLES LIKE 'character_set_connection'");
$row3 = mysqli_fetch_assoc($r3);
echo "Connection charset: " . $row3['Value'] . "\n";
