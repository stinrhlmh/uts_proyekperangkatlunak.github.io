/* =============================================
   D'one Fried Chicken - Shared App Logic
   Data storage: MySQL via PHP API
   ============================================= */

// ── API Helper ───────────────────────────────
const API = {
  async get(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (res.status === 401) { window.location.replace('login.html'); return null; }
    return res.json();
  },
  async post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    if (res.status === 401) { window.location.replace('login.html'); return null; }
    return res.json();
  },
  async delete(url) {
    const res = await fetch(url, { method: 'DELETE', credentials: 'same-origin' });
    if (res.status === 401) { window.location.replace('login.html'); return null; }
    return res.json();
  },
};

// ── Formatters ───────────────────────────────
const Fmt = {
  currency(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
  },
  date(str) {
    if (!str) return '-';
    const d = new Date(str + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
  },
  dateShort(str) {
    if (!str) return '-';
    const d = new Date(str + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  },
  today() {
    return new Date().toISOString().split('T')[0];
  },
  todayLabel() {
    return new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  },
  number(n) {
    return Number(n || 0).toLocaleString('id-ID');
  },
};

// ── DOM Helpers ──────────────────────────────
function el(id) { return document.getElementById(id); }
function qs(sel) { return document.querySelector(sel); }

function showAlert(containerId, message, type = 'success', duration = 3500) {
  const c = el(containerId);
  if (!c) return;
  c.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
  if (duration > 0) setTimeout(() => { c.innerHTML = ''; }, duration);
}

function confirmDelete(msg = 'Hapus data ini?') {
  return window.confirm(msg);
}

function setLoading(tbodyId, cols, msg = 'Memuat data…') {
  const el_ = el(tbodyId);
  if (el_) el_.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted" style="padding:24px;">⏳ ${msg}</td></tr>`;
}

// ── Mark active nav link ─────────────────────
function markActiveNav() {
  const page = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.sidebar-nav a').forEach(a => {
    if (a.getAttribute('href') === page) a.classList.add('active');
  });
}

// ── Auth ─────────────────────────────────────
async function requireAuth() {
  const data = await API.get('api/auth.php?action=check');
  if (!data || !data.success) {
    window.location.replace('login.html');
    return null;
  }
  return data.user;
}

async function logout() {
  if (!confirm('Yakin ingin keluar dari sistem?')) return;
  await API.post('api/auth.php?action=logout', {});
  window.location.replace('login.html');
}

function renderUserInfo(user) {
  if (!user) return;
  const footer = document.querySelector('.sidebar-footer');
  if (footer) {
    footer.innerHTML = `
      <div style="margin-bottom:6px;">
        <div style="font-size:12px;font-weight:700;color:#fff;opacity:.9;">👤 ${user.nama}</div>
        <div style="font-size:10px;opacity:.6;">${user.role}</div>
      </div>
      <button onclick="logout()" style="
        width:100%;padding:7px 0;background:rgba(255,255,255,.15);
        border:1px solid rgba(255,255,255,.25);border-radius:6px;
        color:#fff;font-size:12px;font-weight:600;cursor:pointer;
        font-family:inherit;transition:background .18s;
      " onmouseover="this.style.background='rgba(255,255,255,.28)'"
         onmouseout="this.style.background='rgba(255,255,255,.15)'">
        🚪 Keluar
      </button>`;
  }
  const uiEl = el('user-info');
  if (uiEl) uiEl.textContent = `👤 ${user.nama} (${user.role})`;
}

// ── Init ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  const user = await requireAuth();
  if (!user) return;
  markActiveNav();
  renderUserInfo(user);
  const todayEl = el('today-label');
  if (todayEl) todayEl.textContent = Fmt.todayLabel();
});
