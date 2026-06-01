<?php
header('Content-Type: text/html; charset=UTF-8');
include 'auth.php';
include 'config/db.php';

if ($_SESSION['role'] !== 'admin') { die('Admin only'); }

$userId = (int)$_SESSION['user_id'];
$msg = '';

// Apply fix
if (isset($_POST['fix'])) {
    // Reverse mojibake: data was stored as UTF-8 bytes in a latin1 column,
    // then CONVERT TO utf8mb4 double-encoded each byte.
    // Fix: re-interpret the garbled utf8mb4 string as latin1 bytes — those bytes are the original UTF-8.
    $sql = "UPDATE notes SET
        title   = CONVERT(BINARY CONVERT(title   USING latin1) USING utf8),
        content = CONVERT(BINARY CONVERT(content USING latin1) USING utf8)";
    if (@mysqli_query($conn, $sql)) {
        $affected = mysqli_affected_rows($conn);
        $msg = "✅ แก้ไขสำเร็จ {$affected} แถว — รีโหลดหน้า notes.php เพื่อดูผล";
    } else {
        $msg = "❌ เกิดข้อผิดพลาด: " . mysqli_error($conn);
    }
}

// Preview: show first 5 notes
$rows = [];
$r = mysqli_query($conn, "SELECT id, title, content FROM notes WHERE user_id={$userId} ORDER BY id DESC LIMIT 5");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $rows[] = $row; }

// Preview after fix (dry-run, not saved)
$preview = [];
foreach ($rows as $row) {
    $escaped = mysqli_real_escape_string($conn, $row['title']);
    $rp = mysqli_query($conn, "SELECT CONVERT(BINARY CONVERT('{$escaped}' USING latin1) USING utf8) AS fixed_title");
    if ($rp) {
        $fp = mysqli_fetch_assoc($rp);
        $preview[$row['id']] = $fp['fixed_title'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Notes Repair</title>
<style>
body { font-family: sans-serif; padding: 24px; max-width: 900px; margin: 0 auto; }
table { width: 100%; border-collapse: collapse; margin: 16px 0; }
th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; font-size: 14px; }
th { background: #f1f5f9; }
.bad { color: #dc2626; }
.good { color: #16a34a; }
.btn { padding: 10px 20px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 700; }
.msg { padding: 12px 16px; border-radius: 8px; background: #f0fdf4; border: 1px solid #86efac; margin: 16px 0; font-weight: 700; }
</style>
</head>
<body>
<h2>&#x1F527; Notes Repair Tool</h2>
<p>ใช้ซ่อม notes ที่ภาษาเพี้ยนจาก ALTER TABLE ที่รันผิด</p>

<?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<h3>ตัวอย่าง 5 รายการล่าสุด</h3>
<table>
    <tr><th>ID</th><th>ปัจจุบัน (เพี้ยน)</th><th>หลังซ่อม (preview)</th></tr>
    <?php foreach ($rows as $row): ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td class="bad"><?= htmlspecialchars($row['title']) ?></td>
        <td class="good"><?= htmlspecialchars($preview[$row['id']] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p>ถ้าคอลัมน์ "หลังซ่อม" ถูกต้องแล้ว ให้กดปุ่มด้านล่าง:</p>

<form method="post">
    <button type="submit" name="fix" class="btn">&#x1F527; ซ่อม Notes ทั้งหมด</button>
</form>

<p style="margin-top:24px"><a href="notes.php">&larr; กลับไปหน้า Notes</a></p>
</body>
</html>
