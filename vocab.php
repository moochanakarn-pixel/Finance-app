<?php
header('Content-Type: text/html; charset=UTF-8');
include 'auth.php';
include 'config/db.php';
$page_title = 'จดคำศัพท์';
$userId = (int)$_SESSION['user_id'];

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add', 'edit'], true)) {
        $word    = trim($_POST['word'] ?? '');
        $meaning = trim($_POST['meaning'] ?? '');
        $example = trim($_POST['example'] ?? '');
        $note    = trim($_POST['note'] ?? '');
        if ($word !== '' && $meaning !== '') {
            if ($action === 'add') {
                $st = mysqli_prepare($conn, "INSERT INTO vocab (user_id,word,meaning,example,note) VALUES (?,?,?,?,?)");
                mysqli_stmt_bind_param($st, 'issss', $userId, $word, $meaning, $example, $note);
            } else {
                $vid = (int)($_POST['vocab_id'] ?? 0);
                $st  = mysqli_prepare($conn, "UPDATE vocab SET word=?,meaning=?,example=?,note=? WHERE id=? AND user_id=?");
                mysqli_stmt_bind_param($st, 'ssssii', $word, $meaning, $example, $note, $vid, $userId);
            }
            mysqli_stmt_execute($st);
        }
    } elseif ($action === 'delete') {
        $vid = (int)($_POST['vocab_id'] ?? 0);
        $st  = mysqli_prepare($conn, "DELETE FROM vocab WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($st, 'ii', $vid, $userId);
        mysqli_stmt_execute($st);
    }

    $mode = !empty($_POST['mode']) ? '?mode=' . urlencode($_POST['mode']) : '';
    header('Location: vocab.php' . $mode);
    exit;
}

$mode   = $_GET['mode'] ?? 'list';
$search = trim($_GET['q'] ?? '');

$sql   = "SELECT * FROM vocab WHERE user_id=?";
$parms = [$userId];
$types = 'i';
if ($search !== '') {
    $like   = '%' . $search . '%';
    $sql   .= " AND (word LIKE ? OR meaning LIKE ? OR example LIKE ?)";
    $parms[] = $like; $parms[] = $like; $parms[] = $like;
    $types .= 'sss';
}
$sql .= " ORDER BY id DESC";
$stV = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stV, $types, ...$parms);
mysqli_stmt_execute($stV);
$vocab = mysqli_fetch_all(mysqli_stmt_get_result($stV), MYSQLI_ASSOC);
$total = count($vocab);

include 'partials/header.php';
?>

<div class="page-hero mb-3">
    <div class="d-flex align-items-center gap-3">
        <div class="page-hero-icon"><i class="bi bi-translate"></i></div>
        <div>
            <div class="page-hero-title">จดคำศัพท์</div>
            <div class="page-hero-sub"><?= $total ?> คำ · ฝึกด้วย Flashcard ได้เลย</div>
        </div>
    </div>
    <div class="page-hero-actions mt-3">
        <a href="vocab.php?mode=<?= $mode === 'flash' ? 'list' : 'flash' ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
           class="btn-hero">
            <i class="bi <?= $mode === 'flash' ? 'bi-list-ul' : 'bi-layers-fill' ?>"></i>
            <?= $mode === 'flash' ? 'โหมดรายการ' : 'โหมด Flashcard' ?>
        </a>
        <button class="btn-hero btn-hero-primary" data-bs-toggle="modal" data-bs-target="#vocabModal">
            <i class="bi bi-plus-lg"></i> เพิ่มคำศัพท์
        </button>
    </div>
</div>

<?php if ($mode !== 'flash'): ?>
<!-- LIST MODE -->
<div class="card card-soft mb-3">
    <div class="card-body p-3">
        <form method="get">
            <input type="hidden" name="mode" value="list">
            <div class="input-icon-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="form-control" placeholder="ค้นหาคำศัพท์..." value="<?= h($search) ?>">
            </div>
        </form>
    </div>
</div>

<?php if (empty($vocab)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-book" style="font-size:3rem;display:block;margin-bottom:.75rem;opacity:.4"></i>
        <div class="fw-600">ยังไม่มีคำศัพท์<?= $search !== '' ? ' ที่ตรงกับการค้นหา' : '' ?></div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($vocab as $v): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card card-soft h-100" style="border-left:4px solid #6366f1">
                    <div class="card-body d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="fw-800" style="font-size:1.15rem;color:#4338ca"><?= h($v['word']) ?></div>
                            <span class="badge bg-indigo text-white" style="background:#eef2ff;color:#4f46e5;font-size:.75rem">EN</span>
                        </div>
                        <div class="fw-700 text-secondary"><?= h($v['meaning']) ?></div>
                        <?php if (trim($v['example']) !== ''): ?>
                            <div class="small p-2 rounded" style="background:#f8fafc;border-left:3px solid #c7d2fe;color:#334155;font-style:italic">
                                <?= h($v['example']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (trim($v['note']) !== ''): ?>
                            <div class="small text-muted"><?= h($v['note']) ?></div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mt-auto">
                            <button class="btn btn-sm btn-outline-primary flex-fill fw-700 js-edit-vocab"
                                data-id="<?= (int)$v['id'] ?>"
                                data-word="<?= h($v['word']) ?>"
                                data-meaning="<?= h($v['meaning']) ?>"
                                data-example="<?= h($v['example']) ?>"
                                data-note="<?= h($v['note']) ?>">
                                <i class="bi bi-pencil-fill"></i> แก้ไข
                            </button>
                            <button class="btn btn-sm btn-outline-danger fw-700 js-delete-vocab"
                                data-id="<?= (int)$v['id'] ?>"
                                data-word="<?= h($v['word']) ?>">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php else: ?>
<!-- FLASHCARD MODE -->
<?php if (empty($vocab)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-layers" style="font-size:3rem;display:block;margin-bottom:.75rem;opacity:.4"></i>
        <div class="fw-600">ยังไม่มีคำศัพท์</div>
    </div>
<?php else: ?>
<style>
.flash-scene { perspective: 1000px; width: 100%; max-width: 520px; margin: 0 auto; cursor: pointer; }
.flash-card  { position: relative; width: 100%; padding-top: 62%; transform-style: preserve-3d; transition: transform .5s cubic-bezier(.4,0,.2,1); }
.flash-card.flipped { transform: rotateY(180deg); }
.flash-face  {
    position: absolute; inset: 0; border-radius: 24px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 2rem; backface-visibility: hidden; -webkit-backface-visibility: hidden;
    box-shadow: 0 8px 32px rgba(99,102,241,.18);
}
.flash-front { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #fff; }
.flash-back  { background: #fff; border: 2px solid #e0e7ff; transform: rotateY(180deg); }
.flash-word  { font-size: clamp(1.8rem, 5vw, 2.8rem); font-weight: 800; text-align: center; }
.flash-hint  { font-size: .85rem; opacity: .72; margin-top: .5rem; }
.flash-meaning { font-size: clamp(1.2rem, 3vw, 1.7rem); font-weight: 800; color: #1e1b4b; text-align: center; }
.flash-example { font-size: .9rem; color: #64748b; margin-top: .75rem; font-style: italic; text-align: center; line-height: 1.5; }
.flash-controls { display: flex; align-items: center; gap: 1rem; margin-top: 1.5rem; justify-content: center; }
.flash-btn { width: 48px; height: 48px; border-radius: 50%; border: 2px solid #e0e7ff; background: #fff; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: .15s; box-shadow: 0 2px 8px rgba(99,102,241,.1); }
.flash-btn:hover { background: #eef2ff; border-color: #6366f1; color: #4338ca; }
.flash-counter { font-weight: 800; color: #4338ca; min-width: 70px; text-align: center; }
</style>

<?php $vocabJson = json_encode(array_values($vocab), JSON_UNESCAPED_UNICODE); ?>

<div class="mb-4" style="max-width:520px;margin:0 auto">
    <div class="flash-scene" id="flashScene" onclick="flipCard()">
        <div class="flash-card" id="flashCard">
            <div class="flash-face flash-front">
                <div class="flash-word" id="flashWord"></div>
                <div class="flash-hint">แตะการ์ดเพื่อดูความหมาย</div>
            </div>
            <div class="flash-face flash-back">
                <div class="flash-meaning" id="flashMeaning"></div>
                <div class="flash-example" id="flashExample"></div>
            </div>
        </div>
    </div>

    <div class="flash-controls">
        <button class="flash-btn" onclick="prevCard()" title="ก่อนหน้า"><i class="bi bi-chevron-left"></i></button>
        <div class="flash-counter"><span id="flashCur">1</span> / <span id="flashTotal"><?= $total ?></span></div>
        <button class="flash-btn" onclick="nextCard()" title="ถัดไป"><i class="bi bi-chevron-right"></i></button>
    </div>

    <div class="text-center mt-3">
        <button class="btn btn-sm btn-outline-secondary" onclick="shuffleCards()">
            <i class="bi bi-shuffle"></i> สุ่มการ์ด
        </button>
    </div>
</div>

<script>
(function () {
    var data = <?= $vocabJson ?>;
    var idx  = 0;
    var card = document.getElementById('flashCard');

    function shuffle(arr) {
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
        }
    }

    function show(i) {
        card.classList.remove('flipped');
        var v = data[i];
        document.getElementById('flashWord').textContent    = v.word;
        document.getElementById('flashMeaning').textContent = v.meaning;
        document.getElementById('flashExample').textContent = v.example || '';
        document.getElementById('flashCur').textContent     = i + 1;
    }

    window.flipCard = function () { card.classList.toggle('flipped'); };
    window.nextCard = function () { idx = (idx + 1) % data.length; show(idx); };
    window.prevCard = function () { idx = (idx - 1 + data.length) % data.length; show(idx); };
    window.shuffleCards = function () { shuffle(data); idx = 0; show(0); };

    // Keyboard navigation
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') nextCard();
        else if (e.key === 'ArrowLeft') prevCard();
        else if (e.key === ' ') { e.preventDefault(); flipCard(); }
    });

    show(0);
})();
</script>
<?php endif; ?>
<?php endif; ?>

<!-- Add/Edit Modal -->
<div class="modal fade" id="vocabModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="vocabForm">
                <input type="hidden" name="action" value="add" id="vocabAction">
                <input type="hidden" name="vocab_id" value="" id="vocabId">
                <input type="hidden" name="mode" value="<?= h($mode) ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-800" id="vocabModalTitle">เพิ่มคำศัพท์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-700 small">คำศัพท์ภาษาอังกฤษ *</label>
                        <input type="text" name="word" id="vocabWordInput" class="form-control fw-700"
                            required placeholder="e.g. Persistent" style="font-size:1.1rem">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-700 small">ความหมายภาษาไทย *</label>
                        <input type="text" name="meaning" id="vocabMeaningInput" class="form-control"
                            required placeholder="e.g. ยืนหยัด, ไม่ยอมแพ้">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-700 small">ตัวอย่างประโยค</label>
                        <textarea name="example" id="vocabExampleInput" class="form-control" rows="3"
                            placeholder="e.g. She is persistent in achieving her goals."></textarea>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-700 small">โน็ต / เคล็ดลับจำ</label>
                        <input type="text" name="note" id="vocabNoteInput" class="form-control"
                            placeholder="เช่น พบบ่อยใน TOEIC, คำนี้จำด้วย...">
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
<div class="modal fade" id="vocabDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="vocabDeleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="vocab_id" id="deleteVocabId">
                <input type="hidden" name="mode" value="<?= h($mode) ?>">
                <div class="modal-body text-center py-4">
                    <i class="bi bi-trash3-fill text-danger" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
                    <div class="fw-800 mb-1">ลบคำศัพท์นี้?</div>
                    <div class="text-muted small mb-4 fw-700" id="deleteVocabWord"></div>
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
(function () {
    var vocabModal  = new bootstrap.Modal(document.getElementById('vocabModal'));
    var deleteModal = new bootstrap.Modal(document.getElementById('vocabDeleteModal'));

    document.querySelectorAll('.js-edit-vocab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('vocabModalTitle').textContent = 'แก้ไขคำศัพท์';
            document.getElementById('vocabAction').value           = 'edit';
            document.getElementById('vocabId').value               = this.dataset.id;
            document.getElementById('vocabWordInput').value        = this.dataset.word;
            document.getElementById('vocabMeaningInput').value     = this.dataset.meaning;
            document.getElementById('vocabExampleInput').value     = this.dataset.example;
            document.getElementById('vocabNoteInput').value        = this.dataset.note;
            vocabModal.show();
        });
    });

    document.querySelectorAll('.js-delete-vocab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('deleteVocabId').value  = this.dataset.id;
            document.getElementById('deleteVocabWord').textContent = this.dataset.word;
            deleteModal.show();
        });
    });

    document.getElementById('vocabModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('vocabForm').reset();
        document.getElementById('vocabModalTitle').textContent = 'เพิ่มคำศัพท์';
        document.getElementById('vocabAction').value = 'add';
        document.getElementById('vocabId').value     = '';
    });
})();
</script>

<?php include 'partials/footer.php'; ?>
