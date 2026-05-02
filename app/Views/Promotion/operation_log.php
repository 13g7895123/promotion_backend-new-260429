<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>後台操作紀錄</title>
  <style>
    :root {
      --bg: #0f1117;
      --surface: #1a1d27;
      --border: #2a2d3e;
      --text: #e2e8f0;
      --muted: #8b949e;
      --primary: #6c8ef5;
      --green: #34d399;
      --yellow: #fbbf24;
      --red: #f87171;
      --blue: #60a5fa;
    }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--text); font-family: "Segoe UI", Arial, sans-serif; font-size: 14px; }
    header { display: flex; align-items: center; gap: 16px; padding: 14px 24px; background: var(--surface); border-bottom: 1px solid var(--border); }
    h1 { margin: 0; font-size: 18px; color: var(--primary); }
    .subtitle { color: var(--muted); font-size: 12px; }
    .main { max-width: 1440px; margin: 0 auto; padding: 24px; }
    .filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; padding: 16px; margin-bottom: 18px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
    label { display: flex; flex-direction: column; gap: 4px; color: var(--muted); font-size: 12px; }
    input, select { min-height: 34px; padding: 6px 10px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); outline: none; }
    input:focus, select:focus { border-color: var(--primary); }
    .check { flex-direction: row; align-items: center; min-height: 34px; }
    .check input { min-height: auto; }
    .btn { cursor: pointer; border: 0; border-radius: 4px; min-height: 34px; padding: 7px 16px; color: #fff; background: var(--primary); font-weight: 700; }
    .btn-muted { background: var(--border); color: var(--text); }
    .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
    table { width: 100%; border-collapse: collapse; }
    th { padding: 10px 12px; background: var(--surface); color: var(--muted); font-size: 11px; text-align: left; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    td { padding: 11px 12px; border-top: 1px solid var(--border); vertical-align: top; }
    tbody tr { cursor: pointer; }
    tbody tr:hover { background: rgba(108, 142, 245, .08); }
    .mono { font-family: Consolas, monospace; font-size: 12px; }
    .muted { color: var(--muted); }
    .summary { max-width: 420px; word-break: break-word; }
    .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .badge-delete { background: rgba(248, 113, 113, .15); color: var(--red); }
    .badge-delete_blocked { background: rgba(251, 191, 36, .15); color: var(--yellow); }
    .badge-delete_failed { background: rgba(248, 113, 113, .2); color: var(--red); }
    .badge-api { background: rgba(96, 165, 250, .15); color: var(--blue); }
    .badge-completed { background: rgba(52, 211, 153, .15); color: var(--green); }
    .badge-pending { background: rgba(251, 191, 36, .15); color: var(--yellow); }
    .badge-error { background: rgba(248, 113, 113, .15); color: var(--red); }
    .pagination { display: flex; justify-content: flex-end; align-items: center; gap: 8px; margin-top: 16px; }
    .pagination span { color: var(--muted); font-size: 12px; }
    .empty { padding: 40px; text-align: center; color: var(--muted); }
    .modal-overlay { display: none; position: fixed; inset: 0; z-index: 99; background: rgba(0, 0, 0, .72); align-items: center; justify-content: center; padding: 20px; }
    .modal-overlay.open { display: flex; }
    .modal { width: min(960px, 96vw); max-height: 88vh; overflow: hidden; display: flex; flex-direction: column; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
    .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--border); }
    .modal-header h2 { margin: 0; font-size: 16px; }
    .modal-close { cursor: pointer; border: 0; background: none; color: var(--muted); font-size: 22px; }
    .modal-body { overflow: auto; padding: 18px; }
    .grid { display: grid; grid-template-columns: 150px 1fr; gap: 8px 14px; margin-bottom: 16px; }
    .grid dt { color: var(--muted); font-size: 12px; }
    .grid dd { margin: 0; word-break: break-word; }
    .block-title { margin: 18px 0 8px; color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    pre { margin: 0; padding: 12px; overflow: auto; white-space: pre-wrap; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 12px; }
  </style>
</head>
<body>
<header>
  <h1>後台操作紀錄</h1>
  <span class="subtitle">API 操作與資料變更快照</span>
  <button class="btn btn-muted" style="margin-left:auto" onclick="loadData(currentPage)">刷新</button>
</header>

<main class="main">
  <section class="filters">
    <label>操作類型
      <select id="f-type">
        <option value="">全部</option>
        <option value="delete">刪除</option>
        <option value="delete_blocked">刪除阻擋</option>
        <option value="delete_failed">刪除失敗</option>
      </select>
    </label>
    <label>HTTP Method
      <select id="f-method">
        <option value="">全部</option>
        <option value="GET">GET</option>
        <option value="POST">POST</option>
        <option value="PUT">PUT</option>
        <option value="DELETE">DELETE</option>
      </select>
    </label>
    <label>日期
      <input type="date" id="f-date" />
    </label>
    <label>URI
      <input type="text" id="f-uri" placeholder="/api/promotion/player/delete" style="width:240px" />
    </label>
    <label>關鍵字
      <input type="text" id="f-keyword" placeholder="ID / 帳號 / 摘要" style="width:200px" />
    </label>
    <label>每頁
      <select id="f-per-page">
        <option value="20">20</option>
        <option value="50" selected>50</option>
        <option value="100">100</option>
      </select>
    </label>
    <label class="check">
      <input type="checkbox" id="f-only-operation" />
      只看有資料快照
    </label>
    <button class="btn" onclick="loadData(1)">查詢</button>
    <button class="btn btn-muted" onclick="resetFilters()">重設</button>
  </section>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>時間</th>
          <th>操作</th>
          <th>摘要</th>
          <th>API</th>
          <th>來源 IP</th>
          <th>狀態</th>
          <th>耗時</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="8" class="empty">載入中...</td></tr>
      </tbody>
    </table>
  </div>

  <div class="pagination">
    <span id="page-info"></span>
    <button class="btn btn-muted" id="btn-prev" onclick="changePage(-1)">上一頁</button>
    <button class="btn btn-muted" id="btn-next" onclick="changePage(1)">下一頁</button>
  </div>
</main>

<div class="modal-overlay" id="modal" onclick="closeModal(event)">
  <div class="modal">
    <div class="modal-header">
      <h2 id="modal-title">操作詳細</h2>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body" id="modal-body"></div>
  </div>
</div>

<script>
const BASE = window.location.pathname.replace(/\/operations.*$/, '') + '/operations';
let currentPage = 1;
let totalPages = 1;

function paramsFor(page) {
  return new URLSearchParams({
    page,
    per_page: document.getElementById('f-per-page').value,
    operation_type: document.getElementById('f-type').value,
    method: document.getElementById('f-method').value,
    date: document.getElementById('f-date').value,
    uri: document.getElementById('f-uri').value,
    keyword: document.getElementById('f-keyword').value,
    only_with_operation: document.getElementById('f-only-operation').checked ? '1' : '',
  });
}

async function loadData(page = 1) {
  currentPage = page;
  const res = await fetch(BASE + '/data?' + paramsFor(page));
  const json = await res.json();
  if (!json.success) return;

  totalPages = json.total_pages || 1;
  renderRows(json.data || []);
  document.getElementById('page-info').textContent = `第 ${page} / ${totalPages} 頁，共 ${json.total} 筆`;
  document.getElementById('btn-prev').disabled = page <= 1;
  document.getElementById('btn-next').disabled = page >= totalPages;
}

function renderRows(rows) {
  const tbody = document.getElementById('tbody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="empty">無資料</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(row => {
    const type = row.operation_type || 'api';
    const summary = row.operation_summary || `${row.controller || '-'}::${row.action || '-'}`;
    return `<tr onclick="showDetail(${row.id})">
      <td class="mono">#${esc(row.id)}</td>
      <td class="mono">${fmt(row.triggered_at)}</td>
      <td>${operationBadge(type)}</td>
      <td class="summary">${esc(summary)}</td>
      <td><div class="mono">${esc(row.method)} ${esc(row.uri)}</div><div class="muted">${esc(row.controller || '-')}::${esc(row.action || '-')}</div></td>
      <td class="mono">${esc(row.ip_address || '-')}</td>
      <td><span class="badge badge-${esc(row.status)}">${esc(row.status)}</span></td>
      <td class="mono">${row.duration_ms ? esc(row.duration_ms) + ' ms' : '-'}</td>
    </tr>`;
  }).join('');
}

async function showDetail(id) {
  document.getElementById('modal-title').textContent = `操作 #${id}`;
  document.getElementById('modal-body').innerHTML = '<p class="muted">載入中...</p>';
  document.getElementById('modal').classList.add('open');

  const res = await fetch(BASE + '/detail/' + id);
  const json = await res.json();
  if (!json.success) {
    document.getElementById('modal-body').innerHTML = '<p style="color:var(--red)">載入失敗</p>';
    return;
  }

  const d = json.data;
  document.getElementById('modal-body').innerHTML = `
    <dl class="grid">
      <dt>操作 ID</dt><dd class="mono">#${esc(d.id)}</dd>
      <dt>操作類型</dt><dd>${operationBadge(d.operation_type || 'api')}</dd>
      <dt>摘要</dt><dd>${esc(d.operation_summary || '-')}</dd>
      <dt>API</dt><dd class="mono">${esc(d.method)} ${esc(d.uri)}</dd>
      <dt>Controller</dt><dd class="mono">${esc(d.controller || '-')}::${esc(d.action || '-')}</dd>
      <dt>來源 IP</dt><dd class="mono">${esc(d.ip_address || '-')}</dd>
      <dt>狀態</dt><dd><span class="badge badge-${esc(d.status)}">${esc(d.status)}</span> ${esc(d.response_code || '')}</dd>
      <dt>時間</dt><dd class="mono">${fmt(d.triggered_at)} → ${fmt(d.completed_at)}</dd>
      <dt>耗時</dt><dd class="mono">${d.duration_ms ? esc(d.duration_ms) + ' ms' : '-'}</dd>
    </dl>
    <div class="block-title">操作資料快照</div>
    <pre>${pretty(d.operation_data)}</pre>
    <div class="block-title">Request Data</div>
    <pre>${pretty(d.request_data)}</pre>
    <div class="block-title">Performance Data</div>
    <pre>${pretty(d.perf_data)}</pre>
  `;
}

function operationBadge(type) {
  const labels = {
    delete: '刪除',
    delete_blocked: '刪除阻擋',
    delete_failed: '刪除失敗',
    api: 'API',
  };
  return `<span class="badge badge-${esc(type)}">${labels[type] || esc(type)}</span>`;
}

function changePage(delta) {
  const next = currentPage + delta;
  if (next >= 1 && next <= totalPages) loadData(next);
}

function resetFilters() {
  document.getElementById('f-type').value = '';
  document.getElementById('f-method').value = '';
  document.getElementById('f-date').value = '';
  document.getElementById('f-uri').value = '';
  document.getElementById('f-keyword').value = '';
  document.getElementById('f-only-operation').checked = false;
  loadData(1);
}

function closeModal(event) {
  if (!event || event.target === document.getElementById('modal')) {
    document.getElementById('modal').classList.remove('open');
  }
}

function pretty(value) {
  if (!value) return '-';
  try {
    return esc(JSON.stringify(JSON.parse(value), null, 2));
  } catch (e) {
    return esc(value);
  }
}

function fmt(value) {
  return value ? String(value).replace('T', ' ').slice(0, 19) : '-';
}

function esc(value) {
  return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

['f-uri', 'f-keyword'].forEach(id => {
  document.getElementById(id).addEventListener('keydown', event => {
    if (event.key === 'Enter') loadData(1);
  });
});

loadData(1);
</script>
</body>
</html>