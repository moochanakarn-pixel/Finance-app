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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
