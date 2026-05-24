<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';
mysqli_set_charset($conn, 'utf8');

$userId = (int)$_SESSION['user_id'];
$page_title = 'จัดการหมวดหมู่';
$message = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') $message = 'เพิ่มหมวดหมู่สำเร็จ';
    if ($_GET['success'] === 'updated') $message = 'แก้ไขหมวดหมู่สำเร็จ';
    if ($_GET['success'] === 'deleted') $message = 'ลบหรือซ่อนหมวดหมู่สำเร็จ';
}

$showInactive = isset($_GET['show']) && $_GET['show'] === 'inactive';

$categories = array();
$whereActive = $showInactive ? 'AND is_active = 0' : 'AND is_active = 1';
$rs = mysqli_query($conn, "
    SELECT id, name, type, sort_order, is_active, budget_amount
    FROM categories
    WHERE user_id = {$userId} {$whereActive}
    ORDER BY FIELD(type,'income','saving','expense'), sort_order ASC, id ASC
");
if ($rs) {
    while ($row = mysqli_fetch_assoc($rs)) {
        $categories[] = $row;
    }
}

include 'partials/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">จัดการหมวดหมู่</h1>
        <p class="page-subtitle">เพิ่ม แก้ไข และจัดลำดับหมวดรายรับ รายจ่าย และเงินออม</p>
        <div class="mt-2">
            <?php if ($showInactive): ?>
                <a class="btn btn-sm btn-outline-secondary" href="categories.php">ดูหมวดที่ใช้งาน</a>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" href="categories.php?show=inactive">ดูหมวดที่ปิดใช้งาน</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="alert-success-soft mb-3"><?php echo h($message); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card card-soft h-100">
            <div class="card-body p-3 p-lg-4">
                <h5 class="mb-3"><?php echo $showInactive ? 'รายการหมวดที่ปิดใช้งาน' : 'เพิ่มหมวดหมู่ใหม่'; ?></h5>
                <?php if (!$showInactive): ?>
                <form method="post" action="save_category.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="return_year" value="<?php echo date('Y') + 543; ?>">
                    <input type="hidden" name="return_url" value="categories.php<?php echo $showInactive ? '?show=inactive' : ''; ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อหมวด</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ประเภท</label>
                        <select name="category_type" class="form-select" required>
                            <option value="income">รายรับ</option>
                            <option value="saving">เงินออม</option>
                            <option value="expense" selected>รายจ่าย</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ลำดับ</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">งบประมาณ/เดือน (บาท)</label>
                        <input type="number" name="budget_amount" class="form-control" value="0" min="0" step="0.01">
                        <div class="form-text">ใส่ 0 = ไม่กำหนดงบ</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">เพิ่มหมวดหมู่</button>
                </form>
                <?php else: ?>
                    <div class="text-muted">หน้านี้แสดงเฉพาะหมวดที่ถูกปิดใช้งานไว้ ซึ่งจะไม่โผล่ในรายการเพิ่มข้อมูลและรายงานปกติ</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card card-soft table-card h-100">
            <div class="card-body p-0">
                <div class="table-wrap">
                    <table class="app-table mb-0">
                        <thead>
                            <tr>
                                <th>ชื่อหมวด</th>
                                <th>ประเภท</th>
                                <th>งบ/เดือน</th>
                                <th>ลำดับ</th>
                                <th>สถานะ</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <?php $typeClass = $cat['type'] === 'income' ? 'badge-income' : ($cat['type'] === 'expense' ? 'badge-expense' : 'badge-saving'); ?>
                                    <tr>
                                        <td><?php echo h($cat['name']); ?></td>
                                        <td>
                                            <span class="badge-soft <?php echo $typeClass; ?>">
                                                <?php echo $cat['type'] === 'income' ? 'รายรับ' : ($cat['type'] === 'expense' ? 'รายจ่าย' : 'เงินออม'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo (float)$cat['budget_amount'] > 0 ? '฿' . number_format((float)$cat['budget_amount'], 0) : '<span style="color:#94a3b8">-</span>'; ?></td>
                                        <td><?php echo (int)$cat['sort_order']; ?></td>
                                        <td><?php if ((int)$cat['is_active'] === 1): ?>ใช้งาน<?php else: ?><span class="status-muted">ปิดใช้งาน</span><?php endif; ?></td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-2 flex-wrap justify-content-center">
                                                <a class="btn btn-sm btn-outline-secondary" href="edit.php?category_id=<?php echo (int)$cat['id']; ?>">แก้ไข</a>
                                                <form method="post" action="save_category.php" class="m-0" onsubmit="return confirm('ยืนยันการลบหมวดนี้?\nถ้ามีรายการใช้งานอยู่ ระบบจะปิดใช้งานและซ่อนออกจากหน้าหลักแทน');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="category_id" value="<?php echo (int)$cat['id']; ?>">
                                                    <input type="hidden" name="return_url" value="categories.php<?php echo $showInactive ? '?show=inactive' : ''; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"><?php echo (int)$cat['is_active'] === 1 ? 'ลบ/ซ่อน' : 'ลบ'; ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">ยังไม่มีหมวดหมู่</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'partials/footer.php'; ?>
