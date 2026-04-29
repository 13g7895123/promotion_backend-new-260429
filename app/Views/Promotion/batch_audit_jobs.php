<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>批次審核排程監控</title>
  <style>
    :root {
      --bg:       #0f1117;
      --surface:  #1a1d27;
      --border:   #2a2d3e;
      --text:     #e2e8f0;
      --muted:    #8892a4;
      --primary:  #6c8ef5;
      --green:    #34d399;
      --yellow:   #fbbf24;
      --red:      #f87171;
      --blue:     #60a5fa;
      --radius:   8px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; font-size: 14px; min-height: 100vh; }

    header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; gap: 16px; }
    header h1 { font-size: 18px; font-weight: 700; color: var(--primary); }
    header .subtitle { color: var(--muted); font-size: 12px; }
    .main { padding: 24px; max-width: 1400px; margin: 0 auto; }

    /* Stats */
    .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 20px; }
    .stat-card .label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
    .stat-card .value { font-size: 28px; font-weight: 700; line-height: 1; }
    .stat-card.total       .value { color: var(--primary); }
    .stat-card.pending     .value { color: var(--yellow);  }
    .stat-card.processing  .value { color: var(--blue);    }
    .stat-card.completed   .value { color: var(--green);   }
    .stat-card.failed      .value { color: var(--red);     }

    /* Filters */
    .filters { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
    .filters label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); }
    .filters input, .filters select { background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; padding: 6px 10px; font-size: 13px; outline: none; }
    .filters input:focus, .filters select:focus { border-color: var(--primary); }
    .btn { cursor: pointer; border: none; border-radius: 4px; padding: 7px 16px; font-size: 13px; font-weight: 600; transition: opacity .15s; }
    .btn:hover { opacity: .85; }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-muted   { background: var(--border);  color: var(--text); }

    /* Table */
    .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius); }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: var(--surface); color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 10px 14px; text-align: left; white-space: nowrap; }
    tbody tr { border-top: 1px solid var(--border); cursor: pointer; transition: background .1s; }
    tbody tr:hover { background: rgba(108,142,245,.08); }
    tbody td { padding: 10px 14px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .badge-pending    { background: rgba(251,191,36,.15);  color: var(--yellow); }
    .badge-processing { background: rgba(96,165,250,.15);  color: var(--blue);   }
    .badge-completed  { background: rgba(52,211,153,.15);  color: var(--green);  }
    .badge-failed     { background: rgba(248,113,113,.15); color: var(--red);    }
    .mono { font-family: monospace; font-size: 12px; }

    /* Progress bar */
    .progress-wrap { display: flex; align-items: center; gap: 8px; }
    .progress-bg   { flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; min-width: 80px; }
    .progress-fill { height: 100%; background: var(--green); border-radius: 3px; transition: width .3s; }
    .progress-fill.partial { background: var(--yellow); }
    .progress-fill.zero    { background: var(--muted);  }

    /* Pagination */
    .pagination { display: flex; justify-content: flex-end; align-items: center; gap: 8px; margin-top: 16px; }
    .pagination span { color: var(--muted); font-size: 12px; }

    /* Modal */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 99; }
    .modal-overlay.open { display: flex; align-items: center; justify-content: center; }
    .modal { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); width: 680px; max-width: 95vw; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; }
    .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .modal-header h2 { font-size: 15px; }
    .modal-close { cursor: pointer; background: none; border: none; color: var(--muted); font-size: 20px; line-height: 1; }
    .modal-body { padding: 20px; overflow-y: auto; font-size: 13px; }
    .modal-body dl { display: grid; grid-template-columns: 140px 1fr; gap: 8px 16px; }
    .modal-body dt { color: var(--muted); font-size: 11px; text-transform: uppercase; align-self: start; padding-top: 2px; }
    .modal-body dd { word-break: break-all; }
    pre.json { background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 12px; overflow-x: auto; margin-top: 12px; font-size: 12px; color: var(--text); white-space: pre-wrap; }

    /* Auto-refresh indicator */
    .refresh-bar { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 12px; }
    .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
  </style>
</head>
<body>
<header>
  <h1>批次審核排程監控</h1>
  <span class="subtitle">Batch Audit Job Queue</span>
  <div style="margin-left:auto; display:flex; gap:12px; align-items:center;">
    <div class="refresh-bar"><div class="dot"></div><span id="countdown">30s 後自動更新</span></div>
    <button class="btn btn-muted" onclick="loadAll()">立即刷新</button>
  </div>
</header>

<div class="main">
  <!-- Stats -->
  <div class="stats">
    <div class="stat-card total">
      <div class="label">Total</div>
      <div class="value" id="s-total">-</div>
    </div>
    <div class="stat-card pending">
      <div class="label">Pending</div>
      <div class="value" id="s-pending">-</div>
    </div>
    <div class="stat-card processing">
      <div class="label">Processing</div>
      <div class="value" id="s-processing">-</div>
    </div>
    <div class="stat-card completed">
      <div class="label">Completed</div>
      <div class="value" id="s-completed">-</div>
    </div>
    <div class="stat-card failed">
      <div class="label">Failed</div>
      <div class="value" id="s-failed">-</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="filters">
    <label>
      狀態
      <select id="f-status">
        <option value="">全部</option>
        <option value="pending">pending</option>
        <option value="processing">processing</option>
        <option value="completed">completed</option>
        <option value="failed">failed</option>
      </select>
    </label>
    <label>
      日期
      <input type="date" id="f-date" />
    </label>
    <label>
      觸發人
      <input type="text" id="f-created-by" placeholder="user_id / IP" style="width:160px;" />
    </label>
    <label>
      每頁
      <select id="f-per-page">
        <option value="20">20</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </label>
    <button class="btn btn-primary" onclick="loadData(1)">搜尋</button>
    <button class="btn btn-muted"    onclick="resetFilters()">重設</button>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Job ID</th>
          <th>狀態</th>
          <th>審核目標</th>
          <th>進度</th>
          <th>總筆數</th>
          <th>觸發人</th>
          <th>入列時間</th>
          <th>開始時間</th>
          <th>完成時間</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:40px;">載入中…</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="pagination">
    <span id="page-info"></span>
    <button class="btn btn-muted" id="btn-prev" onclick="changePage(-1)">‹ 上一頁</button>
    <button class="btn btn-muted" id="btn-next" onclick="changePage(1)">下一頁 ›</button>
  </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="modal" onclick="closeModal(event)">
  <div class="modal">
    <div class="modal-header">
      <h2 id="modal-title">Job 詳細</h2>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modal-body"></div>
  </div>
</div>

<script>
  const BASE = window.location.pathname.replace(/\/jobs.*$/, '');
  let currentPage = 1;
  let totalPages  = 1;
  let countdownSec = 30;
  let countdownTimer;

  // ── 自動刷新倒數 ──────────────────────────────────────────────
  function startCountdown() {
    clearInterval(countdownTimer);
    countdownSec = 30;
    countdownTimer = setInterval(() => {
      countdownSec--;
      document.getElementById('countdown').textContent = countdownSec + 's 後自動更新';
      if (countdownSec <= 0) {
        loadAll();
      }
    }, 1000);
  }

  // ── 主要載入 ─────────────────────────────────────────────────
  async function loadAll() {
    startCountdown();
    await Promise.all([loadStats(), loadData(currentPage)]);
  }

  async function loadStats() {
    const res  = await fetch(BASE + '/jobs/stats');
    const json = await res.json();
    if (!json.success) return;
    const d = json.data;
    document.getElementById('s-total').textContent      = d.total;
    document.getElementById('s-pending').textContent    = d.pending;
    document.getElementById('s-processing').textContent = d.processing;
    document.getElementById('s-completed').textContent  = d.completed;
    document.getElementById('s-failed').textContent     = d.failed;
  }

  async function loadData(page) {
    currentPage = page;
    const params = new URLSearchParams({
      page,
      per_page:   document.getElementById('f-per-page').value,
      status:     document.getElementById('f-status').value,
      date:       document.getElementById('f-date').value,
      created_by: document.getElementById('f-created-by').value,
    });

    const res  = await fetch(BASE + '/jobs/data?' + params);
    const json = await res.json();
    if (!json.success) return;

    totalPages = json.total_pages || 1;
    renderTable(json.data);
    document.getElementById('page-info').textContent =
      `第 ${page} / ${totalPages} 頁，共 ${json.total} 筆`;
    document.getElementById('btn-prev').disabled = page <= 1;
    document.getElementById('btn-next').disabled = page >= totalPages;
  }

  function changePage(delta) {
    const next = currentPage + delta;
    if (next >= 1 && next <= totalPages) loadData(next);
  }

  function resetFilters() {
    document.getElementById('f-status').value     = '';
    document.getElementById('f-date').value       = '';
    document.getElementById('f-created-by').value = '';
    loadData(1);
  }

  // ── 表格渲染 ─────────────────────────────────────────────────
  function renderTable(rows) {
    const tbody = document.getElementById('tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:40px;">無資料</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(r => {
      const pct  = r.total > 0 ? Math.round(r.processed / r.total * 100) : 0;
      const cls  = pct === 100 ? '' : pct > 0 ? ' partial' : ' zero';
      const prog = `
        <div class="progress-wrap">
          <div class="progress-bg"><div class="progress-fill${cls}" style="width:${pct}%"></div></div>
          <span class="mono">${r.processed}/${r.total}</span>
        </div>`;
      return `<tr onclick="showDetail(${r.id})">
        <td class="mono">#${r.id}</td>
        <td><span class="badge badge-${r.status}">${r.status}</span></td>
        <td class="mono">${esc(r.audit_status)}</td>
        <td>${prog}</td>
        <td class="mono">${r.total}</td>
        <td>${esc(r.created_by ?? '-')}</td>
        <td class="mono">${fmt(r.created_at)}</td>
        <td class="mono">${fmt(r.started_at)}</td>
        <td class="mono">${fmt(r.completed_at)}</td>
      </tr>`;
    }).join('');
  }

  // ── Detail Modal ──────────────────────────────────────────────
  async function showDetail(id) {
    document.getElementById('modal-title').textContent = `Job #${id} 詳細`;
    document.getElementById('modal-body').innerHTML    = '<p style="color:var(--muted)">載入中…</p>';
    document.getElementById('modal').classList.add('open');

    const res  = await fetch(BASE + '/jobs/' + id);
    const json = await res.json();
    if (!json.success) {
      document.getElementById('modal-body').innerHTML = '<p style="color:var(--red)">載入失敗</p>';
      return;
    }
    const d = json.data;
    document.getElementById('modal-body').innerHTML = `
      <dl>
        <dt>Job ID</dt>  <dd class="mono">#${d.id}</dd>
        <dt>狀態</dt>    <dd><span class="badge badge-${d.status}">${d.status}</span></dd>
        <dt>目標狀態</dt><dd class="mono">${esc(d.audit_status)}</dd>
        <dt>總筆數</dt>  <dd class="mono">${d.total}</dd>
        <dt>已處理</dt>  <dd class="mono">${d.processed}</dd>
        <dt>觸發人</dt>  <dd>${esc(d.created_by ?? '-')}</dd>
        <dt>入列時間</dt><dd class="mono">${fmt(d.created_at)}</dd>
        <dt>開始時間</dt><dd class="mono">${fmt(d.started_at)}</dd>
        <dt>完成時間</dt><dd class="mono">${fmt(d.completed_at)}</dd>
        ${d.error_message ? `<dt>錯誤訊息</dt><dd style="color:var(--red)">${esc(d.error_message)}</dd>` : ''}
      </dl>
      <div style="margin-top:20px">
        <p style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Promotion IDs</p>
        <pre class="json">${JSON.stringify(d.promotion_ids, null, 2)}</pre>
      </div>
      ${d.failed_ids && d.failed_ids.length ? `
      <div style="margin-top:12px">
        <p style="font-size:11px;color:var(--red);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Failed IDs</p>
        <pre class="json">${JSON.stringify(d.failed_ids, null, 2)}</pre>
      </div>` : ''}
    `;
  }

  function closeModal(e) {
    if (!e || e.target === document.getElementById('modal')) {
      document.getElementById('modal').classList.remove('open');
    }
  }

  // ── 工具函式 ──────────────────────────────────────────────────
  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  function fmt(s) {
    return s ? s.replace('T', ' ').slice(0, 19) : '-';
  }

  // ── 初始化 ────────────────────────────────────────────────────
  loadAll();
</script>
</body>
</html>
