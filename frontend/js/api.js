/* =========================================================
   FRESH SCENTS — Admin API Helper
========================================================= */
const API_BASE = '../backend/api';

async function apiGet(endpoint, params = {}) {
  const url = new URL(`${API_BASE}/${endpoint}`, window.location.href);
  Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== '') url.searchParams.set(k, v); });
  const res = await fetch(url, { credentials: 'include' });
  return res.json();
}

async function apiPost(endpoint, data = {}, action = '') {
  const url = new URL(`${API_BASE}/${endpoint}`, window.location.href);
  if (action) url.searchParams.set('action', action);
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  return res.json();
}

/** Kwa maombi yenye faili (mfano kupakia picha ya bidhaa) */
async function apiPostForm(endpoint, formData, action = '') {
  const url = new URL(`${API_BASE}/${endpoint}`, window.location.href);
  if (action) url.searchParams.set('action', action);
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  });
  return res.json();
}

function money(n) {
  return Number(n || 0).toLocaleString('en-US') + ' TZS';
}

function fdate(dt) {
  if (!dt) return '-';
  const d = new Date(dt.replace(' ', 'T'));
  return d.toLocaleDateString('sw-TZ', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
    ' ' + d.toLocaleTimeString('sw-TZ', { hour: '2-digit', minute: '2-digit' });
}

function fdateOnly(dt) {
  if (!dt) return '-';
  const d = new Date(dt.includes('T') ? dt : dt + 'T00:00:00');
  return d.toLocaleDateString('sw-TZ', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/**
 * Onyesha/Ficha password kwenye sehemu ya kuandika (icon ya jicho).
 * Tumia kwa kubofya kitufe: <button type="button" class="password-toggle-btn" onclick="togglePasswordField(this)">
 * kilichowekwa ndani ya <div class="password-field"> pamoja na <input type="password">.
 */
function togglePasswordField(btn) {
  const input = btn.parentElement.querySelector('input');
  if (!input) return;
  const showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  const icon = btn.querySelector('i');
  if (icon) {
    icon.classList.toggle('bi-eye', showing);
    icon.classList.toggle('bi-eye-slash', !showing);
  }
}
