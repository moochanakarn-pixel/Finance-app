<?php
include 'auth.php';
include 'config/db.php';
mysqli_set_charset($conn, 'utf8');
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$entryId = isset($_GET['entry_id']) ? (int)$_GET['entry_id'] : 0;

if ($categoryId > 0) {
    $rs = mysqli_query($conn, "SELECT id, name, type, sort_order, budget_amount FROM categories WHERE id = {$categoryId} AND user_id = {$userId} LIMIT 1");
    if (!$rs || mysqli_num_rows($rs) === 0) {
        die('ไม่พบหมวดหมู่');
    }
    $cat = mysqli_fetch_assoc($rs);
    $page_title = 'แก้ไขหมวดหมู่';
    include 'partials/header.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-12 col-xl-7">
            <div class="card card-soft">
                <div class="card-body p-3 p-lg-4">
                    <div class="page-header mb-3">
                        <div>
                            <h1 class="page-title h3 mb-1">แก้ไขหมวดหมู่</h1>
                            <p class="page-subtitle">ปรับชื่อ ประเภท และลำดับการแสดงผล</p>
                        </div>
                    </div>
                    <form method="post" action="save_category.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="category_id" value="<?php echo (int)$cat['id']; ?>">
                        <input type="hidden" name="return_year" value="<?php echo date('Y') + 543; ?>">
                        <input type="hidden" name="return_url" value="categories.php">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">ชื่อหมวด</label>
                            <input type="text" name="category_name" class="form-control" value="<?php echo h($cat['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">ประเภท</label>
                            <select name="category_type" class="form-select" required>
                                <option value="income" <?php echo $cat['type']==='income'?'selected':''; ?>>รายรับ</option>
                                <option value="saving" <?php echo $cat['type']==='saving'?'selected':''; ?>>เงินออม</option>
                                <option value="expense" <?php echo $cat['type']==='expense'?'selected':''; ?>>รายจ่าย</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">ลำดับ</label>
                            <input type="number" name="sort_order" class="form-control" value="<?php echo (int)$cat['sort_order']; ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">งบประมาณ/เดือน (บาท)</label>
                            <input type="number" name="budget_amount" class="form-control" step="0.01" min="0" value="<?php echo h($cat['budget_amount'] ?? 0); ?>">
                            <div class="form-text">ใส่ 0 = ไม่กำหนดงบ</div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                            <a href="categories.php" class="btn btn-outline-secondary">กลับ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    include 'partials/footer.php';
    exit;
}

if ($entryId > 0) {
    $rs = mysqli_query($conn, "
        SELECT e.id, e.entry_date, e.amount, e.note, e.category_id
        FROM entries e
        INNER JOIN categories c ON e.category_id = c.id
        WHERE e.id = {$entryId} AND e.user_id = {$userId} AND c.user_id = {$userId}
        LIMIT 1
    ");
    if (!$rs || mysqli_num_rows($rs) === 0) {
        die('ไม่พบรายการ');
    }
    $entry = mysqli_fetch_assoc($rs);
    $cats = array();
    $rsCats = mysqli_query($conn, "SELECT id, name, type FROM categories WHERE is_active = 1 AND user_id = {$userId} ORDER BY FIELD(type,'income','saving','expense'), sort_order ASC, id ASC");
    if ($rsCats) {
        while ($row = mysqli_fetch_assoc($rsCats)) {
            $cats[] = $row;
        }
    }
    $page_title = 'แก้ไขรายการ';
    include 'partials/header.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-12 col-xl-8">
            <div class="card card-soft">
                <div class="card-body p-3 p-lg-4">
                    <div class="page-header mb-3">
                        <div>
                            <h1 class="page-title h3 mb-1">แก้ไขรายการ</h1>
                            <p class="page-subtitle">ปรับวันที่ หมวดหมู่ จำนวนเงิน และหมายเหตุ</p>
                        </div>
                    </div>
                    <form method="post" action="save_entry.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="entry_id" value="<?php echo (int)$entry['id']; ?>">
                        <input type="hidden" name="year_be" value="<?php echo (int)date('Y', strtotime($entry['entry_date'])) + 543; ?>">
                        <input type="hidden" name="return_url" value="entries.php?year=<?php echo (int)date('Y', strtotime($entry['entry_date'])) + 543; ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">วันที่</label>
                                <input type="date" name="entry_date" class="form-control" value="<?php echo h($entry['entry_date']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">หมวดหมู่</label>
                                <select name="category_id" class="form-select" required>
                                    <?php foreach ($cats as $cat): ?>
                                        <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)$entry['category_id']===(int)$cat['id'])?'selected':''; ?>>
                                            [<?php echo strtoupper(h($cat['type'])); ?>] <?php echo h($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">จำนวนเงิน</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="<?php echo h($entry['amount']); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">หมายเหตุ</label>
                                <textarea name="note" class="form-control" rows="6"><?php echo h($entry['note']); ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap mt-4">
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                            <a href="entries.php?year=<?php echo (int)date('Y', strtotime($entry['entry_date'])) + 543; ?>" class="btn btn-outline-secondary">กลับ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    include 'partials/footer.php';
    exit;
}

die('ไม่พบข้อมูลที่ต้องการแก้ไข');
