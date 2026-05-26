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
  <a href="notes.php?add=1" class="mbn-item <?php echo $current_file_footer === 'notes.php' ? 'active' : ''; ?>">
    <i class="bi bi-journal-plus"></i><span>โน็ต</span>
  </a>
</nav>

<!-- PWA Install Banner -->
<div id="pwa-banner" style="display:none;position:fixed;top:68px;left:12px;right:12px;z-index:1040;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(79,70,229,.22);padding:14px 16px;align-items:center;gap:12px;">
  <img src="finance-icon-dark.svg" width="44" height="44" style="border-radius:10px;flex-shrink:0" alt="">
  <div style="flex:1;min-width:0">
    <div style="font-weight:700;font-size:.95rem;color:#1e1b4b">Finance App</div>
    <div style="font-size:.8rem;color:#6b7280">ติดตั้งแอพบนหน้าจอหลัก</div>
  </div>
  <button id="pwa-install-btn" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:10px;padding:8px 14px;font-size:.85rem;font-weight:600;cursor:pointer;white-space:nowrap">ติดตั้ง</button>
  <button id="pwa-dismiss-btn" style="background:none;border:none;color:#9ca3af;font-size:1.1rem;cursor:pointer;padding:4px;line-height:1">✕</button>
</div>

<!-- iOS Install Hint (shown only when user taps install button) -->
<div id="ios-hint" style="display:none;position:fixed;top:68px;left:12px;right:12px;z-index:1040;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(79,70,229,.22);padding:14px 16px;">
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

<!-- iOS install trigger button (visible in bottom nav area on iOS only) -->
<button id="ios-install-trigger" style="display:none;position:fixed;bottom:68px;right:14px;z-index:995;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:50px;padding:6px 12px;font-size:.75rem;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(99,102,241,.35);align-items:center;gap:5px">
  <span>⬇</span> ติดตั้ง
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="assets/js/app.js" defer></script>
</body>
</html>
