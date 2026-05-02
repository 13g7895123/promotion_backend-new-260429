<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>推廣狀態追蹤器</title>
  <style>
    :root {
      --bg:      #0d1117;
      --bg2:     #161b22;
      --bg3:     #21262d;
      --border:  #30363d;
      --text:    #e6edf3;
      --muted:   #8b949e;
      --accent:  #58a6ff;
      --success: #3fb950;
      --failure: #f85149;
      --warning: #d29922;
      --purple:  #bc8cff;
      --cyan:    #39d3f5;
      --radius:  8px;
      --font:    'Segoe UI', system-ui, sans-serif;
      --mono:    'Cascadia Code', 'Fira Code', Consolas, monospace;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); font-family: var(--font); font-size: 14px; min-height: 100vh; }

    /* Header */
    header {
      background: var(--bg2); border-bottom: 1px solid var(--border);
      padding: 14px 24px; display: flex; align-items: center; gap: 12px;
      position: sticky; top: 0; z-index: 200;
    }
    header h1 { font-size: 18px; font-weight: 700; }
    header h1 span { color: var(--cyan); }
    .header-right { margin-left: auto; display: flex; gap: 8px; align-items: center; }

    /* Search bar */
    .search-bar {
      background: var(--bg2); border-bottom: 1px solid var(--border);
      padding: 12px 24px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;
    }
    .fg { display: flex; flex-direction: column; gap: 4px; }
    .fg label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .7px; }
    .fg input, .fg select {
      background: var(--bg3); border: 1px solid var(--border); color: var(--text);
      padding: 6px 10px; border-radius: var(--radius); font-size: 13px; min-width: 120px;
    }
    .fg input:focus, .fg select:focus { outline: none; border-color: var(--accent); }
    .fg input[type=text] { min-width: 220px; }
    .btn { padding: 7px 16px; border-radius: var(--radius); border: none; cursor: pointer; font-size: 13px; font-weight: 600; transition: opacity .15s; }
    .btn:hover { opacity: .82; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-cyan    { background: var(--cyan); color: #0d1117; }
    .btn-muted   { background: var(--bg3); color: var(--text); border: 1px solid var(--border); }

    /* Layout */
    .layout { display: flex; height: calc(100vh - 110px); overflow: hidden; }
    .list-pane { flex: 0 0 52%; border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
    .detail-pane { flex: 1; overflow-y: auto; background: var(--bg); }

    /* Results table */
    .results-header {
      padding: 10px 16px; background: var(--bg2); border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: var(--muted);
    }
    .results-body { flex: 1; overflow-y: auto; }
    .result-row {
      padding: 12px 16px; border-bottom: 1px solid var(--border);
      cursor: pointer; transition: background .1s; display: flex; flex-direction: column; gap: 6px;
    }
    .result-row:hover { background: var(--bg2); }
    .result-row.active { background: var(--bg3); border-left: 3px solid var(--cyan); }
    .rr-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .rr-id { font-family: var(--mono); font-size: 12px; color: var(--muted); }
    .rr-user { font-weight: 700; font-size: 14px; }
    .rr-char { color: var(--muted); font-size: 12px; }
    .rr-server { font-size: 11px; padding: 2px 8px; border-radius: 4px; background: rgba(88,166,255,.15); color: #79c0ff; }
    .rr-bottom { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .rr-time { font-size: 11px; color: var(--muted); }

    /* Badges */
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .badge-green   { background: rgba(63,185,80,.2);   color: var(--success); }
    .badge-red     { background: rgba(248,81,73,.2);   color: var(--failure); }
    .badge-yellow  { background: rgba(210,153,34,.2);  color: var(--warning); }
    .badge-blue    { background: rgba(88,166,255,.2);  color: var(--accent); }
    .badge-purple  { background: rgba(188,140,255,.2); color: var(--purple); }
    .badge-cyan    { background: rgba(57,211,245,.2);  color: var(--cyan); }
    .badge-gray    { background: rgba(139,148,158,.15);color: var(--muted); }

    /* Pagination */
    .pagination {
      padding: 10px 16px; background: var(--bg2); border-top: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
    }
    .pag-info { font-size: 12px; color: var(--muted); }
    .pag-btns { display: flex; gap: 4px; flex-wrap: wrap; }
    .pag-btn {
      padding: 4px 10px; border-radius: 4px; border: 1px solid var(--border);
      background: var(--bg3); color: var(--text); cursor: pointer; font-size: 12px;
    }
    .pag-btn:hover { border-color: var(--cyan); color: var(--cyan); }
    .pag-btn.active { background: var(--cyan); color: #0d1117; border-color: var(--cyan); }
    .pag-btn:disabled { opacity: .35; cursor: not-allowed; }

    /* Detail pane */
    .detail-empty {
      display: flex; align-items: center; justify-content: center; height: 100%;
      color: var(--muted); flex-direction: column; gap: 12px; font-size: 15px;
    }
    .detail-empty .hint { font-size: 12px; color: var(--border); }

    .detail-content { padding: 20px 24px; }

    /* Section card */
    .section { margin-bottom: 20px; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
    .section-header {
      background: var(--bg2); padding: 10px 16px; font-size: 13px; font-weight: 700;
      display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border);
      cursor: pointer; user-select: none;
    }
    .section-header .chevron { margin-left: auto; color: var(--muted); transition: transform .2s; }
    .section-header.collapsed .chevron { transform: rotate(-90deg); }
    .section-body { background: var(--bg); padding: 14px 16px; }

    /* KV grid */
    .kv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
    .kv-item { }
    .kv-key { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 3px; }
    .kv-val { font-size: 13px; word-break: break-all; }
    .mono { font-family: var(--mono); font-size: 12px; }

    /* Tables */
    .data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .data-table th {
      background: var(--bg2); padding: 7px 10px; text-align: left; color: var(--muted);
      font-size: 10px; text-transform: uppercase; letter-spacing: .6px; border-bottom: 1px solid var(--border);
    }
    .data-table td { padding: 8px 10px; border-bottom: 1px solid var(--border); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg2); }

    /* Timeline in detail */
    .lifecycle-steps { display: flex; flex-direction: column; gap: 0; }
    .lc-step-row {
      display: flex; align-items: flex-start; gap: 12px; position: relative; padding-bottom: 16px;
    }
    .lc-step-row:last-child { padding-bottom: 0; }
    .lc-dot-col { display: flex; flex-direction: column; align-items: center; width: 24px; flex-shrink: 0; }
    .lc-dot {
      width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0;
      border: 2px solid var(--border); z-index: 1;
    }
    .lc-dot.done   { background: var(--success); border-color: var(--success); }
    .lc-dot.failed { background: var(--failure); border-color: var(--failure); }
    .lc-dot.active { background: var(--warning); border-color: var(--warning); }
    .lc-dot.pending { background: var(--border); border-color: var(--muted); }
    .lc-line { flex: 1; width: 2px; background: var(--border); margin-top: 4px; min-height: 24px; }
    .lc-step-row:last-child .lc-line { display: none; }
    .lc-body { flex: 1; padding-top: 0; }
    .lc-title { font-weight: 700; font-size: 13px; margin-bottom: 4px; }
    .lc-meta  { font-size: 12px; color: var(--muted); }

    /* JSON viewer */
    .json-kv { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
    .json-row { display: flex; gap: 8px; font-size: 12px; }
    .json-k { color: var(--muted); min-width: 120px; flex-shrink: 0; }
    .json-v { color: var(--text); word-break: break-all; }

    /* Loading */
    .spinner {
      width: 22px; height: 22px; border: 2px solid var(--border); border-top-color: var(--cyan);
      border-radius: 50%; animation: spin .7s linear infinite; display: inline-block;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .state-loading { padding: 30px; text-align: center; color: var(--muted); }

    /* A link */
    a.a-link { color: var(--accent); font-family: var(--mono); font-size: 11px; text-decoration: none; word-break: break-all; }
    a.a-link:hover { text-decoration: underline; }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--muted); }
  </style>
</head>
<body>

<header>
  <h1>🔍 推廣 <span>狀態追蹤器</span></h1>
  <div class="header-right">
    <span id="result-count" style="font-size:12px;color:var(--muted)"></span>
    <button class="btn btn-muted" onclick="doSearch(1)">↻ 重新整理</button>
  </div>
</header>

<div class="search-bar">
  <div class="fg">
    <label>關鍵字（帳號 / 推廣 ID）</label>
    <input type="text" id="q" placeholder="輸入帳號或 ID…" onkeydown="if(event.key==='Enter') doSearch(1)" />
  </div>
  <div class="fg">
    <label>伺服器</label>
    <select id="f-server"><option value="">所有伺服器</option></select>
  </div>
  <div class="fg">
    <label>狀態</label>
    <select id="f-status">
      <option value="">全部</option>
      <option value="standby">審核中 (standby)</option>
      <option value="success">已通過 (success)</option>
      <option value="failed">未通過 (failed)</option>
    </select>
  </div>
  <div class="fg">
    <label>開始日期</label>
    <input type="date" id="f-from" />
  </div>
  <div class="fg">
    <label>結束日期</label>
    <input type="date" id="f-to" />
  </div>
  <div class="fg">
    <label>每頁</label>
    <select id="f-per-page">
      <option value="20" selected>20</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
  </div>
  <button class="btn btn-cyan" onclick="doSearch(1)">🔍 查詢</button>
</div>

<div class="layout">
  <!-- Left: search result list -->
  <div class="list-pane">
    <div class="results-header">
      <span id="list-info">尚未查詢</span>
    </div>
    <div class="results-body" id="result-list">
      <div class="state-loading">請輸入條件後查詢</div>
    </div>
    <div class="pagination" id="pagination" style="display:none"></div>
  </div>

  <!-- Right: detail panel -->
  <div class="detail-pane" id="detail-pane">
    <div class="detail-empty" id="detail-empty">
      <span style="font-size:32px">←</span>
      <span>點選左側推廣以查看完整生命週期</span>
      <span class="hint">涵蓋：建立 → 批次審核 → 細項審核 → 獎勵派送</span>
    </div>
    <div class="detail-content" id="detail-content" style="display:none"></div>
  </div>
</div>

<script>
/* ── BASE ─── */
const BASE = window.location.pathname.replace(/\/tracker$/, '') + '/tracker';

/* ── State ── */
let currentPage = 1;
let selectedId  = null;

/* ── Init ── */
(function init() {
  const today = new Date();
  const from  = new Date(today);
  from.setDate(from.getDate() - 29);
  document.getElementById('f-to').value   = fmtDate(today);
  document.getElementById('f-from').value = fmtDate(from);
  loadServers();
})();

async function loadServers() {
  try {
    const r = await fetch(BASE.replace('/tracker', '/server/single'), {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({})
    });
    if (!r.ok) return;
    const j = await r.json();
    const list = Array.isArray(j) ? j : (j.data ?? []);
    const sel = document.getElementById('f-server');
    list.forEach(s => {
      const o = document.createElement('option');
      o.value = s.code;
      o.textContent = `【${s.code}】${s.name}`;
      sel.appendChild(o);
    });
  } catch(e) {}
}

/* ── Search ── */
async function doSearch(page = 1) {
  currentPage = page;
  const params = buildParams(page);
  const listEl = document.getElementById('result-list');
  listEl.innerHTML = '<div class="state-loading"><div class="spinner"></div><br>搜尋中…</div>';
  document.getElementById('pagination').style.display = 'none';

  try {
    const r = await fetch(BASE + '/search?' + params);
    const j = await r.json();
    if (!j.success) { listEl.innerHTML = '<div class="state-loading">查詢失敗</div>'; return; }
    renderList(j);
  } catch(e) {
    listEl.innerHTML = '<div class="state-loading">網路錯誤</div>';
    console.error(e);
  }
}

function buildParams(page) {
  const p = new URLSearchParams();
  const q = document.getElementById('q').value.trim();
  if (q) p.append('q', q);
  const sv = document.getElementById('f-server').value;
  if (sv) p.append('server', sv);
  const st = document.getElementById('f-status').value;
  if (st) p.append('status', st);
  const from = document.getElementById('f-from').value;
  const to   = document.getElementById('f-to').value;
  if (from) p.append('date_from', from);
  if (to)   p.append('date_to',   to);
  p.append('page',     page);
  p.append('per_page', document.getElementById('f-per-page').value);
  return p;
}

function renderList(j) {
  const { total, page, per_page, total_pages, data } = j;
  const from = total === 0 ? 0 : (page - 1) * per_page + 1;
  const to   = Math.min(page * per_page, total);

  document.getElementById('list-info').textContent = `共 ${total.toLocaleString()} 筆，顯示 ${from}–${to}`;
  document.getElementById('result-count').textContent = `共 ${total.toLocaleString()} 筆`;

  const listEl = document.getElementById('result-list');
  if (!data || data.length === 0) {
    listEl.innerHTML = '<div class="state-loading">無符合條件的推廣</div>';
    return;
  }

  listEl.innerHTML = data.map(row => buildRow(row)).join('');

  // Pagination
  const pagEl = document.getElementById('pagination');
  pagEl.style.display = '';
  let html = `<span class="pag-info">${from}–${to} / ${total.toLocaleString()}</span><div class="pag-btns">`;
  html += `<button class="pag-btn" onclick="doSearch(${page-1})" ${page<=1?'disabled':''}>‹</button>`;
  const start = Math.max(1, page - 2), end = Math.min(total_pages, page + 2);
  if (start > 1) html += `<button class="pag-btn" onclick="doSearch(1)">1</button>${start>2?'<span style="padding:0 4px;color:var(--muted)">…</span>':''}`;
  for (let i = start; i <= end; i++) html += `<button class="pag-btn ${i===page?'active':''}" onclick="doSearch(${i})">${i}</button>`;
  if (end < total_pages) html += `${end<total_pages-1?'<span style="padding:0 4px;color:var(--muted)">…</span>':''}<button class="pag-btn" onclick="doSearch(${total_pages})">${total_pages}</button>`;
  html += `<button class="pag-btn" onclick="doSearch(${page+1})" ${page>=total_pages?'disabled':''}>›</button></div>`;
  pagEl.innerHTML = html;
}

function buildRow(row) {
  const statusBadge = {
    standby: '<span class="badge badge-yellow">審核中</span>',
    success: '<span class="badge badge-green">已通過</span>',
    failed:  '<span class="badge badge-red">未通過</span>',
  }[row.status] || row.status;

  const jobBadge = !row.latest_job ? '' : {
    pending:    '<span class="badge badge-yellow">Job:等待</span>',
    processing: '<span class="badge badge-blue">Job:執行中</span>',
    completed:  '<span class="badge badge-green">Job:完成</span>',
    failed:     '<span class="badge badge-red">Job:失敗</span>',
  }[row.latest_job.job_status] || '';

  const rewardBadge = row.has_reward ? '<span class="badge badge-purple">已派獎</span>' : '';

  return `
    <div class="result-row ${selectedId == row.promotion_id ? 'active' : ''}"
         id="row-${row.promotion_id}"
         onclick="loadDetail(${row.promotion_id})">
      <div class="rr-top">
        <span class="rr-id">#${row.promotion_id}</span>
        <span class="rr-user">${esc(row.username || '—')}</span>
        ${row.character_name ? `<span class="rr-char">/ ${esc(row.character_name)}</span>` : ''}
        <span class="rr-server">${esc(row.server_name || row.server)}</span>
        <span style="margin-left:auto;display:flex;gap:4px">
          ${statusBadge} ${jobBadge} ${rewardBadge}
        </span>
      </div>
      <div class="rr-bottom">
        <span class="rr-time">建立：${fmtDT(row.created_at)}</span>
        <span class="rr-time">更新：${fmtDT(row.updated_at)}</span>
        <span class="badge badge-blue">細項 ${row.items_total}</span>
        <span class="badge badge-green">通過 ${row.items_success}</span>
        ${row.items_failed > 0 ? `<span class="badge badge-red">失敗 ${row.items_failed}</span>` : ''}
        ${row.items_standby > 0 ? `<span class="badge badge-yellow">待審 ${row.items_standby}</span>` : ''}
      </div>
    </div>`;
}

/* ── Detail ── */
async function loadDetail(id) {
  selectedId = id;
  document.querySelectorAll('.result-row').forEach(el => el.classList.remove('active'));
  const rowEl = document.getElementById('row-' + id);
  if (rowEl) rowEl.classList.add('active');

  const emptyEl   = document.getElementById('detail-empty');
  const contentEl = document.getElementById('detail-content');
  emptyEl.style.display   = 'none';
  contentEl.style.display = '';
  contentEl.innerHTML = '<div class="state-loading"><div class="spinner"></div><br>載入中…</div>';

  try {
    const r = await fetch(BASE + '/detail/' + id);
    const j = await r.json();
    if (!j.success) { contentEl.innerHTML = '<div class="state-loading">載入失敗</div>'; return; }
    contentEl.innerHTML = buildDetail(j.data);
  } catch(e) {
    contentEl.innerHTML = '<div class="state-loading">網路錯誤</div>';
    console.error(e);
  }
}

function buildDetail(d) {
  const p = d.promotion;
  const statusMap = { standby: '審核中', success: '已通過', failed: '未通過' };
  const statusBadgeMap = {
    standby: '<span class="badge badge-yellow">審核中</span>',
    success: '<span class="badge badge-green">已通過</span>',
    failed:  '<span class="badge badge-red">未通過</span>',
  };

  // ── 主資料 ──
  const mainHtml = `
    <div class="kv-grid">
      <div class="kv-item"><div class="kv-key">推廣 ID</div><div class="kv-val mono">#${p.promotion_id}</div></div>
      <div class="kv-item"><div class="kv-key">狀態</div><div class="kv-val">${statusBadgeMap[p.status] || p.status}</div></div>
      <div class="kv-item"><div class="kv-key">玩家帳號</div><div class="kv-val">${esc(p.username || '—')}</div></div>
      <div class="kv-item"><div class="kv-key">角色名稱</div><div class="kv-val">${esc(p.character_name || '—')}</div></div>
      <div class="kv-item"><div class="kv-key">伺服器</div><div class="kv-val">${esc(p.server_name || '')} <span class="mono">${esc(p.server)}</span></div></div>
      <div class="kv-item"><div class="kv-key">審核週期</div><div class="kv-val">${esc(p.cycle || '—')}</div></div>
      <div class="kv-item"><div class="kv-key">達標數量</div><div class="kv-val">${p.limit_number ?? '—'}</div></div>
      <div class="kv-item"><div class="kv-key">建立時間</div><div class="kv-val mono">${fmtDT(p.created_at)}</div></div>
      <div class="kv-item"><div class="kv-key">最後更新</div><div class="kv-val mono">${fmtDT(p.updated_at)}</div></div>
    </div>`;

  // ── 生命週期時間軸 ──
  const hasAudit   = d.audit_jobs && d.audit_jobs.length > 0;
  const hasReward  = d.rewards    && d.rewards.length > 0;
  const latestJob  = hasAudit ? d.audit_jobs[0] : null;
  const jobStatusMap = {
    pending:    ['pending', '等待執行'],
    processing: ['active',  '執行中'],
    completed:  ['done',    '完成'],
    failed:     ['failed',  '失敗'],
  };

  const lcSteps = [
    { dotCls: 'done', title: '推廣建立', meta: fmtDT(p.created_at) },
    {
      dotCls: hasAudit ? (latestJob.status === 'failed' ? 'failed' : 'done') : 'pending',
      title: '批次審核入列',
      meta: hasAudit ? `Job #${latestJob.id}，${fmtDT(latestJob.created_at)}` : '尚未入列',
    },
    {
      dotCls: hasAudit && latestJob.started_at ? (latestJob.status === 'failed' ? 'failed' : 'done') : 'pending',
      title: '排程開始執行',
      meta: hasAudit && latestJob.started_at ? `${fmtDT(latestJob.started_at)}` : '尚未執行',
    },
    {
      dotCls: p.status === 'success' ? 'done' : p.status === 'failed' ? 'failed' : 'pending',
      title: `細項審核完成`,
      meta: p.status !== 'standby' ? `結果：${statusMap[p.status]}，${fmtDT(p.updated_at)}` : '等待審核',
    },
    {
      dotCls: hasReward ? 'done' : (p.status === 'success' ? 'active' : 'pending'),
      title: '獎勵派送',
      meta: hasReward ? `共 ${d.rewards.length} 筆，${fmtDT(d.rewards[0].created_at)}` : (p.status === 'success' ? '未找到獎勵記錄（可能待補發）' : '等待審核通過'),
    },
  ];

  const lcHtml = lcSteps.map((step, i) => `
    <div class="lc-step-row">
      <div class="lc-dot-col">
        <div class="lc-dot ${step.dotCls}"></div>
        ${i < lcSteps.length - 1 ? '<div class="lc-line"></div>' : ''}
      </div>
      <div class="lc-body">
        <div class="lc-title">${step.title}</div>
        <div class="lc-meta">${step.meta}</div>
      </div>
    </div>`).join('');

  // ── 細項審核 ──
  const itemsHtml = !d.items || d.items.length === 0 ? '<p style="color:var(--muted)">無細項資料</p>' : `
    <table class="data-table">
      <thead><tr><th>ID</th><th>類型</th><th>內容</th><th>狀態</th><th>更新時間</th></tr></thead>
      <tbody>${d.items.map(item => {
        const sb = { standby: '<span class="badge badge-yellow">待審</span>', success: '<span class="badge badge-green">通過</span>', failed: '<span class="badge badge-red">失敗</span>' }[item.status] || item.status;
        const content = item.type === 'text'
          ? `<a href="${esc(item.content)}" target="_blank" class="a-link">${esc(item.content.length > 60 ? item.content.substring(0,60)+'…' : item.content)}</a>`
          : `<span style="color:var(--muted)">圖片 #${esc(item.content)}</span>`;
        return `<tr><td class="mono">${item.id}</td><td>${item.type === 'image' ? '🖼 圖片' : '🔗 連結'}</td><td>${content}</td><td>${sb}</td><td class="mono">${fmtDT(item.updated_at)}</td></tr>`;
      }).join('')}</tbody>
    </table>`;

  // ── Batch Audit Jobs ──
  const jobsHtml = !d.audit_jobs || d.audit_jobs.length === 0 ? '<p style="color:var(--muted)">無相關批次審核記錄</p>' : d.audit_jobs.map(job => {
    const jsBadge = {
      pending:    '<span class="badge badge-yellow">等待</span>',
      processing: '<span class="badge badge-blue">執行中</span>',
      completed:  '<span class="badge badge-green">完成</span>',
      failed:     '<span class="badge badge-red">失敗</span>',
    }[job.status] || job.status;
    const errHtml = job.error_message ? `<div style="color:var(--failure);font-size:12px;margin-top:4px">⚠ ${esc(job.error_message)}</div>` : '';
    const failedIdsHtml = job.failed_ids && job.failed_ids.length > 0
      ? `<div style="font-size:11px;color:var(--failure);margin-top:4px">失敗 IDs: ${job.failed_ids.join(', ')}</div>` : '';
    return `
      <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <span class="mono" style="color:var(--warning)">Job #${job.id}</span>
          ${jsBadge}
          <span class="badge badge-gray">目標狀態：${esc(job.audit_status)}</span>
          <span style="font-size:12px;color:var(--muted)">共 ${job.total} 筆，已處理 ${job.processed} 筆</span>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">
          建立：${fmtDT(job.created_at)} ｜ 開始：${fmtDT(job.started_at)} ｜ 完成：${fmtDT(job.completed_at)}
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px">
          包含推廣 IDs：${job.promotion_ids.join(', ')}
        </div>
        ${errHtml}${failedIdsHtml}
      </div>`;
  }).join('');

  // ── Rewards ──
  const rewardsHtml = !d.rewards || d.rewards.length === 0 ? '<p style="color:var(--muted)">無獎勵記錄</p>' : d.rewards.map(r => {
    const kvs = r.reward_decoded ? Object.entries(r.reward_decoded).map(([k, v]) => `<div class="json-row"><span class="json-k">${esc(k)}</span><span class="json-v">${esc(v)}</span></div>`).join('') : '';
    return `
      <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
          <span class="mono" style="color:var(--purple)">Reward #${r.id}</span>
          <span class="badge badge-purple">派發成功</span>
          <span style="font-size:12px;color:var(--muted)">${fmtDT(r.created_at)}</span>
        </div>
        <div class="json-kv">${kvs}</div>
      </div>`;
  }).join('');

  // ── Reward Logs ──
  const rewardLogsHtml = !d.reward_logs || d.reward_logs.length === 0 ? '<p style="color:var(--muted)">無 reward_log 記錄</p>' : d.reward_logs.map(rl => {
    const insertKvs = rl.insert_data_decoded ? Object.entries(rl.insert_data_decoded).map(([k, v]) => `<div class="json-row"><span class="json-k">${esc(k)}</span><span class="json-v">${esc(v)}</span></div>`).join('') : '';
    return `
      <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
          <span class="mono" style="color:var(--cyan)">Log #${rl.id}</span>
          <span class="badge badge-cyan">伺服器：${esc(rl.server_code)}</span>
          <span style="font-size:12px;color:var(--muted)">${fmtDT(rl.create_at)}</span>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">寫入遊戲資料庫：</div>
        <div class="json-kv">${insertKvs}</div>
      </div>`;
  }).join('');

  // ── Reissuance ──
  const reissuanceHtml = !d.reissuance || d.reissuance.length === 0 ? '<p style="color:var(--muted)">無補發記錄</p>' : d.reissuance.map(re => {
    const kvs = re.reward_decoded ? Object.entries(re.reward_decoded).map(([k, v]) => `<div class="json-row"><span class="json-k">${esc(k)}</span><span class="json-v">${esc(v)}</span></div>`).join('') : '';
    return `
      <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
          <span class="mono" style="color:var(--warning)">補發 #${re.id}</span>
          <span class="badge badge-yellow">${fmtDT(re.created_at)}</span>
        </div>
        <div class="json-kv">${kvs}</div>
      </div>`;
  }).join('');

  return `
    <!-- 主資料 -->
    <div class="section">
      <div class="section-header" onclick="toggleSection(this)">
        📋 推廣主資料
        <span style="margin-left:8px">${statusBadgeMap[p.status] || p.status}</span>
        <span class="chevron">▾</span>
      </div>
      <div class="section-body">${mainHtml}</div>
    </div>

    <!-- 生命週期時間軸 -->
    <div class="section">
      <div class="section-header" onclick="toggleSection(this)">
        ⏱ 生命週期時間軸
        <span class="chevron">▾</span>
      </div>
      <div class="section-body">
        <div class="lifecycle-steps">${lcHtml}</div>
      </div>
    </div>

    <!-- 細項審核 -->
    <div class="section">
      <div class="section-header" onclick="toggleSection(this)">
        📂 推廣細項（promotion_items）
        <span class="badge badge-gray" style="margin-left:8px">${d.items ? d.items.length : 0} 筆</span>
        <span class="chevron">▾</span>
      </div>
      <div class="section-body">${itemsHtml}</div>
    </div>

    <!-- Batch Audit Jobs -->
    <div class="section">
      <div class="section-header" onclick="toggleSection(this)">
        ⚙ 批次審核佇列（batch_audit_jobs）
        <span class="badge badge-gray" style="margin-left:8px">${d.audit_jobs ? d.audit_jobs.length : 0} 筆</span>
        <span class="chevron">▾</span>
      </div>
      <div class="section-body">${jobsHtml}</div>
    </div>

    <!-- Rewards -->
    <div class="section">
      <div class="section-header" onclick="toggleSection(this)">
        🏆 獎勵發送記錄（reward）
        <span class="badge badge-gray" style="margin-left:8px">${d.rewards ? d.rewards.length : 0} 筆</span>
        <span class="chevron">▾</span>
      </div>
      <div class="section-body">${rewardsHtml}</div>
    </div>

    <!-- Reward Logs -->
    <div class="section">
      <div class="section-header" onclick="toggleSection(this)">
        📝 派獎詳細記錄（reward_log）
        <span class="badge badge-gray" style="margin-left:8px">${d.reward_logs ? d.reward_logs.length : 0} 筆</span>
        <span class="chevron">▾</span>
      </div>
      <div class="section-body">${rewardLogsHtml}</div>
    </div>

    <!-- Reissuance -->
    <div class="section">
      <div class="section-header" onclick="toggleSection(this)">
        🔄 補發記錄（reissuance_reward）
        <span class="badge badge-gray" style="margin-left:8px">${d.reissuance ? d.reissuance.length : 0} 筆</span>
        <span class="chevron">▾</span>
      </div>
      <div class="section-body">${reissuanceHtml}</div>
    </div>`;
}

/* ── Helpers ── */
function toggleSection(header) {
  const body = header.nextElementSibling;
  const hidden = body.style.display === 'none';
  body.style.display = hidden ? '' : 'none';
  header.classList.toggle('collapsed', !hidden);
}

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtDate(d) { return d.toISOString().slice(0, 10); }

function fmtDT(dt) {
  if (!dt) return '—';
  return String(dt).replace('T',' ').slice(0, 16);
}
</script>
</body>
</html>
