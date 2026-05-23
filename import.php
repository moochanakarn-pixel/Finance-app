<?php
header('Content-Type: text/html; charset=UTF-8');
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];
$message = '';
$errors = array();
$preview = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'อัปโหลดไฟล์ไม่สำเร็จ';
    } else {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmpName, 'r');

        if (!$handle) {
            $errors[] = 'เปิดไฟล์ CSV ไม่ได้';
        } else {
            $header = fgetcsv($handle);
            if (!$header) {
                $errors[] = 'ไฟล์ CSV ว่างหรืออ่านหัวตารางไม่ได้';
            } else {
                if (isset($header[0])) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                }

                $headerMap = array();
                foreach ($header as $i => $col) {
                    $headerMap[strtolower(trim($col))] = $i;
                }

                $required = array('date', 'category', 'amount', 'note');
                foreach ($required as $col) {
                    if (!array_key_exists($col, $headerMap)) {
                        $errors[] = 'ต้องมีคอลัมน์: date, category, amount, note';
                        break;
                    }
                }

                if (empty($errors)) {
                    $inserted = 0;
                    $skipped = 0;

                    $stmtCategory = mysqli_prepare($conn, "
                        SELECT id, name
                        FROM categories
                        WHERE user_id = ?
                          AND is_active = 1
                          AND (name = ? OR import_alias = ?)
                        LIMIT 1
                    ");
                    $stmtInsert = mysqli_prepare($conn, "
                        INSERT INTO entries (category_id, user_id, entry_date, amount, note, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NULL)
                    ");

                    while (($row = fgetcsv($handle)) !== false) {
                        $rawDate = trim($row[$headerMap['date']] ?? '');
                        $rawCategory = trim($row[$headerMap['category']] ?? '');
                        $rawAmount = trim($row[$headerMap['amount']] ?? '');
                        $rawNote = trim($row[$headerMap['note']] ?? '');

                        if ($rawDate === '' && $rawCategory === '' && $rawAmount === '' && $rawNote === '') {
                            continue;
                        }

                        $entryDate = '';
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
                            $entryDate = $rawDate;
                        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $rawDate, $m)) {
                            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                            $year = $m[3];
                            if ((int)$year > 2400) {
                                $year = (string)((int)$year - 543);
                            }
                            $entryDate = $year . '-' . $month . '-' . $day;
                        }

                        $amount = str_replace(',', '', $rawAmount);
                        $amount = preg_replace('/[^0-9\.\-]/', '', $amount);
                        $amountValue = (float)$amount;

                        if (!valid_date($entryDate) || $rawCategory === '' || $amount === '' || $amountValue <= 0) {
                            $skipped++;
                            $preview[] = "ข้าม: {$rawDate} | {$rawCategory} | {$rawAmount}";
                            continue;
                        }

                        mysqli_stmt_bind_param($stmtCategory, 'iss', $userId, $rawCategory, $rawCategory);
                        mysqli_stmt_execute($stmtCategory);
                        $rsCat = mysqli_stmt_get_result($stmtCategory);
                        $cat = $rsCat ? mysqli_fetch_assoc($rsCat) : null;

                        if (!$cat) {
                            $skipped++;
                            $preview[] = "ไม่พบหมวด: {$rawCategory}";
                            continue;
                        }

                        $categoryId = (int)$cat['id'];
                        mysqli_stmt_bind_param($stmtInsert, 'iisds', $categoryId, $userId, $entryDate, $amountValue, $rawNote);
                        if (mysqli_stmt_execute($stmtInsert)) {
                            $inserted++;
                        } else {
                            $skipped++;
                            $preview[] = 'insert error';
                        }
                    }

                    mysqli_stmt_close($stmtCategory);
                    mysqli_stmt_close($stmtInsert);
                    $message = "นำเข้าสำเร็จ {$inserted} รายการ";
                    if ($skipped > 0) {
                        $message .= " | ข้าม {$skipped} รายการ";
                    }
                }
            }

            fclose($handle);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV</title>
    <style>
        body {font-family: Tahoma, sans-serif;background: #f3f4f6;margin: 0;padding: 24px;color: #111827;}
        .wrap {max-width: 900px;margin: 0 auto;}
        .card {background: #fff;border-radius: 16px;padding: 24px;box-shadow: 0 8px 24px rgba(0,0,0,.06);margin-bottom: 20px;}
        h1, h2 {margin-top: 0;}
        input[type="file"] {display: block;margin: 12px 0 16px;}
        button, .btn {background: #2563eb;color: #fff;border: 0;border-radius: 10px;padding: 12px 16px;cursor: pointer;text-decoration: none;display: inline-block;}
        .btn-secondary {background: #374151;}
        .ok {background: #ecfdf5;color: #065f46;padding: 12px;border-radius: 10px;margin-bottom: 16px;}
        .err {background: #fef2f2;color: #991b1b;padding: 12px;border-radius: 10px;margin-bottom: 16px;}
        table {width: 100%;border-collapse: collapse;}
        th, td {border: 1px solid #e5e7eb;padding: 10px;text-align: left;}
        th {background: #f9fafb;}
        code {background: #f3f4f6;padding: 2px 6px;border-radius: 6px;}
        ul {margin-top: 8px;}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>นำเข้าข้อมูล CSV</h1>
        <p>รูปแบบไฟล์ต้องมีหัวตาราง <code>date</code>, <code>category</code>, <code>amount</code>, <code>note</code></p>
        <p><a class="btn btn-secondary" href="index.php">กลับหน้า Dashboard</a></p>
    </div>

    <div class="card">
        <?php if ($message !== ''): ?><div class="ok"><?php echo h($message); ?></div><?php endif; ?>
        <?php foreach ($errors as $err): ?><div class="err"><?php echo h($err); ?></div><?php endforeach; ?>

        <form method="post" enctype="multipart/form-data">
            <label>เลือกไฟล์ CSV</label>
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit">อัปโหลดและนำเข้า</button>
        </form>
    </div>

    <?php if (!empty($preview)): ?>
    <div class="card">
        <h2>รายการที่ข้าม</h2>
        <ul>
            <?php foreach ($preview as $item): ?>
                <li><?php echo h($item); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
