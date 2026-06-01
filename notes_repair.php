<?php
header('Content-Type: text/html; charset=UTF-8');
include 'auth.php';
include 'config/db.php';

if ($_SESSION['role'] !== 'admin') { die('Admin only'); }

$userId = (int)$_SESSION['user_id'];
$msg = '';
$msgType = 'ok';

if (isset($_POST['fix'])) {
    $sql = "UPDATE notes SET
        title   = CONVERT(BINARY CONVERT(title   USING latin1) USING utf8mb4),
        content = CONVERT(BINARY CONVERT(content USING latin1) USING utf8mb4)";
    if (@mysqli_query($conn, $sql)) {
        $affected = mysqli_affected_rows($conn);
        $msg = "✅ แก้ไขสำเร็จ {$affected} แถว";
        $msgType = 'ok';
    } else {
        $msg = "❌ utf8mb4 ไม่สำเร็จ: " . mysqli_error($conn);
        $msgType = 'err';
    }
}

$rows = [];
$r = mysqli_query($conn, "SELECT id, title, content, HEX(title) AS hex_title FROM notes WHERE user_id={$userId} ORDER BY id DESC LIMIT 5");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $rows[] = $row; }

$preview = [];
foreach ($rows as $row) {
    $esc = mysqli_real_escape_string($conn, $row['title']);
    $rp = mysqli_query($conn, "SELECT CONVERT(BINARY CONVERT('{$esc}' USING latin1) USING utf8mb4) AS fixed");
    if ($rp) {
        $fp = mysqli_fetch_assoc($rp);
        $preview[$row['id']] = $fp['fixed'] ?? null;
    }
}

$canFix = 0; $cantFix = 0;
foreach ($preview as $v) { $v !== null ? $canFix++ : $cantFix++; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Notes Repair</title>
<style>
body { font-family: sans-serif; padding: 24px; max-width: 1100px; margin: 0 auto; font-size: 14px; }
table { width: 100%; border-collapse: collapse; margin: 12px 0; }
th, td { border: 1px solid #ddd; padding: 7px 10px; text-align: left; vertical-align: top; }
th { background: #f1f5f9; font-size: 13px; }
.bad  { color: #dc2626; }
.good { color: #16a34a; font-weight: 700; }
.null { color: #94a3b8; font-style: italic; }
.hex  { font-family: monospace; font-size: 11px; color: #64748b; word-break: break-all; }
.btn  { padding: 10px 22px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 700; }
.msg-ok  { padding: 12px 16px; border-radius: 8px; background: #f0fdf4; border: 1px solid #86efac; margin: 12px 0; font-weight: 700; }
.msg-err { padding: 12px 16px; border-radius: 8px; background: #fef2f2; border: 1px solid #fca5a5; margin: 12px 0; font-weight: 700; color: #991b1b; }
</style>
</head>
<body>
<h2>&#x1F527; Notes Repair Tool</h2>

<?php if ($msg): ?>
<div class="msg-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<h3>&#x1F50D; HEX dump (5 รายการล่าสุด)</h3>
<table>
    <tr>
        <th style="width:35px">ID</th>
        <th style="width:28%">ปัจจุบัน (เพี้ยน)</th>
        <th style="width:36%">HEX bytes ใน DB</th>
        <th style="width:28%">Preview หลัง latin1&rarr;utf8mb4</th>
    </tr>
    <?php foreach ($rows as $row):
        $hex = $row['hex_title'] ?? '';
        $hexFmt = implode(' ', str_split($hex, 2));
        $fixedVal = $preview[$row['id']] ?? null;
    ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td class="bad"><?= htmlspecialchars($row['title']) ?></td>
        <td class="hex"><?= htmlspecialchars($hexFmt) ?></td>
        <td><?php if ($fixedVal === null): ?>
            <span class="null">NULL (แปลงไม่ได้)</span>
        <?php else: ?>
            <span class="good"><?= htmlspecialchars($fixedVal) ?></span>
        <?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p>
    Preview สำเร็จ: <strong style="color:#16a34a"><?= $canFix ?> แถว</strong> &nbsp;|&nbsp;
    แปลงไม่ได้: <strong style="color:#dc2626"><?= $cantFix ?> แถว</strong>
</p>

<?php if ($canFix > 0): ?>
<form method="post" onsubmit="return confirm('ยืนยันซ่อมข้อมูลทั้งหมด?')">
    <button type="submit" name="fix" class="btn">&#x1F527; ซ่อม Notes ทั้งหมด</button>
</form>
<?php else: ?>
<p style="color:#dc2626;font-weight:700">&#x26A0;️ Preview ทั้งหมดเป็น NULL — กรุณาส่ง HEX ด้านบนให้ผู้พัฒนาดูเพื่อวิเคราะห์เพิ่มเติม</p>
<?php endif; ?>

<p style="margin-top:24px"><a href="notes.php">&larr; กลับหน้า Notes</a></p>
</body>
</html>
