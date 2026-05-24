</div>

<?php $current_file_footer = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<nav class="mobile-bottom-nav">
  <a href="index.php" class="mbn-item <?php echo $current_file_footer === 'index.php' ? 'active' : ''; ?>">
    <i class="bi bi-grid-1x2-fill"></i><span>หน้าหลัก</span>
  </a>
  <a href="entries.php" class="mbn-item <?php echo $current_file_footer === 'entries.php' ? 'active' : ''; ?>">
    <i class="bi bi-journal-text"></i><span>รายการ</span>
  </a>
  <div class="mbn-center">
    <a href="add_mobile.php" class="mbn-fab <?php echo $current_file_footer === 'add_mobile.php' ? 'mbn-fab-active' : ''; ?>">
      <i class="bi bi-plus-lg"></i>
    </a>
  </div>
  <a href="report.php" class="mbn-item <?php echo $current_file_footer === 'report.php' ? 'active' : ''; ?>">
    <i class="bi bi-bar-chart-fill"></i><span>รายงาน</span>
  </a>
  <a href="categories.php" class="mbn-item <?php echo $current_file_footer === 'categories.php' ? 'active' : ''; ?>">
    <i class="bi bi-tags-fill"></i><span>หมวดหมู่</span>
  </a>
</nav>

<!-- PWA Install Banner -->
<div id="pwa-banner" style="display:none;position:fixed;bottom:70px;left:12px;right:12px;z-index:1050;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(79,70,229,.22);padding:14px 16px;display:none;align-items:center;gap:12px;">
  <img src="finance-icon-dark.svg" width="44" height="44" style="border-radius:10px;flex-shrink:0" alt="">
  <div style="flex:1;min-width:0">
    <div style="font-weight:700;font-size:.95rem;color:#1e1b4b">Finance App</div>
    <div style="font-size:.8rem;color:#6b7280">ติดตั้งแอพบนหน้าจอหลัก</div>
  </div>
  <button id="pwa-install-btn" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:10px;padding:8px 14px;font-size:.85rem;font-weight:600;cursor:pointer;white-space:nowrap">ติดตั้ง</button>
  <button id="pwa-dismiss-btn" style="background:none;border:none;color:#9ca3af;font-size:1.1rem;cursor:pointer;padding:4px;line-height:1">✕</button>
</div>

<!-- iOS Install Hint (shown only on iOS Safari when not installed) -->
<div id="ios-hint" style="display:none;position:fixed;bottom:70px;left:12px;right:12px;z-index:1050;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(79,70,229,.22);padding:14px 16px;">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
    <img src="finance-icon-dark.svg" width="40" height="40" style="border-radius:9px;flex-shrink:0" alt="">
    <div style="flex:1">
      <div style="font-weight:700;font-size:.95rem;color:#1e1b4b">ติดตั้งบน iPhone/iPad</div>
    </div>
    <button id="ios-hint-close" style="background:none;border:none;color:#9ca3af;font-size:1.1rem;cursor:pointer;padding:4px;line-height:1">✕</button>
  </div>
  <div style="font-size:.82rem;color:#4b5563;line-height:1.6">
    แตะ <strong>แชร์</strong> <span style="font-size:1rem">⬆️</span> ที่แถบด้านล่าง แล้วเลือก <strong>"เพิ่มไปยังหน้าจอหลัก"</strong>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
