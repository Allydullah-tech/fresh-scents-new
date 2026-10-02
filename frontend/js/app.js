/* =========================================================
   FRESH SCENTS — Admin Panel Shell (sidebar, topbar, auth guard)
========================================================= */

const ROLE_LABELS = { super_admin: 'Msimamizi Mkuu', admin: 'Msimamizi', worker: 'Mfanyakazi' };

const NAV_ITEMS = [
  { href: 'dashboard.html', icon: 'bi-speedometer2', label: 'Dashibodi', roles: null },
  { href: 'products.html', icon: 'bi-droplet-half', label: 'Bidhaa (Manukato)', roles: ['super_admin', 'admin'] },
  { href: 'pos.html', icon: 'bi-bag-check', label: 'Rekodi Mauzo', roles: null },
  { href: 'damages.html', icon: 'bi-exclamation-diamond', label: 'Uharibifu / Muda Kuisha', roles: null },
  { href: 'stock.html', icon: 'bi-box-arrow-in-down', label: 'Stock', roles: null },
  { section: 'Fedha', roles: null },
  { href: 'reports.html', icon: 'bi-file-earmark-bar-graph', label: 'Ripoti', roles: ['super_admin', 'admin'] },
  { href: 'finance.html', icon: 'bi-graph-up-arrow', label: 'Matumizi', roles: null },
  { href: 'payroll.html', icon: 'bi-receipt', label: 'Malipo ya Wafanyakazi', roles: ['super_admin', 'admin'] },
  { section: 'Wafanyakazi', roles: ['super_admin', 'admin'] },
  { href: 'employees.html', icon: 'bi-people', label: 'Wafanyakazi', roles: ['super_admin', 'admin'] },
  { section: 'Usimamizi', roles: ['super_admin'] },
  { href: 'admins.html', icon: 'bi-shield-lock', label: 'Watumiaji wa Mfumo', roles: ['super_admin'] },
  { href: 'activity-log.html', icon: 'bi-clock-history', label: 'Shughuli za Mfumo', roles: ['super_admin'] },
  { href: 'settings.html', icon: 'bi-gear', label: 'Mipangilio ya Duka', roles: ['super_admin'] },
  { section: 'Akaunti', roles: null },
  { href: 'profile.html', icon: 'bi-person-circle', label: 'Wasifu Wangu', roles: null },
  { href: 'logout.html', icon: 'bi-box-arrow-right', label: 'Toka', roles: null, isLogout: true },
];

function buildSidebarNav(currentPage, role) {
  let html = '';
  NAV_ITEMS.forEach(item => {
    if (item.roles && !item.roles.includes(role)) return;
    if (item.section) {
      html += `<div class="section-label">${item.section}</div>`;
      return;
    }
    const active = item.href === currentPage ? 'active' : '';
    const onclick = item.isLogout ? ' onclick="return doLogout(event)"' : '';
    html += `<a href="${item.href}" class="${active}"${onclick}><i class="bi ${item.icon}"></i> ${item.label}</a>`;
  });
  return html;
}

async function doLogout(e) {
  if (e) e.preventDefault();
  await apiPost('auth.php', {}, 'logout');
  window.location.href = 'login.html';
  return false;
}

/**
 * Anzisha "shell" ya Admin Panel (sidebar + topbar), angalia kama mtumiaji
 * ameingia (la sivyo mpeleke login.html), kisha rudisha taarifa za mtumiaji.
 */
async function initAdminShell(opts) {
  const { activePage, pageTitle } = opts;

  const me = await apiGet('auth.php', { action: 'me' });
  if (!me.logged_in) {
    window.location.href = 'login.html';
    return null;
  }

  const admin = me.admin;
  const shopName = me.shop_name || 'FRESH SCENTS';
  document.title = pageTitle + ' - Admin | ' + shopName;

  const wrap = document.getElementById('adminWrap');
  wrap.innerHTML = `
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <img src="images/logo.jpeg" alt="logo">
        <span>${shopName}</span>
        <button class="sidebar-close" onclick="closeSidebar()" aria-label="Funga menyu"><i class="bi bi-x-lg"></i></button>
      </div>
      <nav>${buildSidebarNav(activePage, admin.role)}</nav>
    </aside>
    <div class="main">
      <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px">
          <button class="hamburger" onclick="toggleSidebar()" aria-label="Fungua menyu"><i class="bi bi-list"></i></button>
          <h1>${pageTitle}</h1>
        </div>
        <div class="user-chip">
          Karibu, <strong>${admin.full_name}</strong>&nbsp;(${ROLE_LABELS[admin.role] || admin.role})
        </div>
      </div>
      <div class="content" id="pageContent"></div>
    </div>
  `;

  setupSidebarBehavior();
  return admin;
}

/** Onyesha ujumbe wa "Huna Ruhusa" kwenye #pageContent na simamisha zaidi */
function denyAccess() {
  document.getElementById('pageContent').innerHTML = `
    <div style="max-width:480px;margin:60px auto;text-align:center;line-height:1.6">
      <h2 style="color:#c0392b">Huna Ruhusa</h2>
      <p>Huna ruhusa ya kuona ukurasa huu. Wasiliana na Msimamizi Mkuu kama unadhani hii ni kosa.</p>
      <a href="dashboard.html" style="color:#8a6d1f;font-weight:700">&larr; Rudi Dashibodi</a>
    </div>`;
}

/** Angalia kama cheo cha mtumiaji kiko kwenye orodha ya ruhusa; kama hapana, onyesha denyAccess() na rudisha false */
function requireRole(admin, allowedRoles) {
  if (!allowedRoles.includes(admin.role)) {
    denyAccess();
    return false;
  }
  return true;
}

function isAdminOrAbove(admin) {
  return admin.role === 'super_admin' || admin.role === 'admin';
}
function isSuperAdmin(admin) {
  return admin.role === 'super_admin';
}

// ---------------- Sidebar toggle / mobile behavior ----------------
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
  document.body.classList.toggle('sidebar-locked');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
  document.body.classList.remove('sidebar-locked');
}

function setupSidebarBehavior() {
  document.querySelectorAll('.sidebar nav a').forEach(function (link) {
    link.addEventListener('click', function () {
      if (!link.hasAttribute('onclick')) closeSidebar();
    });
  });

  const sidebar = document.getElementById('sidebar');
  const savedScroll = sessionStorage.getItem('fs_sidebar_scroll');
  if (savedScroll !== null) sidebar.scrollTop = parseInt(savedScroll, 10) || 0;
  sidebar.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      sessionStorage.setItem('fs_sidebar_scroll', sidebar.scrollTop);
    });
  });
}

// Zuia browser kurudisha (restore) scroll ya ukurasa uliopita kiotomatiki
if ('scrollRestoration' in history) {
  history.scrollRestoration = 'manual';
}

/* =========================================================
   Search / Autocomplete inayoweza kutumika popote
   (Rekodi Mauzo, Uharibifu, Ongeza Stock, Malipo ya Wafanyakazi, n.k.)
========================================================= */
/**
 * Tengeneza sehemu ya utafutaji (search/autocomplete) ndani ya wrapEl.
 * opts:
 *   wrapEl        - DOM element itakayoshikilia input + orodha ya matokeo
 *   items         - array ya vitu vinavyoweza kuchaguliwa
 *   getLabel(it)  - maandishi makuu ya kuonyesha kwenye orodha
 *   getSub(it)    - (hiari) maandishi madogo ya ziada
 *   getSearchText(it) - maandishi ya kutumika kutafutia (jina + maelezo)
 *   onSelect(it)  - (hiari) kinachotokea baada ya kuchagua kipengele
 *   placeholder   - maandishi ya awali kwenye input
 *   noResultsText - maandishi kama hakuna kilicholingana
 * Inarudisha: { setItems(items), clear(), getSelected(), focus() }
 */
function createAutocomplete(opts) {
  const wrapEl = opts.wrapEl;
  let items = opts.items || [];
  let selected = null;

  wrapEl.classList.add('ac-wrap');
  wrapEl.innerHTML = `
    <input type="text" class="ac-input" autocomplete="off" placeholder="${opts.placeholder || 'Bonyeza au andika kutafuta...'}">
    <div class="ac-list hidden"></div>
  `;
  const input = wrapEl.querySelector('.ac-input');
  const list = wrapEl.querySelector('.ac-list');

  function plainLabel(it) {
    return (opts.getLabel(it) || '').replace(/<[^>]*>/g, '');
  }

  function render(filterText) {
    const q = (filterText || '').trim().toLowerCase();
    const filtered = !q ? items : items.filter(it => (opts.getSearchText ? opts.getSearchText(it) : opts.getLabel(it) || '').toLowerCase().includes(q));
    if (!filtered.length) {
      list.innerHTML = `<div class="ac-empty">${opts.noResultsText || 'Hakuna kilicholingana.'}</div>`;
    } else {
      list.innerHTML = filtered.slice(0, 60).map(it => `
        <div class="ac-item" data-idx="${items.indexOf(it)}">
          <div class="ac-item-label">${opts.getLabel(it)}</div>
          ${opts.getSub ? `<div class="ac-item-sub">${opts.getSub(it)}</div>` : ''}
        </div>
      `).join('');
    }
    list.classList.remove('hidden');
  }

  input.addEventListener('focus', () => render(input.value));
  input.addEventListener('input', () => { selected = null; render(input.value); });
  list.addEventListener('click', (e) => {
    const row = e.target.closest('.ac-item');
    if (!row) return;
    const idx = parseInt(row.getAttribute('data-idx'), 10);
    const item = items[idx];
    if (!item) return;
    selected = item;
    input.value = plainLabel(item);
    list.classList.add('hidden');
    if (opts.onSelect) opts.onSelect(item);
  });
  document.addEventListener('click', (e) => {
    if (!wrapEl.contains(e.target)) list.classList.add('hidden');
  });

  return {
    setItems(newItems) { items = newItems || []; },
    clear() { selected = null; input.value = ''; list.classList.add('hidden'); },
    getSelected() { return selected; },
    focus() { input.focus(); },
  };
}
