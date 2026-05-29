<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId     = (int)$_SESSION['user_id'];
$page_title = 'บันทึกรายการ';

$categories = ['income' => [], 'saving' => [], 'expense' => []];
$rs = mysqli_query($conn, "
    SELECT id, name, type FROM categories
    WHERE is_active = 1 AND user_id = {$userId}
    ORDER BY FIELD(type,'income','saving','expense'), sort_order ASC, id ASC
");
while ($r = mysqli_fetch_assoc($rs)) {
    if (isset($categories[$r['type']])) $categories[$r['type']][] = $r;
}

$today         = date('Y-m-d');
$currentYearBE = date('Y') + 543;
$returnUrl     = 'add_mobile.php?saved=1';
$typeColors    = ['income' => '#16a34a', 'expense' => '#dc2626', 'saving' => '#7c3aed'];
$typeBg        = ['income' => '#dcfce7', 'expense' => '#fee2e2', 'saving' => '#ede9fe'];
$presets       = [50, 100, 150, 200, 300, 500, 1000];

include 'partials/header.php';
?>
<style>
/* Hide global navbar & bottom nav on this page */
.app-navbar{display:none!important}
.mobile-bottom-nav{display:none!important}
body{padding-bottom:0!important}
.app-shell{padding-bottom:0!important;padding-top:0!important;max-width:100%}

:root{
  --income:#16a34a; --income-grad:linear-gradient(135deg,#16a34a,#15803d);
  --expense:#dc2626; --expense-grad:linear-gradient(135deg,#ef4444,#dc2626);
  --saving:#7c3aed;  --saving-grad:linear-gradient(135deg,#8b5cf6,#7c3aed);
  --indigo:linear-gradient(135deg,#312e81,#4f46e5);
  --r:20px;
}

/* ── TOP BAR ── */
.mob-topbar{
  display:flex;align-items:center;justify-content:space-between;gap:.5rem;
  padding:.9rem 1rem;
  background:var(--indigo);
  color:#fff;
  position:sticky;top:0;z-index:50;
}
.mob-topbar-title{font-weight:800;font-size:1rem;flex:1;text-align:center}
.mob-icon-btn{
  width:38px;height:38px;border-radius:12px;
  background:rgba(255,255,255,.15);border:none;
  display:flex;align-items:center;justify-content:center;
  color:#fff;text-decoration:none;font-size:1.1rem;cursor:pointer;
  flex-shrink:0;
}
.mob-icon-btn:hover{background:rgba(255,255,255,.25);color:#fff}

/* ── SCROLL BODY ── */
.mob-body{
  max-width:480px;margin:0 auto;
  padding:.85rem .85rem calc(88px + env(safe-area-inset-bottom));
}

/* ── TYPE TABS ── */
.type-tabs{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:1rem}
.type-tab{
  border:2px solid transparent;border-radius:18px;
  padding:.72rem .4rem;text-align:center;
  font-size:.88rem;font-weight:800;cursor:pointer;
  transition:all .15s;background:#fff;color:#64748b;
  box-shadow:0 2px 10px rgba(99,102,241,.09);
  -webkit-tap-highlight-color:transparent;
}
.type-tab.active{color:#fff;box-shadow:0 5px 18px rgba(0,0,0,.22)}
.type-tab[data-type=income].active{background:var(--income-grad)}
.type-tab[data-type=expense].active{background:var(--expense-grad)}
.type-tab[data-type=saving].active{background:var(--saving-grad)}
.type-tab-icon{font-size:1.1rem;margin-bottom:3px}

/* ── AMOUNT DISPLAY ── */
.amount-display{
  text-align:center;padding:1.3rem 1rem .9rem;
  background:#fff;border-radius:var(--r);
  border:1.5px solid #e0e7ff;margin-bottom:.75rem;
  box-shadow:0 4px 20px rgba(99,102,241,.09);
}
.amount-expression{font-size:.83rem;color:#94a3b8;min-height:1.3em;word-break:break-all;margin-bottom:.2rem}
.amount-value{font-size:2.8rem;font-weight:800;color:#0f172a;line-height:1.05;letter-spacing:-1px}
.amount-value.is-income{color:var(--income)}
.amount-value.is-expense{color:var(--expense)}
.amount-value.is-saving{color:var(--saving)}
.amount-unit{font-size:.95rem;font-weight:600;color:#94a3b8;margin-top:2px}

/* ── PRESET CHIPS ── */
.preset-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:.75rem}
.preset-chip{
  border:1px solid #e0e7ff;border-radius:99px;
  padding:.38rem .82rem;font-size:.82rem;font-weight:700;
  color:#4338ca;cursor:pointer;background:#eef2ff;
  -webkit-tap-highlight-color:transparent;transition:background .1s,transform .08s;
}
.preset-chip:active{background:#e0e7ff;transform:scale(.95)}

/* ── NUMPAD ── */
.numpad{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:1rem}
.nk{
  background:#fff;border:1.5px solid #e0e7ff;border-radius:16px;
  padding:.78rem .4rem;font-size:1.3rem;font-weight:700;text-align:center;
  cursor:pointer;color:#1e1b4b;
  transition:background .08s,transform .08s;
  -webkit-tap-highlight-color:transparent;
  box-shadow:0 2px 6px rgba(99,102,241,.07);
  user-select:none;
}
.nk:active{background:#eef2ff;transform:scale(.93)}
.nk.op{color:#6366f1;font-size:1.15rem;background:#f5f3ff;border-color:#ddd6fe}
.nk.del{color:#ef4444;background:#fff5f5;border-color:#fecaca}
.nk.zero{grid-column:span 2}
.nk.confirm{
  background:linear-gradient(135deg,#6366f1,#4f46e5);
  color:#fff;border:none;font-size:1rem;
  box-shadow:0 4px 14px rgba(99,102,241,.38);
}
.nk.confirm:active{transform:scale(.93);opacity:.85}

/* ── FIELD CARD ── */
.field-card{background:#fff;border-radius:var(--r);border:1.5px solid #e0e7ff;margin-bottom:.75rem;overflow:hidden;box-shadow:0 2px 12px rgba(99,102,241,.07)}
.field-row{display:flex;align-items:flex-start;padding:.88rem 1rem;gap:.75rem;border-bottom:1px solid #f0f4ff}
.field-row:last-child{border-bottom:none}
.field-icon{width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;margin-top:1px}
.field-label{font-size:.75rem;color:#94a3b8;font-weight:700;margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
.field-input{flex:1;min-width:0}
.field-input input,.field-input select,.field-input textarea{
  width:100%;border:none;outline:none;background:transparent;
  font-size:.98rem;color:#0f172a;font-family:inherit;font-weight:600;padding:0;
}
.field-input textarea{resize:none;line-height:1.5}

/* ── CATEGORY SECTION ── */
.cat-section{background:#fff;border-radius:var(--r);border:1.5px solid #e0e7ff;overflow:hidden;margin-bottom:.75rem;box-shadow:0 2px 12px rgba(99,102,241,.07)}
.cat-header{padding:.75rem 1rem .4rem;border-bottom:1px solid #f0f4ff}
.cat-group-label{font-size:.72rem;font-weight:800;color:#94a3b8;letter-spacing:.07em;text-transform:uppercase}
.cat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;padding:.75rem}
.cat-chip{
  border:1.5px solid #e0e7ff;border-radius:14px;
  padding:.62rem .75rem;font-size:.87rem;font-weight:700;
  cursor:pointer;color:#4338ca;background:#f5f8ff;
  transition:all .12s;-webkit-tap-highlight-color:transparent;
  word-break:break-word;line-height:1.3;
}
.cat-chip.active{border-color:var(--cat-color);background:var(--cat-bg);color:var(--cat-color);box-shadow:0 2px 8px rgba(0,0,0,.08)}
.cat-chip:active{opacity:.7;transform:scale(.97)}

/* ── SAVE BAR ── */
.save-bar{
  position:fixed;bottom:0;left:0;right:0;
  padding:.75rem 1rem calc(.75rem + env(safe-area-inset-bottom));
  background:rgba(255,255,255,.96);backdrop-filter:blur(14px);
  border-top:1px solid #e0e7ff;z-index:50;
}
.save-btn{
  width:100%;padding:1.05rem 1rem;border-radius:18px;border:none;
  font-size:1.05rem;font-weight:800;cursor:pointer;color:#fff;
  font-family:inherit;transition:opacity .15s,transform .1s;
  box-shadow:0 5px 20px rgba(0,0,0,.2);
}
.save-btn:active{opacity:.85;transform:scale(.98)}
.save-btn:disabled{opacity:.32;cursor:not-allowed;box-shadow:none;background:#94a3b8!important}
.save-btn.is-income{background:var(--income-grad)}
.save-btn.is-expense{background:var(--expense-grad)}
.save-btn.is-saving{background:var(--saving-grad)}

/* ── TOAST ── */
.mob-toast{
  position:fixed;top:1rem;left:50%;
  transform:translateX(-50%) translateY(-80px);
  background:linear-gradient(135deg,#312e81,#4f46e5);color:#fff;
  padding:.65rem 1.5rem;border-radius:99px;
  font-size:.92rem;font-weight:700;z-index:999;
  transition:transform .28s cubic-bezier(.34,1.56,.64,1);
  pointer-events:none;white-space:nowrap;
  box-shadow:0 6px 22px rgba(49,46,129,.35);
}
.mob-toast.show{transform:translateX(-50%) translateY(0)}
</style>

<!-- Top bar -->
<div class="mob-topbar">
  <a href="javascript:history.back()" class="mob-icon-btn">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div class="mob-topbar-title">บันทึกรายการ</div>
  <a href="index.php" class="mob-icon-btn">
    <i class="bi bi-house-fill"></i>
  </a>
</div>

<div class="mob-body">
  <form action="save_entry.php" method="post" id="mob-form">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="year_be" value="<?php echo $currentYearBE; ?>">
    <input type="hidden" name="return_url" value="<?php echo h($returnUrl); ?>">
    <input type="hidden" name="category_id" id="f-category-id" value="">
    <input type="hidden" name="entry_date"  id="f-date"        value="<?php echo $today; ?>">
    <input type="hidden" name="amount"      id="f-amount"      value="">
    <input type="hidden" name="note"        id="f-note"        value="">

    <!-- Type tabs -->
    <div class="type-tabs">
      <?php foreach (['expense' => ['label'=>'รายจ่าย','icon'=>'💸'], 'income' => ['label'=>'รายรับ','icon'=>'💰'], 'saving' => ['label'=>'เงินออม','icon'=>'🏦']] as $t => $info): ?>
        <div class="type-tab <?php echo $t === 'expense' ? 'active' : ''; ?>" data-type="<?php echo $t; ?>">
          <div class="type-tab-icon"><?php echo $info['icon']; ?></div>
          <?php echo $info['label']; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Amount display -->
    <div class="amount-display">
      <div class="amount-expression" id="amt-expr">&nbsp;</div>
      <div class="amount-value is-expense" id="amt-display">0</div>
      <div class="amount-unit">บาท</div>
    </div>

    <!-- Preset chips -->
    <div class="preset-row">
      <?php foreach ($presets as $p): ?>
        <div class="preset-chip" data-preset="<?php echo $p; ?>">+<?php echo number_format($p); ?></div>
      <?php endforeach; ?>
    </div>

    <!-- Numpad -->
    <div class="numpad">
      <?php
      $keys = [
        ['7',''], ['8',''], ['9',''], ['+','op'],
        ['4',''], ['5',''], ['6',''], ['-','op'],
        ['1',''], ['2',''], ['3',''], ['.','op'],
        ['00','zero'], ['0',''], ['⌫','del'], ['=','confirm'],
      ];
      foreach ($keys as [$k, $cls]):
      ?>
        <div class="nk <?php echo $cls; ?>" data-key="<?php echo h($k); ?>"><?php echo h($k); ?></div>
      <?php endforeach; ?>
    </div>

    <!-- Date & Note -->
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
          <textarea id="disp-note" rows="2" placeholder="เช่น ข้าวกลางวัน, Grab, ค่าไฟ..."></textarea>
        </div>
      </div>
    </div>

    <!-- Categories -->
    <div class="cat-section">
      <div class="cat-header">
        <div class="cat-group-label" id="cat-group-label">เลือกหมวดรายจ่าย</div>
      </div>
      <div class="cat-grid" id="cat-grid">
        <?php foreach (['expense','income','saving'] as $t):
          foreach ($categories[$t] as $cat): ?>
            <div class="cat-chip"
                 data-cat-id="<?php echo $cat['id']; ?>"
                 data-cat-type="<?php echo $t; ?>"
                 data-cat-name="<?php echo h($cat['name']); ?>"
                 style="--cat-color:<?php echo $typeColors[$t]; ?>;--cat-bg:<?php echo $typeBg[$t]; ?>"
            ><?php echo h($cat['name']); ?></div>
        <?php endforeach; endforeach; ?>
      </div>
    </div>

  </form>
</div>

<!-- Save bar -->
<div class="save-bar">
  <button class="save-btn is-expense" id="save-btn" disabled>เลือกหมวดหมู่ก่อน</button>
</div>

<div class="mob-toast" id="mob-toast"></div>

<script>
(function(){
  var currentType = 'expense';
  var expression  = '';
  var categoryId  = '';

  var typeLabels  = {expense:'รายจ่าย', income:'รายรับ', saving:'เงินออม'};

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
    return n%1===0 ? n.toLocaleString('th-TH') : parseFloat(n.toFixed(2)).toLocaleString('th-TH',{minimumFractionDigits:2,maximumFractionDigits:2});
  }

  function updateAmountDisplay(){
    var val = evaluate(expression);
    amtDisplay.textContent = formatNum(val);
    amtExpr.textContent    = expression.replace(/^\+/,'') || ' ';
    amtDisplay.className   = 'amount-value is-' + currentType;
    document.getElementById('f-amount').value = val > 0 ? val.toFixed(2) : '';
    updateSaveBtn();
  }

  function updateSaveBtn(){
    var val    = evaluate(expression);
    var hasAmt = val > 0;
    var hasCat = categoryId !== '';
    saveBtn.disabled  = !(hasAmt && hasCat);
    saveBtn.className = 'save-btn is-' + currentType;
    if(!hasCat)      saveBtn.textContent = 'เลือกหมวดหมู่ก่อน';
    else if(!hasAmt) saveBtn.textContent = 'กรอกจำนวนเงินก่อน';
    else             saveBtn.textContent = 'บันทึก' + typeLabels[currentType] + ' ฿' + formatNum(val);
  }

  function filterCats(){
    var chips = catGrid.querySelectorAll('.cat-chip');
    chips.forEach(function(c){
      c.style.display = c.dataset.catType === currentType ? '' : 'none';
      c.classList.remove('active');
    });
    catGroupLbl.textContent = 'เลือกหมวด' + typeLabels[currentType];
    categoryId = '';
    document.getElementById('f-category-id').value = '';
    updateSaveBtn();
  }

  document.querySelectorAll('.type-tab').forEach(function(tab){
    tab.addEventListener('click', function(){
      document.querySelectorAll('.type-tab').forEach(function(t){ t.classList.remove('active'); });
      tab.classList.add('active');
      currentType = tab.dataset.type;
      filterCats();
      restoreLastCat(currentType);
      updateAmountDisplay();
    });
  });

  document.querySelectorAll('.nk').forEach(function(key){
    key.addEventListener('click', function(){
      var k = key.dataset.key;
      if(k === '='){
        var val = evaluate(expression);
        expression = val > 0 ? String(parseFloat(val.toFixed(2))) : '';
      } else if(k === '⌫'){
        expression = expression.slice(0,-1);
      } else if(k === '+' || k === '-'){
        if(expression === '') return;
        var last = expression.slice(-1);
        if(last === '+' || last === '-') expression = expression.slice(0,-1);
        expression += k;
      } else if(k === '00'){
        if(expression !== '' && expression !== '0') expression += '00';
      } else {
        if(k === '.' && expression.split(/[+\-]/).pop().includes('.')) return;
        if(k === '0' && expression === '0') return;
        expression += k;
      }
      updateAmountDisplay();
    });
  });

  document.querySelectorAll('.preset-chip').forEach(function(chip){
    chip.addEventListener('click', function(){
      var v = chip.dataset.preset;
      if(expression === '' || expression.slice(-1) === '+' || expression.slice(-1) === '-'){
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
    categoryId = chip.dataset.catId;
    document.getElementById('f-category-id').value = categoryId;
    try { localStorage.setItem('mob_last_cat_' + currentType, categoryId); } catch(e){}
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

  function restoreLastCat(type) {
    try {
      var savedId = localStorage.getItem('mob_last_cat_' + type);
      if (!savedId) return;
      var chip = catGrid.querySelector('.cat-chip[data-cat-id="' + savedId + '"][data-cat-type="' + type + '"]');
      if (chip) {
        chip.classList.add('active');
        categoryId = savedId;
        document.getElementById('f-category-id').value = categoryId;
      }
    } catch(e) {}
  }

  filterCats();
  restoreLastCat(currentType);
  updateAmountDisplay();

  function showToast(msg){
    var t = document.getElementById('mob-toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); }, 2500);
  }

  if(new URLSearchParams(location.search).get('saved')) showToast('✓ บันทึกสำเร็จแล้ว');
})();
</script>

<?php include 'partials/footer.php'; ?>
