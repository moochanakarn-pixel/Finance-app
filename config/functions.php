<?php
function thai_month_short($month)
{
    $months = array(
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    );
    return isset($months[(int)$month]) ? $months[(int)$month] : '';
}

function thai_date($date)
{
    if (!$date || $date == '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return date('d/m/', $ts) . (date('Y', $ts) + 543);
}

function money($number)
{
    return number_format((float)$number, 2);
}

function baht($number)
{
    return number_format((float)$number, 2) . ' บาท';
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function valid_date($date)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
        return false;
    }

    $parts = explode('-', $date);
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

function get_query_string($extra)
{
    $query = $_GET;
    foreach ($extra as $key => $value) {
        $query[$key] = $value;
    }
    return http_build_query($query);
}

function build_return_url($fallback = 'index.php')
{
    $returnUrl = '';
    if (isset($_POST['return_url'])) {
        $returnUrl = trim((string)$_POST['return_url']);
    } elseif (isset($_GET['return_url'])) {
        $returnUrl = trim((string)$_GET['return_url']);
    }

    if ($returnUrl === '') {
        return $fallback;
    }

    if (preg_match('/^[a-z]+:/i', $returnUrl) || strpos($returnUrl, '//') === 0) {
        return $fallback;
    }

    if (strpos($returnUrl, '..') !== false) {
        return $fallback;
    }

    return $returnUrl;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}
