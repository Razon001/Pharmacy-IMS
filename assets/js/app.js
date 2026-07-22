// Minimal enhancement JS. The system works without JavaScript.
document.addEventListener('click', function (e) {
  const el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.getAttribute('data-confirm'))) {
    e.preventDefault();
  }
});

// POS: live filter the medicine tiles
const posSearch = document.getElementById('posSearch');
if (posSearch) {
  posSearch.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.med-tile').forEach(function (t) {
      const txt = t.getAttribute('data-search') || '';
      t.style.display = txt.includes(q) ? '' : 'none';
    });
  });
  posSearch.focus();
}

// Auto-print pages that request it (invoice view)
if (document.body.dataset.autoprint === '1') {
  window.addEventListener('load', () => setTimeout(() => window.print(), 400));
}
