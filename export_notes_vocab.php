<?php
header('Content-Type: text/html; charset=UTF-8');
include 'auth.php';
include 'config/db.php';

if ($_SESSION['role'] !== 'admin') { die('Admin only'); }
$userId = (int)$_SESSION['user_id'];

function reverse_tis620($s) {
    if ($s === null || $s === '') return $s;
    $out = ''; $len = strlen($s); $i = 0;
    while ($i < $len) {
        $b0 = ord($s[$i]);
        if ($b0 < 0x80) { $out .= chr($b0); $i++; }
        elseif ($b0 < 0xC0) { $i++; }
        elseif ($b0 < 0xE0) {
            if ($i + 1 >= $len) break;
            $cp = (($b0 & 0x1F) << 6) | (ord($s[$i+1]) & 0x3F);
            if ($cp >= 0x80 && $cp <= 0xFF) $out .= chr($cp);
            $i += 2;
        } elseif ($b0 < 0xF0) {
            if ($i + 2 >= $len) break;
            $cp = (($b0 & 0x0F) << 12) | ((ord($s[$i+1]) & 0x3F) << 6) | (ord($s[$i+2]) & 0x3F);
            if ($cp >= 0x0E01 && $cp <= 0x0E5B) $out .= chr($cp - 0x0D60);
            $i += 3;
        } else { $i += 4; }
    }
    return $out;
}
function strip_4byte($s) {
    $out = ''; $len = strlen($s); $i = 0;
    while ($i < $len) {
        $b = ord($s[$i]);
        if ($b >= 0xF0) { $i += 4; }
        elseif ($b >= 0xE0) { $out .= substr($s, $i, 3); $i += 3; }
        elseif ($b >= 0xC0) { $out .= substr($s, $i, 2); $i += 2; }
        else { $out .= $s[$i]; $i++; }
    }
    return $out;
}
function fix($s) { return strip_4byte(reverse_tis620($s)); }

$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($type === 'notes') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="notes_export.csv"');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, array('ID', 'Title', 'Content', 'Category', 'Date', 'Created At'));
    $r = mysqli_query($conn, "SELECT id, title, content, category, note_date, created_at FROM notes WHERE user_id={$userId} ORDER BY note_date DESC");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            fputcsv($fp, array(
                $row['id'],
                fix($row['title']),
                fix($row['content']),
                $row['category'],
                $row['note_date'],
                $row['created_at']
            ));
        }
    }
    fclose($fp);
    exit;
}

if ($type === 'vocab') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="vocab_export.csv"');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, array('ID', 'Word', 'Meaning', 'Example', 'Note', 'Created At'));
    $r = mysqli_query($conn, "SELECT id, word, meaning, example, note, created_at FROM vocab WHERE user_id={$userId} ORDER BY id");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            fputcsv($fp, array(
                $row['id'],
                $row['word'],
                $row['meaning'],
                $row['example'],
                $row['note'],
                $row['created_at']
            ));
        }
    }
    fclose($fp);
    exit;
}

$cNotes = 0; $cVocab = 0;
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notes WHERE user_id={$userId}");
if ($r) { $row = mysqli_fetch_assoc($r); $cNotes = (int)$row['c']; }
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM vocab WHERE user_id={$userId}");
if ($r) { $row = mysqli_fetch_assoc($r); $cVocab = (int)$row['c']; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Export ข้อมูล</title>
<style>
body { font-family: sans-serif; padding: 40px 24px; max-width: 560px; margin: 0 auto; font-size: 15px; background: #f8fafc; }
h2 { margin-bottom: 8px; }
p { color: #64748b; margin-bottom: 28px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.card-info { flex: 1; }
.card-info strong { font-size: 16px; display: block; margin-bottom: 4px; }
.card-info span { color: #64748b; font-size: 13px; }
.btn { padding: 10px 20px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 700; text-decoration: none; white-space: nowrap; }
.btn:hover { background: #4338ca; color: #fff; }
.back { display: inline-block; margin-top: 20px; color: #64748b; text-decoration: none; font-size: 13px; }
</style>
</head>
<body>
<h2>📦 Export ข้อมูล</h2>
<p>ดาวน์โหลดข้อมูลเป็นไฟล์ CSV (เปิดได้ใน Excel หรือ Google Sheets)</p>

<div class="card">
    <div class="card-info">
        <strong>📓 โน็ตส่วนตัว</strong>
        <span><?php echo $cNotes; ?> รายการ — title, content, category, วันที่</span>
    </div>
    <a href="export_notes_vocab.php?type=notes" class="btn">⬇ ดาวน์โหลด</a>
</div>

<div class="card">
    <div class="card-info">
        <strong>📖 คำศัพท์</strong>
        <span><?php echo $cVocab; ?> รายการ — word, meaning, example, note</span>
    </div>
    <a href="export_notes_vocab.php?type=vocab" class="btn">⬇ ดาวน์โหลด</a>
</div>

<a href="index.php" class="back">← กลับหน้าหลัก</a>
</body>
</html>
