document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-confirm], [data-confirm]').forEach(function (el) {
    const message = el.getAttribute('data-confirm');
    if (!message) return;
    el.addEventListener('submit', function (e) { if (!confirm(message)) e.preventDefault(); });
  });
});
