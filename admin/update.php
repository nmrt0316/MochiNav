<?php ob_start();
/* ==========================================================================
   糯米の引导页 · 在线升级管理页 admin/update.php
   ========================================================================== */

require_once __DIR__ . '/defaults.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('NUOMI_ADMIN');
    session_start();
}
if (empty($_SESSION['nuomi_admin_login'])) {
    $__dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    header('Location: ' . $__dir . '/index.php');
    exit;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$releasesPage = 'https://github.com/' . UPDATE_GITHUB_REPO . '/releases';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>在线升级 · 糯米の引导页</title>
<link rel="stylesheet" href="../assets/admin.css">
<script src="../assets/updater.js"></script>
<script>
(function () {
    try {
        var m = localStorage.getItem('themeMode') || 'auto';
        var d = window.matchMedia('(prefers-color-scheme: dark)');
        document.documentElement.dataset.theme = (m === 'dark' || (m === 'auto' && d.matches)) ? 'dark' : 'light';
    } catch (e) {}
})();
</script>
<style>
body.update-page { padding: 0; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); background-attachment: fixed; }
html[data-theme="dark"] body.update-page { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); }
.update-container { max-width: 760px; margin: 0 auto; padding: 24px 16px 60px; }
.update-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 4px 16px; flex-wrap: wrap; }
.update-header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.update-header-left h1 { color: #fff; font-size: 18px; font-weight: 600; margin: 0; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; }
html[data-theme="dark"] .update-header-left h1 { color: #e8eaf6; }
.update-card { background: var(--bg-card, #ffffff); border-radius: 14px; padding: 16px 18px; margin-bottom: 12px; box-shadow: 0 2px 8px var(--shadow-card, rgba(0,0,0,0.06)); color: var(--text-main, #2c3e50); }
.update-card-title { font-size: 15px; font-weight: 600; margin-bottom: 10px; color: var(--text-main, #2c3e50); }
.update-version-card { text-align: center; }
.update-version-row { display: flex; align-items: center; justify-content: center; gap: 24px; padding: 8px 0 12px; flex-wrap: wrap; }
.update-version-item { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.update-version-label { font-size: 12px; color: var(--text-muted, #909399); letter-spacing: 0.3px; }
.update-version-value { font-size: 20px; font-weight: 700; color: var(--text-main, #2c3e50); font-family: Consolas, Menlo, monospace; }
.update-version-arrow { font-size: 20px; color: var(--text-muted, #909399); opacity: 0.5; }
.update-status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12.5px; background: var(--bg-chip, #f0f2f7); color: var(--text-muted, #909399); }
.update-status-badge.is-new { background: rgba(245, 158, 11, 0.15); color: #d97706; font-weight: 600; }
.update-status-badge.is-ok { background: rgba(16, 185, 129, 0.14); color: #059669; font-weight: 600; }
html[data-theme="dark"] .update-status-badge.is-new { background: rgba(245, 158, 11, 0.22); color: #fbbf24; }
html[data-theme="dark"] .update-status-badge.is-ok { background: rgba(16, 185, 129, 0.22); color: #34d399; }
.update-env-row { padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; font-size: 13px; line-height: 1.6; }
.update-env-row.is-ok { background: rgba(16, 185, 129, 0.10); color: #059669; border: 1px solid rgba(16, 185, 129, 0.30); }
.update-env-row.is-err { background: rgba(239, 68, 68, 0.10); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.30); }
html[data-theme="dark"] .update-env-row.is-ok { background: rgba(16, 185, 129, 0.18); color: #34d399; }
html[data-theme="dark"] .update-env-row.is-err { background: rgba(239, 68, 68, 0.18); color: #f87171; }
.update-env-meta { font-size: 12.5px; color: var(--text-code, #606266); margin-top: 4px; line-height: 1.6; word-break: break-all; }
.update-env-meta code { font-family: Consolas, Menlo, monospace; background: var(--bg-chip, #f0f2f7); padding: 1px 6px; border-radius: 4px; font-size: 12px; }
.update-env-meta.is-err { color: #dc2626; }
html[data-theme="dark"] .update-env-meta.is-err { color: #f87171; }
.update-release-line { font-size: 13.5px; margin-bottom: 4px; color: var(--text-code, #606266); }
.update-release-line b { color: var(--text-main, #2c3e50); font-family: Consolas, Menlo, monospace; }
.update-release-body { white-space: pre-wrap; word-break: break-word; max-height: 260px; overflow-y: auto; background: var(--bg-chip, #f0f2f7); border-radius: 8px; padding: 10px 12px; font-size: 13px; line-height: 1.7; color: var(--text-code, #606266); margin: 10px 0; font-family: inherit; }
html[data-theme="dark"] .update-release-body { background: #33384e; color: #c5c9e2; }
.update-release-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.update-actions { display: flex; flex-direction: column; gap: 10px; align-items: stretch; }
.update-actions .btn { padding: 10px 20px; font-size: 14px; font-weight: 500; justify-content: center; }
.update-action-tip { font-size: 12.5px; color: #4b5563; line-height: 1.6; margin-top: 4px; }
html[data-theme="dark"] .update-action-tip { color: #c5c9e2; }
.update-action-empty { padding: 12px; text-align: center; color: var(--text-muted, #909399); font-size: 14px; }
.update-progress { margin-top: 14px; }
.update-progress-bar { width: 100%; height: 6px; background: var(--bg-chip, #f0f2f7); border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
.update-progress-fill { width: 0%; height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 3px; transition: width 0.3s ease; animation: updatePulse 1.8s ease-in-out infinite; }
@keyframes updatePulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.65; } }
.update-progress-text { font-size: 12.5px; color: var(--text-muted, #909399); text-align: center; }
.update-state-line { display: flex; gap: 12px; padding: 4px 0; font-size: 13px; align-items: flex-start; }
.update-state-label { color: var(--text-muted, #909399); flex-shrink: 0; min-width: 40px; }
.update-state-value { color: var(--text-main, #2c3e50); word-break: break-all; line-height: 1.6; flex: 1; }
.update-state-value.is-ok { color: #059669; font-weight: 600; }
.update-state-value.is-err { color: #dc2626; font-weight: 600; }
.update-state-value.is-warn { color: #d97706; font-weight: 600; }
.update-state-value.is-loading { color: #2563eb; font-weight: 600; }
html[data-theme="dark"] .update-state-value.is-ok { color: #34d399; }
html[data-theme="dark"] .update-state-value.is-err { color: #f87171; }
html[data-theme="dark"] .update-state-value.is-warn { color: #fbbf24; }
html[data-theme="dark"] .update-state-value.is-loading { color: #79bbff; }
.update-warning {
    margin-top: 20px;
    padding: 12px 14px;
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.32);
    border-radius: 12px;
    font-size: 12.5px;
    line-height: 1.7;
    color: #78350f;
}
html[data-theme="dark"] .update-warning {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.35);
    color: #fbbf24;
}
.update-warning code {
    background: rgba(0, 0, 0, 0.06);
    padding: 1px 6px;
    border-radius: 4px;
    font-family: Consolas, Menlo, monospace;
    font-size: 11.5px;
    word-break: break-all;
    color: inherit;
}
html[data-theme="dark"] .update-warning code { background: rgba(0, 0, 0, 0.25); }
.update-header .btn-back { background: rgba(255, 255, 255, 0.18); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.28); box-shadow: none; padding: 8px 14px; border-radius: 8px; }
.update-header .btn-back:hover { background: rgba(255, 255, 255, 0.28); box-shadow: none; }
html[data-theme="dark"] .update-header .btn-back { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.15); color: #e8eaf6; }
html[data-theme="dark"] .update-header .btn-back:hover { background: rgba(255, 255, 255, 0.14); }
.update-tool-row { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; justify-content: center; }
.update-tool-row .btn { flex: 1; min-width: 130px; }
.update-rollback-btn { background: rgba(245, 108, 108, 0.12); color: #dc2626; border: 1px solid rgba(245, 108, 108, 0.40); }
html[data-theme="dark"] .update-rollback-btn { background: rgba(245, 108, 108, 0.18); color: #f87171; border-color: rgba(245, 108, 108, 0.5); }
</style>
</head>
<body class="update-page">
<div class="update-container">

    <!-- 顶部 -->
    <header class="update-header">
        <div class="update-header-left">
            <a class="btn btn-back" href="index.php">← 返回后台</a>
            <h1>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 2px;">
                    <polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                在线升级
            </h1>
        </div>
        <button type="button" class="btn btn-ghost theme-toggle-btn" id="themeToggleBtn" title="切换主题" aria-label="切换主题"></button>
    </header>

    <!-- 版本卡 -->
    <div class="update-card update-version-card">
        <div class="update-version-row">
            <div class="update-version-item">
                <div class="update-version-label">当前版本</div>
                <div class="update-version-value">v<?= e(NUOMI_VERSION) ?></div>
            </div>
            <div class="update-version-arrow">→</div>
            <div class="update-version-item">
                <div class="update-version-label">最新版本</div>
                <div class="update-version-value" id="latestVersion">—</div>
            </div>
        </div>
        <div class="update-status-badge" id="versionStatus">正在检查更新…</div>
    </div>

    <!-- 环境检测 -->
    <div class="update-card" id="envCard" style="display:none">
        <div class="update-card-title">🔍 环境检测</div>
        <div id="envResult"></div>
    </div>

    <!-- 更新信息 -->
    <div class="update-card" id="releaseCard" style="display:none">
        <div class="update-card-title">📦 更新信息</div>
        <div class="update-release-meta" id="releaseMeta"></div>
        <div class="update-release-body" id="releaseBody"></div>
        <div class="update-release-actions">
            <a class="btn" id="releaseViewLink" href="<?= e($releasesPage) ?>" target="_blank" rel="noopener noreferrer">🔗 查看更新说明</a>
        </div>
    </div>

    <!-- 操作区 -->
    <div class="update-card" id="actionsCard">
        <div class="update-card-title">⚡ 操作</div>
        <div class="update-actions" id="actionButtons"></div>
        <div class="update-tool-row" id="toolButtons"></div>
        <div class="update-progress" id="progressArea" style="display:none">
            <div class="update-progress-bar"><div class="update-progress-fill" id="progressFill"></div></div>
            <div class="update-progress-text" id="progressText">准备中…</div>
        </div>
    </div>

    <!-- 状态卡 -->
    <div class="update-card">
        <div class="update-card-title">📋 升级状态</div>
        <div class="update-state-line">
            <span class="update-state-label">状态</span>
            <span class="update-state-value" id="stateStatus">—</span>
        </div>
        <div class="update-state-line">
            <span class="update-state-label">信息</span>
            <span class="update-state-value" id="stateMessage">—</span>
        </div>
        <div class="update-state-line">
            <span class="update-state-label">开始</span>
            <span class="update-state-value" id="stateStarted">—</span>
        </div>
        <div class="update-state-line">
            <span class="update-state-label">结束</span>
            <span class="update-state-value" id="stateFinished">—</span>
        </div>
    </div>

    <!-- 风险提示 -->
    <div class="update-warning">
        ⚠️ 在线升级会覆盖代码文件（<code>*.php</code> / <code>*.css</code> / <code>*.js</code> / <code>README.md</code> / <code>CHANGELOG.md</code>），
        但<b>不会动</b> <code>admin/data.json</code>、<code>admin/.admin.conf</code>、<code>admin/.update/</code>、
        <code>photos/</code> 下的普通图片（<code>photos/kanban/</code> 内的形象文件仅覆盖同名文件，不会删除你添加的其它形象）、
        <code>assets/js/</code> 与 <code>assets/preloaders/</code> 下你自建的同名以外文件（同名文件会被覆盖）。
        升级失败会自动回滚，也可以手动回滚到上一版本。
    </div>

</div>

<div id="toast" class="toast"></div>

<script>
(function () {
    'use strict';

    var currentVersion = <?= json_encode(NUOMI_VERSION, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    var releasesPage   = <?= json_encode($releasesPage, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    var toastTimer = null;
    function toast(msg, type) {
        var t = document.getElementById('toast'); if (!t) return;
        t.textContent = msg;
        t.className = 'toast show' + (type ? ' ' + type : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { t.className = 'toast'; }, type === 'error' ? 4500 : 2600);
    }

    async function api(action, body) {
        var fd = body instanceof FormData ? body : new FormData();
        if (!(body instanceof FormData)) {
            for (var k in body) fd.append(k, body[k]);
        }
        fd.append('action', action);
        var res = await fetch('index.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        var data;
        try { data = await res.json(); }
        catch (err) { throw new Error('服务器返回异常：' + res.status + ' ' + res.statusText); }
        return data;
    }

    /* 主题切换 */
    (function () {
        var btn = document.getElementById('themeToggleBtn');
        if (!btn) return;
        var ORDER = ['auto', 'light', 'dark'];
        var LABELS = { auto: '跟随系统', light: '浅色模式', dark: '深色模式' };
        var ICONS = {
            auto: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor" stroke="none"/></svg>',
            light: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>',
            dark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
        };
        var mode = localStorage.getItem('themeMode') || 'auto';
        if (ORDER.indexOf(mode) === -1) mode = 'auto';
        function apply(m) {
            var isDark = m === 'dark' || (m === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
            btn.innerHTML = ICONS[m];
            btn.title = LABELS[m] + '（点击切换）';
            btn.setAttribute('aria-label', LABELS[m]);
        }
        apply(mode);
        btn.addEventListener('click', function () {
            mode = ORDER[(ORDER.indexOf(mode) + 1) % ORDER.length];
            try { localStorage.setItem('themeMode', mode); } catch (e) {}
            apply(mode);
        });
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if ((localStorage.getItem('themeMode') || 'auto') === 'auto') apply('auto');
        });
    })();

    var state = {
        hasUpdate: false,
        latestVersion: '',
        currentVersion: '',
        releaseUrl: '',
        releaseBody: '',
        releasePublished: '',
        env: null,
        statusValue: 'idle',
        canRollback: false,
        pollingTimer: null
    };

    function showProgress(text) {
        var area = document.getElementById('progressArea');
        var fill = document.getElementById('progressFill');
        var txt  = document.getElementById('progressText');
        if (area) area.style.display = 'block';
        if (txt) txt.textContent = text || '处理中…';
        if (fill) fill.style.width = '100%';
    }
    function hideProgress() {
        var area = document.getElementById('progressArea');
        var fill = document.getElementById('progressFill');
        if (area) area.style.display = 'none';
        if (fill) fill.style.width = '0%';
    }

    function updateStateView(s) {
        var st = (s && s.status) || 'idle';
        var map = {
            idle:        { label: '空闲',                cls: '' },
            downloading: { label: '下载中',              cls: 'is-loading' },
            downloaded:  { label: '已下载，等待应用',     cls: 'is-ok' },
            applying:    { label: '应用中',              cls: 'is-loading' },
            success:     { label: '升级成功',            cls: 'is-ok' },
            failed:      { label: '失败',                cls: 'is-err' },
            rolled_back: { label: '已回滚',              cls: 'is-warn' }
        };
        var m = map[st] || { label: st, cls: '' };
        var el = document.getElementById('stateStatus');
        if (el) { el.textContent = m.label; el.className = 'update-state-value ' + m.cls; }
        var msgEl = document.getElementById('stateMessage');
        if (msgEl) msgEl.textContent = (s && s.message) || '—';
        var stEl = document.getElementById('stateStarted');
        if (stEl) stEl.textContent = (s && s.started) || '—';
        var fiEl = document.getElementById('stateFinished');
        if (fiEl) fiEl.textContent = (s && s.finished) || '—';

        if (st === 'downloading' || st === 'applying') {
            showProgress(st === 'downloading' ? '正在下载更新包…' : '正在应用升级…');
        } else {
            hideProgress();
        }
    }

    function renderEnv(env) {
        var card = document.getElementById('envCard');
        var box  = document.getElementById('envResult');
        if (!card || !box) return;
        if (!env) { card.style.display = 'none'; return; }
        card.style.display = 'block';
        var ok = !!env.writable;
        var html = '';
        html += '<div class="update-env-row ' + (ok ? 'is-ok' : 'is-err') + '">' +
            (ok ? '✅ 当前环境支持在线升级' : '❌ 当前环境不支持在线升级，请手动下载后覆盖') +
            '</div>';
        if (env.php_user) html += '<div class="update-env-meta">PHP 运行用户：<code>' + esc(env.php_user) + '</code></div>';
        if (env.owner)    html += '<div class="update-env-meta">文件所有者：<code>' + esc(env.owner) + '</code></div>';
        if (env.reason)   html += '<div class="update-env-meta is-err">原因：' + esc(env.reason) + '</div>';
        box.innerHTML = html;
    }

    function renderRelease() {
        var card = document.getElementById('releaseCard');
        var meta = document.getElementById('releaseMeta');
        var body = document.getElementById('releaseBody');
        var viewLink = document.getElementById('releaseViewLink');
        if (!card) return;
        if (!state.hasUpdate && state.statusValue !== 'downloaded') {
            card.style.display = 'none';
            return;
        }
        card.style.display = 'block';
        if (meta) {
            meta.innerHTML =
                '<div class="update-release-line">最新版本：<b>v' + esc(state.latestVersion || '—') + '</b></div>' +
                (state.releasePublished ? '<div class="update-release-line">发布时间：' + esc(state.releasePublished) + '</div>' : '');
        }
        if (body) body.textContent = state.releaseBody || '（暂无更新说明）';
        if (viewLink) viewLink.href = state.releaseUrl || releasesPage;
    }

    /* ======================================================================
       操作区渲染：只要「有新版本 + 环境可写」，永远优先展示升级入口
       无论上次是 idle / failed / rolled_back，都不锁死升级按钮
       ====================================================================== */
    function renderActions() {
        var box = document.getElementById('actionButtons');
        var toolBox = document.getElementById('toolButtons');
        if (!box) return;
        box.innerHTML = '';
        if (toolBox) toolBox.innerHTML = '';

        var st = state.statusValue || 'idle';

        /* --- 1. 进行中：只显示进度 --- */
        if (st === 'downloading' || st === 'applying') {
            showProgress(st === 'downloading' ? '正在下载更新包…' : '正在应用升级…');
            return;
        }
        hideProgress();

        /* --- 2. success：显示「刷新后台」+ 次级回滚 --- */
        if (st === 'success') {
            var refreshBtn = document.createElement('button');
            refreshBtn.className = 'btn btn-primary';
            refreshBtn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>刷新后台查看新版本';
            refreshBtn.onclick = function () { window.location.href = 'index.php'; };
            box.appendChild(refreshBtn);

            var tipS = document.createElement('div');
            tipS.className = 'update-action-tip';
            tipS.textContent = '升级已完成。如果后台顶部栏版本号仍显示旧版本，请按 Ctrl+F5 强制刷新以清除浏览器缓存。';
            box.appendChild(tipS);

            if (state.canRollback && toolBox) {
                var rbBtn = document.createElement('button');
                rbBtn.className = 'btn update-rollback-btn';
                rbBtn.textContent = '↩ 回滚到上一版本';
                rbBtn.onclick = doRollback;
                toolBox.appendChild(rbBtn);
            }
            appendRecheckButton(toolBox);
            return;
        }

        /* --- 3. downloaded：应用按钮 --- */
        if (st === 'downloaded') {
            var applyBtn = document.createElement('button');
            applyBtn.className = 'btn btn-primary';
            applyBtn.textContent = '🚀 应用升级到 v' + (state.latestVersion || '新版本');
            applyBtn.onclick = doApply;
            box.appendChild(applyBtn);

            var applyTip = document.createElement('div');
            applyTip.className = 'update-action-tip';
            applyTip.textContent = '更新包已下载完成，点击上方按钮即可应用。应用前会自动备份当前代码。';
            box.appendChild(applyTip);

            if (state.canRollback && toolBox) {
                var rbBtn2 = document.createElement('button');
                rbBtn2.className = 'btn update-rollback-btn';
                rbBtn2.textContent = '↩ 回滚到上一版本';
                rbBtn2.onclick = doRollback;
                toolBox.appendChild(rbBtn2);
            }
            appendRecheckButton(toolBox);
            return;
        }

        /* --- 4. idle / failed / rolled_back：主入口永远是「立即升级」 --- */
        if (state.hasUpdate && state.env && state.env.writable) {
            var upBtn = document.createElement('button');
            upBtn.className = 'btn btn-primary';
            upBtn.innerHTML = '⬆ 立即升级到 v' + (state.latestVersion || '');
            upBtn.onclick = doUpgrade;
            box.appendChild(upBtn);

            var tipUp = document.createElement('div');
            tipUp.className = 'update-action-tip';
            if (st === 'failed' || st === 'rolled_back') {
                var stLabel = (st === 'failed') ? '失败' : '已回滚';
                tipUp.innerHTML = '上次操作状态：<b>' + stLabel + '</b>。你可以直接再次尝试升级。';
            } else {
                tipUp.textContent = '点击按钮将自动下载并覆盖代码（不会动 data.json / photos / assets 中的私有数据）。';
            }
            box.appendChild(tipUp);
        } else if (state.hasUpdate && state.env && !state.env.writable) {
            var ghBtn = document.createElement('a');
            ghBtn.className = 'btn btn-primary';
            ghBtn.href = state.releaseUrl || releasesPage;
            ghBtn.target = '_blank';
            ghBtn.rel = 'noopener noreferrer';
            ghBtn.textContent = '🔗 去 GitHub 下载';
            box.appendChild(ghBtn);

            var tipNoEnv = document.createElement('div');
            tipNoEnv.className = 'update-action-tip';
            tipNoEnv.textContent = '当前主机环境不支持在线升级，请下载最新压缩包后按 README 说明手动覆盖。';
            box.appendChild(tipNoEnv);
        } else if (!state.hasUpdate && state.latestVersion) {
            var p = document.createElement('div');
            p.className = 'update-action-empty';
            p.textContent = '✅ 已是最新版本 v' + state.latestVersion + '，无需升级。';
            box.appendChild(p);
        } else {
            var loading = document.createElement('div');
            loading.className = 'update-action-empty';
            loading.textContent = '正在检查更新…';
            box.appendChild(loading);
        }

        /* --- 5. 次级工具区：永远显示「重新检查」；有可用备份时额外显示回滚 --- */
        if (state.canRollback && toolBox) {
            var rbBtn3 = document.createElement('button');
            rbBtn3.className = 'btn update-rollback-btn';
            rbBtn3.textContent = '↩ 回滚到上一版本';
            rbBtn3.onclick = doRollback;
            toolBox.appendChild(rbBtn3);
        }
        appendRecheckButton(toolBox);
    }

    function appendRecheckButton(toolBox) {
        if (!toolBox) return;
        var chkBtn = document.createElement('button');
        chkBtn.className = 'btn';
        chkBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>重新检查更新';
        chkBtn.onclick = function () {
            state.statusValue = 'idle';
            doCheckUpdate();
        };
        toolBox.appendChild(chkBtn);
    }

    /* 动作 */
    async function doCheckUpdate() {
        var vsEl = document.getElementById('versionStatus');
        if (vsEl) {
            vsEl.className = 'update-status-badge';
            vsEl.textContent = '正在检查更新…';
        }
        try {
            var data = await window.NuomiUpdater.check();
            if (!data.ok) {
                toast(data.msg || '检查更新失败', 'error');
                if (vsEl) { vsEl.textContent = '检查失败'; vsEl.className = 'update-status-badge'; }
                return;
            }
            state.currentVersion    = data.current_version || currentVersion;
            state.latestVersion     = data.latest_version || '';
            state.hasUpdate         = !!data.has_update;
            state.releaseUrl        = data.release_url || releasesPage;
            state.releaseBody       = data.release_body || '';
            state.releasePublished  = data.release_published || '';
            state.env               = data.env || null;

            var lvEl = document.getElementById('latestVersion');
            if (lvEl) lvEl.textContent = state.latestVersion ? ('v' + state.latestVersion) : '—';

            if (vsEl) {
                if (state.hasUpdate) {
                    vsEl.textContent = '有可用更新';
                    vsEl.className = 'update-status-badge is-new';
                } else if (state.latestVersion) {
                    vsEl.textContent = '已是最新版本';
                    vsEl.className = 'update-status-badge is-ok';
                } else {
                    vsEl.textContent = data.msg || '无法获取最新版本';
                    vsEl.className = 'update-status-badge';
                }
            }

            renderEnv(state.env);
            renderRelease();
            renderActions();

            if (state.hasUpdate) toast('发现新版本 v' + state.latestVersion, 'success');
            else if (state.latestVersion) toast('已是最新版本', 'success');
        } catch (err) {
            toast(err.message, 'error');
            if (vsEl) { vsEl.textContent = '检查失败'; vsEl.className = 'update-status-badge'; }
        }
    }

    async function doUpgrade() {
        if (!confirm('确定升级到 v' + state.latestVersion + ' 吗？\n\n升级过程会先备份当前代码，再覆盖代码文件；不会动 data.json / photos / assets/js / assets/preloaders。\n升级失败会自动回滚。')) return;
        try {
            showProgress('正在下载更新包…');
            toast('正在下载更新包…');
            var d = await api('update_download');
            if (!d.ok) {
                hideProgress();
                toast(d.msg || '下载失败', 'error');
                await refreshStatus();
                return;
            }
            showProgress('下载完成，正在应用升级…');
            toast('下载完成，正在应用升级…');
            var a = await api('update_apply');
            if (!a.ok) {
                hideProgress();
                toast(a.msg || '应用失败，已自动回滚', 'error');
                await refreshStatus();
                return;
            }
            hideProgress();
            toast('升级成功！', 'success');
            await refreshStatus();
            setTimeout(function () {
                if (confirm('升级成功，是否刷新后台查看新版本？')) window.location.href = 'index.php';
            }, 600);
        } catch (err) {
            hideProgress();
            toast(err.message, 'error');
            await refreshStatus();
        }
    }

    async function doApply() {
        if (!confirm('确定要应用升级吗？\n\n升级过程会先备份当前代码，再覆盖代码文件；不会动 data.json / photos 等私有数据。\n升级失败会自动回滚。')) return;
        try {
            showProgress('正在应用升级…');
            toast('正在应用升级…');
            var a = await api('update_apply');
            if (!a.ok) {
                hideProgress();
                toast(a.msg || '应用失败，已自动回滚', 'error');
                await refreshStatus();
                return;
            }
            hideProgress();
            toast('升级成功！', 'success');
            await refreshStatus();
            setTimeout(function () {
                if (confirm('升级成功，是否刷新后台查看新版本？')) window.location.href = 'index.php';
            }, 600);
        } catch (err) {
            hideProgress();
            toast(err.message, 'error');
            await refreshStatus();
        }
    }

    async function doRollback() {
        if (!confirm('确定要回滚到上一版本吗？\n\n回滚会用最近一次备份覆盖当前代码文件。\n如果当前版本已经比备份版本新，回滚后将失去新代码（数据文件不受影响）。')) return;
        try {
            showProgress('正在回滚…');
            toast('正在回滚…');
            var r = await api('update_rollback');
            if (!r.ok) {
                hideProgress();
                toast(r.msg || '回滚失败', 'error');
                return;
            }
            hideProgress();
            toast('回滚成功！', 'success');
            await refreshStatus();
            setTimeout(function () {
                if (confirm('回滚成功，是否刷新后台？')) window.location.href = 'index.php';
            }, 600);
        } catch (err) {
            hideProgress();
            toast(err.message, 'error');
        }
    }

    async function refreshStatus() {
        try {
            var data = await api('update_status');
            if (!data.ok) return;
            if (data.state) {
                state.statusValue = data.state.status || 'idle';
                updateStateView(data.state);
                if (data.state.status === 'downloaded' && data.state.version) {
                    state.latestVersion = data.state.version;
                }
            }
            state.canRollback = !!data.can_rollback;
            renderActions();
        } catch (err) {}
    }

    function startPolling() {
        if (state.pollingTimer) return;
        state.pollingTimer = setInterval(function () {
            var v = state.statusValue;
            if (v === 'downloading' || v === 'applying') refreshStatus();
        }, 1500);
    }

    /* 初始化 */
    (async function init() {
        try {
            var st = await api('update_status');
            if (st.ok) {
                if (st.state) {
                    state.statusValue = st.state.status || 'idle';
                    updateStateView(st.state);
                    if (st.state.status === 'downloaded' && st.state.version) {
                        state.latestVersion = st.state.version;
                    }
                }
                state.canRollback = !!st.can_rollback;
            }
        } catch (e) {}

        await doCheckUpdate();
        startPolling();
    })();

})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>