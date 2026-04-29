<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Promotion API Log 查看器</title>
  <style>
    :root {
      --bg: #0d1117;
      --bg2: #161b22;
      --bg3: #21262d;
      --border: #30363d;
      --text: #e6edf3;
      --text-muted: #8b949e;
      --accent: #58a6ff;
      --success: #3fb950;
      --failure: #f85149;
      --warning: #d29922;
      --tag-bg: #1f2937;
      --radius: 8px;
      --font: 'Segoe UI', system-ui, sans-serif;
      --mono: 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg);
      color: var(--text);
      font-family: var(--font);
      font-size: 14px;
      min-height: 100vh;
    }

    /* ── Header ── */
    header {
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      padding: 14px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    header h1 {
      font-size: 18px;
      font-weight: 600;
      letter-spacing: .5px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    header h1 span { color: var(--accent); }
    .header-right { margin-left: auto; display: flex; gap: 10px; align-items: center; }

    /* ── Stats bar ── */
    #stats-bar {
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      padding: 12px 24px;
      display: flex;
      gap: 24px;
      flex-wrap: wrap;
    }
    .stat-item { display: flex; flex-direction: column; }
    .stat-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .8px; }
    .stat-value { font-size: 22px; font-weight: 700; }
    .stat-value.success { color: var(--success); }
    .stat-value.failure { color: var(--failure); }
    .stat-value.total   { color: var(--accent); }
    .stat-value.avg     { color: var(--warning); }

    /* ── Filters ── */
    .filters {
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      padding: 12px 24px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: flex-end;
    }
    .filter-group { display: flex; flex-direction: column; gap: 4px; }
    .filter-group label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; }
    .filters input, .filters select {
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--text);
      padding: 6px 10px;
      border-radius: var(--radius);
      font-size: 13px;
      min-width: 130px;
    }
    .filters input:focus, .filters select:focus {
      outline: none;
      border-color: var(--accent);
    }
    .btn {
      padding: 7px 16px;
      border-radius: var(--radius);
      border: none;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: opacity .15s;
    }
    .btn:hover { opacity: .85; }
    .btn-primary   { background: var(--accent); color: #fff; }
    .btn-danger    { background: var(--failure); color: #fff; }
    .btn-secondary { background: var(--bg3); color: var(--text); border: 1px solid var(--border); }

    /* ── Table ── */
    .table-wrap {
      padding: 16px 24px;
      overflow-x: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    thead th {
      background: var(--bg2);
      color: var(--text-muted);
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .7px;
      padding: 10px 12px;
      text-align: left;
      border-bottom: 2px solid var(--border);
      white-space: nowrap;
    }
    tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background .1s;
      cursor: pointer;
    }
    tbody tr:hover { background: var(--bg2); }
    tbody td {
      padding: 10px 12px;
      vertical-align: middle;
      max-width: 320px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .4px;
    }
    .badge-success { background: rgba(63,185,80,.2); color: var(--success); }
    .badge-failure { background: rgba(248,81,73,.2); color: var(--failure); }
    .badge-method-get    { background: rgba(88,166,255,.2);  color: #79c0ff; }
    .badge-method-post   { background: rgba(63,185,80,.2);   color: var(--success); }
    .badge-method-put    { background: rgba(210,153,34,.2);  color: var(--warning); }
    .badge-method-delete { background: rgba(248,81,73,.2);   color: var(--failure); }
    .badge-method-patch  { background: rgba(188,140,230,.2); color: #d2a8ff; }
    .text-muted { color: var(--text-muted); }
    .endpoint-text { font-family: var(--mono); font-size: 12px; }

    /* ── Pagination ── */
    .pagination {
      padding: 16px 24px;
      display: flex;
      align-items: center;
      gap: 8px;
      justify-content: space-between;
    }
    .pagination-info { color: var(--text-muted); font-size: 13px; }
    .pagination-btns { display: flex; gap: 6px; }
    .page-btn {
      padding: 5px 12px;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: var(--bg3);
      color: var(--text);
      cursor: pointer;
      font-size: 13px;
    }
    .page-btn:hover { border-color: var(--accent); color: var(--accent); }
    .page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
    .page-btn:disabled { opacity: .4; cursor: not-allowed; }

    /* ── Modal ── */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.7);
      z-index: 999;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 12px;
      width: 100%;
      max-width: 900px;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .modal-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .modal-header h2 { font-size: 16px; font-weight: 600; }
    .modal-close {
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      font-size: 20px;
      line-height: 1;
      padding: 0 4px;
    }
    .modal-close:hover { color: var(--text); }
    .modal-body {
      padding: 20px;
      overflow-y: auto;
      flex: 1;
    }
    .detail-section {
      margin-bottom: 20px;
    }
    .detail-section h3 {
      font-size: 12px;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: .8px;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .detail-section h3::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }
    .meta-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 12px;
      margin-bottom: 16px;
    }
    .meta-item { display: flex; flex-direction: column; gap: 3px; }
    .meta-item .key { font-size: 11px; color: var(--text-muted); }
    .meta-item .val { font-weight: 500; }
    pre.code-block {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 14px;
      font-family: var(--mono);
      font-size: 12px;
      line-height: 1.6;
      overflow: auto;
      max-height: 360px;
      white-space: pre-wrap;
      word-break: break-all;
    }
    .json-key   { color: #79c0ff; }
    .json-str   { color: #a5d6ff; }
    .json-num   { color: #f2cc60; }
    .json-bool  { color: #ff7b72; }
    .json-null  { color: var(--text-muted); }

    /* ── Loading / empty ── */
    .state-loading, .state-empty {
      text-align: center;
      padding: 60px 24px;
      color: var(--text-muted);
    }
    .spinner {
      width: 30px;
      height: 30px;
      border: 3px solid var(--border);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin .7s linear infinite;
      margin: 0 auto 12px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

    /* ── Endpoint stats tab ── */
    #tabs { padding: 0 24px; margin-top: 8px; display: flex; gap: 2px; border-bottom: 1px solid var(--border); }
    .tab-btn {
      padding: 10px 18px;
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      color: var(--text-muted);
      cursor: pointer;
      font-size: 13px;
    }
    .tab-btn.active { border-bottom-color: var(--accent); color: var(--text); font-weight: 600; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
  </style>
</head>
<body>

<!-- Header -->
<header>
  <h1>📋 Promotion <span>API Log</span> 查看器</h1>
  <div class="header-right">
    <span id="last-refresh" class="text-muted" style="font-size:12px"></span>
    <button class="btn btn-secondary" onclick="loadStats()">↻ 重整統計</button>
    <button class="btn btn-danger" onclick="openCleanModal()">🗑 清除舊 Log</button>
  </div>
</header>

<!-- Stats bar -->
<div id="stats-bar">
  <div class="stat-item">
    <span class="stat-label">總請求數</span>
    <span class="stat-value total" id="stat-total">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">成功</span>
    <span class="stat-value success" id="stat-success">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">失敗</span>
    <span class="stat-value failure" id="stat-fail">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">成功率</span>
    <span class="stat-value avg" id="stat-rate">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">平均回應</span>
    <span class="stat-value avg" id="stat-avg">—</span>
  </div>
</div>

<!-- Tabs -->
<div id="tabs">
  <button class="tab-btn active" onclick="switchTab('logs')">🗄 Log 清單</button>
  <button class="tab-btn" onclick="switchTab('endpoints')">📊 Endpoint 統計</button>
</div>

<!-- ─── Tab: Logs ─── -->
<div id="tab-logs" class="tab-content active">
  <!-- Filters -->
  <div class="filters">
    <div class="filter-group">
      <label>Method</label>
      <select id="f-method">
        <option value="">全部</option>
        <option>GET</option><option>POST</option><option>PUT</option>
        <option>DELETE</option><option>PATCH</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Endpoint 含關鍵字</label>
      <input type="text" id="f-endpoint" placeholder="e.g. /api/promotion/player" />
    </div>
    <div class="filter-group">
      <label>Action (方法名)</label>
      <input type="text" id="f-action" placeholder="e.g. submit" />
    </div>
    <div class="filter-group">
      <label>狀態</label>
      <select id="f-success">
        <option value="">全部</option>
        <option value="1">成功</option>
        <option value="0">失敗</option>
      </select>
    </div>
    <div class="filter-group">
      <label>日期起</label>
      <input type="datetime-local" id="f-date-from" />
    </div>
    <div class="filter-group">
      <label>日期迄</label>
      <input type="datetime-local" id="f-date-to" />
    </div>
    <div class="filter-group">
      <label>IP 位址</label>
      <input type="text" id="f-ip" placeholder="e.g. 127.0.0.1" />
    </div>
    <div class="filter-group">
      <label>每頁筆數</label>
      <select id="f-per-page">
        <option value="20">20</option>
        <option value="50" selected>50</option>
        <option value="100">100</option>
      </select>
    </div>
    <button class="btn btn-primary" onclick="loadLogs(1)">🔍 查詢</button>
    <button class="btn btn-secondary" onclick="resetFilters()">✕ 重置</button>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <div id="log-loading" class="state-loading" style="display:none">
      <div class="spinner"></div>載入中...
    </div>
    <div id="log-empty" class="state-empty" style="display:none">沒有符合條件的記錄</div>
    <table id="log-table">
      <thead>
        <tr>
          <th>#</th>
          <th>時間</th>
          <th>Method</th>
          <th>Endpoint</th>
          <th>Action</th>
          <th>狀態</th>
          <th>HTTP</th>
          <th>耗時 (ms)</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody id="log-tbody"></tbody>
    </table>
  </div>
  <!-- Pagination -->
  <div class="pagination">
    <div class="pagination-info" id="page-info"></div>
    <div class="pagination-btns" id="page-btns"></div>
  </div>
</div>

<!-- ─── Tab: Endpoints ─── -->
<div id="tab-endpoints" class="tab-content">
  <div class="table-wrap">
    <table id="ep-table">
      <thead>
        <tr>
          <th>Endpoint</th>
          <th>Action</th>
          <th>呼叫次數</th>
          <th>成功次數</th>
          <th>失敗次數</th>
          <th>成功率</th>
          <th>平均耗時 (ms)</th>
        </tr>
      </thead>
      <tbody id="ep-tbody">
        <tr><td colspan="7" class="state-empty">載入中...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ─── Detail Modal ─── -->
<div class="modal-overlay" id="detail-modal">
  <div class="modal">
    <div class="modal-header">
      <h2>📄 Log 詳細內容</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modal-body">
      <div class="state-loading"><div class="spinner"></div>載入中...</div>
    </div>
  </div>
</div>

<!-- ─── Clean Modal ─── -->
<div class="modal-overlay" id="clean-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h2>🗑 清除舊 Log</h2>
      <button class="modal-close" onclick="closeCleanModal()">✕</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:16px;color:var(--text-muted)">清除指定天數前的所有 log 紀錄，此操作無法復原。</p>
      <div class="filter-group" style="margin-bottom:16px">
        <label>保留最近幾天</label>
        <input type="number" id="clean-days" value="30" min="1" style="max-width:200px" />
      </div>
      <button class="btn btn-danger" onclick="doClean()">確認清除</button>
      <button class="btn btn-secondary" style="margin-left:8px" onclick="closeCleanModal()">取消</button>
    </div>
  </div>
</div>

<script>
const BASE = window.location.pathname.replace(/\/$/, '').replace(/\/logs$/, '') + '/logs';
let currentPage = 1;
let totalPages  = 1;

// ── Utils ──────────────────────────────────────────────────────────
function esc(s) {
  return String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;');
}

function methodBadge(m) {
  const cls = 'badge badge-method-' + (m || '').toLowerCase();
  return `<span class="${cls}">${esc(m)}</span>`;
}

function successBadge(v) {
  return v == 1
    ? `<span class="badge badge-success">✓ 成功</span>`
    : `<span class="badge badge-failure">✗ 失敗</span>`;
}

function fmtDate(s) {
  if (!s) return '—';
  return s.replace('T', ' ');
}

function syntaxHighlight(json) {
  if (typeof json !== 'string') json = JSON.stringify(json, null, 2);
  json = json.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  return json.replace(
    /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
    function(m) {
      let cls = 'json-num';
      if (/^"/.test(m)) cls = /:$/.test(m) ? 'json-key' : 'json-str';
      else if (/true|false/.test(m)) cls = 'json-bool';
      else if (/null/.test(m)) cls = 'json-null';
      return `<span class="${cls}">${m}</span>`;
    }
  );
}

function tryPrettyJson(str) {
  if (!str) return '(空)';
  try {
    const obj = (typeof str === 'string') ? JSON.parse(str) : str;
    return syntaxHighlight(JSON.stringify(obj, null, 2));
  } catch(e) {
    return esc(str);
  }
}

// ── Stats ──────────────────────────────────────────────────────────
async function loadStats() {
  try {
    const r = await fetch(BASE + '/stats');
    const j = await r.json();
    if (!j.success) return;
    const d = j.data;
    document.getElementById('stat-total').textContent   = d.total.toLocaleString();
    document.getElementById('stat-success').textContent = d.success.toLocaleString();
    document.getElementById('stat-fail').textContent    = d.fail.toLocaleString();
    const rate = d.total > 0 ? ((d.success / d.total) * 100).toFixed(1) : '0.0';
    document.getElementById('stat-rate').textContent    = rate + '%';
    document.getElementById('stat-avg').textContent     = d.avg_duration + ' ms';

    // Endpoints tab
    const tbody = document.getElementById('ep-tbody');
    if (!d.endpoints || d.endpoints.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="state-empty">無資料</td></tr>';
      return;
    }
    tbody.innerHTML = d.endpoints.map(e => {
      const fail = e.count - e.success_count;
      const rate = e.count > 0 ? ((e.success_count / e.count) * 100).toFixed(1) : '0.0';
      return `<tr>
        <td class="endpoint-text">${esc(e.endpoint)}</td>
        <td>${esc(e.action)}</td>
        <td><strong>${Number(e.count).toLocaleString()}</strong></td>
        <td style="color:var(--success)">${Number(e.success_count).toLocaleString()}</td>
        <td style="color:var(--failure)">${Number(fail).toLocaleString()}</td>
        <td>${rate}%</td>
        <td>${Math.round(e.avg_ms)} ms</td>
      </tr>`;
    }).join('');
  } catch(e) {
    console.error(e);
  }
}

// ── Logs ───────────────────────────────────────────────────────────
async function loadLogs(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    page,
    per_page : document.getElementById('f-per-page').value,
    method   : document.getElementById('f-method').value,
    endpoint : document.getElementById('f-endpoint').value,
    action   : document.getElementById('f-action').value,
    is_success: document.getElementById('f-success').value,
    date_from: document.getElementById('f-date-from').value.replace('T',' '),
    date_to  : document.getElementById('f-date-to').value.replace('T',' '),
    ip_address: document.getElementById('f-ip').value,
  });

  const table   = document.getElementById('log-table');
  const loading = document.getElementById('log-loading');
  const empty   = document.getElementById('log-empty');
  loading.style.display = 'block';
  table.style.display   = 'none';
  empty.style.display   = 'none';

  try {
    const r = await fetch(BASE + '/data?' + params);
    const j = await r.json();
    loading.style.display = 'none';

    if (!j.success) { empty.style.display = 'block'; return; }
    const { total, page: pg, per_page, data } = j.data;

    if (!data || data.length === 0) {
      empty.style.display = 'block';
      renderPagination(0, 0, 0);
      return;
    }

    table.style.display = '';
    const tbody = document.getElementById('log-tbody');
    tbody.innerHTML = data.map(row => `
      <tr onclick="openDetail(${row.id})">
        <td class="text-muted">${row.id}</td>
        <td class="text-muted" style="font-size:12px;white-space:nowrap">${esc(row.created_at)}</td>
        <td>${methodBadge(row.method)}</td>
        <td class="endpoint-text" title="${esc(row.endpoint)}">${esc(row.endpoint)}</td>
        <td class="text-muted">${esc(row.action)}</td>
        <td>${successBadge(row.is_success)}</td>
        <td><code style="font-size:11px">${row.response_status || '—'}</code></td>
        <td style="color:${row.duration_ms > 1000 ? 'var(--failure)' : row.duration_ms > 300 ? 'var(--warning)' : 'var(--text)'}">${row.duration_ms ?? '—'}</td>
        <td class="text-muted">${esc(row.ip_address)}</td>
      </tr>`
    ).join('');

    totalPages = Math.ceil(total / per_page);
    renderPagination(total, pg, per_page);
    document.getElementById('last-refresh').textContent = '更新: ' + new Date().toLocaleTimeString();
  } catch(e) {
    loading.style.display = 'none';
    empty.style.display   = 'block';
    console.error(e);
  }
}

function renderPagination(total, page, perPage) {
  const totalPg = Math.ceil(total / perPage) || 1;
  const from    = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to      = Math.min(page * perPage, total);
  document.getElementById('page-info').textContent = `顯示 ${from}–${to} / 共 ${total.toLocaleString()} 筆`;

  const container = document.getElementById('page-btns');
  let html = `<button class="page-btn" onclick="loadLogs(${page - 1})" ${page <= 1 ? 'disabled' : ''}>‹ 上一頁</button>`;

  const start = Math.max(1, page - 2);
  const end   = Math.min(totalPg, page + 2);
  if (start > 1)      html += `<button class="page-btn" onclick="loadLogs(1)">1</button>${start > 2 ? '<span style="padding:0 4px;color:var(--text-muted)">…</span>' : ''}`;
  for (let i = start; i <= end; i++) {
    html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadLogs(${i})">${i}</button>`;
  }
  if (end < totalPg) html += `${end < totalPg - 1 ? '<span style="padding:0 4px;color:var(--text-muted)">…</span>' : ''}<button class="page-btn" onclick="loadLogs(${totalPg})">${totalPg}</button>`;
  html += `<button class="page-btn" onclick="loadLogs(${page + 1})" ${page >= totalPg ? 'disabled' : ''}>下一頁 ›</button>`;
  container.innerHTML = html;
}

function resetFilters() {
  ['f-method','f-endpoint','f-action','f-success','f-date-from','f-date-to','f-ip'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  loadLogs(1);
}

// ── Detail Modal ───────────────────────────────────────────────────
async function openDetail(id) {
  document.getElementById('detail-modal').classList.add('open');
  const body = document.getElementById('modal-body');
  body.innerHTML = '<div class="state-loading"><div class="spinner"></div>載入中...</div>';

  try {
    const r = await fetch(BASE + '/detail/' + id);
    const j = await r.json();
    if (!j.success) { body.innerHTML = '<p style="color:var(--failure)">載入失敗</p>'; return; }
    const d = j.data;

    body.innerHTML = `
      <div class="detail-section">
        <h3>基本資訊</h3>
        <div class="meta-grid">
          <div class="meta-item"><span class="key">ID</span><span class="val">${d.id}</span></div>
          <div class="meta-item"><span class="key">時間</span><span class="val">${esc(d.created_at)}</span></div>
          <div class="meta-item"><span class="key">Method</span><span class="val">${methodBadge(d.method)}</span></div>
          <div class="meta-item"><span class="key">狀態</span><span class="val">${successBadge(d.is_success)}</span></div>
          <div class="meta-item"><span class="key">HTTP Status</span><span class="val">${d.response_status || '—'}</span></div>
          <div class="meta-item"><span class="key">耗時</span><span class="val" style="color:var(--warning)">${d.duration_ms ?? '—'} ms</span></div>
          <div class="meta-item"><span class="key">IP</span><span class="val">${esc(d.ip_address)}</span></div>
          <div class="meta-item"><span class="key">Controller</span><span class="val" style="font-size:12px;font-family:var(--mono)">${esc(d.controller)}</span></div>
          <div class="meta-item"><span class="key">Action</span><span class="val" style="font-family:var(--mono)">${esc(d.action)}</span></div>
        </div>
        <div class="meta-item" style="margin-bottom:12px">
          <span class="key">Endpoint</span>
          <span class="val endpoint-text">${esc(d.endpoint)}</span>
        </div>
      </div>

      <div class="detail-section">
        <h3>Request Headers</h3>
        <pre class="code-block">${tryPrettyJson(d.request_headers)}</pre>
      </div>

      <div class="detail-section">
        <h3>Request Body</h3>
        <pre class="code-block">${tryPrettyJson(d.request_body)}</pre>
      </div>

      <div class="detail-section">
        <h3>Response Body</h3>
        <pre class="code-block">${tryPrettyJson(d.response_body)}</pre>
      </div>
    `;
  } catch(e) {
    body.innerHTML = `<p style="color:var(--failure)">錯誤: ${esc(e.message)}</p>`;
  }
}

function closeModal() {
  document.getElementById('detail-modal').classList.remove('open');
}

// ── Clean Modal ────────────────────────────────────────────────────
function openCleanModal() {
  document.getElementById('clean-modal').classList.add('open');
}
function closeCleanModal() {
  document.getElementById('clean-modal').classList.remove('open');
}
async function doClean() {
  const days = parseInt(document.getElementById('clean-days').value) || 30;
  if (!confirm(`確定要清除 ${days} 天前的所有 log 嗎？`)) return;
  const r = await fetch(BASE + '/clean', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ days }),
  });
  const j = await r.json();
  alert(j.msg || (j.success ? '清除成功' : '清除失敗'));
  closeCleanModal();
  loadStats();
  loadLogs(1);
}

// ── Tabs ───────────────────────────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach((b, i) => {
    b.classList.toggle('active', ['logs','endpoints'][i] === name);
  });
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  if (name === 'endpoints') loadStats();
}

// Close modal on overlay click
document.getElementById('detail-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
document.getElementById('clean-modal').addEventListener('click', function(e) {
  if (e.target === this) closeCleanModal();
});

// Enter key on filter inputs
document.querySelectorAll('.filters input, .filters select').forEach(el => {
  el.addEventListener('keydown', e => { if(e.key === 'Enter') loadLogs(1); });
});

// ── Init ───────────────────────────────────────────────────────────
loadStats();
loadLogs(1);
</script>
</body>
</html>
