<?php
header('Content-Type: text/html; charset=UTF-8');

include_once 'auth.php';
include_once 'config/db.php';
include_once 'config/functions.php';

$userId = (int)$_SESSION['user_id'];

$action        = isset($_POST['action'])         ? trim($_POST['action'])        : 'update';
$categoryId    = isset($_POST['category_id'])    ? (int)$_POST['category_id']   : 0;
$categoryName  = isset($_POST['category_name'])  ? trim($_POST['category_name']) : '';
$categoryType  = isset($_POST['category_type'])  ? trim($_POST['category_type']) : 'expense';
$sortOrder     = isset($_POST['sort_order'])     ? (int)$_POST['sort_order']     : 0;
$budgetAmount  = isset($_POST['budget_amount'])  ? max(0, (float)$_POST['budget_amount']) : 0;
$returnYear    = isset($_POST['return_year'])    ? (int)$_POST['return_year']    : ((int)date('Y') + 543);

// ── Validate type ──────────────────────────────────────────────
$allowedTypes = ['income', 'expense', 'saving'];
if (!in_array($categoryType, $allowedTypes, true)) {
    $categoryType = 'expense';
}

// ── Sanitize name ──────────────────────────────────────────────
$categoryName = trim(preg_replace('/\s+/u', ' ', $categoryName));
if ($action !== 'delete') {
    if ($categoryName === '') die('กรุณากรอกชื่อหมวด');
    if (!preg_match('/[\p{L}\p{N}]/u', $categoryName)) {
        die('ชื่อหมวดต้องมีตัวอักษรหรือตัวเลขอย่างน้อย 1 ตัว');
    }
}

// ── Safe return URL (reuse existing build_return_url from functions.php) ───────
$fallback  = 'index.php?year=' . $returnYear;
$returnUrl = build_return_url($fallback);

// ── Actions — all use Prepared Statements ─────────────────────────────────
$success = '';

if ($action === 'add') {

    $stmt = mysqli_prepare($conn,
        'INSERT INTO categories (name, type, sort_order, budget_amount, is_active, created_at, updated_at, user_id)
         VALUES (?, ?, ?, ?, 1, NOW(), NULL, ?)');
    mysqli_stmt_bind_param($stmt, 'ssidi', $categoryName, $categoryType, $sortOrder, $budgetAmount, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $success = 'added';

} elseif ($action === 'update') {

    if ($categoryId <= 0) die('ข้อมูลไม่ถูกต้อง');

    $stmt = mysqli_prepare($conn,
        'UPDATE categories
            SET name = ?, type = ?, sort_order = ?, budget_amount = ?, updated_at = NOW()
          WHERE id = ? AND user_id = ?
          LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ssidii', $categoryName, $categoryType, $sortOrder, $budgetAmount, $categoryId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $success = 'updated';

} elseif ($action === 'delete') {

    if ($categoryId <= 0) die('ข้อมูลไม่ถูกต้อง');

    $stmt = mysqli_prepare($conn,
        'SELECT COUNT(*) FROM entries WHERE category_id = ? AND user_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $categoryId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $hasEntries);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($hasEntries > 0) {
        $stmt = mysqli_prepare($conn,
            'UPDATE categories SET is_active = 0, updated_at = NOW()
              WHERE id = ? AND user_id = ? LIMIT 1');
    } else {
        $stmt = mysqli_prepare($conn,
            'DELETE FROM categories WHERE id = ? AND user_id = ? LIMIT 1');
    }
    mysqli_stmt_bind_param($stmt, 'ii', $categoryId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $success = 'deleted';
}

if ($success !== '') {
    $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
    $returnUrl .= $glue . 'success=' . $success;
}

redirect($returnUrl);