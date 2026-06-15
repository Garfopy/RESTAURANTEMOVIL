// app.js — Global utilities for CarniHub

// Toast notifications
function showToast(message, type = 'success', duration = 3500) {
  const existing = document.querySelector('.toast-global');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = 'toast-global';
  const bg = type === 'success' ? '#065F46' : type === 'error' ? '#991B1B' : '#1E40AF';
  toast.style.cssText = `
    position:fixed; top:16px; right:16px; left:16px; max-width:420px; margin:0 auto;
    background:${bg}; color:#fff; padding:12px 16px; border-radius:10px;
    font-size:.875rem; font-weight:600; z-index:9999;
    box-shadow:0 4px 20px rgba(0,0,0,.25); animation:slideDown .25s ease;
  `;
  toast.textContent = message;

  if (!document.getElementById('toast-anim')) {
    const style = document.createElement('style');
    style.id = 'toast-anim';
    style.textContent = '@keyframes slideDown{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}';
    document.head.appendChild(style);
  }

  document.body.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, duration);
}

// Sidebar toggle (mobile)
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  if (!sidebar) return;
  sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('active');
}

// Confirm delete with custom message
function confirmDelete(message, callback) {
  if (confirm(message || '¿Eliminar este registro?')) callback();
}

// Format number as MXN currency string
function formatMXN(amount) {
  return '$' + parseFloat(amount || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Generic AJAX JSON POST
function postJSON(url, data) {
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  }).then(r => r.json());
}

// Auto-close flash messages
document.addEventListener('DOMContentLoaded', () => {
  const flashes = document.querySelectorAll('.toast[data-auto-close]');
  flashes.forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .4s'; setTimeout(() => el.remove(), 400); }, 3500);
  });

  // Sidebar overlay click closes it
  const overlay = document.querySelector('.sidebar-overlay');
  if (overlay) overlay.addEventListener('click', toggleSidebar);
});
