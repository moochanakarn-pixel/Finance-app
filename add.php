<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];
$page_title = 'เพิ่มรายการ';
$categories = array();
$res = mysqli_query($conn, "
    SELECT * FROM categories
    WHERE is_active = 1
      AND user_id = {$userId}
    ORDER BY FIELD(type,'income','saving','expense'), sort_order ASC, id ASC
");
while ($res && $row = mysqli_fetch_assoc($res)) {
    $categories[] = $row;
}

$typeLabels = array('income' => 'รายรับ', 'expense' => 'รายจ่าย', 'saving' => 'เงินออม');
$today = date('Y-m-d');
$currentYearBE = date('Y') + 543;
$returnUrl = 'entries.php?year=' . $currentYearBE;
$categoryOptionsHtml = '';
foreach ($categories as $cat) {
    $label = isset($typeLabels[$cat['type']]) ? $typeLabels[$cat['type']] : strtoupper($cat['type']);
    $categoryOptionsHtml .= '<option value="' . (int)$cat['id'] . '">[' . h($label) . '] ' . h($cat['name']) . '</option>';
}

include 'partials/header.php';
?>
<style>
.batch-inline-card { border:1px solid rgba(15,23,42,.06); border-radius:20px; }
.batch-inline-head,.batch-inline-row { display:grid; grid-template-columns: 150px 1.2fr 1fr 1.5fr auto; gap:.75rem; align-items:start; }
.batch-inline-head { padding:.8rem .95rem; background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; font-weight:800; color:#475569; font-size:.85rem; }
.batch-inline-wrap { display:grid; gap:.75rem; }
.batch-inline-row { padding:.9rem; border:1px solid #e5e7eb; border-radius:16px; background:#fff; }
.batch-inline-actions { display:flex; gap:.4rem; flex-wrap:wrap; }
.batch-inline-summary { background:#f8fafc; border:1px dashed #cbd5e1; border-radius:14px; padding:.95rem 1rem; }
.batch-inline-total { font-size:1.12rem; font-weight:800; color:#0f172a; }
.batch-inline-topbar { display:flex; flex-wrap:wrap; gap:.5rem; }
.batch-inline-mainhint { color:#64748b; font-size:.95rem; }
@media (max-width: 991.98px){ .batch-inline-head { display:none; } .batch-inline-row { grid-template-columns:1fr; } }
</style>

<div class="page-hero">
    <div class="page-hero-icon"><i class="bi bi-plus-circle-fill"></i></div>
    <div class="page-hero-title">เพิ่มรายการ</div>
    <div class="page-hero-sub">บันทึกรายรับ รายจ่าย หรือเงินออม — รองรับหลายแถวพร้อมกัน</div>
    <div class="page-hero-actions">
        <a href="add_mobile.php" class="btn-hero btn-hero-primary"><i class="bi bi-phone me-1"></i>โหมดมือถือ</a>
        <a href="entries.php" class="btn-hero"><i class="bi bi-list me-1"></i>ดูรายการทั้งหมด</a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-xl-11">
        <div class="card card-soft batch-inline-card">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">เพิ่มรายการรายรับ / รายจ่าย / เงินออม</h4>
                        <div class="batch-inline-mainhint">เพิ่มได้ทั้งแบบรายการเดียว หรือกดเพิ่มหลายแถวแล้วบันทึกทั้งหมดครั้งเดียว — <a href="add_mobile.php">ใช้มือถือ? คลิกที่นี่</a></div>
                    </div>
                    <a href="entries.php?year=<?php echo (int)$currentYearBE; ?>" class="btn btn-outline-secondary">กลับหน้ารายการทั้งหมด</a>
                </div>

                <form action="save_entry.php" method="post" id="smart-add-form">
                    <input type="hidden" name="action" value="batch_add">
                    <input type="hidden" name="year_be" value="<?php echo (int)$currentYearBE; ?>">
                    <input type="hidden" name="return_url" value="<?php echo h($returnUrl); ?>">

                    <div class="batch-inline-topbar mb-3">
                        <button type="button" class="preset-chip secondary" id="smart-add-row">+ เพิ่มอีก 1 แถว</button>
                        <button type="button" class="preset-chip secondary" id="smart-add-5">+ เพิ่มอีก 5 แถว</button>
                        <button type="button" class="preset-chip secondary" id="smart-copy-date">คัดลอกวันที่แถวแรกลงทุกแถว</button>
                        <button type="button" class="preset-chip secondary" id="smart-copy-category">คัดลอกหมวดแถวแรกลงทุกแถว</button>
                    </div>

                    <div class="batch-inline-summary mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                            <div>จำนวนแถวทั้งหมด: <strong id="smart-row-count">1</strong></div>
                            <div class="batch-inline-total">รวมทั้งหมด: <span id="smart-grand-total">0.00</span> บาท</div>
                        </div>
                    </div>

                    <div class="batch-inline-head mb-2">
                        <div>วันที่</div>
                        <div>หมวดหมู่</div>
                        <div>จำนวนเงิน</div>
                        <div>รายละเอียด</div>
                        <div class="text-center">จัดการ</div>
                    </div>

                    <div class="batch-inline-wrap" id="smart-rows"></div>

                    <div class="row mt-3">
                        <div class="col-lg-7">
                            <div class="note-box h-100">
                                <strong>ตัวอย่างการกรอกเร็ว</strong><br>
                                จำนวนเงินพิมพ์แบบ <code>30+120+40</code> ได้<br>
                                เหมาะกับการคีย์ย้อนหลังทีเดียวหลายรายการ<br>
                                ถ้ามีแค่ 1 แถว ระบบก็ทำงานเหมือนการเพิ่มรายการปกติ
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2 flex-wrap mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">บันทึกทั้งหมด</button>
                        <a href="index.php" class="btn btn-outline-secondary">กลับหน้า Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.smartAddCategoryOptionsHtml = <?php echo json_encode($categoryOptionsHtml, JSON_UNESCAPED_UNICODE); ?>;
window.smartAddDefaultDate = <?php echo json_encode($today); ?>;
</script>
<script>
(function(){
  function normalizeAmountExpression(value){ value=(value||'').toString().replace(/,/g,'').replace(/\s+/g,''); if(!value) return ''; if(!/^[0-9+\-.]+$/.test(value)) return value; return value; }
  function evaluateAmountExpression(value){ value=normalizeAmountExpression(value); if(!value) return 0; if(!/^[0-9+\-.]+$/.test(value)) return NaN; var tokens=value.match(/[+\-]?\d+(?:\.\d+)?/g); if(!tokens) return NaN; var total=0; for(var i=0;i<tokens.length;i++){ total += parseFloat(tokens[i]); } return total; }
  var wrap=document.getElementById('smart-rows'); if(!wrap) return;
  function uniqueIndex(){ return Date.now().toString() + Math.floor(Math.random()*100000).toString(); }
  function rowTemplate(index){
    return '<div class="batch-inline-row" data-row>' +
      '<div><label class="form-label fw-semibold d-lg-none">วันที่</label><input type="date" class="form-control" name="batch['+index+'][entry_date]" value="'+window.smartAddDefaultDate+'" required></div>' +
      '<div><label class="form-label fw-semibold d-lg-none">หมวดหมู่</label><select class="form-select" name="batch['+index+'][category_id]" required><option value="">เลือกหมวดหมู่</option>'+window.smartAddCategoryOptionsHtml+'</select></div>' +
      '<div><label class="form-label fw-semibold d-lg-none">จำนวนเงิน</label><input type="text" class="form-control js-smart-amount" name="batch['+index+'][amount]" placeholder="เช่น 100+50+20" required><div class="small text-muted mt-1">รวม: <span class="js-row-total">0.00</span> บาท</div></div>' +
      '<div><label class="form-label fw-semibold d-lg-none">รายละเอียด</label><textarea class="form-control" name="batch['+index+'][note]" rows="3" placeholder="บอกให้ละเอียดได้เลย ว่าทำอะไรไป เช่น ซื้ออะไร ที่ไหน หรือจ่ายค่าอะไร"></textarea></div>' +
      '<div class="batch-inline-actions"><button type="button" class="btn btn-outline-secondary btn-sm js-duplicate-row">คัดลอก</button><button type="button" class="btn btn-outline-danger btn-sm js-remove-row">ลบ</button></div>' +
    '</div>';
  }
  function refreshSummary(){
    var rows=wrap.querySelectorAll('[data-row]');
    document.getElementById('smart-row-count').textContent=rows.length;
    var grand=0;
    rows.forEach(function(row){
      var input=row.querySelector('.js-smart-amount');
      var totalEl=row.querySelector('.js-row-total');
      var total=evaluateAmountExpression(input.value);
      if(isNaN(total)){ totalEl.textContent='-'; }
      else { totalEl.textContent=total.toFixed(2); grand+=total; }
    });
    document.getElementById('smart-grand-total').textContent=grand.toFixed(2);
  }
  function addRows(count){ for(var i=0;i<count;i++){ wrap.insertAdjacentHTML('beforeend', rowTemplate(uniqueIndex()+i)); } refreshSummary(); }
  addRows(1);
  document.getElementById('smart-add-row').addEventListener('click', function(){ addRows(1); });
  document.getElementById('smart-add-5').addEventListener('click', function(){ addRows(5); });
  document.getElementById('smart-copy-date').addEventListener('click', function(){ var first=wrap.querySelector('[name$="[entry_date]"]'); if(!first) return; wrap.querySelectorAll('[name$="[entry_date]"]').forEach(function(el,idx){ if(idx>0) el.value=first.value; }); });
  document.getElementById('smart-copy-category').addEventListener('click', function(){ var first=wrap.querySelector('[name$="[category_id]"]'); if(!first) return; wrap.querySelectorAll('[name$="[category_id]"]').forEach(function(el,idx){ if(idx>0) el.value=first.value; }); });
  wrap.addEventListener('input', function(e){ if(e.target.classList.contains('js-smart-amount')) refreshSummary(); });
  wrap.addEventListener('click', function(e){
    var btn=e.target.closest('button'); if(!btn) return;
    var row=e.target.closest('[data-row]'); if(!row) return;
    if(btn.classList.contains('js-remove-row')){ if(wrap.querySelectorAll('[data-row]').length > 1){ row.remove(); refreshSummary(); } return; }
    if(btn.classList.contains('js-duplicate-row')){
      var newIndex=uniqueIndex();
      wrap.insertAdjacentHTML('beforeend', rowTemplate(newIndex));
      var rows=wrap.querySelectorAll('[data-row]');
      var newRow=rows[rows.length-1];
      var oldDate=row.querySelector('[name$="[entry_date]"]').value;
      var oldCategory=row.querySelector('[name$="[category_id]"]').value;
      var oldAmount=row.querySelector('[name$="[amount]"]').value;
      var oldNote=row.querySelector('[name$="[note]"]').value;
      newRow.querySelector('[name$="[entry_date]"]').value=oldDate;
      newRow.querySelector('[name$="[category_id]"]').value=oldCategory;
      newRow.querySelector('[name$="[amount]"]').value=oldAmount;
      newRow.querySelector('[name$="[note]"]').value=oldNote;
      refreshSummary();
    }
  });
  document.getElementById('smart-add-form').addEventListener('submit', function(){
    wrap.querySelectorAll('.js-smart-amount').forEach(function(input){ var total=evaluateAmountExpression(input.value); if(!isNaN(total) && input.value.trim()!==''){ input.value=total.toFixed(2).replace(/\.00$/,''); } });
  });
})();
</script>
<?php include 'partials/footer.php'; ?>
