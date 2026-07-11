<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId     = (int)$_SESSION['user_id'];
$page_title = 'กลุ่มรายงาน';

// Messages
$message      = '';
$messageClass = 'alert-success-soft';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added')    $message = 'สร้างกลุ่มสำเร็จ';
    if ($_GET['success'] === 'updated')  $message = 'แก้ไขกลุ่มสำเร็จ';
    if ($_GET['success'] === 'deleted')  $message = 'ลบกลุ่มสำเร็จ';
    if ($_GET['success'] === 'assigned') $message = 'บันทึกการจัดกลุ่มหมวดหมู่สำเร็จ';
}
if (isset($_GET['error'])) {
    $messageClass = 'alert alert-danger';
    if ($_GET['error'] === 'duplicate_code') $message = 'รหัสกลุ่มนี้มีอยู่แล้ว กรุณาใช้รหัสอื่น';
    if ($_GET['error'] === 'failed')         $message = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
}

// Edit mode
$editId    = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$editGroup = null;
if ($editId > 0) {
    $rsEdit = mysqli_query($conn, "SELECT id, name, code, sort_order FROM category_groups WHERE id = {$editId} AND user_id = {$userId} LIMIT 1");
    if ($rsEdit) $editGroup = mysqli_fetch_assoc($rsEdit);
    if (!$editGroup) $editId = 0;
}

// Assign mode
$assignId    = isset($_GET['assign_id']) ? (int)$_GET['assign_id'] : 0;
$assignGroup = null;
if ($assignId > 0) {
    $rsAssign = mysqli_query($conn, "SELECT id, name, code FROM category_groups WHERE id = {$assignId} AND user_id = {$userId} LIMIT 1");
    if ($rsAssign) $assignGroup = mysqli_fetch_assoc($rsAssign);
    if (!$assignGroup) $assignId = 0;
}

// All groups with category counts
$groups = [];
$rsGroups = mysqli_query($conn, "
    SELECT g.id, g.name, g.code, g.sort_order,
           COUNT(c.id) AS cat_count
    FROM category_groups g
    LEFT JOIN categories c ON c.group_id = g.id AND c.user_id = {$userId} AND c.is_active = 1
    WHERE g.user_id = {$userId}
    GROUP BY g.id, g.name, g.code, g.sort_order
    ORDER BY g.sort_order ASC, g.id ASC
");
if ($rsGroups) {
    while ($row = mysqli_fetch_assoc($rsGroups)) $groups[] = $row;
}

// For assign mode: all active categories with current group info
$allCats = [];
if ($assignId > 0) {
    $rsCats = mysqli_query($conn, "
        SELECT c.id, c.name, c.type, c.group_id, g.name AS group_name
        FROM categories c
        LEFT JOIN category_groups g ON g.id = c.group_id AND g.user_id = {$userId}
        WHERE c.user_id = {$userId} AND c.is_active = 1
        ORDER BY FIELD(c.type,'income','saving','expense'), c.sort_order ASC, c.id ASC
    ");
    if ($rsCats) {
        while ($row = mysqli_fetch_assoc($rsCats)) $allCats[] = $row;
    }
}

include 'partials/header.php';
?>
<style>
.text-income { color: #15803d !important; }
.text-expense { color: #dc2626 !important; }
.text-saving  { color: #6d28d9 !important; }
.cat-assign-label {
    display: flex; align-items: flex-start; gap: .6rem; padding: .6rem .75rem;
    border-radius: 10px; border: 1px solid #e2e8f0; cursor: pointer;
    transition: border-color .12s, background .12s;
}
.cat-assign-label:hover { border-color: #6366f1; background: #f5f3ff; }
.cat-assign-label.is-checked { border-color: #6366f1; background: #eef2ff; }
</style>

<div class="page-hero">
    <div>
        <div class="page-hero-icon"><i class="bi bi-collection-fill"></i></div>
        <div class="page-hero-title">กลุ่มรายงาน</div>
        <div class="page-hero-sub">สร้างกลุ่ม กำหนดหมวดหมู่ แล้วดูรายงานรวมตามกลุ่ม</div>
    </div>
    <div class="page-hero-actions">
        <a class="btn-hero" href="group_report.php"><i class="bi bi-bar-chart-steps me-1"></i>ดูรายงานตามกลุ่ม</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="<?php echo $messageClass; ?> mb-3"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if ($assignId > 0 && $assignGroup): ?>
<!-- ─── Assign mode ──────────────────────────────────────────────────── -->
<div class="card card-soft mb-4">
    <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h4 class="mb-1">จัดหมวดหมู่เข้ากลุ่ม</h4>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold fs-5"><?php echo h($assignGroup['name']); ?></span>
                    <span class="badge-soft" style="background:#f1f5f9;color:#475569;font-family:monospace;font-size:.85rem"><?php echo h($assignGroup['code']); ?></span>
                </div>
            </div>
            <a href="groups.php" class="btn btn-outline-secondary flex-shrink-0">← กลับ</a>
        </div>

        <?php if (empty($allCats)): ?>
            <div class="text-muted">ยังไม่มีหมวดหมู่ที่ใช้งานอยู่ <a href="categories.php">ไปเพิ่มหมวดหมู่</a></div>
        <?php else: ?>
        <p class="text-muted mb-4">ติ๊กหมวดหมู่ที่ต้องการให้อยู่ในกลุ่มนี้ หมวดที่ติ๊กไว้จะถูกย้ายออกจากกลุ่มเดิม (ถ้ามี) โดยอัตโนมัติ</p>

        <form method="post" action="save_group.php">
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="group_id" value="<?php echo (int)$assignGroup['id']; ?>">
            <input type="hidden" name="return_url" value="groups.php">

            <?php
            $catsByType  = ['income' => [], 'saving' => [], 'expense' => []];
            foreach ($allCats as $cat) $catsByType[$cat['type']][] = $cat;
            $typeLabels  = ['income' => 'รายรับ', 'saving' => 'เงินออม', 'expense' => 'รายจ่าย'];
            $typeClasses = ['income' => 'text-income', 'saving' => 'text-saving', 'expense' => 'text-expense'];
            foreach ($catsByType as $type => $cats):
                if (empty($cats)) continue;
            ?>
            <div class="mb-4">
                <div class="fw-bold mb-2 <?php echo $typeClasses[$type]; ?>"><?php echo $typeLabels[$type]; ?></div>
                <div class="row g-2">
                    <?php foreach ($cats as $cat):
                        $inThisGroup  = ((int)$cat['group_id'] === (int)$assignGroup['id']);
                        $inOtherGroup = ($cat['group_id'] !== null && !$inThisGroup);
                    ?>
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="cat-assign-label<?php echo $inThisGroup ? ' is-checked' : ''; ?>">
                            <input type="checkbox" name="category_ids[]" value="<?php echo (int)$cat['id']; ?>" class="mt-1 flex-shrink-0"<?php echo $inThisGroup ? ' checked' : ''; ?>>
                            <div>
                                <div class="fw-semibold" style="font-size:.9rem"><?php echo h($cat['name']); ?></div>
                                <?php if ($inOtherGroup): ?>
                                    <div class="text-muted" style="font-size:.78rem">กลุ่มปัจจุบัน: <?php echo h((string)$cat['group_name']); ?></div>
                                <?php endif; ?>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary">บันทึก</button>
                <a href="groups.php" class="btn btn-outline-secondary">ยกเลิก</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($groups)): ?>
<div class="card card-soft">
    <div class="card-body p-3">
        <div class="fw-semibold text-muted mb-2" style="font-size:.85rem">กลุ่มทั้งหมด — คลิกเพื่อจัดการ</div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($groups as $grp): ?>
                <a href="groups.php?assign_id=<?php echo (int)$grp['id']; ?>"
                   class="badge-soft text-decoration-none"
                   style="<?php echo (int)$grp['id'] === $assignId ? 'background:#6366f1;color:#fff' : 'background:#e0e7ff;color:#3730a3'; ?>">
                    <?php echo h($grp['name']); ?>
                    <?php if ((int)$grp['cat_count'] > 0): ?>
                        <span style="opacity:.7"> (<?php echo (int)$grp['cat_count']; ?>)</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ─── Create / Edit + Groups table ────────────────────────────────── -->
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card card-soft h-100">
            <div class="card-body p-3 p-lg-4">
                <h5 class="mb-3"><?php echo $editId > 0 ? 'แก้ไขกลุ่ม' : 'สร้างกลุ่มใหม่'; ?></h5>
                <form method="post" action="save_group.php">
                    <input type="hidden" name="action" value="<?php echo $editId > 0 ? 'update' : 'add'; ?>">
                    <?php if ($editId > 0): ?>
                    <input type="hidden" name="group_id" value="<?php echo (int)$editGroup['id']; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="return_url" value="groups.php">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อกลุ่ม</label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-collection-fill"></i>
                            <input type="text" name="name" class="form-control"
                                   value="<?php echo h((string)($editGroup['name'] ?? '')); ?>"
                                   placeholder="เช่น ธุรกิจการ์ด, ใช้จ่ายส่วนตัว" required maxlength="100">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสกลุ่ม <span class="text-muted fw-normal">(กันซ้ำ)</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-upc"></i>
                            <input type="text" name="code" class="form-control"
                                   value="<?php echo h((string)($editGroup['code'] ?? '')); ?>"
                                   placeholder="เช่น biz, personal, card01" required maxlength="50">
                        </div>
                        <div class="form-text">ใช้ตรวจสอบว่ากลุ่มถูกสร้างแล้ว ห้ามซ้ำกัน</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">ลำดับ</label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-sort-numeric-down"></i>
                            <input type="number" name="sort_order" class="form-control"
                                   value="<?php echo (int)($editGroup['sort_order'] ?? 0); ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editId > 0 ? 'บันทึกการแก้ไข' : 'สร้างกลุ่ม'; ?>
                        </button>
                        <?php if ($editId > 0): ?>
                            <a href="groups.php" class="btn btn-outline-secondary">ยกเลิก</a>
                        <?php endif; ?>
                    </div>
                </form>
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
                                <th>ชื่อกลุ่ม</th>
                                <th>รหัส</th>
                                <th class="text-center">จำนวนหมวด</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($groups)): ?>
                                <?php foreach ($groups as $grp): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo h($grp['name']); ?></td>
                                    <td>
                                        <span class="badge-soft" style="background:#f1f5f9;color:#475569;font-family:monospace">
                                            <?php echo h($grp['code']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$grp['cat_count'] > 0): ?>
                                            <span class="badge-soft" style="background:#e0e7ff;color:#3730a3">
                                                <?php echo (int)$grp['cat_count']; ?> หมวด
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-center">
                                            <a href="groups.php?assign_id=<?php echo (int)$grp['id']; ?>"
                                               class="btn btn-sm btn-primary">จัดการหมวด</a>
                                            <a href="groups.php?edit_id=<?php echo (int)$grp['id']; ?>"
                                               class="btn btn-sm btn-outline-secondary">แก้ไข</a>
                                            <form method="post" action="save_group.php" class="m-0">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="group_id" value="<?php echo (int)$grp['id']; ?>">
                                                <input type="hidden" name="return_url" value="groups.php">
                                                <button type="submit" class="btn btn-sm btn-danger"
                                                        data-name="<?php echo h($grp['name']); ?>">ลบ</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        ยังไม่มีกลุ่ม สร้างกลุ่มแรกได้เลย
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Confirm dialog for delete buttons
document.querySelectorAll('button[data-name]').forEach(function(btn) {
    btn.closest('form').addEventListener('submit', function(e) {
        if (!confirm('ยืนยันการลบกลุ่ม "' + btn.getAttribute('data-name') + '"?\nหมวดในกลุ่มนี้จะถูกนำออก แต่ไม่ถูกลบ')) {
            e.preventDefault();
        }
    });
});

// Toggle checked style on assign checkboxes
document.querySelectorAll('.cat-assign-label input[type=checkbox]').forEach(function(cb) {
    cb.addEventListener('change', function() {
        cb.closest('.cat-assign-label').classList.toggle('is-checked', cb.checked);
    });
});
</script>

<?php include 'partials/footer.php'; ?>
