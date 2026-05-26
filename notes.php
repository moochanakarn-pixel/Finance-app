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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add', 'edit'], true)) {
        $title    = trim($_POST['title'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $cat      = array_key_exists($_POST['category'] ?? '', $cats) ? $_POST['category'] : 'other';
        $noteDate = $_POST['note_date'] ?? date('Y-m-d');
        if ($title !== '') {
            if ($action === 'add') {
                $st = mysqli_prepare($conn, "INSERT INTO notes (user_id,title,content,category,note_date) VALUES (?,?,?,?,?)");
                mysqli_stmt_bind_param($st, 'issss', $userId, $title, $content, $cat, $noteDate);
            } else {
                $nid = (int)($_POST['note_id'] ?? 0);
                $st  = mysqli_prepare($conn, "UPDATE notes SET title=?,content=?,category=?,note_date=? WHERE id=? AND user_id=?");
                mysqli_stmt_bind_param($st, 'ssssii', $title, $content, $cat, $noteDate, $nid, $userId);
            }
            mysqli_stmt_execute($st);
        }
    } elseif ($action === 'delete') {
        $nid = (int)($_POST['note_id'] ?? 0);
        $st  = mysqli_prepare($conn, "DELETE FROM notes WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($st, 'ii', $nid, $userId);
        mysqli_stmt_execute($st);
    }

    $redir = 'notes.php';
    if (!empty($_POST['filter_cat'])) $redir .= '?cat=' . urlencode($_POST['filter_cat']);
    header('Location: ' . $redir);
    exit;
}

$filterCat = $_GET['cat'] ?? 'all';
$search    = trim($_GET['q'] ?? '');

// Count per category
$counts = ['all' => 0];
$rC = mysqli_query($conn, "SELECT category, COUNT(*) AS c FROM notes WHERE user_id={$userId} GROUP BY category");
if ($rC) {
    while ($row = mysqli_fetch_assoc($rC)) {
        $counts[$row['category']] = (int)$row['c'];
        $counts['all'] += (int)$row['c'];
    }
}

// Fetch notes
$sqlWhere = "user_id={$userId}";
if ($filterCat !== 'all' && isset($cats[$filterCat])) {
    $sqlWhere .= " AND category='" . mysqli_real_escape_string($conn, $filterCat) . "'";
}
if ($search !== '') {
    $like = "'" . mysqli_real_escape_string($conn, '%' . $search . '%') . "'";
    $sqlWhere .= " AND (title LIKE {$like} OR content LIKE {$like})";
}
$rN    = mysqli_query($conn, "SELECT * FROM notes WHERE {$sqlWhere} ORDER BY note_date DESC, id DESC");
$notes = $rN ? mysqli_fetch_all($rN, MYSQLI_ASSOC) : [];

include 'partials/header.php';
?>

<div class="page-hero mb-3">
    <div class="d-flex align-items-center gap-3">
        <div class="page-hero-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
        <div>
            <div class="page-hero-title">โน็ตส่วนตัว</div>
            <div class="page-hero-sub">บันทึกร้านอาหาร สถานที่ ความทรงจำ และวันพิเศษ</div>
        </div>
    </div>
    <div class="page-hero-actions mt-3">
        <button class="btn-hero btn-hero-primary" data-bs-toggle="modal" data-bs-target="#noteModal">
            <i class="bi bi-plus-lg"></i> เพิ่มโน็ต
        </button>
    </div>
</div>

<!-- Filter & Search -->
<div class="card card-soft mb-3">
    <div class="card-body p-3">
        <form method="get" class="mb-3">
            <input type="hidden" name="cat" value="<?= h($filterCat) ?>">
            <div class="input-icon-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="form-control" placeholder="ค้นหาโน็ต..." value="<?= h($search) ?>">
            </div>
        </form>
        <div class="d-flex flex-wrap gap-2">
            <a href="notes.php<?= $search !== '' ? '?q=' . urlencode($search) : '' ?>"
               class="badge text-decoration-none fs-6 py-2 px-3 <?= $filterCat === 'all' ? 'bg-primary text-white' : 'bg-light text-dark border' ?>">
                ทั้งหมด (<?= $counts['all'] ?? 0 ?>)
            </a>
            <?php foreach ($cats as $key => $c): ?>
                <a href="notes.php?cat=<?= $key ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                   class="badge text-decoration-none fs-6 py-2 px-3"
                   style="background:<?= $filterCat === $key ? $c['color'] : $c['bg'] ?>;color:<?= $filterCat === $key ? '#fff' : $c['color'] ?>;border:1.5px solid <?= $c['color'] ?>">
                    <i class="bi <?= $c['icon'] ?>"></i> <?= h($c['label']) ?> (<?= $counts[$key] ?? 0 ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Notes Grid -->
<?php if (empty($notes)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-journal-x" style="font-size:3rem;display:block;margin-bottom:.75rem;opacity:.4"></i>
        <div class="fw-600">ยังไม่มีโน็ต<?= $search !== '' ? ' ที่ตรงกับการค้นหา' : '' ?></div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($notes as $note):
            $c       = $cats[$note['category']] ?? $cats['other'];
            $dAD     = $note['note_date'] ? strtotime($note['note_date']) : null;
            $dateTH  = $dAD ? date('d/m/', $dAD) . ((int)date('Y', $dAD) + 543) : '-';
            $raw     = trim((string)$note['content']);
            $preview = preg_replace('/^(.{140}).+$/su', '$1…', $raw);
        ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card card-soft h-100" style="border-left:4px solid <?= $c['color'] ?>">
                    <div class="card-body d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge rounded-pill py-1 px-2" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;font-size:.78rem">
                                <i class="bi <?= $c['icon'] ?>"></i> <?= h($c['label']) ?>
                            </span>
                            <small class="text-muted"><?= $dateTH ?></small>
                        </div>
                        <div class="fw-800 fs-6"><?= h($note['title']) ?></div>
                        <?php if ($preview !== ''): ?>
                            <div class="small text-secondary flex-grow-1" style="white-space:pre-wrap;word-break:break-word"><?= h($preview) ?></div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mt-2">
                            <button class="btn btn-sm btn-outline-primary flex-fill fw-700 js-edit-note"
                                data-id="<?= (int)$note['id'] ?>"
                                data-title="<?= h($note['title']) ?>"
                                data-content="<?= h($note['content']) ?>"
                                data-category="<?= h($note['category']) ?>"
                                data-date="<?= h($note['note_date']) ?>">
                                <i class="bi bi-pencil-fill"></i> แก้ไข
                            </button>
                            <button class="btn btn-sm btn-outline-danger fw-700 js-delete-note"
                                data-id="<?= (int)$note['id'] ?>"
                                data-title="<?= h($note['title']) ?>">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add/Edit Modal -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="noteForm">
                <input type="hidden" name="action" value="add" id="noteAction">
                <input type="hidden" name="note_id" value="" id="noteId">
                <input type="hidden" name="filter_cat" value="<?= h($filterCat) ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-800" id="noteModalTitle">เพิ่มโน็ต</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-700 small">หัวข้อ *</label>
                        <input type="text" name="title" id="noteTitleInput" class="form-control" required placeholder="ชื่อโน็ต...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-700 small">หมวด</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($cats as $key => $c): ?>
                                <input type="radio" class="btn-check" name="category" id="cat_<?= $key ?>" value="<?= $key ?>">
                                <label class="btn btn-sm fw-700" for="cat_<?= $key ?>"
                                    style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;border:1.5px solid <?= $c['color'] ?>">
                                    <i class="bi <?= $c['icon'] ?>"></i> <?= h($c['label']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-700 small">วันที่</label>
                        <input type="date" name="note_date" id="noteDateInput" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-700 small">รายละเอียด</label>
                        <textarea name="content" id="noteContentInput" class="form-control" rows="6" placeholder="เขียนโน็ตที่นี่..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-700"><i class="bi bi-check-lg"></i> บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="note_id" id="deleteNoteId">
                <input type="hidden" name="filter_cat" value="<?= h($filterCat) ?>">
                <div class="modal-body text-center py-4">
                    <i class="bi bi-trash3-fill text-danger" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
                    <div class="fw-800 mb-1">ลบโน็ตนี้?</div>
                    <div class="text-muted small mb-4" id="deleteNoteTitle"></div>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-danger fw-700">ลบ</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var noteModal = new bootstrap.Modal(document.getElementById('noteModal'));
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    document.querySelectorAll('.js-edit-note').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('noteModalTitle').textContent = 'แก้ไขโน็ต';
            document.getElementById('noteAction').value = 'edit';
            document.getElementById('noteId').value = this.dataset.id;
            document.getElementById('noteTitleInput').value = this.dataset.title;
            document.getElementById('noteContentInput').value = this.dataset.content;
            document.getElementById('noteDateInput').value = this.dataset.date;
            var cat = document.getElementById('cat_' + this.dataset.category);
            if (cat) cat.checked = true;
            noteModal.show();
        });
    });

    document.querySelectorAll('.js-delete-note').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('deleteNoteId').value = this.dataset.id;
            document.getElementById('deleteNoteTitle').textContent = this.dataset.title;
            deleteModal.show();
        });
    });

    // Auto-open add modal when ?add=1
<?php if (isset($_GET['add'])): ?>
    noteModal.show();
<?php endif; ?>

document.getElementById('noteModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('noteForm').reset();
        document.getElementById('noteModalTitle').textContent = 'เพิ่มโน็ต';
        document.getElementById('noteAction').value = 'add';
        document.getElementById('noteId').value = '';
        document.getElementById('noteDateInput').value = '<?= date('Y-m-d') ?>';
        document.getElementById('cat_other').checked = true;
    });
});
</script>

<?php include 'partials/footer.php'; ?>
