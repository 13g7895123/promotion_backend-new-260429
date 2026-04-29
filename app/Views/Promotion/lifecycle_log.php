<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>推廣生命週期 Log</title>
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
      --radius:  8px;
      --font:    'Segoe UI', system-ui, sans-serif;
      --mono:    'Cascadia Code', 'Fira Code', 'Consolas', monospace;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg);
      color: var(--text);
      font-family: var(--font);
      font-size: 14px;
      min-height: 100vh;
    }

    /* ── Header ─────────────────────────── */
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
    header h1 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
    header h1 span { color: var(--purple); }
    .header-right { margin-left: auto; display: flex; gap: 10px; align-items: center; }

    /* ── Summary cards ───────────────────── */
    #summary-bar {
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      padding: 12px 24px;
      display: flex;
      gap: 28px;
      flex-wrap: wrap;
      align-items: center;
    }
    .stat-item { display: flex; flex-direction: column; gap: 2px; }
    .stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .8px; }
    .stat-value { font-size: 22px; font-weight: 700; }
    .c-blue    { color: var(--accent); }
    .c-green   { color: var(--success); }
    .c-red     { color: var(--failure); }
    .c-yellow  { color: var(--warning); }
    .c-purple  { color: var(--purple); }
    .c-muted   { color: var(--muted); }

    /* ── Filters ─────────────────────────── */
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
    .filter-group label { font-size: 11px; color: var(--muted); text-transform: uppercase; }
    .filters input, .filters select {
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--text);
      padding: 6px 10px;
      border-radius: var(--radius);
      font-size: 13px;
      min-width: 130px;
    }
    .filters input:focus, .filters select:focus { outline: none; border-color: var(--accent); }
    .btn {
      padding: 7px 16px;
      border-radius: var(--radius);
      border: none;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: opacity .15s;
    }
    .btn:hover { opacity: .82; }
    .btn-primary   { background: var(--accent);   color: #fff; }
    .btn-secondary { background: var(--bg3); color: var(--text); border: 1px solid var(--border); }
    .btn-purple    { background: var(--purple);   color: #0d1117; }
    .quick-btns    { display: flex; gap: 6px; }
    .quick-btn {
      padding: 6px 12px;
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--muted);
      border-radius: var(--radius);
      cursor: pointer;
      font-size: 12px;
    }
    .quick-btn:hover { border-color: var(--accent); color: var(--accent); }

    /* ── Tabs ────────────────────────────── */
    #tabs {
      padding: 0 24px;
      display: flex;
      gap: 2px;
      border-bottom: 1px solid var(--border);
      background: var(--bg2);
    }
    .tab-btn {
      padding: 10px 18px;
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      color: var(--muted);
      cursor: pointer;
      font-size: 13px;
      transition: color .15s;
    }
    .tab-btn.active { border-bottom-color: var(--purple); color: var(--text); font-weight: 600; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ── Timeline ────────────────────────── */
    #timeline-wrap { padding: 20px 24px; }

    .day-block {
      margin-bottom: 20px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .day-header {
      background: var(--bg2);
      padding: 10px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      border-bottom: 1px solid var(--border);
      user-select: none;
    }
    .day-header:hover { background: var(--bg3); }
    .day-date          { font-size: 15px; font-weight: 700; color: var(--text); }
    .day-chevron       { margin-left: auto; color: var(--muted); font-size: 16px; transition: transform .2s; }
    .day-header.collapsed .day-chevron { transform: rotate(-90deg); }

    .promo-list { background: var(--bg); }
    .promo-row {
      padding: 14px 16px;
      border-bottom: 1px solid var(--border);
      display: grid;
      grid-template-columns: 52px 1fr;
      gap: 14px;
    }
    .promo-row:last-child { border-bottom: none; }
    .promo-row:hover { background: var(--bg2); }

    /* stage icon */
    .stage-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .stage-pending  { background: rgba(210,153,34,.18); }
    .stage-failed   { background: rgba(248,81,73,.18);  }
    .stage-audited  { background: rgba(88,166,255,.18); }
    .stage-rewarded { background: rgba(188,140,255,.18); }

    .promo-main { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
    .promo-title {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
    }
    .promo-title .username { font-weight: 700; font-size: 15px; }
    .promo-title .character { color: var(--muted); font-size: 13px; }
    .promo-id { font-size: 11px; color: var(--muted); margin-left: auto; font-family: var(--mono); }

    /* lifecycle progress bar */
    .lifecycle-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 4px;
      font-size: 12px;
      color: var(--muted);
    }
    .lc-step { display: flex; align-items: center; gap: 4px; }
    .lc-step .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .dot-blue   { background: var(--accent); }
    .dot-orange { background: var(--warning); }
    .dot-purple { background: var(--purple); }
    .lc-arrow { color: var(--border); margin: 0 2px; }
    .lc-duration { color: var(--warning); font-size: 11px; }

    /* item stats */
    .item-stats { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; font-size: 12px; }
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
    }
    .badge-green   { background: rgba(63,185,80,.2);  color: var(--success); }
    .badge-red     { background: rgba(248,81,73,.2);  color: var(--failure); }
    .badge-yellow  { background: rgba(210,153,34,.2); color: var(--warning); }
    .badge-blue    { background: rgba(88,166,255,.2); color: var(--accent); }
    .badge-purple  { background: rgba(188,140,255,.2); color: var(--purple); }
    .badge-gray    { background: rgba(139,148,158,.15); color: var(--muted); }
    .badge-server  { background: rgba(88,166,255,.15); color: #79c0ff; font-size: 11px; }

    /* expand items toggle */
    .toggle-items-btn {
      background: none;
      border: none;
      color: var(--purple);
      cursor: pointer;
      font-size: 12px;
      padding: 0;
    }
    .toggle-items-btn:hover { text-decoration: underline; }

    /* items detail table */
    .items-detail { margin-top: 8px; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
    .items-detail table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .items-detail thead th {
      background: var(--bg2);
      color: var(--muted);
      padding: 7px 10px;
      text-align: left;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .6px;
      border-bottom: 1px solid var(--border);
    }
    .items-detail tbody tr { border-bottom: 1px solid var(--border); }
    .items-detail tbody tr:last-child { border-bottom: none; }
    .items-detail tbody td { padding: 7px 10px; color: var(--text); }
    .items-detail tbody tr:hover { background: var(--bg2); }
    .a-link { color: var(--accent); text-decoration: none; font-family: var(--mono); font-size: 11px; }
    .a-link:hover { text-decoration: underline; }

    /* reward detail */
    .reward-detail { margin-top: 6px; font-size: 12px; }
    .reward-label { color: var(--muted); margin-right: 6px; }
    .reward-tags  { display: inline-flex; flex-wrap: wrap; gap: 4px; }
    .reward-tag   { background: rgba(188,140,255,.15); color: var(--purple); padding: 2px 8px; border-radius: 4px; font-size: 11px; }

    /* ── Pagination ──────────────────────── */
    .pagination {
      padding: 16px 24px;
      display: flex;
      align-items: center;
      gap: 8px;
      justify-content: space-between;
    }
    .pagination-info { color: var(--muted); font-size: 13px; }
    .pagination-btns { display: flex; gap: 6px; flex-wrap: wrap; }
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
    .page-btn.active { background: var(--purple); color: #0d1117; border-color: var(--purple); }
    .page-btn:disabled { opacity: .35; cursor: not-allowed; }

    /* ── Audit Events ────────────────────── */
    #audit-wrap { padding: 20px 24px; }
    .batch-block { margin-bottom: 16px; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
    .batch-header {
      background: var(--bg2);
      padding: 10px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      border-bottom: 1px solid var(--border);
      user-select: none;
    }
    .batch-header:hover { background: var(--bg3); }
    .batch-time { font-weight: 700; font-family: var(--mono); font-size: 14px; color: var(--warning); }
    .batch-body { background: var(--bg); }
    .batch-row {
      padding: 10px 16px;
      border-bottom: 1px solid var(--border);
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      font-size: 13px;
    }
    .batch-row:last-child { border-bottom: none; }
    .batch-row:hover { background: var(--bg2); }

    /* ── Loading / Empty ─────────────────── */
    .state-loading, .state-empty {
      text-align: center;
      padding: 60px 24px;
      color: var(--muted);
    }
    .spinner {
      width: 30px; height: 30px;
      border: 3px solid var(--border);
      border-top-color: var(--purple);
      border-radius: 50%;
      animation: spin .7s linear infinite;
      margin: 0 auto 12px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Scrollbar ───────────────────────── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--muted); }
  </style>
</head>
<body>

<!-- Header -->
<header>
  <h1>📊 推廣 <span>生命週期 Log</span></h1>
  <div class="header-right">
    <span id="last-refresh" style="font-size:12px;color:var(--muted)"></span>
    <button class="btn btn-secondary" onclick="loadAll()">↻ 重新整理</button>
  </div>
</header>

<!-- Summary Cards -->
<div id="summary-bar">
  <div class="stat-item">
    <span class="stat-label">建立推廣</span>
    <span class="stat-value c-blue"  id="s-created">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">審核通過</span>
    <span class="stat-value c-green"  id="s-passed">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">審核失敗</span>
    <span class="stat-value c-red"   id="s-failed">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">審核中</span>
    <span class="stat-value c-yellow" id="s-pending">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">派獎筆數</span>
    <span class="stat-value c-purple" id="s-rewards">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">通過率</span>
    <span class="stat-value c-green"  id="s-passrate">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">派獎率</span>
    <span class="stat-value c-purple" id="s-rewardrate">—</span>
  </div>
  <div class="stat-item">
    <span class="stat-label">平均審核(分)</span>
    <span class="stat-value c-yellow" id="s-avgmin">—</span>
  </div>
</div>

<!-- Filters -->
<div class="filters">
  <div class="filter-group">
    <label>開始日期</label>
    <input type="date" id="f-from" />
  </div>
  <div class="filter-group">
    <label>結束日期</label>
    <input type="date" id="f-to" />
  </div>
  <div class="filter-group">
    <label>伺服器</label>
    <select id="f-server">
      <option value="">所有伺服器</option>
    </select>
  </div>
  <div class="filter-group">
    <label>每頁筆數</label>
    <select id="f-per-page">
      <option value="10" selected>10</option>
      <option value="20">20</option>
      <option value="30">30</option>
      <option value="60">60</option>
    </select>
  </div>
  <button class="btn btn-purple" onclick="loadAll()">🔍 查詢</button>
  <div class="quick-btns">
    <button class="quick-btn" onclick="setQuickRange(1)">今天</button>
    <button class="quick-btn" onclick="setQuickRange(7)">近 7 天</button>
    <button class="quick-btn" onclick="setQuickRange(30)">近 30 天</button>
  </div>
</div>

<!-- Tabs -->
<div id="tabs">
  <button class="tab-btn active" onclick="switchTab('timeline')">📅 時間軸</button>
  <button class="tab-btn" onclick="switchTab('audit')">⚖ 批次審核事件</button>
</div>

<!-- ═══ Tab: 時間軸 ═══ -->
<div id="tab-timeline" class="tab-content active">
  <div id="timeline-wrap">
    <div id="tl-loading" class="state-loading" style="display:none">
      <div class="spinner"></div>載入中…
    </div>
    <div id="tl-empty" class="state-empty" style="display:none">此區間沒有推廣資料</div>
    <div id="tl-content"></div>
  </div>
  <div class="pagination">
    <div class="pagination-info" id="tl-page-info"></div>
    <div class="pagination-btns" id="tl-page-btns"></div>
  </div>
</div>

<!-- ═══ Tab: 批次審核事件 ═══ -->
<div id="tab-audit" class="tab-content">
  <div id="audit-wrap">
    <div id="au-loading" class="state-loading" style="display:none">
      <div class="spinner"></div>載入中…
    </div>
    <div id="au-empty" class="state-empty" style="display:none">此區間沒有審核事件</div>
    <div id="au-content"></div>
  </div>
</div>

<script>
/* ─── 基礎路徑 ───────────────────────────────────────────────── */
const BASE = window.location.pathname.replace(/\/$/, '').replace(/\/lifecycle$/, '') + '/lifecycle';

/* ─── State ─────────────────────────────────────────────────── */
let currentPage = 1;
let totalPages  = 1;

/* ─── 初始化 ─────────────────────────────────────────────────── */
(function init() {
  const today  = new Date();
  const from   = new Date(today);
  from.setDate(from.getDate() - 6);
  document.getElementById('f-to').value   = fmtDate(today);
  document.getElementById('f-from').value = fmtDate(from);

  loadServers();
  loadAll();
})();

function fmtDate(d) {
  return d.toISOString().slice(0, 10);
}

/* ─── 快捷日期 ───────────────────────────────────────────────── */
function setQuickRange(days) {
  const today = new Date();
  const from  = new Date(today);
  from.setDate(from.getDate() - (days - 1));
  document.getElementById('f-to').value   = fmtDate(today);
  document.getElementById('f-from').value = fmtDate(from);
  loadAll();
}

/* ─── 載入伺服器清單（供篩選用） ──────────────────────────────── */
async function loadServers() {
  /* 直接從後端的伺服器 API 取，改用 server 端點獲取伺服器列表 */
  try {
    const r = await fetch(BASE.replace('/lifecycle', '/server/single'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({}),
    });
    if (!r.ok) return;
    const j = await r.json();
    const list = Array.isArray(j) ? j : (j.data ?? []);
    const sel = document.getElementById('f-server');
    list.forEach(s => {
      const opt = document.createElement('option');
      opt.value       = s.code;
      opt.textContent = `【${s.code}】${s.name}`;
      sel.appendChild(opt);
    });
  } catch(e) { /* 靜默失敗，不影響主功能 */ }
}

/* ─── 全部載入 ───────────────────────────────────────────────── */
async function loadAll() {
  currentPage = 1;
  await Promise.all([loadSummary(), loadTimeline(1), loadAuditEvents()]);
  document.getElementById('last-refresh').textContent = '↻ ' + new Date().toLocaleTimeString();
}

/* ─── Summary ────────────────────────────────────────────────── */
async function loadSummary() {
  const params = buildBaseParams();
  try {
    const r = await fetch(BASE + '/summary?' + params);
    const j = await r.json();
    if (!j.success) return;
    const d = j.data;
    document.getElementById('s-created').textContent    = d.total_created;
    document.getElementById('s-passed').textContent     = d.total_passed;
    document.getElementById('s-failed').textContent     = d.total_failed;
    document.getElementById('s-pending').textContent    = d.total_pending;
    document.getElementById('s-rewards').textContent    = d.total_rewards;
    document.getElementById('s-passrate').textContent   = d.pass_rate + '%';
    document.getElementById('s-rewardrate').textContent = d.reward_rate + '%';
    document.getElementById('s-avgmin').textContent     = d.avg_audit_minutes !== null ? d.avg_audit_minutes : '—';
  } catch(e) { console.error(e); }
}

/* ─── Timeline ───────────────────────────────────────────────── */
async function loadTimeline(page = 1) {
  currentPage = page;
  const loading = document.getElementById('tl-loading');
  const empty   = document.getElementById('tl-empty');
  const content = document.getElementById('tl-content');

  loading.style.display = 'block';
  content.innerHTML = '';
  empty.style.display = 'none';

  const params = buildBaseParams();
  params.append('page',     page);
  params.append('per_page', document.getElementById('f-per-page').value);

  try {
    const r = await fetch(BASE + '/data?' + params);
    const j = await r.json();
    loading.style.display = 'none';

    if (!j.success || !j.data.timeline || j.data.timeline.length === 0) {
      empty.style.display = 'block';
      renderTlPagination(0, 0, 1);
      return;
    }

    const { total, page: pg, per_page, timeline } = j.data;
    content.innerHTML = timeline.map(day => buildDayBlock(day)).join('');
    totalPages = Math.ceil(total / per_page);
    renderTlPagination(total, pg, per_page);
  } catch(e) {
    loading.style.display = 'none';
    empty.style.display   = 'block';
    console.error(e);
  }
}

/* ─── Audit Events ───────────────────────────────────────────── */
async function loadAuditEvents() {
  const loading = document.getElementById('au-loading');
  const empty   = document.getElementById('au-empty');
  const content = document.getElementById('au-content');

  loading.style.display = 'block';
  content.innerHTML = '';
  empty.style.display = 'none';

  const params = buildBaseParams();
  try {
    const r = await fetch(BASE + '/audit-events?' + params);
    const j = await r.json();
    loading.style.display = 'none';

    if (!j.success || !j.data || j.data.length === 0) {
      empty.style.display = 'block';
      return;
    }

    content.innerHTML = j.data.map(batch => buildBatchBlock(batch)).join('');
  } catch(e) {
    loading.style.display = 'none';
    empty.style.display   = 'block';
    console.error(e);
  }
}

/* ─── Build Day Block ────────────────────────────────────────── */
function buildDayBlock(day) {
  const rows = day.promotions.map(p => buildPromoRow(p)).join('');
  return `
    <div class="day-block">
      <div class="day-header" onclick="toggleBlock(this)">
        <span style="font-size:16px">📅</span>
        <span class="day-date">${esc(day.date)}</span>
        <span class="badge badge-blue">建立 ${day.created_count}</span>
        <span class="badge badge-green">通過 ${day.passed_count}</span>
        <span class="badge badge-red">失敗 ${day.failed_count}</span>
        <span class="badge badge-yellow">審核中 ${day.pending_count}</span>
        <span class="badge badge-purple">派獎 ${day.reward_count}</span>
        <span class="day-chevron">▾</span>
      </div>
      <div class="promo-list">${rows}</div>
    </div>`;
}

function buildPromoRow(p) {
  /* 階段圖示 */
  const stageMap = {
    pending:  { cls: 'stage-pending',  icon: '⏳' },
    failed:   { cls: 'stage-failed',   icon: '✕' },
    audited:  { cls: 'stage-audited',  icon: '✓' },
    rewarded: { cls: 'stage-rewarded', icon: '🏆' },
  };
  const stage = stageMap[p.lifecycle_stage] || stageMap.pending;

  const stageLabelMap = { pending: '審核中', failed: '未通過', audited: '已審核', rewarded: '已派獎' };
  const stageLabelCol = { pending: 'c-yellow', failed: 'c-red', audited: 'c-blue', rewarded: 'c-purple' };
  const stageLabel = stageLabelMap[p.lifecycle_stage] || '—';
  const stageColor = stageLabelCol[p.lifecycle_stage] || 'c-muted';

  /* 生命週期進度 */
  let lcHtml = `
    <div class="lc-step">
      <span class="dot dot-blue"></span>
      <span>建立：${esc(formatDT(p.created_at))}</span>
    </div>`;

  if (p.audited_at) {
    lcHtml += p.creation_to_audit_minutes !== null
      ? `<span class="lc-arrow">→</span><span class="lc-duration">(+${p.creation_to_audit_minutes}分)</span><span class="lc-arrow">→</span>`
      : `<span class="lc-arrow">→</span>`;
    lcHtml += `
      <div class="lc-step">
        <span class="dot dot-orange"></span>
        <span>審核：${esc(formatDT(p.audited_at))}</span>
      </div>`;
  } else {
    lcHtml += `<span class="lc-arrow">→</span><span style="color:var(--muted);font-style:italic">尚待審核</span>`;
  }

  if (p.reward) {
    lcHtml += p.audit_to_reward_minutes !== null
      ? `<span class="lc-arrow">→</span><span class="lc-duration">(+${p.audit_to_reward_minutes}分)</span><span class="lc-arrow">→</span>`
      : `<span class="lc-arrow">→</span>`;
    lcHtml += `
      <div class="lc-step">
        <span class="dot dot-purple"></span>
        <span>派獎：${esc(formatDT(p.reward.created_at))}</span>
      </div>`;
  }

  /* 細項 table */
  const itemsHtml = buildItemsTable(p.items);

  /* 獎勵 */
  let rewardHtml = '';
  if (p.reward && p.reward.reward_detail) {
    const tags = Object.entries(p.reward.reward_detail)
      .map(([k, v]) => `<span class="reward-tag">${esc(k)}：${esc(v)}</span>`)
      .join('');
    rewardHtml = `<div class="reward-detail">
      <span class="reward-label">🏆 獎勵明細：</span>
      <span class="reward-tags">${tags}</span>
    </div>`;
  }

  const pid = p.promotion_id;

  return `
    <div class="promo-row">
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div class="stage-icon ${stage.cls}">${stage.icon}</div>
        <span class="badge" style="font-size:10px" class="${stageColor}">${stageLabel}</span>
      </div>
      <div class="promo-main">
        <div class="promo-title">
          <span class="username">${esc(p.username)}</span>
          ${p.character_name ? `<span class="character">/ ${esc(p.character_name)}</span>` : ''}
          <span class="badge badge-server">${esc(p.server_name || p.server)}</span>
          <span class="promo-id">#${pid}</span>
        </div>

        <div class="lifecycle-bar">${lcHtml}</div>

        <div class="item-stats">
          <span style="color:var(--muted);font-size:12px">細項審核：</span>
          <span class="badge badge-green">通過 ${p.items_passed}</span>
          <span class="badge badge-red">失敗 ${p.items_failed}</span>
          ${p.items_pending ? `<span class="badge badge-yellow">待審 ${p.items_pending}</span>` : ''}
          <button class="toggle-items-btn" onclick="toggleItemsDetail(${pid})">▾ 展開細項</button>
        </div>

        <div id="items-detail-${pid}" style="display:none">${itemsHtml}</div>

        ${rewardHtml}
      </div>
    </div>`;
}

function buildItemsTable(items) {
  if (!items || items.length === 0) return '<p style="color:var(--muted);font-size:12px;padding:8px 0">無細項資料</p>';
  const rows = items.map(item => {
    const statusBadge = {
      standby: `<span class="badge badge-yellow">待審</span>`,
      success: `<span class="badge badge-green">通過</span>`,
      failed:  `<span class="badge badge-red">失敗</span>`,
    }[item.status] || item.status;
    const typeIcon = item.type === 'image' ? '🖼' : '🔗';
    const contentHtml = item.type === 'text'
      ? `<a href="${esc(item.content)}" target="_blank" class="a-link">${esc(item.content.length > 50 ? item.content.substring(0,50)+'…' : item.content)}</a>`
      : `<span style="color:var(--muted);font-style:italic">（圖片）</span>`;
    const auditTime = item.status !== 'standby' ? esc(formatDT(item.updated_at)) : '—';
    return `<tr>
      <td>${typeIcon} ${item.type === 'image' ? '圖片' : '連結'}</td>
      <td>${contentHtml}</td>
      <td>${statusBadge}</td>
      <td style="color:var(--muted)">${auditTime}</td>
    </tr>`;
  }).join('');
  return `
    <div class="items-detail">
      <table>
        <thead>
          <tr>
            <th>類型</th>
            <th>內容</th>
            <th>審核結果</th>
            <th>審核時間</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}

/* ─── Build Batch Block ──────────────────────────────────────── */
function buildBatchBlock(batch) {
  const rows = batch.promotions.map(p => {
    const statusBadge = {
      standby: `<span class="badge badge-yellow">審核中</span>`,
      success: `<span class="badge badge-green">已通過</span>`,
      failed:  `<span class="badge badge-red">未通過</span>`,
    }[p.promotion_status] || p.promotion_status;
    return `
      <div class="batch-row">
        <span style="font-weight:600;min-width:100px">${esc(p.username)}</span>
        ${p.character_name ? `<span style="color:var(--muted);font-size:12px">${esc(p.character_name)}</span>` : ''}
        <span class="badge badge-server">${esc(p.server_name || p.server)}</span>
        <span style="color:var(--muted);font-size:12px">建立於 ${esc(formatDT(p.promotion_created_at))}</span>
        <span style="margin-left:auto">${statusBadge}</span>
      </div>`;
  }).join('');

  return `
    <div class="batch-block">
      <div class="batch-header" onclick="toggleBlock(this)">
        <span>⚖</span>
        <span class="batch-time">${esc(batch.batch_time)}</span>
        <span class="badge badge-gray">${batch.unique_promotions} 個推廣</span>
        <span class="badge badge-green">通過 ${batch.passed}</span>
        <span class="badge badge-red">失敗 ${batch.failed}</span>
        <span style="color:var(--muted);font-size:12px;margin-left:8px">細項共 ${batch.total} 筆</span>
        <span class="day-chevron" style="margin-left:auto">▾</span>
      </div>
      <div class="batch-body">${rows}</div>
    </div>`;
}

/* ─── Pagination ─────────────────────────────────────────────── */
function renderTlPagination(total, page, perPage) {
  const totalPg = perPage > 0 ? Math.ceil(total / perPage) || 1 : 1;
  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to   = Math.min(page * perPage, total);
  document.getElementById('tl-page-info').textContent = `顯示 ${from}–${to} / 共 ${total.toLocaleString()} 筆`;

  const container = document.getElementById('tl-page-btns');
  let html = `<button class="page-btn" onclick="loadTimeline(${page-1})" ${page<=1?'disabled':''}>‹ 上一頁</button>`;
  const start = Math.max(1, page - 2);
  const end   = Math.min(totalPg, page + 2);
  if (start > 1) html += `<button class="page-btn" onclick="loadTimeline(1)">1</button>${start>2?'<span style="padding:0 4px;color:var(--muted)">…</span>':''}`;
  for (let i = start; i <= end; i++) {
    html += `<button class="page-btn ${i===page?'active':''}" onclick="loadTimeline(${i})">${i}</button>`;
  }
  if (end < totalPg) html += `${end<totalPg-1?'<span style="padding:0 4px;color:var(--muted)">…</span>':''}<button class="page-btn" onclick="loadTimeline(${totalPg})">${totalPg}</button>`;
  html += `<button class="page-btn" onclick="loadTimeline(${page+1})" ${page>=totalPg?'disabled':''}>下一頁 ›</button>`;
  container.innerHTML = html;
}

/* ─── Helpers ────────────────────────────────────────────────── */
function buildBaseParams() {
  const p = new URLSearchParams();
  p.append('date_from', document.getElementById('f-from').value);
  p.append('date_to',   document.getElementById('f-to').value);
  const srv = document.getElementById('f-server').value;
  if (srv) p.append('server', srv);
  return p;
}

function esc(s) {
  return String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatDT(dt) {
  if (!dt) return '—';
  return String(dt).replace('T', ' ').slice(0, 16);
}

function toggleBlock(header) {
  const body = header.nextElementSibling;
  const isHidden = body.style.display === 'none';
  body.style.display = isHidden ? '' : 'none';
  header.classList.toggle('collapsed', !isHidden);
}

function toggleItemsDetail(pid) {
  const el  = document.getElementById('items-detail-' + pid);
  const btn = el.previousElementSibling.querySelector('.toggle-items-btn');
  const isHidden = el.style.display === 'none';
  el.style.display = isHidden ? '' : 'none';
  if (btn) btn.textContent = isHidden ? '▴ 收合細項' : '▾ 展開細項';
}

function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach((b, i) => {
    b.classList.toggle('active', ['timeline','audit'][i] === name);
  });
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
}
</script>
</body>
</html>
