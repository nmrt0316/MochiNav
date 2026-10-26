<?php ob_start(); ?>
<?php
/* ==========================================================================
   糯米の引导页 - 前台入口 index.php
   ========================================================================== */

/* 默认值唯一权威来源（前后台共用） */
require_once __DIR__ . '/admin/defaults.php';

/* ==========================================================================
   0. 未初始化检测
   ========================================================================== */
$__adminConf = __DIR__ . '/admin/.admin.conf';
if (!file_exists($__adminConf)) {
    $__scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    header('Location: ' . $__scriptDir . '/admin');
    exit;
}

/* ==========================================================================
   1. 初始化与数据解析
   ========================================================================== */
$dataFile = __DIR__ . '/admin/data.json';
$jsonData = null;
$categoryObjects = [];
$cards = [];

if (file_exists($dataFile)) {
    $raw = file_get_contents($dataFile);
    $jsonData = json_decode($raw, true);
    if (is_array($jsonData)) {
        $rawCats = isset($jsonData['categories']) && is_array($jsonData['categories']) ? $jsonData['categories'] : [];
        foreach ($rawCats as $catItem) {
            if (is_string($catItem)) {
                $categoryObjects[] = ['name' => $catItem, 'icon' => ''];
            } elseif (is_array($catItem) && isset($catItem['name'])) {
                $categoryObjects[] = [
                    'name' => trim((string)$catItem['name']),
                    'icon' => isset($catItem['icon']) ? trim((string)$catItem['icon']) : ''
                ];
            }
        }
        $cards = $jsonData['cards'] ?? [];
    }
}

/* 分类为空时用出厂默认分类兜底（定义在 admin/defaults.php） */
if (count($categoryObjects) === 0) {
    $categoryObjects = factory_defaults()['categories'];
}

$categories = array_column($categoryObjects, 'name');
$cardsByCat = [];
foreach ($cards as $c) {
    $k = isset($c['category']) ? $c['category'] : '';
    $cardsByCat[$k][] = $c;
}

/* ==========================================================================
   2. 特效与看板娘配置解析
   ========================================================================== */
$jsEffects = (is_array($jsonData) && isset($jsonData['jsEffects']) && is_array($jsonData['jsEffects'])) ? $jsonData['jsEffects'] : [];
$boardGirlCfg = (is_array($jsonData) && isset($jsonData['boardGirl']) && is_array($jsonData['boardGirl'])) ? $jsonData['boardGirl'] : [];
$girlsList = isset($boardGirlCfg['girls']) && is_array($boardGirlCfg['girls']) ? $boardGirlCfg['girls'] : [];
$girlEnabled = array_key_exists('enabled', $boardGirlCfg) ? (bool)$boardGirlCfg['enabled'] : (count($girlsList) > 0);

$boardGirlPublic = [
    'enabled' => $girlEnabled,
    'girls'   => $girlsList
];

$userDisabledEffects = [];
if (!empty($_COOKIE['nuomi_effects_off'])) {
    $__tmp = json_decode($_COOKIE['nuomi_effects_off'], true);
    if (is_array($__tmp)) {
        foreach ($__tmp as $__k) {
            if (is_string($__k) && $__k !== '') $userDisabledEffects[] = $__k;
        }
    }
}

$jsEffectsPublic = [];
foreach ($jsEffects as $e) {
    if (!is_array($e)) continue;
    $name     = isset($e['name'])     ? (string)$e['name']     : '';
    $matchKey = isset($e['matchKey']) ? (string)$e['matchKey'] : $name;
    if ($matchKey === '') $matchKey = $name;

    $file       = isset($e['file']) ? (string)$e['file'] : '';
    $enabled    = !empty($e['enabled']);
    $autoToggle = !empty($e['autoToggle']);
    $userOff    = in_array($matchKey, $userDisabledEffects, true);
    $loadScript = ($file !== '') && $enabled && !$userOff;

    $jsEffectsPublic[] = [
        'matchKey'   => $matchKey,
        'name'       => $name !== '' ? $name : $matchKey,
        'icon'       => isset($e['icon']) ? (string)$e['icon'] : '✨',
        'file'       => $file,
        'enabled'    => $enabled,
        'autoToggle' => $autoToggle,
        'loadScript' => $loadScript,
    ];
}

$tipsCfg = (is_array($jsonData) && isset($jsonData['tips']) && is_array($jsonData['tips'])) ? $jsonData['tips'] : null;

/* ==========================================================================
   3. 站点显示配置合并
   ========================================================================== */
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

unset($siteCfg['analyticsCode']);
$preloaderEnabled = !isset($siteCfg['preloader']) || $siteCfg['preloader'] !== '0';
$__jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;

/* ==========================================================================
   3.1 最新一条启用的公告（用于首页公告条）
   ========================================================================== */
$__latestAnn = null;
if (is_array($jsonData) && isset($jsonData['announcements']) && is_array($jsonData['announcements'])) {
    $__enabledAnns = array_values(array_filter($jsonData['announcements'], function ($a) {
        return is_array($a) && !empty($a['enabled']);
    }));
    usort($__enabledAnns, function ($a, $b) {
        $pa = !empty($a['pinned']) ? 1 : 0;
        $pb = !empty($b['pinned']) ? 1 : 0;
        if ($pa !== $pb) return $pb - $pa;
        return strcmp($b['created'] ?? '', $a['created'] ?? '');
    });
    if (count($__enabledAnns) > 0) {
        $__latestAnn = $__enabledAnns[0];
    }
}

/* ==========================================================================
   3.5 本地背景兜底配置解析
   ========================================================================== */
$localBgCfg = ['enabled' => true, 'images' => []];
if (is_array($jsonData) && isset($jsonData['localBg']) && is_array($jsonData['localBg'])) {
    $localBgCfg['enabled'] = !empty($jsonData['localBg']['enabled']);
    if (isset($jsonData['localBg']['images']) && is_array($jsonData['localBg']['images'])) {
        $localBgCfg['images'] = $jsonData['localBg']['images'];
    }
}

$localBannerCfg = ['enabled' => true, 'images' => []];
if (is_array($jsonData) && isset($jsonData['localBanner']) && is_array($jsonData['localBanner'])) {
    $localBannerCfg['enabled'] = !empty($jsonData['localBanner']['enabled']);
    if (isset($jsonData['localBanner']['images']) && is_array($jsonData['localBanner']['images'])) {
        $localBannerCfg['images'] = $jsonData['localBanner']['images'];
    }
}

/* ==========================================================================
   4. 加载页主题解析
   ========================================================================== */
$preloadersRaw = (is_array($jsonData) && isset($jsonData['preloaders']) && is_array($jsonData['preloaders'])) ? $jsonData['preloaders'] : [];
$preloadersPublic = [];
foreach ($preloadersRaw as $p) {
    if (!is_array($p)) continue;
    $key  = (string)($p['key'] ?? '');
    $file = (string)($p['file'] ?? '');
    if ($key === '' || $file === '') continue;
    if (empty($p['enabled'])) continue;

    $fullPath = __DIR__ . '/' . $file;
    if (!is_file($fullPath)) continue;

    $css = @file_get_contents($fullPath);
    if ($css === false) continue;

    if (stripos($css, '<?php') !== false || stripos($css, '<?=') !== false || stripos($css, '</style') !== false || stripos($css, '<script') !== false || stripos($css, '</script') !== false) {
        continue;
    }

    $preloadersPublic[] = [
        'key'  => $key,
        'name' => (string)($p['name'] ?? $key),
        'desc' => (string)($p['description'] ?? ''),
        'css'  => $css,
    ];
}

$defaultStyleKey = (string)$siteCfg['preloaderStyle'];
$defaultThemeCss = '';
foreach ($preloadersPublic as $p) {
    if ($p['key'] === $defaultStyleKey) { $defaultThemeCss = $p['css']; break; }
}
if ($defaultThemeCss === '' && count($preloadersPublic) > 0) {
    $defaultThemeCss = $preloadersPublic[0]['css'];
    $defaultStyleKey = $preloadersPublic[0]['key'];
}

$__preloaderList = array_map(function ($p) {
    return ['key' => $p['key'], 'name' => $p['name'], 'desc' => $p['desc']];
}, $preloadersPublic);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($siteCfg['title']) ?>">

    <script>
    (function () {
        try {
            var mode = localStorage.getItem('themeMode') || 'auto';
            var mediaDark = window.matchMedia('(prefers-color-scheme: dark)');
            var isDark = mode === 'dark' || (mode === 'auto' && mediaDark.matches);
            document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
        } catch (e) {}

        try {
            if (sessionStorage.getItem('nuomi_seen') === '1') {
                document.documentElement.classList.add('revisit');
            }
        } catch (e) {}
        <?php if ($preloaderEnabled): ?>
        document.documentElement.classList.add('nuomi-preloading');
        <?php endif; ?>

        try {
            var isLowEnd = false;
            var canvas = document.createElement('canvas');
            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) { isLowEnd = true; }
            if (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4) { isLowEnd = true; }
            if (isLowEnd) {
                document.documentElement.classList.add('low-end-device');
            }
        } catch (e) {
            document.documentElement.classList.add('low-end-device');
        }
    })();
    </script>

    <link rel="icon" href="<?= htmlspecialchars($siteCfg['favicon']) ?>">
    <title><?= htmlspecialchars($siteCfg['pageTitle']) ?></title>

    <?php if (!empty($boardGirlPublic['enabled']) && count($boardGirlPublic['girls']) > 0): 
        $firstGirlFile = $boardGirlPublic['girls'][0]['file'] ?? '';
        if ($firstGirlFile): 
    ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($firstGirlFile) ?>">
    <?php endif; endif; ?>

    <?php foreach ($jsEffectsPublic as $eff): ?>
        <?php if (!empty($eff['loadScript'])): ?>
            <link rel="preload" as="script" href="<?= htmlspecialchars($eff['file'], ENT_QUOTES) ?>">
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ((!empty($boardGirlPublic['enabled']) && count($boardGirlPublic['girls']) > 0) || count($jsEffectsPublic) > 0): ?>
        <link rel="preload" as="script" href="assets/mascot.js">
    <?php endif; ?>

    <?php if ($preloaderEnabled): ?>
    <style>
        #preloader{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;
            transition:opacity .7s cubic-bezier(.22,1,.36,1),visibility .7s}
        #preloader.hidden{opacity:0;visibility:hidden;pointer-events:none}
    </style>
    <?php endif; ?>

    <script>
    window.NUOMI_CONFIG = {
        site: <?= json_encode($siteCfg, $__jsonFlags) ?>,
        boardGirl: <?= json_encode($boardGirlPublic, $__jsonFlags) ?>,
        jsEffects: <?= json_encode($jsEffectsPublic, $__jsonFlags) ?>,
        tips: <?= json_encode($tipsCfg, $__jsonFlags) ?>,
        localBg:     <?= json_encode($localBgCfg, $__jsonFlags) ?>,
        localBanner: <?= json_encode($localBannerCfg, $__jsonFlags) ?>,
        preloaders: {
            defaultKey: <?= json_encode($defaultStyleKey, $__jsonFlags) ?>,
            list: <?= json_encode($__preloaderList, $__jsonFlags) ?>
        }
    };

    (function () {
        var s = window.NUOMI_CONFIG.site || {};

        function shouldWarmup(type) {
            var priority = (type === 'bg') ? (s.localBgPriority || 'api') : (s.localBannerPriority || 'api');
            if (priority === 'local') return false;
            try {
                if (localStorage.getItem('nuomi_pool_full_' + type) === '1') return false;
            } catch (e) {}
            return true;
        }

        function warmup(url, timeout) {
            if (!url) return null;
            var sep = url.indexOf('?') >= 0 ? '&' : '?';
            var full = url + sep + '_t=' + Date.now() + '&_rand=' + Math.random().toString(36).slice(2, 8);
            var ctrl = new AbortController();
            var timer = setTimeout(function () { ctrl.abort(); }, timeout || 4000);
            var p = fetch(full, { cache: 'no-store', signal: ctrl.signal })
                .then(function (r) { return r.ok ? r.blob() : null; })
                .catch(function () { return null; })
                .finally(function () { clearTimeout(timer); });
            p.__used = false;
            return p;
        }

        window.__nuomiWarmupBg     = (shouldWarmup('bg')     && s.bgApi)     ? warmup(s.bgApi, 4000)     : null;
        setTimeout(function () {
            window.__nuomiWarmupBanner = (shouldWarmup('banner') && s.bannerApi) ? warmup(s.bannerApi, 4000) : null;
        }, 150);
    })();
    </script>

    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/frontend.js" defer></script>

    <?php if ($preloaderEnabled): ?>
    <?php foreach ($preloadersPublic as $pre): ?>
    <script type="text/x-preloader-css" data-key="<?= htmlspecialchars($pre['key']) ?>"><?= $pre['css'] ?></script>
    <?php endforeach; ?>
    <style id="nuomi-preloader-style" data-default-key="<?= htmlspecialchars($defaultStyleKey) ?>"><?= $defaultThemeCss ?></style>
    <?php endif; ?>
</head>
<body class="<?= $preloaderEnabled ? '' : 'no-preloader' ?>">

<?php if ($preloaderEnabled): ?>
<div id="preloader">
    <div class="preloader-content">
        <div class="preloader-avatar">
            <img src="<?= htmlspecialchars($siteCfg['avatar']) ?>" alt="">
        </div>
        <div class="preloader-title"><?= htmlspecialchars($siteCfg['title']) ?></div>
        <div class="preloader-progress">
            <div class="preloader-progress-bar" id="preloaderBar"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="bg-stage">
    <div class="bg-layer bg-current" id="bgLayer1"></div>
    <div class="bg-layer bg-next" id="bgLayer2"></div>
</div>

<div id="app">
    <?php if ($__latestAnn !== null): ?>
    <div class="home-announcement" id="homeAnnouncement" data-ann-id="<?= htmlspecialchars($__latestAnn['id'] ?? '') ?>">
        <div class="home-announcement-inner">
            <span class="home-announcement-icon">📢</span>
            <div class="home-announcement-content">
                <span class="home-announcement-type type-<?= htmlspecialchars($__latestAnn['type'] ?? 'info') ?>">
                    <?= htmlspecialchars(['info'=>'通知','success'=>'成功','warning'=>'警告','error'=>'错误'][$__latestAnn['type'] ?? 'info'] ?? '通知') ?>
                </span>
                <span class="home-announcement-title"><?= htmlspecialchars($__latestAnn['title'] ?? '') ?></span>
            </div>
            <a href="announcement.php" class="home-announcement-link">查看详情 →</a>
            <button type="button" class="home-announcement-close" id="homeAnnouncementClose" title="关闭" aria-label="关闭公告">✕</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="header">
        <div class="banner-container" id="bannerContainer">
            <div class="banner-layers" id="bannerLayers">
                <div class="banner-layer banner-current" id="bannerLayer1"></div>
                <div class="banner-layer banner-next" id="bannerLayer2"></div>
            </div>
            <div class="logo" id="avatarLogo">
                <img src="<?= htmlspecialchars($siteCfg['avatar']) ?>" alt="">
            </div>
        </div>
        <div class="author-name"><?= htmlspecialchars($siteCfg['title']) ?></div>
        <p class="desc"><?= htmlspecialchars($siteCfg['subtitle']) ?></p>
    </div>

    <div class="body">
        <div class="tab-row">
            <div class="tab-list" id="tabList">
                <?php foreach ($categoryObjects as $i => $catObj): ?>
                    <div class="tab-item <?= $i === 0 ? ' active' : '' ?>" data-cat="<?= htmlspecialchars($catObj['name']) ?>">
                        <?php if (!empty($catObj['icon'])): ?>
                            <?php if (substr($catObj['icon'], 0, 7) === 'photos/'): ?>
                                <img class="tab-icon-img" src="<?= htmlspecialchars($catObj['icon']) ?>" alt="">
                            <?php else: ?>
                                <span class="tab-icon"><?= htmlspecialchars($catObj['icon']) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span class="text"><?= htmlspecialchars($catObj['name']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="tab-indicator" id="tabIndicator"></div>
            </div>
        </div>

        <div class="app-lists-wrap">
        <?php foreach ($categoryObjects as $i => $catObj):
            $catName = $catObj['name'];
        ?>
            <div class="app-list" data-cat="<?= htmlspecialchars($catName) ?>" style="<?= $i === 0 ? 'display:flex' : 'display:none' ?>">
                <?php if (isset($cardsByCat[$catName]) && count($cardsByCat[$catName]) > 0): ?>
                    <?php foreach ($cardsByCat[$catName] as $card):
                        $escTitle = htmlspecialchars($card['name'] ?? '', ENT_QUOTES);
                        $escSub   = htmlspecialchars($card['sub']  ?? '', ENT_QUOTES);
                    ?>
                        <?php if (($card['type'] ?? 'link') === 'image'): ?>
                            <a href="javascript:void(0)" class="item card-item" data-type="image" data-image="<?= htmlspecialchars($card['image'] ?? '') ?>" data-tooltip-title="<?= $escTitle ?>" data-tooltip-sub="<?= $escSub ?>">
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($card['href'] ?? 'javascript:void(0)') ?>" class="item card-item" data-type="link" target="_blank" rel="noopener" data-tooltip-title="<?= $escTitle ?>" data-tooltip-sub="<?= $escSub ?>">
                        <?php endif; ?>
                            <div class="content-wrap">
                                <div class="img-wrap">
                                    <?php if (!empty($card['icon'])): ?>
                                        <img src="<?= htmlspecialchars($card['icon']) ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                </div>
                                <div class="card-text">
                                    <p class="app-name"><?= htmlspecialchars($card['name'] ?? '') ?></p>
                                    <?php if (!empty($card['sub'])): ?>
                                        <span class="app-sub"><?= htmlspecialchars($card['sub']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-tip">该分类暂无卡片</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>

        <div class="control-bottom">
            <button class="ctrl-btn theme-toggle-btn" id="frontThemeBtn" title="切换主题" aria-label="切换主题">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor" stroke="none"/></svg>
            </button>
            <button class="ctrl-btn loop-switch" title="轮播已关闭（点击开启）" aria-label="轮播已关闭（点击开启）">
                <svg class="icon-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                <svg class="icon-pause" style="display:none" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>
                <svg class="icon-fresh" style="display:none" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h8l-1 8 10-12h-8z"/></svg>
            </button>
            <?php if ($preloaderEnabled && count($preloadersPublic) > 1): ?>
            <button class="ctrl-btn preloader-style-btn" id="preloaderStyleBtn" title="选择加载动画" aria-label="选择加载动画">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 3a9 9 0 0 1 9 9"></path>
                </svg>
            </button>
            <?php endif; ?>
        </div>

        <?php
        /* 页脚版权：允许 HTML 渲染（用户可填备案号链接等） */
        $__copyright     = trim((string)($siteCfg['copyright'] ?? ''));
        $__hasCopyright  = ($__copyright !== '');

        /* Powered by / GitHub 链接：硬编码为原作者信息，不读取 site 配置 */
        $__poweredBy     = '糯米の引导页';
        $__poweredUrl    = 'https://github.com/nmrt0316/MochiNav';
        $__githubUrl     = 'https://github.com/nmrt0316';
        ?>
        <?php if ($__hasCopyright): ?>
        <div class="footer-credit">
            <div class="footer-row footer-row-main">
                <span class="footer-copyright"><?= $__copyright /* 允许 HTML 渲染 */ ?></span>
            </div>
            <div class="footer-row footer-row-powered">
                <span class="footer-powered-prefix">Powered by</span>
                <a href="<?= $__poweredUrl ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="footer-powered-link"><?= $__poweredBy ?></a>
                <span class="footer-sep" aria-hidden="true">·</span>
                <a href="<?= $__githubUrl ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="footer-link">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                    <span>GitHub</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="myModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <img id="modalImage" alt="Image" style="width:100%;display:none;">
    </div>
</div>

<?php if ($preloaderEnabled && count($preloadersPublic) > 1): ?>
<div class="preloader-picker" id="preloaderPicker">
    <div class="preloader-picker-title">选择加载动画</div>
    <?php foreach ($preloadersPublic as $pre): ?>
        <div class="preloader-picker-item" data-key="<?= htmlspecialchars($pre['key']) ?>">
            <span class="preloader-picker-check">✓</span>
            <div class="preloader-picker-info">
                <div class="preloader-picker-name"><?= htmlspecialchars($pre['name']) ?></div>
                <?php if (!empty($pre['desc'])): ?>
                    <div class="preloader-picker-desc"><?= htmlspecialchars($pre['desc']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <button type="button" class="preloader-picker-reset" data-key="">跟随默认</button>
</div>
<?php endif; ?>

<script>
(function () {
    var scripts = [
        <?php foreach ($jsEffectsPublic as $eff): ?>
            <?php if (!empty($eff['loadScript'])): ?>
            <?= json_encode($eff['file']) ?>,
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ((!empty($boardGirlPublic['enabled']) && count($boardGirlPublic['girls']) > 0) || count($jsEffectsPublic) > 0): ?>
        "assets/mascot.js"
        <?php endif; ?>
    ];
    
    if (scripts.length === 0) return;

    var executed = false;
    function executeScripts() {
        if (executed) return;
        executed = true;
        scripts.forEach(function(src) {
            if (!src) return;
            var s = document.createElement('script');
            s.src = src;
            s.async = false;
            document.body.appendChild(s);
        });
    }

    if (document.body.classList.contains('page-ready')) {
        executeScripts();
    } else {
        var observer = new MutationObserver(function(mutations) {
            if (document.body.classList.contains('page-ready')) {
                observer.disconnect();
                executeScripts();
            }
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        
        setTimeout(function() {
            observer.disconnect();
            executeScripts();
        }, 4500);
    }
})();
</script>

<?php include __DIR__ . '/analytics.php'; ?>
</body>
<?php ob_end_flush(); ?>