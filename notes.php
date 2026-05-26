<?php
header('Content-Type: text/html; charset=UTF-8');
include 'auth.php';
include 'config/db.php';
$page_title = 'โน็ตส่วนตัว';
$userId = (int)$_SESSION['user_id'];

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$cats = [
    'food'   => ['icon' => 'bi-cup-hot-fill',     'label' => 'ร้านอาหาร',  'color' => '#d97706', 'bg' => '#fef3c7'],
    'travel' => ['icon' => 'bi-compass-fill',     'label' => 'เที่ยว',     'color' => '#0284c7', 'bg' => '#e0f2fe'],
    'memory' => ['icon' => 'bi-heart-fill',       'label' => 'ความทรงจำ',  'color' => '#db2777', 'bg' => '#fce7f3'],
    'diary'  => ['icon' => 'bi-journal-heart',    'label' => 'บันทึกวัน', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
    'other'  => ['icon' => 'bi-pin-fill',         'label' => 'อื่นๆ',      'color' => '#475569', 'bg' => '#f1f5f9'],
];
