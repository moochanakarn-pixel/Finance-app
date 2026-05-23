<?php
header('Content-Type: text/html; charset=UTF-8');
include_once 'auth.php';
include_once 'config/db.php';
include_once 'config/functions.php';
mysqli_set_charset($conn, 'utf8');

$userId = (int)($_SESSION['user_id'] ?? 0);
$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$entryId = isset($_POST['entry_id']) ? (int)$_POST['entry_id'] : 0;
$categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$entryDate = isset($_POST['entry_date']) ? trim((string)$_POST['entry_date']) : '';
$amount = 0;
$note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';
$yearBE = isset($_POST['year_be']) ? (int)$_POST['year_be'] : ((int)date('Y') + 543);

$returnUrl = build_return_url(isset($_POST['return_url']) ? trim((string)$_POST['return_url']) : ('entries.php?year=' . $yearBE));
$amount = parse_amount_expression(isset($_POST['amount']) ? $_POST['amount'] : '');


function parse_amount_expression($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return 0;
    }
    $raw = str_replace(array(',', ' '), '', $raw);
    if (!preg_match('/^[0-9+\-.]+$/', $raw)) {
        return 0;
    }
    preg_match_all('/[+\-]?\d+(?:\.\d+)?/', $raw, $matches);
    if (empty($matches[0])) {
        return 0;
    }
    $total = 0.0;
    foreach ($matches[0] as $piece) {
        $total += (float)$piece;
    }
    return $total;
}

function category_belongs_to_user($conn, $categoryId, $userId, $allowInactive = false)
{
    if ($categoryId <= 0 || $userId <= 0) {
        return false;
    }

    $sql = 'SELECT id FROM categories WHERE id = ? AND user_id = ?';
    if (!$allowInactive) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $categoryId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ok = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $ok;
}

function entry_belongs_to_user($conn, $entryId, $userId)
{
    if ($entryId <= 0 || $userId <= 0) {
        return false;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id FROM entries WHERE id = ? AND user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $entryId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ok = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $ok;
}

if ($action === 'add') {
    if ($categoryId > 0 && valid_date($entryDate) && $amount > 0 && category_belongs_to_user($conn, $categoryId, $userId, false)) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO entries (category_id, user_id, entry_date, amount, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NULL)');
        mysqli_stmt_bind_param($stmt, 'iisds', $categoryId, $userId, $entryDate, $amount, $note);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
        $returnUrl .= $glue . 'saved=1';
    }
} elseif ($action === 'update') {
    if ($entryId > 0 && valid_date($entryDate) && $amount > 0 && entry_belongs_to_user($conn, $entryId, $userId)) {
        if ($categoryId > 0) {
            if (category_belongs_to_user($conn, $categoryId, $userId, true)) {
                $stmt = mysqli_prepare($conn, 'UPDATE entries SET category_id = ?, entry_date = ?, amount = ?, note = ?, updated_at = NOW() WHERE id = ? AND user_id = ? LIMIT 1');
                mysqli_stmt_bind_param($stmt, 'isdsii', $categoryId, $entryDate, $amount, $note, $entryId, $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
                $returnUrl .= $glue . 'updated=1';
            }
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE entries SET entry_date = ?, amount = ?, note = ?, updated_at = NOW() WHERE id = ? AND user_id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'sdsii', $entryDate, $amount, $note, $entryId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
            $returnUrl .= $glue . 'updated=1';
        }
    }
} elseif ($action === 'batch_add') {
    $items = isset($_POST['batch']) && is_array($_POST['batch']) ? $_POST['batch'] : array();
    $savedCount = 0;
    if (!empty($items)) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO entries (category_id, user_id, entry_date, amount, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NULL)');
        foreach ($items as $item) {
            $rowCategoryId = isset($item['category_id']) ? (int)$item['category_id'] : 0;
            $rowEntryDate = isset($item['entry_date']) ? trim((string)$item['entry_date']) : '';
            $rowAmount = parse_amount_expression(isset($item['amount']) ? $item['amount'] : '');
            $rowNote = isset($item['note']) ? trim((string)$item['note']) : '';

            if ($rowCategoryId <= 0 && $rowEntryDate === '' && $rowAmount <= 0 && $rowNote === '') {
                continue;
            }

            if ($rowCategoryId > 0 && valid_date($rowEntryDate) && $rowAmount > 0 && category_belongs_to_user($conn, $rowCategoryId, $userId, false)) {
                mysqli_stmt_bind_param($stmt, 'iisds', $rowCategoryId, $userId, $rowEntryDate, $rowAmount, $rowNote);
                mysqli_stmt_execute($stmt);
                $savedCount++;
            }
        }
        mysqli_stmt_close($stmt);
    }
    $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
    $returnUrl .= $glue . 'batch_saved=1&batch_count=' . (int)$savedCount;
} elseif ($action === 'delete') {
    if ($entryId > 0 && entry_belongs_to_user($conn, $entryId, $userId)) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM entries WHERE id = ? AND user_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $entryId, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $glue = strpos($returnUrl, '?') !== false ? '&' : '?';
        $returnUrl .= $glue . 'deleted=1';
    }
}

redirect($returnUrl);
