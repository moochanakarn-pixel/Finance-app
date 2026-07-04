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
$returnUrl     = 'add_mobile.php';
$typeColors    = ['income' => '#16a34a', 'expense' => '#dc2626', 'saving' => '#7c3aed'];
$typeBg        = ['income' => '#dcfce7', 'expense' => '#fee2e2', 'saving' => '#ede9fe'];
$presets       = [50, 100, 150, 200, 300, 500, 1000];
$page_css      = 'assets/css/add_mobile.css';

include 'partials/header.php';
?>

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
        <?php
        $typeGroupLabels = ['expense' => 'รายจ่าย', 'income' => 'รายรับ', 'saving' => 'เงินออม'];
        foreach (['expense','income','saving'] as $t):
          if (!empty($categories[$t])): ?>
            <div class="cat-group-header" data-cat-type="<?php echo $t; ?>" style="grid-column:1/-1;padding:4px 2px 2px;font-size:11px;font-weight:800;color:#94a3b8;letter-spacing:.06em;text-transform:uppercase"><?php echo $typeGroupLabels[$t]; ?></div>
          <?php endif;
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

  // Cache DOM queries — the chip/header list never changes after load
  var allChips   = Array.from(catGrid.querySelectorAll('.cat-chip'));
  var allHeaders = Array.from(catGrid.querySelectorAll('.cat-group-header'));

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
    allChips.forEach(function(c){
      c.style.display = c.dataset.catType === currentType ? '' : 'none';
      c.classList.remove('active');
    });
    allHeaders.forEach(function(h){
      h.style.display = h.dataset.catType === currentType ? '' : 'none';
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

    // Lock immediately — form.submit() skips the submit event so app.js won't do this
    saveBtn.disabled = true;
    saveBtn.textContent = 'กำลังบันทึก...';
    saveBtn.style.opacity = '0.7';
    if (window._startNavBar) window._startNavBar();

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
    setTimeout(function(){ t.classList.remove('show'); }, 4000);
  }

  var qs = new URLSearchParams(location.search);
  if(qs.get('saved'))      showToast('✓ บันทึกสำเร็จแล้ว');
  if(qs.get('save_error')) showToast('⚠ บันทึกไม่สำเร็จ — หมวดหมู่อาจถูกลบไปแล้ว');
})();
</script>

<?php include 'partials/footer.php'; ?>