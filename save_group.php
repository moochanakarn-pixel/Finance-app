<?php
header('Content-Type: text/html; charset=UTF-8');

include_once 'auth.php';
include_once 'config/db.php';
include_once 'config/functions.php';

$userId   = (int)$_SESSION['user_id'];
$action   = isset($_POST['action'])   ? trim($_POST['action'])   : '';
$groupId  = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

$returnUrl = build_return_url('groups.php');

// ── helpers ──────────────────────────────────────────────────────────────
function parse_group_name($raw) {
    return trim(preg_replace('/\s+/u', ' ', (string)$raw));
}
function parse_group_code($raw) {
    return trim((string)$raw);
}
// ─────────────────────────────────────────────────────────────────────────

if ($action === 'add') {

    $name      = parse_group_name($_POST['name'] ?? '');
    $code      = parse_group_code($_POST['code'] ?? '');
    $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    if ($name === '') die('กรุณากรอกชื่อกลุ่ม');
    if ($code === '') die('กรุณากรอกรหัสกลุ่ม');

    $stmt = mysqli_prepare($conn,
        'INSERT INTO category_groups (user_id, name, code, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())');
    mysqli_stmt_bind_param($stmt, 'issi', $userId, $name, $code, $sortOrder);
    mysqli_stmt_execute($stmt);
    $errno    = mysqli_errno($conn);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($errno === 1062) {
        $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
        redirect($returnUrl . $glue . 'error=duplicate_code');
    }
    $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
    redirect($returnUrl . $glue . ($affected > 0 ? 'success=added' : 'error=failed'));

} elseif ($action === 'update') {

    if ($groupId <= 0) die('ข้อมูลไม่ถูกต้อง');

    $name      = parse_group_name($_POST['name'] ?? '');
    $code      = parse_group_code($_POST['code'] ?? '');
    $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    if ($name === '') die('กรุณากรอกชื่อกลุ่ม');
    if ($code === '') die('กรุณากรอกรหัสกลุ่ม');

    $stmt = mysqli_prepare($conn,
        'UPDATE category_groups SET name = ?, code = ?, sort_order = ?
          WHERE id = ? AND user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ssiii', $name, $code, $sortOrder, $groupId, $userId);
    mysqli_stmt_execute($stmt);
    $errno = mysqli_errno($conn);
    mysqli_stmt_close($stmt);

    if ($errno === 1062) {
        $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
        redirect($returnUrl . $glue . 'error=duplicate_code');
    }
    $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
    redirect($returnUrl . $glue . 'success=updated');

} elseif ($action === 'delete') {

    if ($groupId <= 0) die('ข้อมูลไม่ถูกต้อง');

    // Remove group membership from categories
    $stmt = mysqli_prepare($conn,
        'UPDATE categories SET group_id = NULL WHERE group_id = ? AND user_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $groupId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Delete the group
    $stmt = mysqli_prepare($conn,
        'DELETE FROM category_groups WHERE id = ? AND user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $groupId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
    redirect($returnUrl . $glue . 'success=deleted');

} elseif ($action === 'assign') {

    if ($groupId <= 0) die('ข้อมูลไม่ถูกต้อง');

    // Verify group belongs to user
    $stmt = mysqli_prepare($conn, 'SELECT id FROM category_groups WHERE id = ? AND user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $groupId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $foundId);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$foundId) die('ไม่พบกลุ่ม');

    // Clear current members of this group
    $stmt = mysqli_prepare($conn,
        'UPDATE categories SET group_id = NULL WHERE group_id = ? AND user_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $groupId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Assign selected categories
    $catIds = [];
    if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
        foreach ($_POST['category_ids'] as $cid) {
            $cid = (int)$cid;
            if ($cid > 0) $catIds[] = $cid;
        }
    }

    if (!empty($catIds)) {
        $placeholders = implode(',', array_fill(0, count($catIds), '?'));
        $bindStr      = 'ii' . str_repeat('i', count($catIds));
        $stmt = mysqli_prepare($conn,
            "UPDATE categories SET group_id = ? WHERE user_id = ? AND id IN ({$placeholders}) AND is_active = 1");
        $bindArgs = array_merge([$groupId, $userId], $catIds);
        mysqli_stmt_bind_param($stmt, $bindStr, ...$bindArgs);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
    redirect($returnUrl . $glue . 'success=assigned&assign_id=' . $groupId);
}

redirect($returnUrl);
