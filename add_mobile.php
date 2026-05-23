<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId     = (int)$_SESSION['user_id'];
$page_title = 'เพิ่มรายการ';

$categories = ['income' => [], 'saving' => [], 'expense' => []];
$stmt = mysqli_prepare($conn, "SELECT id, name, type FROM categories WHERE is_active = 1 AND user_id = ? ORDER BY FIELD(type,'income','saving','expense'), sort_order ASC, id ASC");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($rs)) {
    if (isset($categories[$r['type']])) $categories[$r['type']][] = $r;
}
mysqli_stmt_close($stmt);

$today         = date('Y-m-d');
$currentYearBE = date('Y') + 543;
$returnUrl     = 'entries.php?year=' . $currentYearBE;
$typeLabels    = ['income' => 'รายรับ', 'expense' => 'รายจ่าย', 'saving' => 'เงินออม'];
$typeColors    = ['income' => '#15803d', 'expense' => '#dc2626', 'saving' => '#7c3aed'];
$typeBg        = ['income' => '#dcfce7', 'expense' => '#fee2e2', 'saving' => '#ede9fe'];

// preset amounts per type
$presets = [50, 100, 150, 200, 300, 500, 1000];

include 'partials/header.php';
?>
<style>
:root{--mob-radius:20px;--mob-bottom:80px}
.mob-wrap{max-width:480px;margin:0 auto;padding:0 0 calc(var(--mob-bottom) + 1rem)}
.type-tabs{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:1.25rem}
.type-tab{border:2px solid transparent;border-radius:16px;padding:.65rem .5rem;text-align:center;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .15s;background:#f8fafc;color:#64748b}
.type-tab.active{color:#fff}
.type-tab[data-type=income].active{background:#15803d;border-color:#15803d}
.type-tab[data-type=expense].active{background:#dc2626;border-color:#dc2626}
.type-tab[data-type=saving].active{background:#7c3aed;border-color:#7c3aed}
.type-tab-dot{width:8px;height:8px;border-radius:50%;margin:0 auto 5px;background:currentColor;opacity:.4}
.type-tab.active .type-tab-dot{opacity:1;background:#fff}

.amount-display{text-align:center;padding:1.25rem 1rem .75rem;background:#fff;border-radius:var(--mob-radius);border:1px solid #e5e7eb;margin-bottom:1rem}
.amount-expression{font-size:.85rem;color:#94a3b8;min-height:1.2em;word-break:break-all}
.amount-value{font-size:2.6rem;font-weight:800;color:#0f172a;line-height:1.1}
.amount-value.is-income{color:#15803d}
.amount-value.is-expense{color:#dc2626}
.amount-value.is-saving{color:#7c3aed}
.amount-unit{font-size:1rem;font-weight:400;color:#94a3b8}

.numpad{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:1rem}
.nk{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:.8rem .5rem;font-size:1.25rem;font-weight:700;text-align:center;cursor:pointer;color:#0f172a;transition:background .1s;-webkit-tap-highlight-color:transparent}
.nk:active{background:#f1f5f9}
.nk.op{color:#475569;font-size:1.1rem}
.nk.del{color:#dc2626}
.nk.zero{grid-column:span 2}
.nk.confirm{background:#0f172a;color:#fff;border-color:#0f172a;font-size:1rem}
.nk.confirm:active{background:#1e293b}

.field-card{background:#fff;border-radius:var(--mob-radius);border:1px solid #e5e7eb;margin-bottom:.75rem;overflow:hidden}
.field-row{display:flex;align-items:center;padding:.85rem 1rem;gap:.75rem;border-bottom:1px solid #f1f5f9}
.field-row:last-child{border-bottom:none}
.field-icon{width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.field-label{font-size:.8rem;color:#94a3b8;font-weight:600;margin-bottom:2px}
.field-value{font-size:.95rem;color:#0f172a;font-weight:600}
.field-input{flex:1;min-width:0}
.field-input input,.field-input select,.field-input textarea{width:100%;border:none;outline:none;background:transparent;font-size:.95rem;color:#0f172a;font-family:inherit;font-weight:600;padding:0}
.field-input select{cursor:pointer}
.field-input textarea{resize:none;line-height:1.4}

.cat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;padding:.75rem}
.cat-chip{border:1.5px solid #e5e7eb;border-radius:14px;padding:.6rem .75rem;font-size:.85rem;font-weight:600;cursor:pointer;color:#475569;background:#f8fafc;transition:all .12s;-webkit-tap-highlight-color:transparent}
.cat-chip.active{border-color:var(--cat-color);background:var(--cat-bg);color:var(--cat-color)}
.cat-chip:active{opacity:.7}
.cat-group-label{font-size:.75rem;font-weight:700;color:#94a3b8;letter-spacing:.06em;text-transform:uppercase;padding:.5rem .75rem .2rem}

.save-bar{position:sticky;bottom:0;left:0;right:0;padding:.75rem 1rem;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-top:1px solid #e5e7eb;z-index:50}
.save-btn{width:100%;padding:1rem;border-radius:18px;border:none;font-size:1.05rem;font-weight:800;cursor:pointer;color:#fff;background:#0f172a;transition:opacity .15s;font-family:inherit}
.save-btn:active{opacity:.85}
.save-btn:disabled{opacity:.4;cursor:not-allowed}
.save-btn.is-income{background:#15803d}
.save-btn.is-expense{background:#dc2626}
.save-btn.is-saving{background:#7c3aed}

.preset-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:.75rem}
.preset-chip{border:1px solid #e5e7eb;border-radius:99px;padding:.35rem .75rem;font-size:.82rem;font-weight:700;color:#475569;cursor:pointer;background:#f8fafc;-webkit-tap-highlight-color:transparent}
.preset-chip:active{background:#e5e7eb}

.toast{position:fixed;top:1rem;left:50%;transform:translateX(-50%) translateY(-60px);background:#0f172a;color:#fff;padding:.6rem 1.25rem;border-radius:99px;font-size:.9rem;font-weight:600;z-index:999;transition:transform .25s;pointer-events:none}
.toast.show{transform:translateX(-50%) translateY(0)}

.desktop-note{display:none}
@media(min-width:600px){
  .mob-wrap{padding:0 1rem calc(var(--mob-bottom) + 1rem)}
  .desktop-note{display:block;text-align:center;padding:.5rem;color:#94a3b8;font-size:.82rem;margin-bottom:.5rem}
}
</style>

<div class="mob-wrap">
  <div class="desktop-note">หน้านี้ออกแบบสำหรับมือถือ — <a href="add.php">ใช้หน้า desktop แทน</a></div>

  <form action="save_entry.php" method="post" id="mob-form">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="year_be" value="<?php echo $currentYearBE; ?>">
    <input type="hidden" name="return_url" value="<?php echo h($returnUrl); ?>">
    <input type="hidden" name="category_id" id="f-category-id" value="">
    <input type="hidden" name="entry_date"  id="f-date"        value="<?php echo $today; ?>">
    <input type="hidden" name="amount"      id="f-amount"      value="">
    <input type="hidden" name="note"        id="f-note"        value="">

    <div class="type-tabs">
      <?php foreach (['expense' => 'รายจ่าย', 'income' => 'รายรับ', 'saving' => 'เงินออม'] as $t => $label): ?>
        <div class="type-tab <?php echo $t === 'expense' ? 'active' : ''; ?>" data-type="<?php echo $t; ?>">
          <div class="type-tab-dot"></div>
          <?php echo $label; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="amount-display">
      <div class="amount-expression" id="amt-expr"></div>
      <div class="amount-value is-expense" id="amt-display">0</div>
      <div class="amount-unit">บาท</div>
    </div>

    <div class="preset-row" id="preset-row">
      <?php foreach ($presets as $p): ?>
        <div class="preset-chip" data-preset="<?php echo $p; ?>">+<?php echo number_format($p); ?></div>
      <?php endforeach; ?>
    </div>

    <div class="numpad">
      <?php
      $keys = ['7','8','9','+','4','5','6','-','1','2','3','.','0','0','⌫','='];
      foreach ($keys as $k):
        $cls = '';
        if ($k === '0' && $keys[array_search($k, $keys, true)] === '0') {}
        if (in_array($k, ['+','-','.'])) $cls = 'op';
        if ($k === '⌫') $cls = 'del';
        if ($k === '=') $cls = 'confirm';
      ?>
        <div class="nk <?php echo $cls; ?>" data-key="<?php echo h($k); ?>"><?php echo h($k); ?></div>
      <?php endforeach; ?>
    </div>

    <div class="field-card">
      <div class="field-row">
        <div class="field-icon" style="background:#fff7ed">📅</div>
        <div class="field-input">
          <div class="field-label">วันที่</div>
          <input type="date" id="disp-date" value="<?php echo $today; ?>">
        </div>
      </div>
      <div class="field-row">
        <div class="field-icon" style="background:#f0f9ff">📝</div>
        <div class="field-input">
          <div class="field-label">หมายเหตุ (ไม่บังคับ)</div>
          <textarea id="disp-note" rows="2" placeholder="เช่น ข้าวพี่ติง, Lotus, KFC..."></textarea>
        </div>
      </div>
    </div>

    <div class="field-card">
      <div class="cat-group-label" id="cat-group-label">เลือกหมวดรายจ่าย</div>
      <div class="cat-grid" id="cat-grid">
        <?php foreach (['expense','income','saving'] as $t):
          foreach ($categories[$t] as $cat): ?>
            <div class="cat-chip" data-cat-id="<?php echo $cat['id']; ?>"
                 data-cat-type="<?php echo $t; ?>"
                 data-cat-name="<?php echo h($cat['name']); ?>"
                 style="--cat-color:<?php echo $typeColors[$t]; ?>;--cat-bg:<?php echo $typeBg[$t]; ?>"
            ><?php echo h($cat['name']); ?></div>
        <?php endforeach; endforeach; ?>
      </div>
    </div>

  </form>
</div>

<div class="save-bar">
  <button class="save-btn is-expense" id="save-btn" disabled>เลือกหมวดหมู่ก่อน</button>
</div>

<div class="toast" id="toast"></div>

<script>
(function(){
  var currentType = 'expense';
  var expression  = '';
  var categoryId  = '';
  var categoryName = '';

  var typeColors = {expense:'#dc2626', income:'#15803d', saving:'#7c3aed'};
  var typeLabels = {expense:'รายจ่าย', income:'รายรับ', saving:'เงินออม'};
  var typeBg     = {expense:'#fee2e2', income:'#dcfce7', saving:'#ede9fe'};

  var amtDisplay  = document.getElementById('amt-display');
  var amtExpr     = document.getElementById('amt-expr');
  var saveBtn     = document.getElementById('save-btn');
  var catGrid     = document.getElementById('cat-grid');
  var catGroupLbl = document.getElementById('cat-group-label');
  var dispDate    = document.getElementById('disp-date');
  var dispNote    = document.getElementById('disp-note');

  function evaluate(expr){
    if(!expr) return 0;
    var tokens = expr.match(/[+\-]?\d+(?:\.\d+)?/g);
    if(!tokens) return 0;
    return tokens.reduce(function(s,v){return s+parseFloat(v);},0);
  }

  function formatNum(n){
    if(n===0) return '0';
    return n%1===0 ? n.toLocaleString() : n.toFixed(2).replace(/\.?0+$/,'');
  }

  function updateAmountDisplay(){
    var val = evaluate(expression);
    amtDisplay.textContent = formatNum(val);
    amtExpr.textContent = expression.replace(/^\+/,'');
    amtDisplay.className = 'amount-value is-'+currentType;
    document.getElementById('f-amount').value = val > 0 ? val.toFixed(2) : '';
    updateSaveBtn();
  }

  function updateSaveBtn(){
    var val = evaluate(expression);
    var hasAmt = val > 0;
    var hasCat = categoryId !== '';
    saveBtn.disabled = !(hasAmt && hasCat);
    saveBtn.className = 'save-btn is-'+currentType;
    if(!hasCat)       saveBtn.textContent = 'เลือกหมวดหมู่ก่อน';
    else if(!hasAmt)  saveBtn.textContent = 'กรอกจำนวนเงินก่อน';
    else              saveBtn.textContent = 'บันทึก'+typeLabels[currentType]+' ฿'+formatNum(val);
  }

  function filterCats(){
    var chips = catGrid.querySelectorAll('.cat-chip');
    chips.forEach(function(c){
      c.style.display = c.dataset.catType === currentType ? '' : 'none';
    });
    catGroupLbl.textContent = 'เลือกหมวด' + typeLabels[currentType];
    categoryId = ''; categoryName = '';
    document.getElementById('f-category-id').value = '';
    chips.forEach(function(c){ c.classList.remove('active'); });
    updateSaveBtn();
  }

  document.querySelectorAll('.type-tab').forEach(function(tab){
    tab.addEventListener('click', function(){
      document.querySelectorAll('.type-tab').forEach(function(t){ t.classList.remove('active'); });
      tab.classList.add('active');
      currentType = tab.dataset.type;
      filterCats();
      updateAmountDisplay();
    });
  });

  document.querySelectorAll('.nk').forEach(function(key){
    key.addEventListener('click', function(){
      var k = key.dataset.key;
      if(k === '='){
        var val = evaluate(expression);
        expression = val > 0 ? val.toFixed(2).replace(/\.?0+$/,'') : '';
      } else if(k === '⌫'){
        expression = expression.slice(0,-1);
      } else if(['+','-'].includes(k)){
        if(expression === '') return;
        var last = expression.slice(-1);
        if(['+','-'].includes(last)) expression = expression.slice(0,-1);
        expression += k;
      } else {
        if(k === '.' && expression.split(/[+\-]/).pop().includes('.')) return;
        expression += k;
      }
      updateAmountDisplay();
    });
  });

  document.querySelectorAll('.preset-chip').forEach(function(chip){
    chip.addEventListener('click', function(){
      var v = chip.dataset.preset;
      if(expression === '' || ['+','-'].includes(expression.slice(-1))){
        expression += v;
      } else {
        expression += '+' + v;
      }
      updateAmountDisplay();
    });
  });

  catGrid.addEventListener('click', function(e){
    var chip = e.target.closest('.cat-chip');
    if(!chip || chip.dataset.catType !== currentType) return;
    catGrid.querySelectorAll('.cat-chip').forEach(function(c){ c.classList.remove('active'); });
    chip.classList.add('active');
    categoryId   = chip.dataset.catId;
    categoryName = chip.dataset.catName;
    document.getElementById('f-category-id').value = categoryId;
    updateSaveBtn();
  });

  dispDate.addEventListener('change', function(){
    document.getElementById('f-date').value = dispDate.value;
  });

  dispNote.addEventListener('input', function(){
    document.getElementById('f-note').value = dispNote.value;
  });

  saveBtn.addEventListener('click', function(){
    if(saveBtn.disabled) return;
    var val = evaluate(expression);
    if(val <= 0 || !categoryId) return;
    document.getElementById('f-amount').value = val.toFixed(2);
    document.getElementById('f-note').value   = dispNote.value;
    document.getElementById('f-date').value   = dispDate.value;
    document.getElementById('mob-form').submit();
  });

  filterCats();
  updateAmountDisplay();

  function showToast(msg){
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); }, 2200);
  }

  var saved = new URLSearchParams(location.search).get('saved');
  if(saved) showToast('บันทึกสำเร็จ');
})();
</script>

<?php include 'partials/footer.php'; ?>
