<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$page_title = 'จัดการผู้ใช้งาน';
$userId = (int)$_SESSION['user_id'];

$message = '';
$msgType = 'success';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added')    $message = 'เพิ่มผู้ใช้งานสำเร็จ';
    if ($_GET['success'] === 'toggled')  $message = 'เปลี่ยนสถานะผู้ใช้งานสำเร็จ';
    if ($_GET['success'] === 'password') $message = 'เปลี่ยนรหัสผ่านสำเร็จ';
}
if (isset($_GET['error'])) {
    $msgType = 'danger';
    if ($_GET['error'] === 'duplicate') $message = 'ชื่อผู้ใช้นี้มีอยู่แล้ว';
    if ($_GET['error'] === 'invalid')   $message = 'ข้อมูลไม่ถูกต้อง';
}

$users = array();
$rs = mysqli_query($conn, "SELECT id, username, full_name, role, is_active, created_at FROM users ORDER BY id ASC");
if ($rs) {
    while ($row = mysqli_fetch_assoc($rs)) {
        $users[] = $row;
    }
}

include 'partials/header.php';
?>

<div class="page-hero">
    <div class="page-hero-icon"><i class="bi bi-people-fill"></i></div>
    <div class="page-hero-title">จัดการผู้ใช้งาน</div>
    <div class="page-hero-sub">เพิ่ม แก้ไข และจัดการสิทธิ์ผู้ใช้งาน (เฉพาะ Admin)</div>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?php echo $msgType; ?> mb-3"><?php echo h($message); ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Add User Form -->
    <div class="col-12 col-xl-4">
        <div class="card card-soft h-100">
            <div class="card-body p-3 p-lg-4">
                <h5 class="mb-3">เพิ่มผู้ใช้งานใหม่</h5>
                <form method="post" action="save_user.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="return_url" value="admin_users.php">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อผู้ใช้ (Username / Email)</label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-person-fill"></i>
                            <input type="text" name="username" class="form-control" placeholder="เช่น user@email.com" required autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อแสดง</label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-card-text"></i>
                            <input type="text" name="full_name" class="form-control" placeholder="เช่น สมชาย ใจดี">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสผ่าน</label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" name="password" class="form-control" placeholder="อย่างน้อย 8 ตัวอักษร" required autocomplete="new-password" minlength="8">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">สิทธิ์</label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-shield-fill"></i>
                            <select name="role" class="form-select">
                                <option value="user" selected>user — ผู้ใช้ทั่วไป</option>
                                <option value="admin">admin — ผู้ดูแลระบบ</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">เพิ่มผู้ใช้งาน</button>
                </form>
            </div>
        </div>
    </div>

    <!-- User List -->
    <div class="col-12 col-xl-8">
        <div class="card card-soft table-card h-100">
            <div class="card-body p-0">
                <div class="table-wrap">
                    <table class="app-table mb-0">
                        <thead>
                            <tr>
                                <th>ชื่อผู้ใช้</th>
                                <th>ชื่อแสดง</th>
                                <th>สิทธิ์</th>
                                <th>สถานะ</th>
                                <th>วันที่สร้าง</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <?php echo h($u['username']); ?>
                                            <?php if ((int)$u['id'] === $userId): ?>
                                                <span class="badge bg-primary ms-1" style="font-size:.7rem">คุณ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo h((string)$u['full_name']); ?></td>
                                        <td>
                                            <?php if ($u['role'] === 'admin'): ?>
                                                <span class="badge-soft badge-income">Admin</span>
                                            <?php else: ?>
                                                <span class="badge-soft" style="background:#f1f5f9;color:#64748b">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$u['is_active'] === 1): ?>
                                                <span style="color:#16a34a;font-weight:600">ใช้งาน</span>
                                            <?php else: ?>
                                                <span class="status-muted">ปิดใช้งาน</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap;font-size:.85rem;color:#64748b">
                                            <?php echo h(substr((string)$u['created_at'], 0, 10)); ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-2 flex-wrap justify-content-center">
                                                <!-- Reset Password -->
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    onclick="openResetModal(<?php echo (int)$u['id']; ?>, <?php echo json_encode(h($u['username']), JSON_HEX_TAG|JSON_HEX_AMP); ?>)">
                                                    รหัสผ่าน
                                                </button>

                                                <!-- Toggle Active (cannot deactivate self) -->
                                                <?php if ((int)$u['id'] !== $userId): ?>
                                                    <form method="post" action="save_user.php" class="m-0"
                                                        onsubmit="return confirm('ยืนยันการ<?php echo (int)$u['is_active'] === 1 ? 'ปิดใช้งาน' : 'เปิดใช้งาน'; ?>ผู้ใช้นี้?')">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="target_user_id" value="<?php echo (int)$u['id']; ?>">
                                                        <input type="hidden" name="return_url" value="admin_users.php">
                                                        <button type="submit" class="btn btn-sm <?php echo (int)$u['is_active'] === 1 ? 'btn-danger' : 'btn-success'; ?>">
                                                            <?php echo (int)$u['is_active'] === 1 ? 'ปิดใช้งาน' : 'เปิดใช้งาน'; ?>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size:.78rem;color:#94a3b8">(ตัวเอง)</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">ยังไม่มีผู้ใช้งาน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">เปลี่ยนรหัสผ่าน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="save_user.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="set_password">
                    <input type="hidden" name="return_url" value="admin_users.php">
                    <input type="hidden" name="target_user_id" id="reset-user-id">
                    <p class="mb-3">ผู้ใช้: <strong id="reset-username"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 8 ตัวอักษร" minlength="8" required autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกรหัสผ่านใหม่</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openResetModal(uid, username) {
    document.getElementById('reset-user-id').value = uid;
    document.getElementById('reset-username').textContent = username;
    var modal = new bootstrap.Modal(document.getElementById('resetModal'));
    modal.show();
}
</script>

<?php include 'partials/footer.php'; ?>
