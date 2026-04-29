<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>API Log 儀表板</title>
  <style>
    :root {
      --bg:        #0f1117;
      --surface:   #1a1d27;
      --border:    #2a2d3e;
      --text:      #e2e8f0;
      --muted:     #8892a4;
      --primary:   #6c8ef5;
      --green:     #34d399;
      --yellow:    #fbbf24;
      --red:       #f87171;
      --radius:    8px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; font-size: 14px; min-height: 100vh; }

    /* ── Layout ── */
    header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; gap: 16px; }
    header h1 { font-size: 18px; font-weight: 700; color: var(--primary); }
    header .subtitle { color: var(--muted); font-size: 12px; }
    .main { padding: 24px; max-width: 1400px; margin: 0 auto; }

    /* ── Stats Cards ── */
    .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 20px; }
    .stat-card .label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
    .stat-card .value { font-size: 28px; font-weight: 700; line-height: 1; }
    .stat-card.total  .value { color: var(--primary); }
    .stat-card.ok     .value { color: var(--green);   }
    .stat-card.pend   .value { color: var(--yellow);  }
    .stat-card.err    .value { color: var(--red);     }
    .stat-card.avg    .value { color: var(--text);    }
    .stat-card.max    .value { color: var(--text);    }

    /* ── Filters ── */
    .filters { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
    .filters label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); }
    .filters input, .filters select { background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; padding: 6px 10px; font-size: 13px; outline: none; }
    .filters input:focus, .filters select:focus { border-color: var(--primary); }
    .btn { cursor: pointer; border: none; border-radius: 4px; padding: 7px 16px; font-size: 13px; font-weight: 600; transition: opacity .15s; }
    .btn:hover { opacity: .85; }
    .btn-primary  { background: var(--primary); color: #fff; }
    .btn-danger   { background: var(--red);     color: #fff; }
    .btn-muted    { background: var(--border);  color: var(--text); }

    /* ── Table ── */
    .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius); }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: var(--surface); color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 10px 14px; text-align: left; white-space: nowrap; }
    tbody tr { border-top: 1px solid var(--border); cursor: pointer; transition: background .1s; }
    tbody tr:hover { background: rgba(108,142,245,.08); }
    tbody td { padding: 10px 14px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; }
    .badge-completed { background: rgba(52,211,153,.15); color: var(--green);  }
    .badge-pending   { background: rgba(251,191,36,.15);  color: var(--yellow); }
    .badge-error     { background: rgba(248,113,113,.15); color: var(--red);    }
    .method { font-family: monospace; font-weight: 700; font-size: 12px; }
    .m-GET    { color: var(--green);   }
    .m-POST   { color: var(--primary); }
    .m-PUT    { color: var(--yellow);  }
    .m-DELETE { color: var(--red);     }
    td.uri    { font-family: monospace; font-size: 12px; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    td.dur    { font-family: monospace; }
    .dur-slow  { color: var(--red);    }
    .dur-med   { color: var(--yellow); }
    .dur-ok    { color: var(--green);  }

    /* ── Pagination ── */
    .pagination { display: flex; justify-content: flex-end; align-items: center; gap: 8px; margin-top: 16px; }
    .pagination span { color: var(--muted); font-size: 12px; }

    /* ── Modal ── */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 99; }
    .modal-overlay.open { display: flex; align-items: center; justify-content: center; }
    .modal { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); width: 760px; max-width: 95vw; max-height: 80vh; display: flex; flex-direction: column; overflow: hidden; }
    .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .modal-header h2 { font-size: 15px; }
    .modal-close { cursor: pointer; background: none; border: none; color: var(--muted); font-size: 20px; line-height: 1; }
    .modal-body { padding: 20px; overflow-y: auto; font-size: 13px; }
    .modal-body dl { display: grid; grid-template-columns: 140px 1fr; gap: 8px 16px; }
    .modal-body dt { color: var(--muted); font-size: 11px; text-transform: uppercase; align-self: start; padding-top: 2px; }
    .modal-body dd { word-break: break-all; }
    pre.json { background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 12px; overflow-x: auto; margin-top: 12px; font-size: 12px; color: var(--text); }
    .perf-section { margin-top: 20px; }
    .perf-section h3 { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; }
    .perf-table { width: 100%; border-collapse: collapse; font-size: 12px; font-family: monospace; }
    .perf-table th { background: var(--bg); color: var(--muted); padding: 6px 10px; text-align: left; border-bottom: 1px solid var(--border); }
    .perf-table td { padding: 6px 10px; border-bottom: 1px solid var(--border); }
    .perf-table tr:last-child td { border-bottom: none; }
    .perf-bar-wrap { display: flex; align-items: center; gap: 8px; }
    .perf-bar { height: 8px; border-radius: 4px; background: var(--primary); min-width: 2px; transition: width .3s; }
    .perf-bar.slow { background: var(--red); }
    .perf-bar.med  { background: var(--yellow); }
    .perf-total { margin-top: 8px; font-size: 12px; font-family: monospace; color: var(--muted); text-align: right; }
    .last-step-badge { display: inline-block; background: rgba(251,191,36,.15); color: var(--yellow); border-radius: 4px; padding: 2px 8px; font-family: monospace; font-size: 12px; }

    /* ── Toast ── */
    #toast { position: fixed; bottom: 24px; right: 24px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 20px; font-size: 13px; opacity: 0; transition: opacity .3s; pointer-events: none; z-index: 200; }
    #toast.show { opacity: 1; }

    /* ── Auto-refresh ── */
    .refresh-label { font-size: 12px; color: var(--muted); }
  </style>
</head>
<body>

<header>
  <h1>⚡ API Log 儀表板</h1>
  <span class="subtitle">監控所有 API 觸發、完成狀態與耗時</span>
  <div style="margin-left:auto; display:flex; gap:12px; align-items:center;">
    <label class="refresh-label">
      <input type="checkbox" id="autoRefresh" checked style="margin-right:4px;" />
      每 10s 自動更新
    </label>
    <button class="btn btn-muted" onclick="loadAll()">🔄 立即更新</button>
    <button class="btn btn-danger" onclick="openClean()">🗑 清除舊 Log</button>
  </div>
</header>

<div class="main">

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card total"><div class="label">Total</div><div class="value" id="s-total">–</div></div>
    <div class="stat-card ok">  <div class="label">Completed</div><div class="value" id="s-completed">–</div></div>
    <div class="stat-card pend"><div class="label">Pending ⚠</div><div class="value" id="s-pending">–</div></div>
    <div class="stat-card err"> <div class="label">Error</div><div class="value" id="s-error">–</div></div>
    <div class="stat-card avg"> <div class="label">Avg (ms)</div><div class="value" id="s-avg">–</div></div>
    <div class="stat-card max"> <div class="label">Max (ms)</div><div class="value" id="s-max">–</div></div>
  </div>

  <!-- Filters -->
  <div class="filters">
    <label>狀態
      <select id="f-status">
        <option value="">全部</option>
        <option value="pending">Pending</option>
        <option value="completed">Completed</option>
        <option value="error">Error</option>
      </select>
    </label>
    <label>Method
      <select id="f-method">
        <option value="">全部</option>
        <option value="GET">GET</option>
        <option value="POST">POST</option>
        <option value="PUT">PUT</option>
        <option value="DELETE">DELETE</option>
      </select>
    </label>
    <label>URI 關鍵字
      <input id="f-uri" type="text" placeholder="e.g. promotion" style="width:200px;" />
    </label>
    <label>日期
      <input id="f-date" type="date" />
    </label>
    <label>每頁
      <select id="f-perpage">
        <option value="20">20</option>
        <option value="50" selected>50</option>
        <option value="100">100</option>
      </select>
    </label>
    <button class="btn btn-primary" onclick="search()">🔍 搜尋</button>
    <button class="btn btn-muted" onclick="resetFilters()">重置</button>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>狀態</th>
          <th>Method</th>
          <th>URI</th>
          <th>Controller</th>
          <th>IP</th>
          <th>觸發時間</th>
          <th>完成時間</th>
          <th>耗時(ms)</th>
          <th>HTTP Code</th>
        </tr>
      </thead>
      <tbody id="log-tbody">
        <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:32px;">載入中…</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="pagination">
    <button class="btn btn-muted" id="btn-prev" onclick="changePage(-1)">‹ 上一頁</button>
    <span id="page-info">–</span>
    <button class="btn btn-muted" id="btn-next" onclick="changePage(1)">下一頁 ›</button>
  </div>

</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal">
  <div class="modal">
    <div class="modal-header">
      <h2>Log 詳細資料</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody">載入中…</div>
  </div>
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
// ─── State ────────────────────────────────────────────────────────────────────
let currentPage = 1;
let totalRows   = 0;
let perPage     = 50;
let refreshTimer = null;

const BASE = window.location.pathname.replace(/\/logs.*$/, '');  // /api/promotion

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadAll();
  scheduleRefresh();
  document.getElementById('autoRefresh').addEventListener('change', scheduleRefresh);
});

function scheduleRefresh() {
  clearInterval(refreshTimer);
  if (document.getElementById('autoRefresh').checked) {
    refreshTimer = setInterval(loadAll, 10000);
  }
}

function loadAll() {
  loadStats();
  loadData(currentPage);
}

// ─── Stats ────────────────────────────────────────────────────────────────────
async function loadStats() {
  try {
    const res  = await fetch(`${BASE}/logs/stats`);
    const json = await res.json();
    if (!json.success) return;
    const d = json.data;
    document.getElementById('s-total').textContent     = d.total;
    document.getElementById('s-completed').textContent = d.completed;
    document.getElementById('s-pending').textContent   = d.pending;
    document.getElementById('s-error').textContent     = d.error;
    document.getElementById('s-avg').textContent       = d.avg_ms;
    document.getElementById('s-max').textContent       = d.max_ms;
  } catch(e) { console.error('stats error', e); }
}

// ─── Data Table ───────────────────────────────────────────────────────────────
async function loadData(page = 1) {
  currentPage = page;
  perPage     = parseInt(document.getElementById('f-perpage').value);

  const params = new URLSearchParams({
    page,
    per_page: perPage,
    status:  document.getElementById('f-status').value,
    method:  document.getElementById('f-method').value,
    uri:     document.getElementById('f-uri').value,
    date:    document.getElementById('f-date').value,
  });

  // 移除空值
  [...params.keys()].forEach(k => { if (!params.get(k)) params.delete(k); });

  try {
    const res  = await fetch(`${BASE}/logs/data?${params}`);
    const json = await res.json();
    if (!json.success) { showToast('載入失敗'); return; }

    totalRows = json.total;
    renderTable(json.data);
    renderPagination();
  } catch(e) { console.error('data error', e); }
}

function renderTable(rows) {
  const tbody = document.getElementById('log-tbody');

  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:var(--muted);padding:32px;">暫無資料</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(r => {
    const statusBadge = `<span class="badge badge-${r.status}">${r.status}</span>`;
    const method      = `<span class="method m-${r.method}">${r.method}</span>`;
    const durClass    = r.duration_ms >= 3000 ? 'dur-slow' : r.duration_ms >= 1000 ? 'dur-med' : 'dur-ok';
    const dur         = r.duration_ms != null ? `<span class="${durClass}">${r.duration_ms}</span>` : '–';
    const ctrl        = r.controller ? r.controller.split('\\').pop() : '–';
    const action      = r.action || '–';

    return `<tr onclick="showDetail(${r.id})">
      <td>${r.id}</td>
      <td>${statusBadge}</td>
      <td>${method}</td>
      <td class="uri" title="${esc(r.uri)}">${esc(r.uri)}</td>
      <td>${ctrl}::${action}</td>
      <td>${r.ip_address || '–'}</td>
      <td>${r.triggered_at || '–'}</td>
      <td>${r.completed_at || '<span style="color:var(--yellow)">未完成</span>'}</td>
      <td class="dur">${dur}</td>
      <td>${r.response_code || '–'}</td>
    </tr>`;
  }).join('');
}

function renderPagination() {
  const totalPages = Math.max(1, Math.ceil(totalRows / perPage));
  document.getElementById('page-info').textContent = `第 ${currentPage} / ${totalPages} 頁 (共 ${totalRows} 筆)`;
  document.getElementById('btn-prev').disabled = currentPage <= 1;
  document.getElementById('btn-next').disabled = currentPage >= totalPages;
}

function changePage(delta) {
  loadData(currentPage + delta);
}

// ─── Search / Reset ────────────────────────────────────────────────────────────
function search() {
  loadData(1);
}

function resetFilters() {
  ['f-status','f-method','f-uri','f-date'].forEach(id => {
    const el = document.getElementById(id);
    el.tagName === 'SELECT' ? el.value = '' : el.value = '';
  });
  loadData(1);
}

// ─── Detail Modal ─────────────────────────────────────────────────────────────
async function showDetail(id) {
  document.getElementById('detailModal').classList.add('open');
  document.getElementById('modalBody').innerHTML = '載入中…';

  try {
    const res  = await fetch(`${BASE}/logs/detail/${id}`);
    const json = await res.json();
    if (!json.success) { document.getElementById('modalBody').textContent = '查無資料'; return; }

    const d = json.data;
    let body = '';
    try { body = JSON.stringify(JSON.parse(d.request_data), null, 2); } catch(e) { body = d.request_data || '–'; }

    // 渲染 perf_data 段落耗時表格
    let perfHtml = '';
    if (d.perf_data) {
      try {
        const perf = JSON.parse(d.perf_data);
        const maxSeg = Math.max(...perf.steps.map(s => s.segment_ms), 1);
        const rows = perf.steps.map(s => {
          const pct   = Math.round((s.segment_ms / maxSeg) * 100);
          const cls   = s.segment_ms >= 1000 ? 'slow' : s.segment_ms >= 300 ? 'med' : '';
          return `<tr>
            <td>${esc(s.step)}</td>
            <td class="perf-bar-wrap">
              <div class="perf-bar ${cls}" style="width:${pct}%"></div>
              <span style="color:${cls==='slow'?'var(--red)':cls==='med'?'var(--yellow)':'var(--green)'}">${s.segment_ms} ms</span>
            </td>
            <td style="color:var(--muted)">${s.elapsed_ms} ms</td>
          </tr>`;
        }).join('');
        perfHtml = `
          <div class="perf-section">
            <h3>⏱ 段落耗時分解（AuditProfiler）</h3>
            <table class="perf-table">
              <thead><tr><th>段落</th><th>本段耗時</th><th>累計</th></tr></thead>
              <tbody>${rows}</tbody>
            </table>
            <div class="perf-total">Total: <strong>${perf.total_ms} ms</strong></div>
          </div>`;
      } catch(e) {
        perfHtml = `<div class="perf-section"><pre class="json">${esc(d.perf_data)}</pre></div>`;
      }
    }

    // last_step（504 逾時時的最後已知段落）
    const lastStepHtml = d.last_step
      ? `<span class="last-step-badge">${esc(d.last_step)}</span>`
      : '<span style="color:var(--muted)">–</span>';

    document.getElementById('modalBody').innerHTML = `
      <dl>
        <dt>ID</dt>          <dd>${d.id}</dd>
        <dt>狀態</dt>        <dd><span class="badge badge-${d.status}">${d.status}</span></dd>
        <dt>Method</dt>      <dd><span class="method m-${d.method}">${d.method}</span></dd>
        <dt>URI</dt>         <dd style="font-family:monospace">${esc(d.uri)}</dd>
        <dt>Controller</dt>  <dd>${d.controller || '–'}</dd>
        <dt>Action</dt>      <dd>${d.action || '–'}</dd>
        <dt>IP</dt>          <dd>${d.ip_address || '–'}</dd>
        <dt>HTTP Code</dt>   <dd>${d.response_code || '–'}</dd>
        <dt>觸發時間</dt>    <dd>${d.triggered_at || '–'}</dd>
        <dt>完成時間</dt>    <dd>${d.completed_at || '<span style="color:var(--yellow)">未完成（可能 504）</span>'}</dd>
        <dt>耗時</dt>        <dd>${d.duration_ms != null ? d.duration_ms + ' ms' : '–'}</dd>
        <dt>最後段落</dt>    <dd>${lastStepHtml}</dd>
      </dl>
      ${perfHtml}
      <div class="perf-section">
        <h3>📦 Request Body</h3>
        <pre class="json">${esc(body)}</pre>
      </div>
    `;
  } catch(e) { document.getElementById('modalBody').textContent = '載入失敗'; }
}

function closeModal() {
  document.getElementById('detailModal').classList.remove('open');
}

document.getElementById('detailModal').addEventListener('click', e => {
  if (e.target === document.getElementById('detailModal')) closeModal();
});

// ─── Clean ────────────────────────────────────────────────────────────────────
async function openClean() {
  const days = prompt('刪除幾天前的 log？（預設 30）', '30');
  if (days === null) return;
  const d = parseInt(days) || 30;

  try {
    const res  = await fetch(`${BASE}/logs/clean`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ days: d }),
    });
    const json = await res.json();
    showToast(json.message || '清除完成');
    loadAll();
  } catch(e) { showToast('清除失敗'); }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function esc(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function showToast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3000);
}
</script>
</body>
</html>
