<?php
header('Content-Type: text/html; charset=UTF-8');
include 'auth.php';
include 'config/db.php';

if ($_SESSION['role'] !== 'admin') { die('Admin only'); }

$userId = (int)$_SESSION['user_id'];
$msg = '';
$msgType = 'ok';

// Reverse TIS-620 misinterpretation corruption.
// Each original byte was read as a TIS-620 char and re-encoded to UTF-8:
//   - Original 0xA1-0xFB (Thai)  → U+0E01-U+0E5B  → stored as 3-byte UTF-8
//   - Original 0x80-0xA0 (undef) → U+0080-U+00A0 (latin1-like) → stored as 2-byte UTF-8
//   - Original 0x00-0x7F (ASCII) → unchanged
// Reverse: walk codepoints, map back to original bytes, return as UTF-8 string.
function reverse_tis620($s) {
    if ($s === null || $s === '') return $s;
    $out = '';
    $len = mb_strlen($s, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($s, $i, 1, 'UTF-8');
        $cp   = mb_ord($char, 'UTF-8');
        if ($cp <= 0x007F) {
            $out .= chr($cp);
        } elseif ($cp >= 0x0080 && $cp <= 0x00FF) {
            // TIS-620 undefined range → byte = codepoint value
            $out .= chr($cp);
        } elseif ($cp >= 0x0E01 && $cp <= 0x0E5B) {
            // Thai block: TIS-620 byte = codepoint - 0x0D60
            $out .= chr($cp - 0x0D60);
        }
        // Other codepoints (incl. U+0E5C+) are unmappable — skip
    }
    // Strip 4-byte sequences (emoji): MySQL utf8 column can't store them
    return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $out);
}

if (isset($_POST['fix'])) {
    $r = mysqli_query($conn, "SELECT id, title, content FROM notes WHERE user_id={$userId}");
    $fixed = 0;
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $newTitle   = reverse_tis620($row['title']);
            $newContent = reverse_tis620($row['content']);
            $et = mysqli_real_escape_string($conn, $newTitle);
            $ec = mysqli_real_escape_string($conn, $newContent);
            $ok = mysqli_query($conn, "UPDATE notes SET title='{$et}', content='{$ec}' WHERE id={$row['id']} AND user_id={$userId}");
            if ($ok) $fixed++;
        }
    }
    $msg = "✅ แก้ไขสำเร็จ {$fixed} แถว";
    $msgType = 'ok';
}

$rows = [];
$r = mysqli_query($conn, "SELECT id, title, content, HEX(title) AS hex_title FROM notes WHERE user_id={$userId} ORDER BY id DESC LIMIT 5");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $rows[] = $row; }

$preview = [];
foreach ($rows as $row) {
    $fixed = reverse_tis620($row['title']);
    $preview[$row['id']] = strlen($fixed) > 0 ? $fixed : null;
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
<h2>&#x1F527; Notes Repair Tool (PHP mode)</h2>

<?php if ($msg): ?>
<div class="msg-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<h3>&#x1F50D; Preview (5 รายการล่าสุด)</h3>
<table>
    <tr>
        <th style="width:35px">ID</th>
        <th style="width:28%">ปัจจุบัน (เพี้ยน)</th>
        <th style="width:36%">HEX bytes ใน DB</th>
        <th style="width:28%">Preview หลัง repair</th>
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
            <span class="null">ว่าง</span>
        <?php else: ?>
            <span class="good"><?= htmlspecialchars($fixedVal) ?></span>
        <?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p>
    Preview สำเร็จ: <strong style="color:#16a34a"><?= $canFix ?> แถว</strong> &nbsp;|&nbsp;
    ว่าง: <strong style="color:#dc2626"><?= $cantFix ?> แถว</strong>
</p>

<?php if ($canFix > 0): ?>
<form method="post" onsubmit="return confirm('ยืนยันซ่อมข้อมูลทั้งหมด?')">
    <button type="submit" name="fix" class="btn">&#x1F527; ซ่อม Notes ทั้งหมด</button>
</form>
<?php else: ?>
<p style="color:#dc2626;font-weight:700">&#x26A0;&#xFE0F; Preview ว่างทั้งหมด — ข้อมูลอาจถูกซ่อมแล้ว</p>
<?php endif; ?>

<p style="margin-top:24px"><a href="notes.php">&larr; กลับหน้า Notes</a></p>
</body>
</html>
