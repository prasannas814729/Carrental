// ============================================================
//  RentX — Main JavaScript
// ============================================================

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}
// Close on backdrop click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// ── Auth tabs ─────────────────────────────────────────────────
function switchTab(tab) {
  document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
  document.querySelector(`.auth-tab[data-tab="${tab}"]`).classList.add('active');
  document.getElementById(`form-${tab}`)?.classList.add('active');
}

// ── Active nav link ───────────────────────────────────────────
(function () {
  const path = window.location.pathname;
  document.querySelectorAll('.nav-link, .sidebar-link').forEach(l => {
    if (l.getAttribute('href') === path) l.classList.add('active');
  });
})();

// ── Date range: auto-calculate total days ────────────────────
const startDate = document.getElementById('start_date');
const endDate   = document.getElementById('end_date');
const totalDays = document.getElementById('total_days');
const totalPrice= document.getElementById('total_price');
const priceDay  = parseFloat(document.getElementById('price_per_day')?.value || 0);

if (startDate && endDate) {
  function calcDays() {
    const s = new Date(startDate.value);
    const e = new Date(endDate.value);
    if (e > s) {
      const days = Math.ceil((e - s) / (1000 * 60 * 60 * 24));
      if (totalDays)  totalDays.value  = days;
      if (totalPrice) totalPrice.value = (days * priceDay).toFixed(2);
    }
  }
  startDate.addEventListener('change', calcDays);
  endDate.addEventListener('change',   calcDays);

  // Set min date to today
  const today = new Date().toISOString().split('T')[0];
  startDate.min = today;
  endDate.min   = today;
}

// ── Confirm dialogs ───────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

// ── Auto-dismiss alerts ───────────────────────────────────────
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    a.style.transition = 'opacity .6s';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 600);
  });
}, 4000);

// ── Search/filter form auto-submit ───────────────────────────
document.querySelectorAll('.auto-submit select, .auto-submit input').forEach(el => {
  el.addEventListener('change', () => el.closest('form').submit());
});
