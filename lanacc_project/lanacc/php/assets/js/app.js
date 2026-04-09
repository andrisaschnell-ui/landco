// LANACC — app.js

// ── Auto-dismiss flash alerts after 5s ──────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const alerts = document.querySelectorAll('.alert.alert-dismissible');
  alerts.forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert?.close();
    }, 5000);
  });

  // ── Confirm delete buttons ─────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  // ── Number formatting on blur ──────────────────────────
  document.querySelectorAll('input[type=number]').forEach(inp => {
    inp.addEventListener('focus', () => inp.select());
  });
});
