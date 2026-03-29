// ============================================================
//  assets/js/main.js — Global JS
// ============================================================

// ── Sidebar toggle (burger button) ───────────────────────────
const burger      = document.getElementById('burgerBtn');
const sidebar     = document.getElementById('sidebar');
const pageWrapper = document.getElementById('pageWrapper');

if (burger && sidebar) {
  burger.addEventListener('click', () => {
    sidebar.classList.toggle('open');        // mobile slide-in
    pageWrapper?.classList.toggle('shifted'); // desktop push
  });

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 &&
        !sidebar.contains(e.target) &&
        !burger.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });
}

// ── Auto-dismiss alerts after 4s ─────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  }, 4000);
});

// ── Confirm dangerous actions ─────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', (e) => {
    const msg = el.dataset.confirm || 'Are you sure?';
    if (!confirm(msg)) e.preventDefault();
  });
});

// ── Set today's date on any input[type=date] with data-today ──
document.querySelectorAll('input[type="date"][data-today]').forEach(el => {
  if (!el.value) {
    el.value = new Date().toISOString().split('T')[0];
  }
});