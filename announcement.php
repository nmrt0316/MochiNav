<?php
ob_start();
/* ==========================================================================
   糯米の引导页 - 站内公告页 announcement.php
   ========================================================================== */

/* ==========================================================================
   0. 未初始化检测
   ========================================================================== */
$__adminConf = __DIR__ . '/admin/.admin.conf';
if (!file_exists($__adminConf)) {
    $__scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    header('Location: ' . $__scriptDir . '/admin');
    exit;
}

/* 默认值唯一权威来源（前后台共用） */
require_once __DIR__ . '/admin/defaults.php';

/* ==========================================================================
   1. 读取数据
   ========================================================================== */
$dataFile = __DIR__ . '/admin/data.json';
$jsonData = null;
if (file_exists($dataFile)) {
    $jsonData = json_decode(@file_get_contents($dataFile), true);
}

/* 站点配置：读 data.json + site_defaults() 空值兜底 */
$siteDefaults = site_defaults();
$siteCfg = [];
if (is_array($jsonData) && isset($jsonData['site']) && is_array($jsonData['site'])) {
    $siteCfg = $jsonData['site'];
}
foreach ($siteDefaults as $k => $v) {
    if (!array_key_exists($k, $siteCfg) || trim((string)$siteCfg[$k]) === '') {
        $siteCfg[$k] = $v;
    }
}

/* ==========================================================================
   2. 解析公告列表（只显示启用的，按置顶 + 创建时间排序）
   ========================================================================== */
$announcements = [];
if (is_array($jsonData) && isset($jsonData['announcements']) && is_array($jsonData['announcements'])) {
    foreach ($jsonData['announcements'] as $a) {
        if (!is_array($a)) continue;
        if (empty($a['enabled'])) continue;
        $announcements[] = $a;
    }
}
usort($announcements, function ($a, $b) {
    $pa = !empty($a['pinned']) ? 1 : 0;
    $pb = !empty($b['pinned']) ? 1 : 0;
    if ($pa !== $pb) return $pb - $pa;
    return strcmp($b['created'] ?? '', $a['created'] ?? '');
});

/* ==========================================================================
   3. 页面元信息
   ========================================================================== */
$siteTitle = trim((string)($siteCfg['title'] ?? '')) ?: '糯米の引导页';
$pageTitle = '公告 · ' . $siteTitle;

$typeLabels = [
    'info'    => 'ℹ️ 通知',
    'success' => '✅ 成功',
    'warning' => '⚠️ 警告',
    'error'   => '🚫 错误',
];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="<?= e($siteTitle) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e($siteCfg['favicon']) ?>">

    <!-- 主题防闪烁 -->
    <script>
    (function () {
        try {
            var m = localStorage.getItem('themeMode') || 'auto';
            var isDark = m === 'dark' || (m === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
        } catch (e) {}
    })();
    </script>

    <style>
    /* ==========================================================================
       公告页样式（独立，列表风格）
       ========================================================================== */
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    html, body {
        height: 100%;
        -webkit-text-size-adjust: 100%;
        -webkit-tap-highlight-color: transparent;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif;
        background: #eef1f8;
        color: #2c3e50;
        font-size: 14px;
        line-height: 1.6;
        min-height: 100vh;
        padding: 24px 16px 40px;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        transition: background-color 0.4s ease, color 0.4s ease;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background-image: linear-gradient(135deg, #f0f4fc 0%, #e2ebf5 50%, #d4def0 100%);
        opacity: 1;
        transition: opacity 0.4s ease;
    }
    body::after {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background-image: linear-gradient(135deg, #141724 0%, #1c1d2e 50%, #12131c 100%);
        opacity: 0;
        transition: opacity 0.4s ease;
    }
    html[data-theme="dark"] body::before { opacity: 0; }
    html[data-theme="dark"] body::after  { opacity: 1; }
    html[data-theme="dark"] body {
        background: #121420;
        color: #e8eaf6;
    }

    .ann-page {
        max-width: 720px;
        margin: 0 auto;
    }

    /* 头部 */
    .ann-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        gap: 12px;
    }
    .ann-header h1 {
        font-size: 20px;
        font-weight: 600;
        color: inherit;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ann-back {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 8px 16px;
        background: #fff;
        color: #2c3e50;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.4s ease, color 0.4s ease;
    }
    html[data-theme="dark"] .ann-back {
        background: #25293c;
        color: #e8eaf6;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
    .ann-back:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .ann-back:active { transform: translateY(0); }

    /* ==========================================================================
       公告列表（列表风格，非卡片）
       ========================================================================== */
    .ann-list {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .ann-card {
        background: transparent;
        border-radius: 0;
        padding: 20px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        transition: background-color 0.4s ease, border-color 0.4s ease;
    }
    .ann-card:first-child {
        padding-top: 8px;
    }
    .ann-card:last-child {
        border-bottom: none;
    }
    html[data-theme="dark"] .ann-card {
        border-bottom-color: rgba(255, 255, 255, 0.08);
    }

    .ann-card-header {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 8px;
        font-size: 12.5px;
        color: #909399;
        transition: color 0.4s ease;
    }
    html[data-theme="dark"] .ann-card-header {
        color: #9499b3;
    }

    .ann-card-time {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.2px;
    }

    .ann-card-type-badge {
        display: inline-block;
        padding: 0 7px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        line-height: 1.7;
    }
    .ann-card-type-badge.type-info    { background: rgba(64, 158, 255, 0.14); color: #409eff; }
    .ann-card-type-badge.type-success { background: rgba(16, 185, 129, 0.14); color: #10b981; }
    .ann-card-type-badge.type-warning { background: rgba(245, 158, 11, 0.14); color: #d97706; }
    .ann-card-type-badge.type-error   { background: rgba(239, 68, 68, 0.14);  color: #ef4444; }
    html[data-theme="dark"] .ann-card-type-badge.type-info    { background: rgba(64, 158, 255, 0.22); color: #79bbff; }
    html[data-theme="dark"] .ann-card-type-badge.type-success { background: rgba(16, 185, 129, 0.22); color: #34d399; }
    html[data-theme="dark"] .ann-card-type-badge.type-warning { background: rgba(245, 158, 11, 0.22); color: #fbbf24; }
    html[data-theme="dark"] .ann-card-type-badge.type-error   { background: rgba(239, 68, 68, 0.22);  color: #f87171; }

    .ann-card-pinned {
        display: inline-block;
        padding: 0 7px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(102, 126, 234, 0.15);
        color: #667eea;
        line-height: 1.7;
    }
    html[data-theme="dark"] .ann-card-pinned {
        background: rgba(120, 150, 230, 0.22);
        color: #a5b4fc;
    }

    .ann-card-title {
        font-size: 17px;
        font-weight: 600;
        color: inherit;
        margin-bottom: 10px;
        line-height: 1.5;
        word-break: break-word;
    }

    .ann-card-content {
        font-size: 14px;
        line-height: 1.85;
        color: #515a6e;
        word-wrap: break-word;
        word-break: break-word;
        overflow-wrap: break-word;
        transition: color 0.4s ease;
    }
    html[data-theme="dark"] .ann-card-content {
        color: #c5c9e2;
    }

    .ann-card-content p { margin: 0 0 10px; }
    .ann-card-content p:last-child { margin-bottom: 0; }

    .ann-card-content h1,
    .ann-card-content h2,
    .ann-card-content h3,
    .ann-card-content h4 {
        margin: 16px 0 8px;
        font-weight: 600;
        line-height: 1.4;
        color: inherit;
    }
    .ann-card-content h1 { font-size: 18px; }
    .ann-card-content h2 { font-size: 16px; }
    .ann-card-content h3 { font-size: 15px; }
    .ann-card-content h4 { font-size: 14px; }
    .ann-card-content h1:first-child,
    .ann-card-content h2:first-child,
    .ann-card-content h3:first-child,
    .ann-card-content h4:first-child { margin-top: 0; }

    .ann-card-content ul,
    .ann-card-content ol {
        padding-left: 22px;
        margin: 0 0 10px;
    }
    .ann-card-content li { margin-bottom: 4px; }

    .ann-card-content a {
        color: #409eff;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    html[data-theme="dark"] .ann-card-content a { color: #79bbff; }

    .ann-card-content strong { font-weight: 600; }
    .ann-card-content em { font-style: italic; }

    .ann-card-content code {
        background: rgba(0, 0, 0, 0.06);
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 12.5px;
        font-family: Consolas, Menlo, "Courier New", monospace;
    }
    html[data-theme="dark"] .ann-card-content code {
        background: rgba(255, 255, 255, 0.08);
    }

    .ann-card-content pre {
        background: rgba(0, 0, 0, 0.06);
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 12.5px;
        font-family: Consolas, Menlo, "Courier New", monospace;
        overflow-x: auto;
        margin: 0 0 10px;
        line-height: 1.6;
    }
    html[data-theme="dark"] .ann-card-content pre {
        background: rgba(255, 255, 255, 0.06);
    }

    .ann-card-content blockquote {
        border-left: 3px solid #dcdfe6;
        padding: 2px 12px;
        margin: 0 0 10px;
        color: #909399;
    }
    html[data-theme="dark"] .ann-card-content blockquote {
        border-left-color: #444960;
        color: #9499b3;
    }

    .ann-card-content hr {
        border: none;
        border-top: 1px solid #dcdfe6;
        margin: 16px 0;
    }
    html[data-theme="dark"] .ann-card-content hr {
        border-top-color: #444960;
    }

    .ann-card-content img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        margin: 6px 0;
        display: block;
    }

    /* 空状态 */
    .ann-empty {
        background: rgba(255, 255, 255, 0.5);
        border-radius: 14px;
        padding: 60px 20px;
        text-align: center;
        color: #909399;
        font-size: 14px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        transition: background-color 0.4s ease, color 0.4s ease, box-shadow 0.4s ease;
    }
    html[data-theme="dark"] .ann-empty {
        background: rgba(30, 33, 42, 0.5);
        color: #9499b3;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
    }
    .ann-empty-icon {
        display: block;
        font-size: 42px;
        margin-bottom: 12px;
        opacity: 0.6;
    }

    /* 页脚 */
    .ann-footer {
        text-align: center;
        margin-top: 36px;
        font-size: 12px;
        color: #909399;
        line-height: 1.8;
        transition: color 0.4s ease;
    }
    html[data-theme="dark"] .ann-footer { color: #9499b3; }
    .ann-footer a {
        color: inherit;
        text-decoration: none;
        transition: color 0.2s ease;
    }
    .ann-footer a:hover { color: #667eea; }
    html[data-theme="dark"] .ann-footer a:hover { color: #a5b4fc; }

    /* 响应式 */
    @media (max-width: 600px) {
        body { padding: 16px 14px 32px; }
        .ann-header h1 { font-size: 18px; }
        .ann-header { margin-bottom: 20px; }
        .ann-card { padding: 16px 0; }
        .ann-card-title { font-size: 16px; }
        .ann-card-content { font-size: 13.5px; }
    }
    </style>
</head>
<body>
    <div class="ann-page">
        <div class="ann-header">
            <h1>📢 站点公告</h1>
            <a class="ann-back" href="./">← 返回首页</a>
        </div>

        <?php if (count($announcements) === 0): ?>
            <div class="ann-empty">
                <span class="ann-empty-icon">📭</span>
                暂无公告
            </div>
        <?php else: ?>
            <div class="ann-list">
                <?php foreach ($announcements as $ann):
                    $type = in_array($ann['type'] ?? 'info', ['info', 'success', 'warning', 'error'], true) ? $ann['type'] : 'info';
                    $typeLabel = $typeLabels[$type] ?? 'ℹ️ 通知';
                ?>
                    <article class="ann-card">
                        <div class="ann-card-header">
                            <time class="ann-card-time"><?= e($ann['created'] ?? '') ?></time>
                            <span class="ann-card-type-badge type-<?= e($type) ?>"><?= $typeLabel ?></span>
                            <?php if (!empty($ann['pinned'])): ?>
                                <span class="ann-card-pinned">置顶</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="ann-card-title"><?= e($ann['title'] ?? '') ?></h2>
                        <?php if (!empty($ann['content'])): ?>
                            <div class="ann-card-content"><?= $ann['content'] ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="ann-footer">
            © <?= date('Y') ?> <?= e($siteTitle) ?>
            <?php if (!empty($siteCfg['poweredBy'])): ?>
                · Powered by
                <?php if (!empty($siteCfg['poweredByUrl'])): ?>
                    <a href="<?= e($siteCfg['poweredByUrl']) ?>" target="_blank" rel="noopener noreferrer"><?= e($siteCfg['poweredBy']) ?></a>
                <?php else: ?>
                    <?= e($siteCfg['poweredBy']) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 主题跟随系统变化 -->
    <script>
    (function () {
        if (!window.matchMedia) return;
        var mediaDark = window.matchMedia('(prefers-color-scheme: dark)');
        mediaDark.addEventListener('change', function () {
            try {
                var m = localStorage.getItem('themeMode') || 'auto';
                if (m !== 'auto') return;
                document.documentElement.dataset.theme = mediaDark.matches ? 'dark' : 'light';
            } catch (e) {}
        });
        window.addEventListener('storage', function (e) {
            if (e.key !== 'themeMode') return;
            try {
                var m = localStorage.getItem('themeMode') || 'auto';
                var isDark = m === 'dark' || (m === 'auto' && mediaDark.matches);
                document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
            } catch (err) {}
        });
    })();
    </script>

    <?php include __DIR__ . '/analytics.php'; ?>
</body>
</html>
<?php ob_end_flush(); ?>