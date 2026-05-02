<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>推廣後台管理系統</title>
  <style>
    :root {
      --bg: #0f1117;
      --surface: #1a1d27;
      --surface2: #20243a;
      --border: #2a2d3e;
      --text: #e2e8f0;
      --muted: #8b949e;
      --primary: #6c8ef5;
      --primary-dark: #5070d9;
      --green: #34d399;
      --yellow: #fbbf24;
      --red: #f87171;
      --blue: #60a5fa;
      --sidebar-w: 220px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { height: 100vh; display: flex; flex-direction: column; background: var(--bg); color: var(--text); font-family: "Segoe UI", Arial, sans-serif; font-size: 14px; overflow: hidden; }

    /* ── 頂部導航欄 ── */
    .topbar {
      display: flex; align-items: center; gap: 14px;
      padding: 0 20px; height: 52px; flex-shrink: 0;
      background: var(--surface); border-bottom: 1px solid var(--border);
      z-index: 10;
    }
    .topbar .logo { font-size: 16px; font-weight: 700; color: var(--primary); letter-spacing: .02em; }
    .topbar .logo span { color: var(--muted); font-weight: 400; font-size: 13px; margin-left: 8px; }
    .topbar .spacer { flex: 1; }
    .topbar .env-badge {
      padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;
      background: rgba(248, 113, 113, .15); color: var(--red); border: 1px solid rgba(248,113,113,.3);
    }

    /* ── 主體（側邊欄 + 內容區） ── */
    .body-wrap { display: flex; flex: 1; overflow: hidden; }

    /* ── 側邊欄 ── */
    .sidebar {
      width: var(--sidebar-w); flex-shrink: 0;
      background: var(--surface); border-right: 1px solid var(--border);
      display: flex; flex-direction: column; overflow-y: auto;
      padding-bottom: 16px;
    }
    .sidebar-section { padding: 16px 12px 4px; }
    .sidebar-section-title {
      font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: var(--muted); padding: 0 6px; margin-bottom: 4px;
    }
    .nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 10px; border-radius: 6px; cursor: pointer;
      color: var(--muted); font-size: 13px; transition: background .15s, color .15s;
      user-select: none; border: 0; background: none; width: 100%; text-align: left;
    }
    .nav-item:hover { background: rgba(108, 142, 245, .1); color: var(--text); }
    .nav-item.active { background: rgba(108, 142, 245, .18); color: var(--primary); font-weight: 600; }
    .nav-item .icon { font-size: 16px; flex-shrink: 0; }
    .nav-item .label { flex: 1; }
    .nav-item .badge {
      font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 999px;
      background: rgba(108,142,245,.25); color: var(--primary);
    }
    .sidebar-divider { height: 1px; background: var(--border); margin: 10px 12px; }

    /* ── 內容 iframe ── */
    .content-area { flex: 1; overflow: hidden; display: flex; flex-direction: column; }
    .content-area iframe {
      flex: 1; width: 100%; height: 100%; border: 0;
      background: var(--bg);
    }
    .content-placeholder {
      flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
      color: var(--muted);
    }
    .content-placeholder .ph-icon { font-size: 56px; opacity: .25; }
    .content-placeholder h2 { font-size: 18px; color: var(--text); opacity: .4; font-weight: 400; }

    /* ── 載入指示 ── */
    .loading-bar {
      height: 3px; width: 100%; background: var(--border); flex-shrink: 0; overflow: hidden;
    }
    .loading-bar-inner {
      height: 100%; width: 0; background: var(--primary);
      transition: width .1s; border-radius: 2px;
    }
    .loading-bar.active .loading-bar-inner { animation: loading 1.4s ease-in-out infinite; }
    @keyframes loading {
      0%   { width: 0;   margin-left: 0; }
      50%  { width: 70%; margin-left: 15%; }
      100% { width: 0;   margin-left: 100%; }
    }
  </style>
</head>
<body>

<!-- 頂部導航欄 -->
<header class="topbar">
  <div class="logo">推廣後台管理 <span>PCGAME Promotion Admin</span></div>
  <div class="spacer"></div>
  <div class="env-badge">PRODUCTION</div>
</header>

<div class="body-wrap">

  <!-- 側邊欄 -->
  <nav class="sidebar" id="sidebar">

    <div class="sidebar-section">
      <div class="sidebar-section-title">監控</div>
      <button class="nav-item" data-page="tracker" onclick="navigate('tracker', '推廣狀態追蹤器', this)">
        <span class="icon">🔍</span>
        <span class="label">推廣狀態追蹤器</span>
      </button>
      <button class="nav-item" data-page="lifecycle" onclick="navigate('lifecycle', '推廣生命週期紀錄', this)">
        <span class="icon">📋</span>
        <span class="label">生命週期紀錄</span>
      </button>
      <button class="nav-item" data-page="batch-audit/jobs" onclick="navigate('batch-audit/jobs', '批次審核排程', this)">
        <span class="icon">⚙️</span>
        <span class="label">批次審核排程</span>
        <span class="badge">排程</span>
      </button>
    </div>

    <div class="sidebar-divider"></div>

    <div class="sidebar-section">
      <div class="sidebar-section-title">紀錄</div>
      <button class="nav-item" data-page="operations" onclick="navigate('operations', '操作紀錄', this)">
        <span class="icon">🗂️</span>
        <span class="label">操作紀錄</span>
      </button>
      <button class="nav-item" data-page="logs" onclick="navigate('logs', 'API 日誌', this)">
        <span class="icon">📝</span>
        <span class="label">API 日誌</span>
      </button>
    </div>

  </nav>

  <!-- 主內容 -->
  <div class="content-area" id="content-area">
    <div class="loading-bar" id="loading-bar"><div class="loading-bar-inner" id="loading-inner"></div></div>
    <div class="content-placeholder" id="placeholder">
      <div class="ph-icon">🏠</div>
      <h2>請從左側選單選擇功能</h2>
    </div>
    <iframe id="main-frame" style="display:none" onload="onFrameLoad()"></iframe>
  </div>
</div>

<script>
const BASE = window.location.pathname.replace(/\/admin.*$/, '');

function navigate(page, title, btn) {
  // 更新 active 樣式
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  if (btn) btn.classList.add('active');

  // 更新瀏覽器標題
  document.title = title + ' — 推廣後台管理系統';

  // 顯示載入中
  const frame = document.getElementById('main-frame');
  const placeholder = document.getElementById('placeholder');
  const loadingBar = document.getElementById('loading-bar');

  placeholder.style.display = 'none';
  frame.style.display = 'none';
  loadingBar.classList.add('active');

  // 載入 iframe
  const url = BASE + '/' + page;
  frame.src = url;

  // 更新 hash 以支援重整保留頁面
  history.replaceState(null, '', '#' + page);
}

function onFrameLoad() {
  const frame = document.getElementById('main-frame');
  document.getElementById('loading-bar').classList.remove('active');
  frame.style.display = 'block';
}

// 重整時恢復上次選擇的頁面
window.addEventListener('DOMContentLoaded', () => {
  const hash = location.hash.replace('#', '');
  if (hash) {
    const btn = document.querySelector(`[data-page="${hash}"]`);
    if (btn) {
      const label = btn.querySelector('.label')?.textContent || hash;
      navigate(hash, label, btn);
      return;
    }
  }
  // 預設打開推廣追蹤器
  const defaultBtn = document.querySelector('[data-page="tracker"]');
  navigate('tracker', '推廣狀態追蹤器', defaultBtn);
});
</script>

</body>
</html>
