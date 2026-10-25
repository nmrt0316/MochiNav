/* ==========================================================================
   糯米引导页 · 前台看板娘控制器 mascot.js
   ========================================================================== */
(function () {
    'use strict';

    /* ==========================================================================
       1. 常量
       ========================================================================== */
    const STORAGE_KEY = 'effects_states';
    const GIRL_KEY = 'board_girl_index';
    const POS_KEY = 'board_girl_pos';
    const TIP_TOGGLE_KEY = 'board_girl_tip_enabled';
    const GIRL_VISIBLE_KEY = 'board_girl_visible';
    const COOKIE_KEY = 'nuomi_effects_off';

    const EDGE = 8, HOLD_MS = 350, MOVE_TOLERANCE = 5;
    const GIRL_MAX_RETRY = 2;
    const GIRL_RETRY_BASE_DELAY = 600;

    /* 主题色过渡 */
    const THEME_T = 'background-color 0.5s ease-in-out, border-color 0.5s ease-in-out, color 0.5s ease-in-out, box-shadow 0.5s ease-in-out';

    const DEFAULT_TIPS = {
        enabled: true,
        duration: 3000,
        texts: ['你好呀，我是这里的小助手～', '欢迎来到糯米の引导页！', '希望你会喜欢这里～'],
        periodTexts: { night: [], morning: [], noon: [], afternoon: [], evening: [] },
        api: '', apiField: 'text'
    };

    /* ==========================================================================
       2. 配置读取
       ========================================================================== */
    var globalCfg = window.NUOMI_CONFIG || {};
    var bgCfg = globalCfg.boardGirl || {};
    var BG_ENABLED = bgCfg.enabled !== false;

    var GIRLS = [];
    if (Array.isArray(bgCfg.girls)) {
        bgCfg.girls.forEach(function (g) {
            if (g && typeof g.file === 'string' && g.file.trim() !== '') {
                GIRLS.push({ file: g.file.trim(), name: (g.name && String(g.name).trim()) || g.file.split('/').pop() });
            }
        });
    }

    var effectsCfg = Array.isArray(globalCfg.jsEffects) ? globalCfg.jsEffects : [];

    var tipsCfg = Object.assign({}, DEFAULT_TIPS, globalCfg.tips || {});
    if (!Array.isArray(tipsCfg.texts) || tipsCfg.texts.length === 0) tipsCfg.texts = DEFAULT_TIPS.texts.slice();
    if (!tipsCfg.periodTexts || typeof tipsCfg.periodTexts !== 'object') {
        tipsCfg.periodTexts = DEFAULT_TIPS.periodTexts;
    } else {
        var mergedPeriod = {};
        ['night', 'morning', 'noon', 'afternoon', 'evening'].forEach(function (p) {
            mergedPeriod[p] = Array.isArray(tipsCfg.periodTexts[p]) ? tipsCfg.periodTexts[p].slice() : [];
        });
        tipsCfg.periodTexts = mergedPeriod;
    }
    if (typeof tipsCfg.duration !== 'number' || tipsCfg.duration <= 0) tipsCfg.duration = DEFAULT_TIPS.duration;

    var registryMap = {};
    (window.EffectsRegistry || []).forEach(function (item) {
        if (item && item.name) registryMap[item.name] = item;
    });

    var MODULES = [];
    var MODULE_MAP = {};
    effectsCfg.forEach(function (cfg) {
        if (!cfg) return;
        var matchKey = (cfg.matchKey || cfg.name || '').trim();
        if (!matchKey) return;

        /* 优先用 matchKey 匹配，未命中时再用 name 二次尝试 */
        var impl = registryMap[matchKey];
        if (!impl && cfg.name && cfg.name !== matchKey) {
            impl = registryMap[cfg.name];
        }
        var implModule = (impl && impl.module) ? impl.module : null;
        var cfgEnabled = !!cfg.enabled;
        var cfgAutoToggle = !!cfg.autoToggle;

        var runtimeToggle;
        if (implModule) {
            runtimeToggle = (typeof implModule.start === 'function')
                && (typeof implModule.stop === 'function');
        } else {
            runtimeToggle = cfgAutoToggle;
        }

        var showInPanel = implModule
            ? (cfgEnabled || runtimeToggle)
            : (cfgEnabled || cfgAutoToggle);
        if (!showInPanel) return;

        var displayName = (cfg.name && String(cfg.name).trim())
            || (impl && impl.displayName)
            || matchKey;

        var entry = {
            matchKey: matchKey,
            displayName: displayName,
            icon: cfg.icon || (impl && impl.icon) || '✨',
            file: cfg.file || '',
            module: implModule,
            cfgAutoToggle: cfgAutoToggle,
            runtimeToggle: runtimeToggle,
            defaultEnabled: cfgEnabled,
            unloaded: !implModule
        };
        MODULES.push(entry);
        MODULE_MAP[matchKey] = entry;
    });

    var hasGirl = BG_ENABLED && GIRLS.length > 0;
    if (!hasGirl && MODULES.length === 0) return;

    /* 形象图片预热 */
    if (BG_ENABLED && GIRLS.length > 0) {
        try {
            var _warmIdx = 0;
            var _savedIdx = null;
            try { _savedIdx = localStorage.getItem(GIRL_KEY); } catch (e) {}
            if (_savedIdx !== null) {
                var _n = parseInt(_savedIdx, 10);
                if (!isNaN(_n) && _n >= 0 && _n < GIRLS.length) _warmIdx = _n;
            }
            var _warmFile = GIRLS[_warmIdx].file;
            var _warmSrc = /^https?:\/\//i.test(_warmFile) ? _warmFile : _warmFile.replace(/^\/+/, '');
            var _warmImg = new Image();
            _warmImg.decoding = 'async';
            _warmImg.src = _warmSrc;
        } catch (e) {}
    }

    /* ==========================================================================
       3. 通用工具
       ========================================================================== */
    function resolveGirlSrc(file) {
        if (!file) return '';
        if (/^https?:\/\//i.test(file)) return file;
        return file.replace(/^\/+/, '');
    }

    function loadStates() {
        try { var s = localStorage.getItem(STORAGE_KEY); if (s) return JSON.parse(s); } catch (e) {}
        return {};
    }
    function saveStates(s) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); } catch (e) {}
    }

    function loadGirlIndex() {
        try { var i = localStorage.getItem(GIRL_KEY); if (i !== null) return parseInt(i, 10); } catch (e) {}
        return 0;
    }
    function saveGirlIndex(i) {
        try { localStorage.setItem(GIRL_KEY, String(i)); } catch (e) {}
    }

    function isTipEnabled() {
        try {
            var v = localStorage.getItem(TIP_TOGGLE_KEY);
            return v === null ? tipsCfg.enabled !== false : v === '1';
        } catch (e) { return tipsCfg.enabled !== false; }
    }
    function setTipEnabled(e) {
        try { localStorage.setItem(TIP_TOGGLE_KEY, e ? '1' : '0'); } catch (e) {}
    }

    function isGirlVisible() {
        try {
            var v = localStorage.getItem(GIRL_VISIBLE_KEY);
            return v === null ? true : v === '1';
        } catch (e) { return true; }
    }
    function setGirlVisible(v) {
        try { localStorage.setItem(GIRL_VISIBLE_KEY, v ? '1' : '0'); } catch (e) {}
    }

    function writeOffCookie(keys) {
        try {
            var uniq = [], seen = {};
            (keys || []).forEach(function (k) {
                if (typeof k === 'string' && k && !seen[k]) { seen[k] = 1; uniq.push(k); }
            });
            var payload = encodeURIComponent(JSON.stringify(uniq));
            while (payload.length > 3500 && uniq.length > 0) {
                uniq.pop();
                payload = encodeURIComponent(JSON.stringify(uniq));
            }
            var maxAge = 60 * 60 * 24 * 365;
            document.cookie = COOKIE_KEY + '=' + payload + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
        } catch (e) {}
    }

    /* 按需加载模块：先查全局注册表，再尝试动态插入 <script> */
    function ensureModuleLoaded(entry) {
        if (entry.module) return Promise.resolve(entry.module);

        var reg = window.EffectsRegistry || [];
        for (var i = 0; i < reg.length; i++) {
            if (reg[i] && reg[i].name === entry.matchKey && reg[i].module) {
                entry.module = reg[i].module;
                entry.unloaded = false;
                entry.runtimeToggle = typeof reg[i].module.start === 'function'
                    && typeof reg[i].module.stop === 'function';
                return Promise.resolve(entry.module);
            }
        }

        if (!entry.file) return Promise.reject(new Error('无文件路径'));
        if (entry._loadPromise) return entry._loadPromise;

        /* 同路径脚本已存在但未注册 —— 直接失败，避免 onload 永不触发导致按钮卡灰。
           script.src 始终解析为绝对 URL，需与 entry.file 的绝对形式比较 */
        var absSrc;
        try { absSrc = new URL(entry.file, location.href).href; }
        catch (e) { absSrc = entry.file; }

        var existingScripts = document.querySelectorAll('script[src]');
        for (var s = 0; s < existingScripts.length; s++) {
            if (existingScripts[s].src === absSrc) {
                return Promise.reject(new Error('脚本已存在但未注册对应模块，请检查 matchKey'));
            }
        }

        entry._loadPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = entry.file;
            script.async = false;
            script.onload = function () {
                var regs = window.EffectsRegistry || [];
                var hit = null;
                for (var j = 0; j < regs.length; j++) {
                    if (regs[j] && regs[j].name === entry.matchKey) { hit = regs[j]; break; }
                }
                if (hit && hit.module) {
                    entry.module = hit.module;
                    entry.unloaded = false;
                    entry.runtimeToggle = typeof hit.module.start === 'function'
                        && typeof hit.module.stop === 'function';
                    resolve(hit.module);
                } else {
                    entry._loadPromise = null;
                    reject(new Error('脚本已加载，但未注册对应模块'));
                }
            };
            script.onerror = function () {
                entry._loadPromise = null;
                reject(new Error('脚本加载失败'));
            };
            document.head.appendChild(script);
        });

        return entry._loadPromise;
    }

    function showRefreshMask(msg) {
        if (document.getElementById('nuomi-refresh-mask')) return;
        var mask = document.createElement('div');
        mask.id = 'nuomi-refresh-mask';
        mask.innerHTML = '<div class="nuomi-refresh-spinner"></div><div class="nuomi-refresh-text">' + (msg || '正在刷新页面…') + '</div>';
        document.body.appendChild(mask);
        setTimeout(function () {
            mask.style.transition = 'opacity 0.25s ease';
            mask.style.opacity = '0';
            setTimeout(function () { window.location.reload(); }, 260);
        }, 700);
    }

    /* ==========================================================================
       4. 图片加载器
       ========================================================================== */
    var girlLoadTokens = new WeakMap();

    function bustGirlSrc(src, attempt) {
        return src + (src.indexOf('?') === -1 ? '?' : '&') + 'nuomi_retry=' + attempt + '&t=' + Date.now();
    }

    function loadGirlImage(imgEl, src, opts) {
        opts = opts || {};
        if (!imgEl || !src) { if (opts.onDone) opts.onDone(false); return; }

        var token = (girlLoadTokens.get(imgEl) || 0) + 1;
        girlLoadTokens.set(imgEl, token);
        var attempt = 0, timer = null, settled = false;

        function isCurrent() { return girlLoadTokens.get(imgEl) === token; }

        function cleanup() {
            imgEl.removeEventListener('load', onLoad);
            imgEl.removeEventListener('error', onError);
            if (timer) { clearTimeout(timer); timer = null; }
        }

        function settle(ok) {
            if (settled) return;
            settled = true;
            cleanup();
            if (opts.onDone) opts.onDone(ok);
        }

        function onLoad() { if (isCurrent()) settle(true); }

        function onError() {
            if (!isCurrent() || settled) return;
            if (attempt >= GIRL_MAX_RETRY) { settle(false); return; }
            attempt++;
            timer = setTimeout(function () {
                timer = null;
                if (!isCurrent() || settled) return;
                imgEl.src = bustGirlSrc(src, attempt);
            }, GIRL_RETRY_BASE_DELAY * attempt);
        }

        imgEl.addEventListener('load', onLoad);
        imgEl.addEventListener('error', onError);
        imgEl.src = src;
        if (imgEl.complete && imgEl.naturalWidth > 0) onLoad();
    }

    /* ==========================================================================
       5. 位置与尺寸
       ========================================================================== */
    function calcGirlSize() {
        var w = window.innerWidth, h = window.innerHeight;
        if (w > h) return Math.max(56, Math.min(96, Math.floor(h * 0.22)));
        if (w < 480) return 90;
        if (w < 768) return 110;
        return 120;
    }

    function clampPosition(left, top, w, h) {
        var maxLeft = Math.max(EDGE, window.innerWidth - w - EDGE);
        var maxTop = Math.max(EDGE, window.innerHeight - h - EDGE);
        return {
            left: Math.min(Math.max(EDGE, left), maxLeft),
            top: Math.min(Math.max(EDGE, top), maxTop)
        };
    }

    function readPos() {
        try {
            var s = localStorage.getItem(POS_KEY);
            if (!s) return null;
            var p = JSON.parse(s);
            return (p && typeof p === 'object') ? p : null;
        } catch (e) { return null; }
    }
    function clearPos() {
        try { localStorage.removeItem(POS_KEY); } catch (e) {}
    }

    function posRange(base) {
        return {
            x: Math.max(1, window.innerWidth - base - EDGE * 2),
            y: Math.max(1, window.innerHeight - base - EDGE * 2)
        };
    }

    function readNormalizedPos(base) {
        var range = posRange(base);
        var hx = 1, hy = 1;
        var p = readPos();
        if (p) {
            if (isFinite(p.hx) && isFinite(p.hy)) {
                hx = p.hx; hy = p.hy;
            } else if (isFinite(p.cx) && isFinite(p.cy)) {
                hx = (p.cx - EDGE - base / 2) / range.x;
                hy = (p.cy - EDGE - base / 2) / range.y;
            } else if (isFinite(p.rx) && isFinite(p.ry)) {
                hx = (p.rx * window.innerWidth - EDGE - base / 2) / range.x;
                hy = (p.ry * window.innerHeight - EDGE - base / 2) / range.y;
            }
        }
        return {
            hx: Math.min(1, Math.max(0, hx)),
            hy: Math.min(1, Math.max(0, hy))
        };
    }

    function writePos(el) {
        if (!el) return;
        var rect = el.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return;

        var base = calcGirlSize();
        var range = posRange(base);
        var centerX = rect.left + rect.width / 2;
        var centerY = rect.top + rect.height / 2;

        try {
            localStorage.setItem(POS_KEY, JSON.stringify({
                hx: Math.min(1, Math.max(0, (centerX - EDGE - base / 2) / range.x)),
                hy: Math.min(1, Math.max(0, (centerY - EDGE - base / 2) / range.y))
            }));
        } catch (e) {}
    }

    function applyPos(el, fallbackSize) {
        if (!el) return;
        var w = el.offsetWidth || fallbackSize || 46;
        var h = el.offsetHeight || fallbackSize || 46;
        var base = calcGirlSize();
        var range = posRange(base);
        var n = readNormalizedPos(base);

        var baseLeft = EDGE + n.hx * range.x;
        var baseTop = EDGE + n.hy * range.y;

        el.style.left = (baseLeft + (base - w) / 2) + 'px';
        el.style.top = (baseTop + (base - h) / 2) + 'px';
    }

    /* ==========================================================================
       6. 语录系统
       ========================================================================== */
    function getCurrentPeriod() {
        var h = new Date().getHours();
        if (h >= 5 && h < 11) return 'morning';
        if (h >= 11 && h < 13) return 'noon';
        if (h >= 13 && h < 17) return 'afternoon';
        if (h >= 17 && h < 23) return 'evening';
        return 'night';
    }

    var tipQueue = [], lastTipText = '', tipQueuePeriod = null;

    function shuffleArray(arr) {
        var a = arr.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = a[i]; a[i] = a[j]; a[j] = t;
        }
        return a;
    }

    function refillTipQueue() {
        var period = getCurrentPeriod();
        var periodArr = ((tipsCfg.periodTexts && tipsCfg.periodTexts[period]) || []).slice();
        var generalArr = (tipsCfg.texts || []).slice();
        var pool = shuffleArray(shuffleArray(periodArr).concat(shuffleArray(generalArr)));
        if (pool.length === 0) pool = DEFAULT_TIPS.texts.slice();
        if (pool.length > 1 && pool[0] === lastTipText) {
            var k = 1 + Math.floor(Math.random() * (pool.length - 1));
            var tmp = pool[0]; pool[0] = pool[k]; pool[k] = tmp;
        }
        tipQueue = pool;
        tipQueuePeriod = period;
    }

    function pickLocalTipText() {
        var curPeriod = getCurrentPeriod();
        if (curPeriod !== tipQueuePeriod) { tipQueue = []; lastTipText = ''; }
        if (tipQueue.length === 0) refillTipQueue();
        var text = tipQueue.shift();
        lastTipText = text;
        return text;
    }

    function fetchApiTipText() {
        if (!tipsCfg.api) return Promise.resolve(null);
        return fetch(tipsCfg.api, { method: 'GET', mode: 'cors' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                var field = tipsCfg.apiField || 'text';
                if (typeof data === 'string' && data) return data;
                if (data && data[field] != null) return String(data[field]);
                return null;
            })
            .catch(function () { return null; });
    }

    var lastTipSource = null;
    function fetchTipText() {
        if (lastTipSource === null) {
            lastTipSource = 'local';
            return Promise.resolve(pickLocalTipText());
        }
        var wantRemote = (lastTipSource === 'local') && !!tipsCfg.api;
        if (!wantRemote) {
            lastTipSource = 'local';
            return Promise.resolve(pickLocalTipText());
        }

        var timeoutId = null;
        var timeoutPromise = new Promise(function (resolve) {
            timeoutId = setTimeout(function () { resolve(null); }, 1500);
        });
        return Promise.race([fetchApiTipText(), timeoutPromise]).then(function (remoteText) {
            clearTimeout(timeoutId);
            if (remoteText) { lastTipSource = 'remote'; return remoteText; }
            lastTipSource = 'local'; return pickLocalTipText();
        });
    }

    /* ==========================================================================
       7. 控制器
       ========================================================================== */
    function initController() {

        /* ---------- 7.1 状态初始化 ---------- */
        var states = loadStates();
        MODULES.forEach(function (item) {
            if (!(item.matchKey in states)) states[item.matchKey] = item.defaultEnabled;
        });
        saveStates(states);

        function updateOffCookieFromStates() {
            var off = [];
            Object.keys(states).forEach(function (k) {
                if (states[k] === false) off.push(k);
            });
            writeOffCookie(off);
        }
        updateOffCookieFromStates();

        /* ---------- 7.2 看板娘按钮与形象 ---------- */
        var currentGirlIndex = loadGirlIndex();
        if (isNaN(currentGirlIndex) || currentGirlIndex < 0 || currentGirlIndex >= GIRLS.length) currentGirlIndex = 0;
        var initialGirlVisible = hasGirl ? isGirlVisible() : false;

        var btn = null, girlImg = null, girlImgToken = 0, initialTipShown = false;

        if (hasGirl) {
            btn = document.createElement('button');
            btn.id = 'effects-toggle-btn';
            btn.setAttribute('aria-label', '看板娘（长按拖动，点击看语录）');
            if (!initialGirlVisible) btn.classList.add('girl-hidden');
            document.body.appendChild(btn);

            var fallbackIcon = document.createElement('span');
            fallbackIcon.className = 'girl-fallback-icon';
            fallbackIcon.setAttribute('aria-hidden', 'true');
            fallbackIcon.textContent = '✨';
            btn.appendChild(fallbackIcon);
        }

        function setGirlFallback(on) {
            if (!btn) return;
            btn.classList.toggle('fallback-mode', !!on);
            btn.setAttribute('aria-label', on ? '看板娘形象加载失败，点击打开控制面板' : '看板娘（长按拖动，点击看语录）');
        }

        function applyGirlImage(index) {
            if (!btn || !GIRLS[index]) return;

            var token = ++girlImgToken;
            var girl = GIRLS[index];
            var src = resolveGirlSrc(girl.file);
            var oldImg = girlImg;

            var pre = document.createElement('img');
            pre.decoding = 'async';
            loadGirlImage(pre, src, {
                onDone: function (ok) {
                    if (token !== girlImgToken) return;

                    if (!ok) {
                        setGirlFallback(true);
                        initialTipShown = true;
                        return;
                    }

                    pre.id = 'board-girl-img';
                    pre.alt = girl.name;
                    btn.appendChild(pre);
                    girlImg = pre;

                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            if (token !== girlImgToken) {
                                if (pre.parentNode) pre.parentNode.removeChild(pre);
                                return;
                            }

                            setGirlFallback(false);
                            pre.classList.add('girl-img-shown');

                            if (oldImg && oldImg.parentNode) {
                                oldImg.classList.remove('girl-img-shown');
                                setTimeout(function () {
                                    if (oldImg.parentNode) oldImg.parentNode.removeChild(oldImg);
                                }, 600);
                            }

                            if (!initialTipShown && hasGirl && isTipEnabled() && isGirlVisible()) {
                                initialTipShown = true;
                                setTimeout(showTip, 260);
                            }
                        });
                    });
                }
            });
        }

        if (hasGirl) applyGirlImage(currentGirlIndex);

        /* ---------- 7.3 面板 DOM 与样式 ---------- */
        var panel = document.createElement('div');
        panel.id = 'effects-panel';
        panel.style.display = 'none';
        document.body.appendChild(panel);

        var style = document.createElement('style');
        style.textContent = `
            #effects-toggle-btn {
                position: fixed; left: 0; top: 0; width: 120px; height: 120px;
                border: none; background: transparent !important;
                backdrop-filter: none !important; -webkit-backdrop-filter: none !important;
                cursor: pointer; z-index: 260; padding: 0; outline: none; box-shadow: none;
                display: flex; align-items: center; justify-content: center;
                touch-action: none; user-select: none; -webkit-user-select: none;
                -webkit-tap-highlight-color: transparent;
                opacity: 1; transform: scale(1); transform-origin: center center;
                transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                            transform 0.5s cubic-bezier(0.34, 1.2, 0.64, 1),
                            ${THEME_T};
            }
            #effects-toggle-btn:not(.girl-hidden):hover { transform: scale(1.08); }
            #effects-toggle-btn:not(.girl-hidden):active { transform: scale(0.92); }
            #effects-toggle-btn.dragging { transform: scale(1.06); cursor: grabbing; transition: none; }
            #effects-toggle-btn.animating-pos {
                transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                            transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                            left 0.5s cubic-bezier(.22,.8,.3,1),
                            top 0.5s cubic-bezier(.22,.8,.3,1),
                            ${THEME_T} !important;
            }
            #effects-toggle-btn.girl-hidden { opacity: 0; transform: scale(0.35); pointer-events: none; }

            #effects-toggle-btn img {
                position: absolute; left: 0; top: 0;
                width: 100%; height: 100%; object-fit: contain; display: block;
                opacity: 0; transform: scale(0.35); transform-origin: center center;
                transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                            transform 0.5s cubic-bezier(0.34, 1.2, 0.64, 1);
                background: transparent;
                pointer-events: none; -webkit-user-drag: none; user-select: none;
                will-change: opacity, transform;
            }
            #effects-toggle-btn img.girl-img-shown { opacity: 1; transform: scale(1); }
            #effects-toggle-btn .girl-fallback-icon {
                display: none;
                position: absolute; left: 0; top: 0;
                width: 100%; height: 100%;
                align-items: center; justify-content: center;
                font-size: 20px; line-height: 1; pointer-events: none;
            }
            #effects-toggle-btn.fallback-mode {
                width: 46px !important; height: 46px !important; border-radius: 50%;
                border: 1px solid rgba(255,255,255,0.15);
                background: rgba(30,30,40,0.55) !important;
                backdrop-filter: blur(20px) !important; -webkit-backdrop-filter: blur(20px) !important;
                box-shadow: 0 6px 24px rgba(0,0,0,0.35);
            }
            #effects-toggle-btn.fallback-mode img { display: none !important; }
            #effects-toggle-btn.fallback-mode .girl-fallback-icon { display: flex; }

            #effects-mini-btn {
                position: fixed; left: 0; top: 0; width: 46px; height: 46px; border-radius: 50%;
                border: 1px solid rgba(255,255,255,0.15);
                background: rgba(30,30,40,0.55);
                backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
                color: #fff; font-size: 20px; cursor: pointer; z-index: 260;
                display: flex; align-items: center; justify-content: center;
                box-shadow: 0 6px 24px rgba(0,0,0,0.35); padding: 0; outline: none;
                touch-action: none; user-select: none; -webkit-user-select: none;
                -webkit-tap-highlight-color: transparent;
                opacity: 0; transform: scale(0.35); transform-origin: center center;
                pointer-events: none;
                transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                            transform 0.5s cubic-bezier(0.34, 1.2, 0.64, 1),
                            ${THEME_T};
            }
            #effects-mini-btn.visible { opacity: 1; transform: scale(1); pointer-events: auto; }
            #effects-mini-btn.visible:hover { transform: scale(1.08); }
            #effects-mini-btn.visible:active { transform: scale(0.94); }
            #effects-mini-btn.dragging { transform: scale(1.06); cursor: grabbing; transition: none; }
            #effects-mini-btn.animating-pos {
                transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                            transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                            left 0.5s cubic-bezier(.22,.8,.3,1),
                            top 0.5s cubic-bezier(.22,.8,.3,1),
                            ${THEME_T} !important;
            }

            #effects-panel {
                position: fixed; left: 0; top: 0;
                width: min(220px, calc(100vw - 32px));
                max-height: min(72vh, 520px);
                overflow-y: auto; overflow-x: hidden; box-sizing: border-box;
                background: rgba(30, 30, 40, 0.55);
                backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
                border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 8px 48px rgba(0, 0, 0, 0.4);
                padding: 12px 10px; z-index: 200;
                opacity: 0; transform: scale(0.92) translateY(8px);
                transition: opacity 0.3s ease,
                            transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                            ${THEME_T};
                pointer-events: none;
                font-family: system-ui, -apple-system, sans-serif;
                color: rgba(255, 255, 255, 0.92);
            }
            #effects-panel.open { opacity: 1; transform: scale(1) translateY(0); pointer-events: auto; }
            #effects-panel.panel-moving {
                transition: opacity 0.3s ease,
                            transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                            left 0.5s cubic-bezier(.22,.8,.3,1),
                            top 0.5s cubic-bezier(.22,.8,.3,1),
                            ${THEME_T};
            }

            .panel-title {
                font-size: 11px; font-weight: 600; letter-spacing: 0.6px;
                color: rgba(255,255,255,0.4); text-transform: uppercase;
                margin: 2px 0 6px 4px;
                transition: color 0.5s ease-in-out;
            }
            .panel-divider {
                height: 1px; background: rgba(255,255,255,0.06); margin: 6px 0 8px 0;
                transition: background-color 0.5s ease-in-out;
            }

            .effect-item {
                display: flex; align-items: center; padding: 4px 6px;
                border-radius: 8px; transition: background 0.2s; cursor: default;
            }
            .effect-item:hover { background: rgba(255, 255, 255, 0.06); }
            .effect-item .effect-icon { font-size: 16px; width: 24px; text-align: center; margin-right: 8px; flex-shrink: 0; }
            .effect-item .effect-name {
                flex: 1; font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.85);
                min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                transition: color 0.5s ease-in-out;
            }

            .switch { position: relative; width: 36px; height: 20px; flex-shrink: 0; cursor: pointer; }
            .switch input { opacity: 0; width: 0; height: 0; }
            .switch .slider {
                position: absolute; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(255, 255, 255, 0.15); border-radius: 20px;
                transition: background-color 0.5s ease-in-out;
            }
            .switch .slider::before {
                content: ''; position: absolute; height: 14px; width: 14px;
                left: 3px; bottom: 3px; background: #fff; border-radius: 50%;
                transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
            }
            .switch input:checked + .slider { background: #6c5ce7; }
            .switch input:checked + .slider::before { transform: translateX(16px); }
            .switch input:disabled + .slider { opacity: 0.4; cursor: wait; }

            .girl-selector { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 2px; }
            .girl-option {
                width: 100%; aspect-ratio: 1; border-radius: 7px;
                border: 2px solid rgba(255,255,255,0.12);
                background: rgba(255,255,255,0.04);
                cursor: pointer; overflow: hidden;
                transition: transform 0.25s ease,
                            border-color 0.5s ease-in-out,
                            background-color 0.5s ease-in-out,
                            box-shadow 0.5s ease-in-out;
                padding: 2px;
                display: flex; align-items: center; justify-content: center;
                box-sizing: border-box;
            }
            .girl-option:hover { transform: scale(1.06); border-color: rgba(255,255,255,0.3); }
            .girl-option.active { border-color: #6c5ce7; box-shadow: 0 0 14px rgba(108, 92, 231, 0.25); transform: scale(1.02); }
            .girl-option img {
                width: 100%; height: 100%; object-fit: contain; display: block;
                background: transparent; pointer-events: none; -webkit-user-drag: none;
                opacity: 0; transition: opacity 0.25s ease;
            }
            .girl-option img.loaded { opacity: 1; }

            .panel-reset-pos {
                display: block; width: 100%; margin-top: 8px; padding: 6px 8px;
                border-radius: 8px; border: 1px solid rgba(255,255,255,0.12);
                background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.75);
                font-size: 12px; cursor: pointer; text-align: center;
                transition: background-color 0.5s ease-in-out,
                            border-color 0.5s ease-in-out,
                            color 0.5s ease-in-out;
            }
            .panel-reset-pos:hover { background: rgba(108,92,231,0.18); border-color: rgba(108,92,231,0.55); color: #fff; }
            .panel-reset-pos:active { transform: scale(0.97); }

            .girl-only-block {
                display: grid; grid-template-rows: 1fr;
                opacity: 1;
                transition: grid-template-rows 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s ease;
            }
            .girl-only-block.collapsed { grid-template-rows: 0fr; opacity: 0; pointer-events: none; }
            .girl-only-inner { overflow: hidden; min-height: 0; }

            #board-girl-tip {
                position: fixed; z-index: 300;
                display: flex; align-items: flex-start; gap: 6px;
                width: max-content; max-width: min(280px, calc(100vw - 48px)); min-width: 60px;
                background: rgba(255, 255, 255, 0.72); color: rgba(0, 0, 0, 0.88);
                backdrop-filter: blur(24px) saturate(180%);
                -webkit-backdrop-filter: blur(24px) saturate(180%);
                border-radius: 16px; padding: 12px 12px 12px 16px;
                font-size: 13px; line-height: 1.6;
                word-break: normal; overflow-wrap: break-word; line-break: strict;
                box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12), 0 4px 12px rgba(0, 0, 0, 0.08);
                opacity: 0; transform: translateY(8px) scale(0.96);
                transition: opacity 0.32s ease,
                            transform 0.32s cubic-bezier(0.34, 1.56, 0.64, 1),
                            background-color 0.5s ease-in-out,
                            color 0.5s ease-in-out,
                            box-shadow 0.5s ease-in-out;
                pointer-events: none;
                font-family: system-ui, -apple-system, sans-serif;
                user-select: none;
            }
            #board-girl-tip.show { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
            #board-girl-tip.show.switching {
                opacity: 0; transform: translateY(4px) scale(0.98);
                transition: opacity 0.22s ease, transform 0.22s ease;
            }
            #board-girl-tip .tip-text { flex: 1; min-width: 0; word-break: normal; overflow-wrap: break-word; }
            #board-girl-tip::after {
                content: ''; position: absolute; inset: 0;
                border-radius: 16px; border: 1px solid rgba(0, 0, 0, 0.05);
                pointer-events: none;
                transition: border-color 0.5s ease-in-out;
            }

            .tip-settings-btn {
                flex-shrink: 0; width: 18px; height: 18px; margin-top: 1px;
                border: none; background: transparent; color: rgba(0, 0, 0, 0.28);
                cursor: pointer; display: flex; align-items: center; justify-content: center;
                padding: 0;
                transition: color 0.5s ease-in-out,
                            transform 0.3s cubic-bezier(.34,1.2,.64,1);
                z-index: 2;
            }
            .tip-settings-btn svg { width: 14px; height: 14px; display: block; fill: currentColor; pointer-events: none; }
            .tip-settings-btn:hover, .tip-settings-btn:active { color: #6c5ce7; transform: rotate(90deg); }

            #nuomi-refresh-mask {
                position: fixed; inset: 0; z-index: 800;
                display: flex; flex-direction: column;
                align-items: center; justify-content: center;
                background: rgba(0, 0, 0, 0.55);
                backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
                color: #fff; opacity: 0;
                animation: nuomiRefreshFade 0.25s forwards;
                font-family: system-ui, -apple-system, sans-serif;
            }
            @keyframes nuomiRefreshFade { to { opacity: 1; } }
            .nuomi-refresh-spinner {
                width: 42px; height: 42px;
                border: 4px solid rgba(255, 255, 255, 0.15);
                border-top-color: #fff; border-radius: 50%;
                animation: nuomiRefreshSpin 0.8s linear infinite;
                margin-bottom: 14px;
            }
            @keyframes nuomiRefreshSpin { to { transform: rotate(360deg); } }
            .nuomi-refresh-text {
                font-size: 14px; font-weight: 500;
                letter-spacing: 1.5px;
                color: rgba(255, 255, 255, 0.92);
            }

            html[data-theme="dark"] #board-girl-tip {
                background: rgba(40, 42, 55, 0.98); color: #e8eaf6;
                box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4), 0 4px 12px rgba(0, 0, 0, 0.2);
            }
            html[data-theme="dark"] #board-girl-tip::after { border-color: rgba(255, 255, 255, 0.08); }
            html[data-theme="dark"] .tip-settings-btn { color: rgba(255, 255, 255, 0.42); }
            html[data-theme="dark"] .tip-settings-btn:hover, html[data-theme="dark"] .tip-settings-btn:active { color: #a5b4fc; }

            html:not([data-theme="dark"]) #effects-toggle-btn.fallback-mode {
                border-color: rgba(255, 255, 255, 0.6);
                background: rgba(255, 255, 255, 0.55) !important;
                box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
                color: rgba(0, 0, 0, 0.75);
            }

            html:not([data-theme="dark"]) #effects-mini-btn {
                border-color: rgba(255, 255, 255, 0.6);
                background: rgba(255, 255, 255, 0.55);
                color: rgba(0, 0, 0, 0.75);
                box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
            }

            html:not([data-theme="dark"]) #effects-panel {
                background: rgba(255, 255, 255, 0.55);
                border-color: rgba(255, 255, 255, 0.65);
                box-shadow: 0 8px 48px rgba(0, 0, 0, 0.12);
                color: rgba(0, 0, 0, 0.88);
            }

            html:not([data-theme="dark"]) .panel-title { color: rgba(0, 0, 0, 0.42); }
            html:not([data-theme="dark"]) .panel-divider { background: rgba(0, 0, 0, 0.06); }
            html:not([data-theme="dark"]) .effect-item:hover { background: rgba(0, 0, 0, 0.04); }
            html:not([data-theme="dark"]) .effect-item .effect-name { color: rgba(0, 0, 0, 0.85); }

            html:not([data-theme="dark"]) .switch input:not(:checked) + .slider { background: rgba(0, 0, 0, 0.12); }

            html:not([data-theme="dark"]) .girl-option:not(.active) {
                border-color: rgba(0, 0, 0, 0.1);
                background: rgba(0, 0, 0, 0.03);
            }
            html:not([data-theme="dark"]) .girl-option:not(.active):hover {
                border-color: rgba(0, 0, 0, 0.22);
            }

            html:not([data-theme="dark"]) .panel-reset-pos {
                border-color: rgba(0, 0, 0, 0.1);
                background: rgba(0, 0, 0, 0.03);
                color: rgba(0, 0, 0, 0.7);
            }
            html:not([data-theme="dark"]) .panel-reset-pos:hover {
                background: rgba(108, 92, 231, 0.12);
                border-color: rgba(108, 92, 231, 0.5);
                color: #6c5ce7;
            }
        `;
        document.head.appendChild(style);

        /* ---------- 7.4 面板定位引擎 ---------- */
        var SETTINGS_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>';

        var isOpen = false, isDraggingGirl = false, lastPanelStrategy = null;
        var panelSizeCache = { w: 0, h: 0 };
        function invalidatePanelSize() { panelSizeCache.w = 0; panelSizeCache.h = 0; }
        function getPanelSize() {
            if (panelSizeCache.w <= 0 || panelSizeCache.h <= 0) {
                panelSizeCache.w = panel.offsetWidth || 220;
                panelSizeCache.h = panel.offsetHeight || 200;
            }
            return panelSizeCache;
        }

        var panelAnimFrame = null, panelAnimating = false, followRAF = null;
        var panelRenderX = 0, panelRenderY = 0;
        var panelFromX = 0, panelFromY = 0, panelToX = 0, panelToY = 0;
        var panelAnimStart = 0, panelAnimDur = 0, panelAnimStrategy = null;
        var PANEL_SWITCH_DURATION = 400;

        function stopAllPanelAnimations() {
            if (followRAF) { cancelAnimationFrame(followRAF); followRAF = null; }
            if (panelAnimFrame) { cancelAnimationFrame(panelAnimFrame); panelAnimFrame = null; }
            panelAnimating = false;
            panelAnimStrategy = null;
        }
        function cancelPanelFollow() {
            if (followRAF) { cancelAnimationFrame(followRAF); followRAF = null; }
        }

        function easeInOutCubic(t) {
            return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        }

        function setPanelImmediate(x, y) {
            stopAllPanelAnimations();
            panelRenderX = x; panelRenderY = y;
            panel.style.left = x + 'px'; panel.style.top = y + 'px';
        }
        function startPanelSwitch(toX, toY, strategy, duration) {
            stopAllPanelAnimations();
            panelFromX = panelRenderX; panelFromY = panelRenderY;
            panelToX = toX; panelToY = toY;
            panelAnimStrategy = strategy;
            panelAnimDur = duration || PANEL_SWITCH_DURATION;
            panelAnimStart = performance.now();
            panelAnimating = true;
            panelAnimFrame = requestAnimationFrame(panelAnimTick);
        }
        function updatePanelSwitchTarget(toX, toY) { panelToX = toX; panelToY = toY; }

        function panelAnimTick(now) {
            panelAnimFrame = null;
            var t = (now - panelAnimStart) / panelAnimDur;
            if (t > 1) t = 1;
            if (t < 0) t = 0;
            var e = easeInOutCubic(t);
            var x = panelFromX + (panelToX - panelFromX) * e;
            var y = panelFromY + (panelToY - panelFromY) * e;
            panelRenderX = x; panelRenderY = y;
            panel.style.left = x + 'px'; panel.style.top = y + 'px';
            if (t < 1) panelAnimFrame = requestAnimationFrame(panelAnimTick);
            else { panelAnimating = false; panelAnimStrategy = null; }
        }

        function calcPanelPosFor(btnLeft, btnTop, btnW, btnH) {
            var size = getPanelSize(), pw = size.w, ph = size.h, gap = 10;
            var vw = window.innerWidth, vh = window.innerHeight;
            var btnRight = btnLeft + btnW, btnBottom = btnTop + btnH;

            function alignX() {
                var l = btnRight - pw;
                if (l < EDGE) l = EDGE;
                if (l + pw > vw - EDGE) l = vw - pw - EDGE;
                return l;
            }
            function alignY() {
                var t = btnTop + btnH / 2 - ph / 2;
                if (t < EDGE) t = EDGE;
                if (t + ph > vh - EDGE) t = vh - ph - EDGE;
                return t;
            }

            var candidates = {};
            var belowTop = btnBottom + gap;
            if (belowTop + ph <= vh - EDGE) candidates.below = { left: alignX(), top: belowTop, strategy: 'below', pr: 1 };
            var aboveTop = btnTop - gap - ph;
            if (aboveTop >= EDGE) candidates.above = { left: alignX(), top: aboveTop, strategy: 'above', pr: 2 };
            var rightLeft = btnRight + gap;
            if (rightLeft + pw <= vw - EDGE) candidates.right = { left: rightLeft, top: alignY(), strategy: 'right', pr: 3 };
            var leftLeft = btnLeft - gap - pw;
            if (leftLeft >= EDGE) candidates.left = { left: leftLeft, top: alignY(), strategy: 'left', pr: 4 };

            if (lastPanelStrategy && candidates[lastPanelStrategy]) return candidates[lastPanelStrategy];

            var keys = Object.keys(candidates);
            if (keys.length > 0) {
                keys.sort(function (a, b) { return candidates[a].pr - candidates[b].pr; });
                return candidates[keys[0]];
            }
            return { left: vw - pw - EDGE, top: alignY(), strategy: 'fallback-right' };
        }

        function getAnchorRect() {
            var base = calcGirlSize();
            var el = (btn && !btn.classList.contains('girl-hidden')) ? btn : miniBtn;
            var cx, cy;

            if (el) {
                var rect = el.getBoundingClientRect();
                cx = rect.left + rect.width / 2;
                cy = rect.top + rect.height / 2;
            } else {
                var range = posRange(base);
                var n = readNormalizedPos(base);
                cx = EDGE + n.hx * range.x + base / 2;
                cy = EDGE + n.hy * range.y + base / 2;
            }

            return {
                left: cx - base / 2,
                top: cy - base / 2,
                right: cx + base / 2,
                bottom: cy + base / 2,
                width: base,
                height: base
            };
        }

        function positionPanel(instant) {
            if (panel.style.display === 'none') return;
            var rect = getAnchorRect();
            var p = calcPanelPosFor(rect.left, rect.top, rect.width, rect.height);
            var targetLeft = p.left, targetTop = p.top, strategy = p.strategy;

            if (instant) {
                lastPanelStrategy = strategy;
                setPanelImmediate(targetLeft, targetTop);
                return;
            }
            if (strategy !== lastPanelStrategy) {
                lastPanelStrategy = strategy;
                startPanelSwitch(targetLeft, targetTop, strategy);
                return;
            }
            if (panelAnimating && panelAnimStrategy === strategy) {
                updatePanelSwitchTarget(targetLeft, targetTop);
                return;
            }
            setPanelImmediate(targetLeft, targetTop);
        }

        function startAnchoredFollow(duration, strategyOverride) {
            stopAllPanelAnimations();
            if (strategyOverride) lastPanelStrategy = strategyOverride;
            var strategy = lastPanelStrategy;
            if (!strategy) return;

            var startTime = performance.now();
            function tick(now) {
                if (!isOpen) { followRAF = null; return; }

                var rect = getAnchorRect();
                var pw = panel.offsetWidth || 220;
                var ph = panel.offsetHeight || 200;
                var gap = 10;
                var vw = window.innerWidth, vh = window.innerHeight;
                var targetLeft, targetTop;

                if (strategy === 'below') {
                    targetTop = rect.bottom + gap;
                    targetLeft = rect.right - pw;
                } else if (strategy === 'above') {
                    targetTop = rect.top - gap - ph;
                    targetLeft = rect.right - pw;
                } else if (strategy === 'right') {
                    targetLeft = rect.right + gap;
                    targetTop = rect.top + rect.height / 2 - ph / 2;
                } else if (strategy === 'left') {
                    targetLeft = rect.left - gap - pw;
                    targetTop = rect.top + rect.height / 2 - ph / 2;
                } else {
                    targetLeft = vw - pw - 16;
                    targetTop = vh - ph - 16;
                }

                if (targetLeft < EDGE) targetLeft = EDGE;
                if (targetLeft + pw > vw - EDGE) targetLeft = vw - pw - EDGE;
                if (targetTop < EDGE) targetTop = EDGE;
                if (targetTop + ph > vh - EDGE) targetTop = vh - ph - EDGE;

                panel.style.left = targetLeft + 'px';
                panel.style.top = targetTop + 'px';
                panelRenderX = targetLeft;
                panelRenderY = targetTop;

                if (now - startTime < duration) followRAF = requestAnimationFrame(tick);
                else followRAF = null;
            }
            followRAF = requestAnimationFrame(tick);
        }

        /* ---------- 7.5 面板开合 ---------- */
        function openPanel() {
            panel.classList.remove('panel-moving');
            panel.style.display = 'block';
            invalidatePanelSize();
            lastPanelStrategy = null;
            positionPanel(true);
            isOpen = true;
            requestAnimationFrame(function () { panel.classList.add('open'); });
        }
        function closePanel() {
            cancelPanelFollow();
            panel.classList.remove('panel-moving');
            panel.classList.remove('open');
            isOpen = false;
            lastPanelStrategy = null;
            setTimeout(function () { if (!isOpen) panel.style.display = 'none'; }, 300);
        }
        function togglePanel() { isOpen ? closePanel() : openPanel(); }

        /* ---------- 7.6 mini 按钮 ---------- */
        var miniBtn = null;
        if (MODULES.length > 0) {
            miniBtn = document.createElement('button');
            miniBtn.id = 'effects-mini-btn';
            miniBtn.type = 'button';
            miniBtn.setAttribute('aria-label', '特效控制面板（长按拖动，点击打开）');
            miniBtn.textContent = '✨';
            if (!hasGirl || !initialGirlVisible) miniBtn.classList.add('visible');
            document.body.appendChild(miniBtn);

            applyPos(miniBtn, 46);

            var miniPointerActive = false, miniDragging = false, miniMoved = false, miniHoldTimer = null;
            var miniStartX = 0, miniStartY = 0, miniStartLeft = 0, miniStartTop = 0;
            var miniW = 0, miniH = 0, miniLastPointerId = null;

            miniBtn.addEventListener('pointerdown', function (e) {
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                panel.classList.remove('panel-moving');

                var rect = miniBtn.getBoundingClientRect();
                miniStartX = e.clientX; miniStartY = e.clientY;
                miniStartLeft = rect.left; miniStartTop = rect.top;
                miniW = rect.width; miniH = rect.height;
                miniLastPointerId = e.pointerId;
                miniPointerActive = true; miniMoved = false; miniDragging = false;

                clearTimeout(miniHoldTimer);
                miniHoldTimer = setTimeout(function () {
                    if (miniPointerActive && !miniMoved) {
                        miniDragging = true;
                        miniBtn.classList.add('dragging');
                        cancelPanelFollow();
                        if (isOpen) positionPanel(false);
                    }
                }, HOLD_MS);

                try { miniBtn.setPointerCapture(e.pointerId); } catch (err) {}
                e.preventDefault();
            });

            miniBtn.addEventListener('pointermove', function (e) {
                if (!miniPointerActive) return;
                var dx = e.clientX - miniStartX, dy = e.clientY - miniStartY;

                if (!miniDragging) {
                    if (Math.sqrt(dx * dx + dy * dy) > MOVE_TOLERANCE) {
                        clearTimeout(miniHoldTimer);
                        miniMoved = true;
                    }
                    return;
                }
                var p = clampPosition(miniStartLeft + dx, miniStartTop + dy, miniW, miniH);
                miniBtn.style.left = p.left + 'px';
                miniBtn.style.top = p.top + 'px';
                if (isOpen) positionPanel(false);
            });

            function endMiniPointer(e) {
                if (!miniPointerActive) return;
                miniPointerActive = false;
                clearTimeout(miniHoldTimer);
                var wasDragging = miniDragging, didMove = miniMoved;
                miniDragging = false; miniMoved = false;
                miniBtn.classList.remove('dragging');
                try {
                    if (e && miniLastPointerId !== null && miniBtn.hasPointerCapture && miniBtn.hasPointerCapture(miniLastPointerId)) {
                        miniBtn.releasePointerCapture(miniLastPointerId);
                    }
                } catch (err) {}
                miniLastPointerId = null;
                if (wasDragging) {
                    writePos(miniBtn);
                    handleViewportChange();
                } else if (!didMove) {
                    togglePanel();
                }
            }
            miniBtn.addEventListener('pointerup', endMiniPointer);
            miniBtn.addEventListener('pointercancel', endMiniPointer);
        }

        /* ---------- 7.7 重置位置 ---------- */
        function resetPosition() {
            clearPos();

            if (btn) {
                btn.classList.add('animating-pos');
                applyPos(btn, calcGirlSize());
                setTimeout(function () { btn.classList.remove('animating-pos'); }, 560);
            }
            if (miniBtn) {
                miniBtn.classList.add('animating-pos');
                applyPos(miniBtn, 46);
                setTimeout(function () { miniBtn.classList.remove('animating-pos'); }, 560);
            }

            if (!isOpen) return;

            stopAllPanelAnimations();
            invalidatePanelSize();

            var base = calcGirlSize();
            var range = posRange(base);

            lastPanelStrategy = null;
            var target = calcPanelPosFor(EDGE + range.x, EDGE + range.y, base, base);
            lastPanelStrategy = target.strategy;

            panel.classList.add('panel-moving');
            requestAnimationFrame(function () {
                panel.style.left = target.left + 'px';
                panel.style.top = target.top + 'px';
                panelRenderX = target.left;
                panelRenderY = target.top;
                setTimeout(function () { panel.classList.remove('panel-moving'); }, 560);
            });
        }

        /* ---------- 7.8 看板娘显隐 ---------- */
        function applyGirlVisibility(visible) {
            if (hasGirl && btn) btn.classList.toggle('girl-hidden', !visible);
            if (miniBtn) {
                var showMini = (!hasGirl || !visible);
                miniBtn.classList.toggle('visible', showMini);
                if (showMini) applyPos(miniBtn, 46);
            }
            if (hasGirl && btn && visible) applyPos(btn, calcGirlSize());

            var block = panel.querySelector('.girl-only-block');
            if (block) block.classList.toggle('collapsed', !visible);
            if (!visible) hideTip();

            if (isOpen) {
                invalidatePanelSize();
                startAnchoredFollow(600);
            }
        }

        /* ---------- 7.9 看板娘拖拽 ---------- */
        function applySize() {
            if (!btn) return;
            var size = calcGirlSize();
            btn.style.width = size + 'px';
            btn.style.height = size + 'px';
        }

        if (btn) {
            applySize();
            requestAnimationFrame(function () { applyPos(btn, calcGirlSize()); });

            var pointerActive = false, dragging = false, moved = false, holdTimer = null;
            var startPointerX = 0, startPointerY = 0;
            var startLeft = 0, startTop = 0;
            var btnW = 0, btnH = 0, lastPointerId = null;

            function enterDragMode() {
                if (dragging) return;
                dragging = true;
                isDraggingGirl = true;
                btn.classList.add('dragging');
                cancelPanelFollow();
                window.dispatchEvent(new Event('board-girl-drag-start'));
                if (isOpen) positionPanel(false);
            }

            btn.addEventListener('pointerdown', function (e) {
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                panel.classList.remove('panel-moving');

                var rect = btn.getBoundingClientRect();
                startPointerX = e.clientX; startPointerY = e.clientY;
                startLeft = rect.left; startTop = rect.top;
                btnW = rect.width; btnH = rect.height;
                lastPointerId = e.pointerId;
                pointerActive = true; moved = false; dragging = false;

                clearTimeout(holdTimer);
                holdTimer = setTimeout(function () {
                    if (pointerActive && !moved) enterDragMode();
                }, HOLD_MS);

                try { btn.setPointerCapture(e.pointerId); } catch (err) {}
                e.preventDefault();
            });

            btn.addEventListener('pointermove', function (e) {
                if (!pointerActive) return;
                var dx = e.clientX - startPointerX, dy = e.clientY - startPointerY;

                if (!dragging) {
                    if (Math.sqrt(dx * dx + dy * dy) > MOVE_TOLERANCE) {
                        clearTimeout(holdTimer);
                        moved = true;
                    }
                    return;
                }
                var p = clampPosition(startLeft + dx, startTop + dy, btnW, btnH);
                btn.style.left = p.left + 'px';
                btn.style.top = p.top + 'px';
                if (isOpen) positionPanel(false);
            });

            function endPointer(e) {
                if (!pointerActive) return;
                pointerActive = false;
                clearTimeout(holdTimer);
                var wasDragging = dragging, didMove = moved;
                dragging = false; moved = false;
                isDraggingGirl = false;
                btn.classList.remove('dragging');
                try {
                    if (e && lastPointerId !== null && btn.hasPointerCapture && btn.hasPointerCapture(lastPointerId)) {
                        btn.releasePointerCapture(lastPointerId);
                    }
                } catch (err) {}
                lastPointerId = null;
                if (wasDragging) {
                    writePos(btn);
                    handleViewportChange();
                } else if (!didMove) {
                    handleGirlClick();
                }
            }

            btn.addEventListener('pointerup', endPointer);
            btn.addEventListener('pointercancel', endPointer);
        }

        /* ---------- 7.10 语录气泡 ---------- */
        var tipEl = null, tipHideTimer = null, tipSessionId = 0, tipSwitchTimer = null;

        function resetTipAutoHide() {
            if (tipHideTimer) clearTimeout(tipHideTimer);
            tipHideTimer = setTimeout(hideTip, tipsCfg.duration || 3000);
        }
        function hideTip() {
            if (!tipEl) return;
            var el = tipEl;
            tipEl = null;
            tipSessionId++;
            if (el._followTimer) { clearInterval(el._followTimer); el._followTimer = null; }
            if (tipSwitchTimer) { clearTimeout(tipSwitchTimer); tipSwitchTimer = null; }
            el.classList.remove('show');
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 380);
            if (tipHideTimer) { clearTimeout(tipHideTimer); tipHideTimer = null; }
        }

        function positionTip() {
            if (!tipEl || !btn) return;
            if (btn.classList.contains('girl-hidden')) return;

            var rect = btn.getBoundingClientRect();
            var tipW = tipEl.offsetWidth, tipH = tipEl.offsetHeight, gap = 10;

            var vv = window.visualViewport;
            var vw = vv ? vv.width : window.innerWidth;
            var vh = vv ? vv.height : window.innerHeight;
            if (!vw) vw = window.innerWidth;
            if (!vh) vh = window.innerHeight;

            var left, top;

            left = rect.left + rect.width / 2 - tipW / 2;
            top = rect.top - tipH - gap;

            if (top < EDGE) {
                top = rect.bottom + gap;
                
                if (top + tipH > vh - EDGE) {
                    top = rect.top + rect.height / 2 - tipH / 2;
                    left = rect.right + gap;

                    if (left + tipW > vw - EDGE) {
                        left = rect.left - tipW - gap;
                        
                        if (left < EDGE) {
                            left = rect.left + rect.width / 2 - tipW / 2;
                            top = rect.top - tipH - gap;
                        }
                    }
                }
            }

            left = Math.max(EDGE, Math.min(left, vw - tipW - EDGE));
            top = Math.max(EDGE, Math.min(top, vh - tipH - EDGE));

            tipEl.style.left = left + 'px';
            tipEl.style.top = top + 'px';
        }

        function showTip() {
            if (!btn || btn.classList.contains('girl-hidden') || btn.classList.contains('fallback-mode')) return;
            if (tipEl) hideTip();
            tipSessionId++;
            var mySession = tipSessionId;

            tipEl = document.createElement('div');
            tipEl.id = 'board-girl-tip';
            tipEl.innerHTML = '<span class="tip-text"></span><button type="button" class="tip-settings-btn" title="打开控制面板">' + SETTINGS_SVG + '</button>';
            document.body.appendChild(tipEl);
            positionTip();

            fetchTipText().then(function (text) {
                if (mySession !== tipSessionId || !tipEl) return;
                var textEl = tipEl.querySelector('.tip-text');
                if (textEl) textEl.textContent = text;
                positionTip();
                requestAnimationFrame(function () {
                    if (tipEl && mySession === tipSessionId) {
                        positionTip();
                        requestAnimationFrame(function () {
                            if (tipEl && mySession === tipSessionId) tipEl.classList.add('show');
                        });
                    }
                });
                resetTipAutoHide();
            });

            var gearBtn = tipEl.querySelector('.tip-settings-btn');
            if (gearBtn) gearBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                hideTip();
                if (!isOpen) openPanel();
            });
            tipEl.addEventListener('click', function (e) {
                if (e.target.closest('.tip-settings-btn')) return;
                hideTip();
            });
        }

        function switchTipText() {
            if (!tipEl || !btn) return;
            var el = tipEl, textEl = el.querySelector('.tip-text');
            if (!textEl) return;
            if (tipSwitchTimer) return;

            el.classList.add('switching');
            tipSwitchTimer = setTimeout(function () {
                tipSwitchTimer = null;
                if (tipEl !== el) return;
                fetchTipText().then(function (text) {
                    if (tipEl !== el) return;
                    textEl.textContent = text;
                    requestAnimationFrame(function () {
                        if (tipEl !== el) return;
                        positionTip();
                        requestAnimationFrame(function () {
                            if (tipEl !== el) return;
                            el.classList.remove('switching');
                        });
                    });
                });
            }, 230);
            resetTipAutoHide();
        }

        function handleGirlClick() {
            if (isOpen) { closePanel(); return; }
            if (!hasGirl || !isTipEnabled()) { togglePanel(); return; }
            if (btn && btn.classList.contains('fallback-mode')) { togglePanel(); return; }
            if (!tipEl) showTip(); else switchTipText();
        }
        window.addEventListener('board-girl-drag-start', hideTip);

        /* ---------- 7.11 面板内容构建 ---------- */
        function buildPanel() {
            panel.innerHTML = '';
            var girlVisible = isGirlVisible();
            var hasEffectsSection = MODULES.length > 0;

            if (hasGirl && hasEffectsSection) {
                var girlItem = document.createElement('div');
                girlItem.className = 'effect-item';
                var girlIcon = document.createElement('span');
                girlIcon.className = 'effect-icon';
                girlIcon.textContent = '🎀';
                girlItem.appendChild(girlIcon);
                var girlName = document.createElement('span');
                girlName.className = 'effect-name';
                girlName.textContent = '看板娘';
                girlItem.appendChild(girlName);
                var girlLabel = document.createElement('label');
                girlLabel.className = 'switch';
                var girlInput = document.createElement('input');
                girlInput.type = 'checkbox';
                girlInput.checked = girlVisible;
                var girlSlider = document.createElement('span');
                girlSlider.className = 'slider';
                girlLabel.appendChild(girlInput);
                girlLabel.appendChild(girlSlider);
                girlItem.appendChild(girlLabel);
                girlInput.addEventListener('change', function () {
                    var v = this.checked;
                    setGirlVisible(v);
                    applyGirlVisibility(v);
                });
                panel.appendChild(girlItem);

                var divAfterGirl = document.createElement('div');
                divAfterGirl.className = 'panel-divider';
                panel.appendChild(divAfterGirl);
            }

            if (hasEffectsSection) {
                var title1 = document.createElement('div');
                title1.className = 'panel-title';
                title1.textContent = '✨ 特效控制';
                panel.appendChild(title1);

                MODULES.forEach(function (item) {
                    var div = document.createElement('div');
                    div.className = 'effect-item';
                    var iconSpan = document.createElement('span');
                    iconSpan.className = 'effect-icon';
                    iconSpan.textContent = item.icon || '✨';
                    div.appendChild(iconSpan);

                    var nameSpan = document.createElement('span');
                    nameSpan.className = 'effect-name';
                    nameSpan.textContent = item.displayName;
                    div.appendChild(nameSpan);

                    var label = document.createElement('label');
                    label.className = 'switch';
                    var input = document.createElement('input');
                    input.type = 'checkbox';
                    input.checked = states[item.matchKey] !== false;
                    input.dataset.matchKey = item.matchKey;
                    var slider = document.createElement('span');
                    slider.className = 'slider';
                    label.appendChild(input);
                    label.appendChild(slider);
                    div.appendChild(label);

                    input.addEventListener('change', function () {
                        var key = this.dataset.matchKey;
                        var enabled = this.checked;
                        var mod = MODULE_MAP[key];
                        if (!mod) { this.checked = !enabled; return; }

                        if (!mod.runtimeToggle) {
                            states[key] = enabled;
                            saveStates(states);
                            updateOffCookieFromStates();
                            showRefreshMask(enabled ? '正在启用特效，页面即将刷新…' : '正在关闭特效，页面即将刷新…');
                            return;
                        }

                        if (!enabled) {
                            if (mod.module && typeof mod.module.stop === 'function') {
                                try { mod.module.stop(); } catch (e) {}
                                states[key] = false;
                                saveStates(states);
                                updateOffCookieFromStates();
                                return;
                            }
                            /* 模块尚未加载：直接刷新，让 index.php 不再输出该脚本 */
                            states[key] = false;
                            saveStates(states);
                            updateOffCookieFromStates();
                            showRefreshMask('正在关闭特效，页面即将刷新…');
                            return;
                        }

                        if (mod.module) {
                            if (typeof mod.module.start === 'function') {
                                try { mod.module.start(); } catch (e) {}
                            }
                            states[key] = true;
                            saveStates(states);
                            updateOffCookieFromStates();
                            return;
                        }

                        var self = this;
                        self.disabled = true;
                        ensureModuleLoaded(mod).then(function () {
                            if (mod.module && typeof mod.module.start === 'function') {
                                try { mod.module.start(); } catch (e) {}
                            }
                            states[key] = true;
                            saveStates(states);
                            updateOffCookieFromStates();
                            self.disabled = false;
                        }).catch(function () {
                            /* 拿不到可用的 module（注册名对不上、脚本未注册等）
                               → 回滚开关状态，禁用开关并加上明确的 ⚠ 提示，
                                 不再走"写 states → 刷新 → 再失败"的死循环 */
                            self.checked = false;
                            self.disabled = true;

                            var item = self.closest ? self.closest('.effect-item') : null;
                            if (item) {
                                item.title = '特效脚本已加载但未注册 matchKey「' + (mod.displayName || mod.matchKey) +
                                    '」，请到后台编辑该特效修正 matchKey 后刷新';
                                var nameEl = item.querySelector('.effect-name');
                                if (nameEl && !nameEl.querySelector('.matchkey-error')) {
                                    var warnEl = document.createElement('span');
                                    warnEl.className = 'matchkey-error';
                                    warnEl.textContent = '⚠';
                                    warnEl.style.cssText = 'color:#ff6b6b;font-size:13px;margin-left:4px;flex-shrink:0;cursor:help;';
                                    nameEl.appendChild(warnEl);
                                }
                            }

                            try {
                                console.error('[nuomi] 无法启动特效模块（matchKey 未注册）：' + mod.matchKey +
                                    '，请到后台「✨ JS特效」里点「🔄 自动读取」或手动修正 matchKey');
                            } catch (e) {}
                        });
                    });
                    panel.appendChild(div);
                });
            }

            if (hasGirl) {
                var girlBlock = document.createElement('div');
                girlBlock.className = 'girl-only-block' + (girlVisible ? '' : ' collapsed');
                var girlInner = document.createElement('div');
                girlInner.className = 'girl-only-inner';

                if (hasEffectsSection) {
                    var divP = document.createElement('div');
                    divP.className = 'panel-divider';
                    girlInner.appendChild(divP);
                }

                var title0 = document.createElement('div');
                title0.className = 'panel-title';
                title0.textContent = '💬 提示';
                girlInner.appendChild(title0);

                var tipItem = document.createElement('div');
                tipItem.className = 'effect-item';
                var tipIcon = document.createElement('span');
                tipIcon.className = 'effect-icon';
                tipIcon.textContent = '💬';
                tipItem.appendChild(tipIcon);
                var tipName = document.createElement('span');
                tipName.className = 'effect-name';
                tipName.textContent = '随机语录';
                tipItem.appendChild(tipName);
                var tipLabel = document.createElement('label');
                tipLabel.className = 'switch';
                var tipInput = document.createElement('input');
                tipInput.type = 'checkbox';
                tipInput.checked = isTipEnabled();
                var tipSlider = document.createElement('span');
                tipSlider.className = 'slider';
                tipLabel.appendChild(tipInput);
                tipLabel.appendChild(tipSlider);
                tipItem.appendChild(tipLabel);
                tipInput.addEventListener('change', function () {
                    setTipEnabled(this.checked);
                    if (!this.checked) hideTip();
                });
                girlInner.appendChild(tipItem);

                if (GIRLS.length > 0) {
                    var div0 = document.createElement('div');
                    div0.className = 'panel-divider';
                    girlInner.appendChild(div0);

                    var title2 = document.createElement('div');
                    title2.className = 'panel-title';
                    title2.textContent = '👘 切换形象';
                    girlInner.appendChild(title2);

                    var selector = document.createElement('div');
                    selector.className = 'girl-selector';
                    GIRLS.forEach(function (girl, index) {
                        var option = document.createElement('div');
                        option.className = 'girl-option' + (index === currentGirlIndex ? ' active' : '');
                        var img = document.createElement('img');
                        img.alt = girl.name;
                        option.appendChild(img);
                        loadGirlImage(img, resolveGirlSrc(girl.file), {
                            onDone: function (ok) {
                                if (ok) img.classList.add('loaded');
                                else option.style.display = 'none';
                            }
                        });
                        option.addEventListener('click', function (e) {
                            e.stopPropagation();
                            if (index === currentGirlIndex) return;
                            currentGirlIndex = index;
                            saveGirlIndex(index);
                            applyGirlImage(index);
                            var allOpts = selector.querySelectorAll('.girl-option');
                            allOpts.forEach(function (el, i) { el.classList.toggle('active', i === index); });
                        });
                        selector.appendChild(option);
                    });
                    girlInner.appendChild(selector);
                }

                girlBlock.appendChild(girlInner);
                panel.appendChild(girlBlock);
            }

            var resetBtn = document.createElement('button');
            resetBtn.type = 'button';
            resetBtn.className = 'panel-reset-pos';
            resetBtn.textContent = '📍 重置位置';
            resetBtn.title = '回到右下角默认位置';
            resetBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                resetPosition();
            });
            panel.appendChild(resetBtn);

            invalidatePanelSize();
        }

        function handleOutsideClick(e) {
            if (!isOpen) return;
            var target = e.target;
            if (panel.contains(target)) return;
            if (btn && (target === btn || btn.contains(target))) return;
            if (miniBtn && (target === miniBtn || miniBtn.contains(target))) return;
            closePanel();
        }

        buildPanel();
        document.addEventListener('click', handleOutsideClick);

        /* ---------- 7.12 模块初始启动 ---------- */
        MODULES.forEach(function (item) {
            var enabled = states[item.matchKey] !== false;

            if (item.module) {
                if (!item.runtimeToggle) return;
                if (!enabled) {
                    if (typeof item.module.stop === 'function') {
                        try { item.module.stop(); } catch (e) {}
                    }
                } else {
                    if (!item.defaultEnabled && typeof item.module.start === 'function') {
                        try { item.module.start(); } catch (e) {}
                    }
                }
                return;
            }

            if (enabled && item.runtimeToggle && item.file) {
                ensureModuleLoaded(item).then(function () {
                    if (item.module && typeof item.module.start === 'function') {
                        try { item.module.start(); } catch (e) {}
                    }
                }).catch(function () {});
            }
        });

        /* ---------- 7.13 视口变化 ---------- */
        function getAnchorRectFromLayout() {
            var base = calcGirlSize();
            var range = posRange(base);
            var n = readNormalizedPos(base);
            var left = EDGE + n.hx * range.x;
            var top = EDGE + n.hy * range.y;
            return {
                left: left,
                top: top,
                right: left + base,
                bottom: top + base,
                width: base,
                height: base
            };
        }

        function smoothMoveEl(el, fallbackSize) {
            if (!el) return;
            el.classList.add('animating-pos');
            applyPos(el, fallbackSize);
            if (el._posAnimTimer) clearTimeout(el._posAnimTimer);
            el._posAnimTimer = setTimeout(function () {
                el._posAnimTimer = null;
                el.classList.remove('animating-pos');
            }, 580);
        }

        var viewportTimer = null;
        var appliedVW = window.innerWidth, appliedVH = window.innerHeight;

        function relayoutForViewport() {
            if (isDraggingGirl) return;

            var vw = window.innerWidth, vh = window.innerHeight;
            if (vw === appliedVW && vh === appliedVH) return;
            appliedVW = vw; appliedVH = vh;

            applySize();

            smoothMoveEl(btn, calcGirlSize());
            smoothMoveEl(miniBtn, 46);

            invalidatePanelSize();

            if (isOpen) {
                panel.classList.remove('panel-moving');
                var rect = getAnchorRectFromLayout();
                var p = calcPanelPosFor(rect.left, rect.top, rect.width, rect.height);
                lastPanelStrategy = p.strategy;
                startPanelSwitch(p.left, p.top, p.strategy, 500);
            }

            if (tipEl) {
                if (tipEl._followTimer) clearInterval(tipEl._followTimer);
                
                tipEl.style.willChange = 'left, top';
                
                var start = performance.now();
                tipEl._followTimer = setInterval(function() {
                    if (!tipEl) {
                        clearInterval(tipEl._followTimer);
                        return;
                    }
                    positionTip();
                    
                    if (performance.now() - start > 600) {
                        clearInterval(tipEl._followTimer);
                        tipEl._followTimer = null;
                        if (tipEl) tipEl.style.willChange = 'auto'; 
                    }
                }, 16);
            }
        }

        function handleViewportChange() {
            clearTimeout(viewportTimer);
            viewportTimer = setTimeout(function () {
                viewportTimer = null;
                relayoutForViewport();
            }, 160);
        }

        window.addEventListener('resize', handleViewportChange);
        window.addEventListener('orientationchange', function () {
            handleViewportChange();
            setTimeout(handleViewportChange, 320);
        });
        
        var scrollTimer = null;
        window.addEventListener('scroll', function() {
            if (!tipEl) return;
            tipEl.style.willChange = 'left, top';
            positionTip();
            
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                if (tipEl) tipEl.style.willChange = 'auto';
            }, 150);
        }, { passive: true });

        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', function() {
                if (tipEl) positionTip();
                handleViewportChange();
            });
            window.visualViewport.addEventListener('scroll', function() {
                if (tipEl) positionTip();
            });
        }

        window.addEventListener('beforeunload', function () {
            saveStates(states);
            if (btn) writePos(btn);
        });
    }

    /* ==========================================================================
       8. 入口调度
       ========================================================================== */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initController);
    } else {
        initController();
    }
})();