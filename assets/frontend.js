/* ==========================================================================
   糯米の引导页 - 前台脚本 frontend.js
   加载位置：<head> 中 defer 加载
   ========================================================================== */
(function () {
    'use strict';

    /* ==========================================================================
       0. 通用异步工具 & 全局缓存
       ========================================================================== */
    function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
    function waitTwoFrames() {
        return new Promise(function (resolve) {
            requestAnimationFrame(function () { requestAnimationFrame(resolve); });
        });
    }
    function randSuffix() { return Math.random().toString(36).slice(2, 10); }
    function clampInt(v, lo, hi, def) {
        var n = parseInt(v, 10);
        return isNaN(n) ? def : Math.max(lo, Math.min(hi, n));
    }

    var mqlMobile = window.matchMedia('(max-width: 600px)');
    function isMobileView() { return mqlMobile.matches; }

    /* ==========================================================================
       1. 主题初始化
       ========================================================================== */
    (function initTheme() {
        try {
            var mode = localStorage.getItem('themeMode') || 'auto';
            var mediaDark = window.matchMedia('(prefers-color-scheme: dark)');
            var isDark = mode === 'dark' || (mode === 'auto' && mediaDark.matches);
            document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
        } catch (e) {}
    })();

    /* ==========================================================================
       2. 首屏揭幕
       ========================================================================== */
    (function initPreloader() {
        function run() {
            var html = document.documentElement;
            var p = document.getElementById('preloader');

            if (!p) {
                document.body.classList.add('page-ready');
                html.classList.remove('nuomi-preloading');
                return;
            }

            var bar = document.getElementById('preloaderBar');
            var isRevisit = html.classList.contains('revisit');
            if (isRevisit) p.classList.add('is-revisit');

            var pct = 0;
            var timer = setInterval(function () {
                pct += isRevisit ? 8 : 5;
                if (pct > 90) pct = 90;
                if (bar) bar.style.width = pct + '%';
            }, 60);

            var finished = false;
            var firstPaintReady = false;
            var FP_TIMEOUT = isRevisit ? 2500 : 4500;

            var fpTimer = setTimeout(function () {
                firstPaintReady = true;
                tryFinish();
            }, FP_TIMEOUT);

            window.addEventListener('nuomi-first-paint', function () {
                if (firstPaintReady) return;
                firstPaintReady = true;
                clearTimeout(fpTimer);
                tryFinish();
            });

            function finish() {
                if (finished) return;
                finished = true;
                clearInterval(timer);
                clearTimeout(fpTimer);
                if (bar) bar.style.width = '100%';
                setTimeout(function () {
                    p.classList.add('hidden');
                    document.body.classList.add('page-ready');
                    try { sessionStorage.setItem('nuomi_seen', '1'); } catch (e) {}
                    window.dispatchEvent(new Event('resize'));
                    setTimeout(function () {
                        if (p.parentNode) p.parentNode.removeChild(p);
                        html.classList.remove('nuomi-preloading');
                    }, 800);
                }, 200);
            }

            var minWait = isRevisit ? 460 : 900;
            var startTime = Date.now();
            
            function tryFinish() {
                if (finished) return;
                var elapsed = Date.now() - startTime;
                if (elapsed >= minWait && firstPaintReady) {
                    finish();
                    return;
                }
                setTimeout(tryFinish, 100);
            }

            if (document.readyState === 'complete') tryFinish();
            else window.addEventListener('load', tryFinish, { once: true });

            setTimeout(finish, 4500);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
    })();

    /* ==========================================================================
       3. NuomiPool 核心引擎
       ========================================================================== */
    var NuomiPool = (function () {
        var SITE = (window.NUOMI_CONFIG && window.NUOMI_CONFIG.site) || {};

        var POOL_LIMIT = clampInt(SITE.cacheLimit, 2, 20, 8);
        var FETCH_TIMEOUT_MS       = 5000;
        var FIRST_FETCH_TIMEOUT_MS = 2500;
        var RETRY_INTERVAL_MS      = 1200;
        var FETCH_MAX_RETRY        = 3;

        var LOCAL_MAX_RETRY         = 3;
        var LOCAL_RETRY_INTERVAL_MS = 800;
        var FIRST_LOCAL_TIMEOUT_MS  = 1500;
        var LOCAL_TIMEOUT_MS        = 2500;

        var DB_NAME    = 'nuomi_img_cache';
        var DB_VERSION = 1;
        var STORE_NAME = 'images';
        var LAST_PREFETCH_PREFIX = 'nuomi_last_prefetch_';
        var LAST_SHOWN_PREFIX    = 'nuomi_last_shown_';
        var LAST_POOL_PREFIX     = 'nuomi_last_pool_';
        var QUEUE_CURSOR_PREFIX  = 'nuomi_queue_cursor_';

        var LOCAL_SHOWN_PREFIX    = 'nuomi_local_shown_';
        var LOCAL_PREFETCH_PREFIX = 'nuomi_local_prefetch_';

        var POOL_FULL_PREFIX      = 'nuomi_pool_full_';

        var REVISIT_SWITCH_DELAY_MS = 1500;
        var PAGE_READY_TIMEOUT_MS   = 3500;

        var LOCAL_PREFETCH_MAX     = 2;

        var SEQ_TIMEOUT_LIMIT = 2;
        var QUEUE_FAIL_LIMIT  = 3;
        var LOCAL_FAIL_LIMIT  = 3;

        var dbPromise = null;
        var dbUnavailable = false;

        var warmupConsumed = { bg: false, banner: false };

        /* ---------------- IndexedDB 基础 ---------------- */
        function openDB() {
            if (dbUnavailable) return Promise.resolve(null);
            if (dbPromise) return dbPromise;
            dbPromise = new Promise(function (resolve) {
                var req;
                try { req = indexedDB.open(DB_NAME, DB_VERSION); }
                catch (e) { dbUnavailable = true; resolve(null); return; }

                req.onupgradeneeded = function () {
                    var db = req.result;
                    if (!db.objectStoreNames.contains(STORE_NAME)) db.createObjectStore(STORE_NAME);
                };
                req.onsuccess = function () { resolve(req.result); };
                req.onerror   = function () { dbUnavailable = true; resolve(null); };
            });
            return dbPromise;
        }

        function idbGet(key) {
            return openDB().then(function (db) {
                if (!db) return null;
                return new Promise(function (resolve) {
                    try {
                        var t = db.transaction(STORE_NAME, 'readonly');
                        var r = t.objectStore(STORE_NAME).get(key);
                        r.onsuccess = function () { resolve(r.result || null); };
                        r.onerror   = function () { resolve(null); };
                    } catch (e) { resolve(null); }
                });
            });
        }

        function idbDelete(key) {
            return openDB().then(function (db) {
                if (!db) return;
                return new Promise(function (resolve) {
                    try {
                        var t = db.transaction(STORE_NAME, 'readwrite');
                        t.objectStore(STORE_NAME).delete(key);
                        t.oncomplete = function () { resolve(); };
                        t.onerror    = function () { resolve(); };
                        t.onabort    = function () { resolve(); };
                    } catch (e) { resolve(); }
                });
            }).catch(function () {});
        }

        function idbListKeys(prefix) {
            return openDB().then(function (db) {
                if (!db) return [];
                return new Promise(function (resolve) {
                    try {
                        var t = db.transaction(STORE_NAME, 'readonly');
                        var store = t.objectStore(STORE_NAME);
                        var range = IDBKeyRange.bound(prefix, prefix + '\uffff');
                        var keys = [];
                        var req = store.openKeyCursor(range);
                        req.onsuccess = function () {
                            var c = req.result;
                            if (c) { keys.push(c.key); c.continue(); }
                            else resolve(keys);
                        };
                        req.onerror = function () { resolve([]); };
                    } catch (e) { resolve([]); }
                });
            });
        }

        function computeBlobFingerprint(blob) {
            var HEAD_LEN = 32 * 1024;
            var head = blob.slice(0, HEAD_LEN);
            var tailStart = Math.max(0, blob.size - HEAD_LEN);
            var tail = blob.slice(tailStart);

            function fnv(bytes) {
                var h = 5381;
                for (var i = 0; i < bytes.length; i++) {
                    h = ((h << 5) + h + bytes[i]) & 0x7fffffff;
                }
                return h.toString(36);
            }

            return Promise.all([head.arrayBuffer(), tail.arrayBuffer()])
                .then(function (bufs) {
                    var h1 = fnv(new Uint8Array(bufs[0]));
                    var h2 = fnv(new Uint8Array(bufs[1]));
                    return blob.size.toString(36) + '_' + h1 + '_' + h2;
                })
                .catch(function () { return null; });
        }

        function extractFpFromKey(key) {
            var parts = key.split(':');
            return parts.length >= 4 ? parts[parts.length - 1] : '';
        }

        function poolPut(type, blob, fp) {
            if (!blob || blob.size === 0) return Promise.resolve(null);
            var prefix = type + ':';
            var key = prefix + Date.now() + ':' + randSuffix() + ':' + (fp || 'nofp');

            return openDB().then(function (db) {
                if (!db) return null;
                return new Promise(function (resolve) {
                    try {
                        var t = db.transaction(STORE_NAME, 'readwrite');
                        var store = t.objectStore(STORE_NAME);
                        var range = IDBKeyRange.bound(prefix, prefix + '\uffff');
                        var keys = [];
                        var cursor = store.openKeyCursor(range);
                        var decided = false;

                        cursor.onsuccess = function () {
                            var c = cursor.result;
                            if (c) { keys.push(c.key); c.continue(); return; }

                            if (fp) {
                                for (var i = 0; i < keys.length; i++) {
                                    if (extractFpFromKey(keys[i]) === fp) {
                                        decided = true;
                                        resolve(keys[i]);
                                        return;
                                    }
                                }
                            }

                            var overflow = keys.length + 1 - POOL_LIMIT;
                            for (var j = 0; j < overflow; j++) store.delete(keys[j]);
                            store.put(blob, key);
                        };

                        t.oncomplete = function () {
                            if (!decided) {
                                try {
                                    var totalCount = keys.length + getLocalCount(type) + 1;
                                    localStorage.setItem(
                                        POOL_FULL_PREFIX + type,
                                        totalCount >= POOL_LIMIT ? '1' : '0'
                                    );
                                } catch (e) {}
                                resolve(key);
                            }
                        };
                        t.onerror    = function () { if (!decided) resolve(null); };
                        t.onabort    = function () { if (!decided) resolve(null); };
                    } catch (e) { resolve(null); }
                });
            }).catch(function () { return null; });
        }

        function localKey(type, filepath) {
            return (type === 'bg' ? 'bg_local:' : 'banner_local:') + filepath;
        }

        function getLocalTarget() {
            return (window.innerWidth >= window.innerHeight) ? 'desktop' : 'mobile';
        }

        function getLocalImageList(type) {
            var cfg = (type === 'bg') ? window.NUOMI_CONFIG.localBg : window.NUOMI_CONFIG.localBanner;
            if (!cfg || !cfg.enabled || !cfg.images || cfg.images.length === 0) return [];
            
            if (type === 'banner') {
                var outBanner = [];
                for (var i = 0; i < cfg.images.length; i++) {
                    var it = cfg.images[i];
                    if (!it || !it.file) continue;
                    var t = it.target || 'both';
                    if (t === 'both' || t === 'desktop') {
                        outBanner.push(it);
                    }
                }
                if (outBanner.length === 0 && cfg.images.length > 0) return cfg.images;
                return outBanner;
            }

            var target = getLocalTarget();
            var out = [];
            var all = [];
            var opposite = [];
            
            for (var i = 0; i < cfg.images.length; i++) {
                var it = cfg.images[i];
                if (!it || !it.file) continue;
                all.push(it);
                var t = it.target || 'both';
                if (t === 'both' || t === target) {
                    out.push(it);
                } else {
                    opposite.push(it);
                }
            }
            
            if (out.length === 0 && all.length > 0) return all; 
            if (out.length > 0 && out.length < 3 && opposite.length > 0) return out.concat(opposite);
            return out;
        }

        function idbGetLocal(type, filepath) {
            return idbGet(localKey(type, filepath));
        }
        function idbDeleteLocal(type, filepath) {
            return idbDelete(localKey(type, filepath));
        }
        function poolPutLocal(type, filepath, blob) {
            if (!blob || blob.size === 0) return Promise.resolve(null);
            var key = localKey(type, filepath);
            return openDB().then(function (db) {
                if (!db) return null;
                return new Promise(function (resolve) {
                    try {
                        var t = db.transaction(STORE_NAME, 'readwrite');
                        t.objectStore(STORE_NAME).put(blob, key);
                        t.oncomplete = function () { resolve(key); };
                        t.onerror    = function () { resolve(null); };
                        t.onabort    = function () { resolve(null); };
                    } catch (e) { resolve(null); }
                });
            }).catch(function () { return null; });
        }

        function _get(prefix, type) {
            try { return localStorage.getItem(prefix + type) || null; } catch (e) { return null; }
        }
        function _set(prefix, type, key) {
            try {
                if (key) localStorage.setItem(prefix + type, key);
                else localStorage.removeItem(prefix + type);
            } catch (e) {}
        }
        function getLastPrefetchKey(type) { return _get(LAST_PREFETCH_PREFIX, type); }
        function setLastPrefetchKey(type, key) { _set(LAST_PREFETCH_PREFIX, type, key); }
        function getLastShownKey(type) { return _get(LAST_SHOWN_PREFIX, type); }
        function setLastShownKey(type, key) { _set(LAST_SHOWN_PREFIX, type, key); }
        function getLastPoolKey(type) { return _get(LAST_POOL_PREFIX, type); }
        function setLastPoolKey(type, key) { _set(LAST_POOL_PREFIX, type, key); }

        function getQueueCursor(type) {
            try { return parseInt(localStorage.getItem(QUEUE_CURSOR_PREFIX + type) || '0', 10) || 0; } catch (e) { return 0; }
        }
        function setQueueCursor(type, n) {
            try { localStorage.setItem(QUEUE_CURSOR_PREFIX + type, String(n)); } catch (e) {}
        }

        function getLocalShownId(type) { return _get(LOCAL_SHOWN_PREFIX, type); }
        function setLocalShownId(type, id) { _set(LOCAL_SHOWN_PREFIX, type, id); }
        function getLocalPrefetchId(type) { return _get(LOCAL_PREFETCH_PREFIX, type); }
        function setLocalPrefetchId(type, id) { _set(LOCAL_PREFETCH_PREFIX, type, id); }

        function updatePoolFullFlag(type, count) {
            try {
                var totalCount = count + getLocalCount(type);
                localStorage.setItem(POOL_FULL_PREFIX + type, totalCount >= POOL_LIMIT ? '1' : '0');
            } catch (e) {}
        }

        function waitPageReady() {
            return new Promise(function (resolve) {
                if (document.body && document.body.classList.contains('page-ready')) { resolve(); return; }
                var done = false;
                var observer = new MutationObserver(function () {
                    if (document.body && document.body.classList.contains('page-ready')) {
                        if (done) return;
                        done = true;
                        observer.disconnect();
                        resolve();
                    }
                });
                observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
                setTimeout(function () {
                    if (done) return;
                    done = true;
                    observer.disconnect();
                    resolve();
                }, PAGE_READY_TIMEOUT_MS);
            });
        }

        function waitPreloaderClosed() {
            return new Promise(function (resolve) {
                function checkRemoved() { return !document.getElementById('preloader'); }
                if (checkRemoved()) { resolve(); return; }

                var done = false;
                function finish() { if (done) return; done = true; resolve(); }

                var observer = new MutationObserver(function () {
                    if (checkRemoved()) { observer.disconnect(); finish(); }
                });
                observer.observe(document.body, { childList: true, subtree: true });
                setTimeout(function () { observer.disconnect(); finish(); }, PAGE_READY_TIMEOUT_MS + 2000);
            });
        }

        var liveBlobUrl = { bg: null, banner: null };
        function revokeURL(url) {
            if (!url) return;
            try { URL.revokeObjectURL(url); } catch (e) {}
        }
        function promoteToLive(type, url) {
            var old = liveBlobUrl[type];
            if (old && old !== url) {
                setTimeout(function () { revokeURL(old); }, 3000);
            }
            liveBlobUrl[type] = url;
        }

        function fetchBlob(url, timeoutMs) {
            var ctrl  = new AbortController();
            var timer = setTimeout(function () { ctrl.abort(); }, timeoutMs);
            return fetch(url, { cache: 'no-store', signal: ctrl.signal })
                .then(function (res) {
                    if (!res.ok) {
                        var err = new Error('HTTP ' + res.status);
                        err.httpStatus = res.status;
                        throw err;
                    }
                    return res.blob();
                })
                .then(function (blob) {
                    if (!blob || blob.size === 0) {
                        var err2 = new Error('empty blob');
                        err2.empty = true;
                        throw err2;
                    }
                    return blob;
                })
                .finally(function () { clearTimeout(timer); });
        }

        function verifyDecodable(url, timeoutMs) {
            return new Promise(function (resolve) {
                var done = false;
                var img = new Image();
                var timer = setTimeout(function () {
                    if (done) return;
                    done = true;
                    img.onload = img.onerror = null;
                    img.src = '';
                    resolve({ ok: false, timeout: true });
                }, timeoutMs || 3000);

                function settle(ok, isTimeout) {
                    if (done) return;
                    done = true;
                    clearTimeout(timer);
                    img.onload = img.onerror = null;
                    resolve({ ok: ok, timeout: !!isTimeout });
                }

                img.onload = function () {
                    if (done) return;
                    if (typeof img.decode === 'function') {
                        img.decode()
                            .then(function () { settle(true, false); })
                            .catch(function () { settle(false, false); });
                    } else {
                        settle(true, false);
                    }
                };
                img.onerror = function () { settle(false, false); };
                img.src = url;
            });
        }

        function createVerifiedPick(blob, key, isLocal, filepath) {
            if (!blob || blob.size === 0) return Promise.resolve({ pick: null, timeout: false });
            var url = URL.createObjectURL(blob);
            return verifyDecodable(url, 3000).then(function (result) {
                if (!result.ok) {
                    revokeURL(url);
                    return { pick: null, timeout: result.timeout };
                }
                return {
                    pick: {
                        key: key,
                        url: url,
                        temp: true,
                        isLocal: !!isLocal,
                        filepath: filepath || null,
                        verified: true
                    },
                    timeout: false
                };
            });
        }

        function fetchAndPool(type, url, timeoutMs) {
            /* 空 URL 短路：未配置 API 时直接走本地兜底 */
            if (!url) return Promise.resolve(null);
            return fetchBlob(url, timeoutMs || FETCH_TIMEOUT_MS)
                .then(function (blob) {
                    return createVerifiedPick(blob, null, false, null).then(function (r) {
                        if (!r.pick) return null;
                        return computeBlobFingerprint(blob).then(function (fp) {
                            return poolPut(type, blob, fp).then(function (key) {
                                r.pick.key = key || null;
                                return r.pick;
                            });
                        });
                    });
                })
                .catch(function () { return null; });
        }

        function poolPickSequential(type, excludeKey) {
            return idbListKeys(type + ':').then(function (keys) {
                if (!keys.length) return null;

                var last = getLastPoolKey(type);
                var startIdx = 0;
                if (last) {
                    var idx = keys.indexOf(last);
                    if (idx >= 0) startIdx = (idx + 1) % keys.length;
                }

                var tried = 0;
                var timeoutCount = 0;

                function tryPick() {
                    if (tried >= keys.length) return null;
                    if (timeoutCount >= SEQ_TIMEOUT_LIMIT) return null;

                    var k = keys[(startIdx + tried) % keys.length];
                    tried++;
                    if (k === excludeKey) return tryPick();

                    return idbGet(k).then(function (blob) {
                        if (!blob || blob.size === 0) {
                            return idbDelete(k).then(tryPick);
                        }
                        return createVerifiedPick(blob, k, false, null).then(function (r) {
                            if (!r.pick) {
                                if (!r.timeout) {
                                    timeoutCount = 0;
                                    return idbDelete(k).then(tryPick);
                                }
                                timeoutCount++;
                                return tryPick();
                            }
                            setLastPoolKey(type, k);
                            return r.pick;
                        });
                    });
                }
                return tryPick();
            });
        }

        function pickPoolByKey(key) {
            return idbGet(key).then(function (blob) {
                if (!blob || blob.size === 0) {
                    return idbDelete(key).then(function () { return null; });
                }
                return createVerifiedPick(blob, key, false, null).then(function (r) {
                    if (!r.pick) {
                        if (!r.timeout) {
                            return idbDelete(key).then(function () { return null; });
                        }
                        return null;
                    }
                    return r.pick;
                });
            }).catch(function () { return null; });
        }

        function simpleHash(str) {
            var h = 5381;
            for (var i = 0; i < str.length; i++) {
                h = ((h << 5) + h) + str.charCodeAt(i);
                h = h & 0x7fffffff;
            }
            return h.toString(36);
        }
        function getLocalListSignature(type) {
            var list = getLocalImageList(type);
            if (list.length === 0) return 'empty';
            var parts = [];
            for (var i = 0; i < list.length; i++) parts.push(list[i].file);
            return simpleHash(getLocalTarget() + '|' + parts.join('|'));
        }
        function getLocalBlacklistKey(type) {
            return 'nuomi_bad_local_' + type + '_' + getLocalListSignature(type);
        }
        function isLocalBlacklisted(type, filepath) {
            try {
                var list = JSON.parse(localStorage.getItem(getLocalBlacklistKey(type)) || '[]');
                return list.indexOf(filepath) >= 0;
            } catch (e) { return false; }
        }
        function markLocalBlacklisted(type, filepath) {
            try {
                var list = JSON.parse(localStorage.getItem(getLocalBlacklistKey(type)) || '[]');
                if (list.indexOf(filepath) < 0) {
                    list.push(filepath);
                    localStorage.setItem(getLocalBlacklistKey(type), JSON.stringify(list));
                }
            } catch (e) {}
        }
        function unmarkLocalBlacklisted(type, filepath) {
            try {
                var list = JSON.parse(localStorage.getItem(getLocalBlacklistKey(type)) || '[]');
                var i = list.indexOf(filepath);
                if (i >= 0) {
                    list.splice(i, 1);
                    localStorage.setItem(getLocalBlacklistKey(type), JSON.stringify(list));
                }
            } catch (e) {}
        }

        function getLocalCount(type) {
            return getLocalImageList(type).length;
        }

        function pickLocalAt(type, idx, isFirstScreen) {
            var list = getLocalImageList(type);
            if (list.length === 0) return Promise.resolve(null);
            var item = list[idx % list.length];
            if (!item || !item.file) return Promise.resolve(null);

            var filepath = item.file;
            if (isLocalBlacklisted(type, filepath)) return Promise.resolve(null);

            return idbGetLocal(type, filepath).then(function (cachedBlob) {
                if (cachedBlob && cachedBlob.size > 0) {
                    return createVerifiedPick(cachedBlob, null, true, filepath).then(function (r) {
                        if (!r.pick) {
                            if (!r.timeout) {
                                markLocalBlacklisted(type, filepath);
                                return idbDeleteLocal(type, filepath).then(function () { return null; });
                            }
                            return null;
                        }
                        return r.pick;
                    });
                }
                
                if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                    return null;
                }
                
                var ctrl = new AbortController();
                var localTimeout = isFirstScreen ? FIRST_LOCAL_TIMEOUT_MS : LOCAL_TIMEOUT_MS;
                var timer = setTimeout(function () { ctrl.abort(); }, localTimeout);
                
                return fetch(filepath, { signal: ctrl.signal, cache: 'default' })
                    .then(function(res) { 
                        clearTimeout(timer);
                        if (!res.ok) {
                            markLocalBlacklisted(type, filepath);
                            return null;
                        }
                        return res.blob(); 
                    })
                    .then(function (blob) {
                        if (!blob) return null;
                        return createVerifiedPick(blob, null, true, filepath).then(function (r) {
                            if (!r.pick) {
                                if (!r.timeout) markLocalBlacklisted(type, filepath);
                                return null;
                            }
                            return poolPutLocal(type, filepath, blob).then(function () { return r.pick; });
                        });
                    }).catch(function(err) {
                        clearTimeout(timer);
                        return null; 
                    });
            }).catch(function () { return null; });
        }

        function pickLocalByFilepath(type, filepath, isFirstScreen) {
            var list = getLocalImageList(type);
            for (var i = 0; i < list.length; i++) {
                if (list[i].file === filepath) {
                    return pickLocalAt(type, i, isFirstScreen);
                }
            }
            return Promise.resolve(null);
        }

        function getLocalFallbackPick(type, isFirstScreen) {
            var list = getLocalImageList(type);
            if (list.length === 0) {
                return Promise.resolve(null);
            }

            var idxKey = 'nuomi_local_fallback_idx_' + type;
            var idx = 0;
            try { idx = parseInt(localStorage.getItem(idxKey) || '0', 10); } catch (e) {}
            if (isNaN(idx) || idx < 0) idx = 0;

            var total = list.length;
            var attempts = 0;
            var maxAttempts = isFirstScreen ? 1 : total;
            var failCount = 0;

            function tryNext() {
                if (attempts >= maxAttempts) return Promise.resolve(null);
                if (failCount >= LOCAL_FAIL_LIMIT) return Promise.resolve(null);

                var cur = (idx + attempts) % total;
                attempts++;

                var item = list[cur];
                if (!item || !item.file) return tryNext();

                return pickLocalAt(type, cur, isFirstScreen).then(function (pick) {
                    if (pick) {
                        try { localStorage.setItem(idxKey, String((cur + 1) % total)); } catch (e) {}
                        return pick;
                    }
                    failCount++;
                    return tryNext();
                }).catch(function () { failCount++; return tryNext(); });
            }

            return tryNext();
        }

        function pickNextFromQueue(type, excludeKey, isFirstScreen) {
            var localTotal = getLocalCount(type);
            return idbListKeys(type + ':').then(function (poolKeys) {
                var poolTotal = poolKeys.length;
                var N = localTotal + poolTotal;
                if (N === 0) return null;

                var startCursor = getQueueCursor(type) % N;
                var tried = 0;
                var maxTry = isFirstScreen ? 1 : N;
                var failCount = 0;

                function tryNext() {
                    if (tried >= maxTry) return null;
                    if (failCount >= QUEUE_FAIL_LIMIT) {
                        setQueueCursor(type, (startCursor + tried) % N);
                        return null;
                    }
                    var cur = (startCursor + tried) % N;
                    tried++;

                    if (cur < localTotal) {
                        return pickLocalAt(type, cur, isFirstScreen).then(function (pick) {
                            if (pick) { setQueueCursor(type, (cur + 1) % N); return pick; }
                            failCount++;
                            return tryNext();
                        });
                    }
                    var poolKey = poolKeys[cur - localTotal];
                    if (!poolKey) return tryNext();
                    if (excludeKey && poolKey === excludeKey) return tryNext();
                    return pickPoolByKey(poolKey).then(function (pick) {
                        if (pick) { setQueueCursor(type, (cur + 1) % N); return pick; }
                        failCount++;
                        return tryNext();
                    });
                }
                return tryNext();
            });
        }

        function takeWarmupBlob(type) {
            if (warmupConsumed[type]) return null;
            var w = (type === 'bg') ? window.__nuomiWarmupBg : window.__nuomiWarmupBanner;
            if (!w) return null;
            warmupConsumed[type] = true;
            return w;
        }

        function createRotator(type, opts) {
            var buildUrl = opts.buildUrl;
            var interval = opts.interval;
            var switchFn = opts.switchFn;

            var loopBackoffMax = interval * 3;

            var currentKey = null;
            var nextReady  = null;
            var prefetchP  = null;
            var applying   = false;
            var loopToken  = 0;
            var running    = false;
            var localFillStopped = false;
            var failCount  = 0;
            var bgFillPending = false;

            function resetBackoff() { failCount = 0; }

            function isLocalPriority() {
                try {
                    if (localStorage.getItem('autoLoopMode') === 'fresh') return false;
                } catch (e) {}
                var site = (window.NUOMI_CONFIG && window.NUOMI_CONFIG.site) || {};
                if (type === 'bg') return site.localBgPriority === 'local';
                return site.localBannerPriority === 'local';
            }

            function idOfPick(pick) {
                if (!pick) return null;
                if (pick.isLocal && pick.filepath) return 'local:' + pick.filepath;
                if (pick.key) return 'pool:' + pick.key;
                return null;
            }
            function resolveIdToPick(id, isFirstScreen) {
                if (!id) return Promise.resolve(null);
                if (id.indexOf('local:') === 0) {
                    return pickLocalByFilepath(type, id.slice(6), isFirstScreen);
                }
                if (id.indexOf('pool:') === 0) {
                    return pickPoolByKey(id.slice(5));
                }
                return Promise.resolve(null);
            }
            function calibrateCursorFromId(id) {
                if (!id) return Promise.resolve();
                return idbListKeys(type + ':').then(function (poolKeys) {
                    var localTotal = getLocalCount(type);
                    var N = localTotal + poolKeys.length;
                    if (N === 0) return;
                    var cur = -1;
                    if (id.indexOf('local:') === 0) {
                        var filepath = id.slice(6);
                        var list = getLocalImageList(type);
                        for (var i = 0; i < list.length; i++) {
                            if (list[i].file === filepath) { cur = i; break; }
                        }
                    } else if (id.indexOf('pool:') === 0) {
                        var key = id.slice(5);
                        var idx = poolKeys.indexOf(key);
                        if (idx >= 0) cur = localTotal + idx;
                    }
                    if (cur >= 0) {
                        setQueueCursor(type, (cur + 1) % N);
                    }
                });
            }

            function doSwitch(pick, isFirst) {
                function applySwitch() {
                    return Promise.resolve()
                        .then(function () { return switchFn(pick.url, isFirst); })
                        .then(function (res) {
                            if (res !== false && pick.temp) promoteToLive(type, pick.url);
                            if (isFirst) {
                                return waitTwoFrames().then(function () { return { ok: true, timeout: false }; });
                            }
                            return { ok: true, timeout: false };
                        });
                }

                function catchSwitch(err) {
                    console.warn('[nuomi] doSwitch error:', err);
                    return { ok: false, timeout: false };
                }

                return applySwitch().catch(catchSwitch);
            }

            function fetchWithRetry(isFirstScreen, skipLocalFallback) {
                if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                    return poolPickSequential(type, currentKey).then(function (poolPick) {
                        if (poolPick) return poolPick;
                        if (skipLocalFallback) return null;
                        return getLocalFallbackPick(type, isFirstScreen);
                    });
                }
                var tried = 0;
                var warmup = takeWarmupBlob(type);
                var maxTry = isFirstScreen ? 1 : FETCH_MAX_RETRY;

                function tryWarmup() {
                    if (!warmup) return Promise.resolve(null);
                    var w = warmup;
                    warmup = null;
                    return w.then(function (blob) {
                        if (!blob) return null;
                        return createVerifiedPick(blob, null, false, null).then(function (r) {
                            if (!r.pick) return null;
                            return computeBlobFingerprint(blob).then(function (fp) {
                                return poolPut(type, blob, fp).then(function (key) {
                                    r.pick.key = key || null;
                                    return r.pick;
                                });
                            });
                        });
                    }).catch(function () { return null; });
                }

                function tryApi() {
                    var t = isFirstScreen ? FIRST_FETCH_TIMEOUT_MS : FETCH_TIMEOUT_MS;
                    return fetchAndPool(type, buildUrl(), t);
                }

                function attempt() {
                    var task;
                    if (tried === 0) {
                        task = tryWarmup().then(function (pick) { return pick || tryApi(); });
                    } else {
                        task = tryApi();
                    }
                    return task.then(function (pick) {
                        if (pick) return pick;
                        tried++;
                        if (tried >= maxTry) {
                            return poolPickSequential(type, currentKey).then(function (poolPick) {
                                if (poolPick) return poolPick;
                                if (skipLocalFallback) return null;
                                return getLocalFallbackPick(type, isFirstScreen);
                            });
                        }
                        return sleep(RETRY_INTERVAL_MS).then(attempt);
                    });
                }
                return attempt();
            }

            function backgroundFillPool() {
                if (!isLocalPriority()) return Promise.resolve();
                if (typeof navigator !== 'undefined' && navigator.onLine === false) return Promise.resolve();
                if (bgFillPending) return Promise.resolve();

                return idbListKeys(type + ':').then(function (keys) {
                    var need = POOL_LIMIT - keys.length - getLocalCount(type);
                    if (need <= 0) return;

                    bgFillPending = true;
                    return fetchAndPool(type, buildUrl(), FETCH_TIMEOUT_MS).then(function (apiPick) {
                        if (apiPick && apiPick.temp) revokeURL(apiPick.url);
                    }).catch(function () {}).finally(function () {
                        bgFillPending = false;
                    });
                }).catch(function () {});
            }

            function pickFromQueueOrFetch(isFirstScreen) {
                return idbListKeys(type + ':').then(function (poolKeys) {
                    var localTotal = getLocalCount(type);
                    var poolFull = (poolKeys.length + localTotal >= POOL_LIMIT);

                    if (localTotal > 0) {
                        return pickNextFromQueue(type, currentKey, isFirstScreen).then(function (p) {
                            if (p) { p.fromQueue = true; return p; }
                            return fetchWithRetry(isFirstScreen, true).then(function (f) {
                                if (f) f.fromQueue = false;
                                return f;
                            });
                        });
                    }
                    if (!poolFull) {
                        return fetchWithRetry(isFirstScreen, true).then(function (f) {
                            if (f) f.fromQueue = false;
                            return f;
                        });
                    }
                    return pickNextFromQueue(type, currentKey, isFirstScreen).then(function (p) {
                        if (p) { p.fromQueue = true; return p; }
                        return null;
                    });
                });
            }

            function prefetchNext() {
                if (prefetchP) return prefetchP;

                prefetchP = (function () {
                    if (nextReady) {
                        backgroundFillPool();
                        return Promise.resolve();
                    }

                    var fillSlot;
                    if (isLocalPriority()) {
                        fillSlot = pickFromQueueOrFetch();
                    } else {
                        fillSlot = fetchWithRetry().then(function (f) {
                            if (f) f.fromQueue = false;
                            return f;
                        });
                    }

                    return fillSlot.then(function (pick) {
                        if (pick && !nextReady) {
                            nextReady = pick;
                            if (!pick.isLocal && pick.key) {
                                setLastPrefetchKey(type, pick.key);
                            }
                            if (isLocalPriority()) {
                                setLocalPrefetchId(type, idOfPick(pick));
                            }
                        } else if (pick && pick.url) {
                            revokeURL(pick.url);
                        }
                        if (pick && pick.fromQueue) {
                            backgroundFillPool();
                        }
                    }).catch(function () {});
                })();

                return prefetchP.finally(function () { prefetchP = null; });
            }

            function applyNext() {
                if (applying) return Promise.resolve(null);
                applying = true;
                return Promise.resolve().then(function () {
                    return doActualApply();
                }).catch(function (err) {
                    console.warn('[nuomi] applyNext inner error:', err);
                    return { ok: false, timeout: false };
                }).finally(function () { applying = false; });
            }

            function doActualApply() {
                var pick = nextReady;
                nextReady = null;

                function usePick(p) {
                    prefetchNext();
                    return doSwitch(p, false).then(function (result) {
                        if (!result.ok) {
                            if (!result.timeout && p.key && !p.isLocal) idbDelete(p.key);
                            if (p.url) revokeURL(p.url);
                            return { ok: false, timeout: !!result.timeout };
                        }
                        currentKey = p.key;
                        if (p.key) setLastShownKey(type, p.key);
                        if (isLocalPriority()) {
                            setLocalShownId(type, idOfPick(p));
                        }
                        return { ok: true, timeout: false };
                    });
                }

                if (pick) return usePick(pick);

                var fallback;
                if (isLocalPriority()) {
                    fallback = pickFromQueueOrFetch();
                } else {
                    fallback = fetchWithRetry();
                }
                return fallback.then(function (p) {
                    if (!p) return { ok: false, timeout: false };
                    return usePick(p);
                });
            }

            function markFirstPaint() {
                if (markFirstPaint._done) return;
                markFirstPaint._done = true;
                try {
                    window.dispatchEvent(new CustomEvent('nuomi-first-paint', { detail: { type: type } }));
                } catch (e) {}
            }

            function doSwitchAfterDelay() {
                return waitPreloaderClosed()
                    .then(function () { return sleep(REVISIT_SWITCH_DELAY_MS); })
                    .then(function () {
                        var p = nextReady;
                        nextReady = null;
                        if (!p) return prefetchNext();

                        return doSwitch(p, false).then(function (result) {
                            if (!result.ok) {
                                if (!result.timeout && p.key && !p.isLocal) idbDelete(p.key);
                                if (p.url) revokeURL(p.url);
                                return prefetchNext();
                            }
                            currentKey = p.key;
                            if (p.key) setLastShownKey(type, p.key);
                            if (isLocalPriority()) {
                                setLocalShownId(type, idOfPick(p));
                            }
                            return prefetchNext();
                        });
                    });
            }

            function doPrefetchAndSwitch() {
                return prefetchNext().then(function () {
                    return doSwitchAfterDelay();
                });
            }

            function tryRevisitLocal() {
                var shownId = getLocalShownId(type);
                if (!shownId) return Promise.resolve(false);

                return resolveIdToPick(shownId, true).then(function (showPick) {
                    if (!showPick) {
                        setLocalShownId(type, null);
                        setLocalPrefetchId(type, null);
                        return false;
                    }

                    currentKey = showPick.key;
                    return doSwitch(showPick, true).then(function (result) {
                        if (!result.ok) {
                            if (showPick.url) revokeURL(showPick.url);
                            setLocalShownId(type, null);
                            setLocalPrefetchId(type, null);
                            return false;
                        }
                        markFirstPaint();
                        setLocalShownId(type, idOfPick(showPick));

                        var prefetchId = getLocalPrefetchId(type);
                        var showIdStr = idOfPick(showPick);

                        if (!prefetchId || prefetchId === showIdStr) {
                            return doPrefetchAndSwitch().then(function () { return true; });
                        }

                        return resolveIdToPick(prefetchId).then(function (prePick) {
                            if (!prePick) {
                                return doPrefetchAndSwitch().then(function () { return true; });
                            }
                            nextReady = prePick;
                            return calibrateCursorFromId(prefetchId).then(function () {
                                return doSwitchAfterDelay().then(function () { return true; });
                            });
                        });
                    });
                }).catch(function () {
                    setLocalShownId(type, null);
                    setLocalPrefetchId(type, null);
                    return false;
                });
            }

            function tryRevisitApi(keys) {
                var lastShownKey = getLastShownKey(type);
                var showKey = null;

                if (lastShownKey && keys.indexOf(lastShownKey) >= 0) {
                    showKey = lastShownKey;
                } else {
                    showKey = keys[keys.length - 1];
                }

                return idbGet(showKey).then(function (blob) {
                    if (!blob) { markFirstPaint(); return; }
                    return createVerifiedPick(blob, showKey, false, null).then(function (r) {
                        var showFallback = function () {
                            return poolPickSequential(type, showKey).then(function (alt) {
                                if (!alt) { markFirstPaint(); return; }
                                return doSwitch(alt, true).then(function (result) {
                                    if (!result.ok) { revokeURL(alt.url); markFirstPaint(); return; }
                                    currentKey = alt.key;
                                    setLastShownKey(type, alt.key);
                                    markFirstPaint();
                                    return doPrefetchAndSwitch();
                                });
                            });
                        };

                        if (!r.pick) {
                            if (!r.timeout) idbDelete(showKey);
                            return showFallback();
                        }

                        return doSwitch(r.pick, true).then(function (result) {
                            if (!result.ok) {
                                if (r.pick.url) revokeURL(r.pick.url);
                                if (!result.timeout) idbDelete(showKey);
                                return showFallback();
                            }
                            currentKey = showKey;
                            setLastShownKey(type, showKey);
                            markFirstPaint();

                            var prefetchKey = getLastPrefetchKey(type);
                            var canReuse = prefetchKey && prefetchKey !== showKey && keys.indexOf(prefetchKey) >= 0;

                            if (!canReuse) {
                                return doPrefetchAndSwitch();
                            }

                            return idbGet(prefetchKey).then(function (tBlob) {
                                if (!tBlob) return doPrefetchAndSwitch();
                                return createVerifiedPick(tBlob, prefetchKey, false, null).then(function (rp) {
                                    if (!rp.pick) return doPrefetchAndSwitch();
                                    nextReady = rp.pick;
                                    return doSwitchAfterDelay();
                                });
                            });
                        });
                    });
                });
            }

            function init() {
                if (isLocalPriority()) {
                    try { localStorage.setItem(POOL_FULL_PREFIX + type, '1'); } catch (e) {}

                    var localProtectTimer = setTimeout(function() {
                        if (!markFirstPaint._done) markFirstPaint();
                    }, 4200);

                    return tryRevisitLocal().then(function (handled) {
                        if (handled) { clearTimeout(localProtectTimer); return; }

                        return pickFromQueueOrFetch(true).then(function (pick) {
                            if (!pick) {
                                markFirstPaint();
                                clearTimeout(localProtectTimer);
                                return;
                            }
                            currentKey = pick.key;
                            return doSwitch(pick, true).then(function (result) {
                                if (!result.ok) {
                                    if (pick.url) revokeURL(pick.url);
                                    markFirstPaint(); clearTimeout(localProtectTimer); return;
                                }
                                markFirstPaint();
                                clearTimeout(localProtectTimer);
                                setLocalShownId(type, idOfPick(pick));
                                prefetchNext();
                            });
                        });
                    }).catch(function(err) {
                        console.warn('[nuomi] local init error:', err);
                        clearTimeout(localProtectTimer);
                        markFirstPaint();
                    });
                }

                return idbListKeys(type + ':').then(function (keys) {
                    updatePoolFullFlag(type, keys.length);

                    if (keys.length === 0) {
                        return fetchWithRetry(true).then(function (fresh) {
                            if (!fresh) { markFirstPaint(); return; }
                            return doSwitch(fresh, true).then(function (result) {
                                if (!result.ok) {
                                    if (fresh.url) revokeURL(fresh.url);
                                    markFirstPaint(); return;
                                }
                                currentKey = fresh.key;
                                if (!fresh.isLocal && fresh.key) setLastShownKey(type, fresh.key);
                                markFirstPaint();

                                return fetchWithRetry().then(function (nextPick) {
                                    if (nextPick && !nextReady) {
                                        nextReady = nextPick;
                                        if (!nextPick.isLocal && nextPick.key) {
                                            setLastPrefetchKey(type, nextPick.key);
                                        }
                                    } else if (nextPick && nextPick.url) {
                                        revokeURL(nextPick.url);
                                    }
                                }).catch(function () {});
                            });
                        });
                    }

                    return tryRevisitApi(keys);
                }).then(function () {
                    return idbListKeys(type + ':').then(function (keys) {
                        updatePoolFullFlag(type, keys.length);
                        if (nextReady || prefetchP) return;

                        if (keys.length >= POOL_LIMIT) {
                            return poolPickSequential(type, currentKey).then(function (pick) {
                                if (pick && !nextReady) {
                                    nextReady = pick;
                                    if (pick.key) setLastPrefetchKey(type, pick.key);
                                } else if (pick && pick.url) {
                                    revokeURL(pick.url);
                                }
                            });
                        }
                        return fetchWithRetry().then(function (fresh) {
                            if (fresh && !nextReady) {
                                nextReady = fresh;
                                if (!fresh.isLocal && fresh.key) setLastPrefetchKey(type, fresh.key);
                            } else if (fresh && fresh.url) {
                                revokeURL(fresh.url);
                            }
                        }).catch(function () {});
                    });
                }).catch(function () {});
            }

            function fillOneLocalWithRetry(type, filepath) {
                var tried = 0;
                function attempt() {
                    return idbGetLocal(type, filepath).then(function (blob) {
                        if (blob && blob.size > 0) return { ok: true };
                        return fetchBlob(filepath, FETCH_TIMEOUT_MS).then(function (b) {
                            return createVerifiedPick(b, null, true, filepath).then(function (r) {
                                if (!r.pick) {
                                    return { ok: false, fatal: !r.timeout };
                                }
                                return poolPutLocal(type, filepath, b).then(function () {
                                    revokeURL(r.pick.url);
                                    return { ok: true };
                                });
                            });
                        }).catch(function (err) {
                            var isHttpError = err && err.httpStatus && err.httpStatus >= 400;
                            return { ok: false, fatal: !!isHttpError };
                        });
                    }).catch(function () { return { ok: false, fatal: false }; });
                }
                function run() {
                    return attempt().then(function (result) {
                        if (result.ok) {
                            unmarkLocalBlacklisted(type, filepath);
                            return true;
                        }
                        if (result.fatal) {
                            markLocalBlacklisted(type, filepath);
                            return false;
                        }
                        tried++;
                        if (tried >= LOCAL_MAX_RETRY) return false;
                        return sleep(LOCAL_RETRY_INTERVAL_MS).then(run);
                    });
                }
                return run();
            }

            function prefetchFillLocal() {
                localFillStopped = false;
                if (!isLocalPriority()) return Promise.resolve();
                if (typeof navigator !== 'undefined' && navigator.onLine === false) return Promise.resolve();

                var list = getLocalImageList(type);
                if (list.length === 0) return Promise.resolve();

                var gap = interval;
                var chain = Promise.resolve();
                var fetched = 0;

                list.forEach(function (item) {
                    if (!item || !item.file) return;
                    var filepath = item.file;
                    if (isLocalBlacklisted(type, filepath)) return;

                    chain = chain.then(function () {
                        if (localFillStopped) return null;
                        if (fetched >= LOCAL_PREFETCH_MAX) return null;
                        return idbGetLocal(type, filepath).then(function (blob) {
                            if (blob && blob.size > 0) return null;
                            fetched++;
                            return fillOneLocalWithRetry(type, filepath).then(function () {
                                return sleep(gap);
                            });
                        });
                    });
                });
                return chain;
            }

            function startLoop() {
                if (running) return;
                running = true;
                localFillStopped = true;
                failCount = 0;
                var token = ++loopToken;

                function scheduleNext(delay) {
                    if (!running || token !== loopToken) return;
                    sleep(Math.max(0, delay)).then(function () {
                        if (!running || token !== loopToken) return;
                        var tickStartAt = Date.now();
                        applyNext().then(function (result) {
                            if (!running || token !== loopToken) return;

                            var elapsed = Date.now() - tickStartAt;

                            if (!result) {
                                scheduleNext(interval);
                                return;
                            }
                            if (result.ok) {
                                failCount = 0;
                                scheduleNext(Math.max(0, interval - elapsed));
                                return;
                            }
                            if (result.timeout) {
                                scheduleNext(Math.max(0, interval - elapsed));
                                return;
                            }
                            failCount++;
                            var target = Math.min(failCount * interval, loopBackoffMax);
                            scheduleNext(Math.max(0, target - elapsed));
                        }).catch(function (err) {
                            console.warn('[nuomi] applyNext failed:', err);
                            failCount++;
                            var target = Math.min(failCount * interval, loopBackoffMax);
                            scheduleNext(Math.max(0, target));
                        });
                    });
                }

                prefetchNext();
                scheduleNext(interval);
            }
            function stopLoop() { running = false; loopToken++; }
            function forceSwitch() { return applyNext(); }
            function destroy() {
                stopLoop();
                if (nextReady && nextReady.url) revokeURL(nextReady.url);
                nextReady = null;
            }

            return {
                init: init,
                startLoop: startLoop,
                stopLoop: stopLoop,
                forceSwitch: forceSwitch,
                prefetchNext: prefetchNext,
                prefetchFillLocal: prefetchFillLocal,
                resetBackoff: resetBackoff,
                destroy: destroy,
                isRunning: function () { return running; }
            };
        }

        window.addEventListener('beforeunload', function () {
            if (liveBlobUrl.bg)     revokeURL(liveBlobUrl.bg);
            if (liveBlobUrl.banner) revokeURL(liveBlobUrl.banner);
        });

        return {
            create: createRotator,
            poolLimit: POOL_LIMIT
        };
    })();

    /* ==========================================================================
       4. main()
       ========================================================================== */
    function main() {
        var SITE_CFG = (window.NUOMI_CONFIG && window.NUOMI_CONFIG.site) || {};
        var STAGGER_DELAY_MS = 300;

        /* 4.0 首页公告条：关闭后不再显示 */
        (function () {
            var bar = document.getElementById('homeAnnouncement');
            if (!bar) return;
            var closeBtn = document.getElementById('homeAnnouncementClose');
            if (!closeBtn) return;

            var annId = bar.dataset.annId || '';
            var dismissKey = 'nuomi_home_ann_dismissed';

            try {
                var dismissed = JSON.parse(localStorage.getItem(dismissKey) || '[]');
                if (annId && dismissed.indexOf(annId) >= 0) {
                    bar.style.display = 'none';
                    return;
                }
            } catch (e) {}

            closeBtn.addEventListener('click', function () {
                bar.classList.add('is-closing');
                try {
                    var dismissed = JSON.parse(localStorage.getItem(dismissKey) || '[]');
                    if (annId && dismissed.indexOf(annId) < 0) {
                        dismissed.push(annId);
                        if (dismissed.length > 20) dismissed = dismissed.slice(-20);
                        localStorage.setItem(dismissKey, JSON.stringify(dismissed));
                    }
                } catch (e) {}
                setTimeout(function () {
                    if (bar.parentNode) bar.parentNode.removeChild(bar);
                }, 320);
            });
        })();

        /* 4.1 主题切换 */
        (function () {
            const html = document.documentElement;
            const btn = document.getElementById('frontThemeBtn');
            if (!btn) return;
            const mediaDark = window.matchMedia('(prefers-color-scheme: dark)');
            const ICONS = {
                auto:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor" stroke="none"/></svg>',
                light: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>',
                dark:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
            };
            const LABELS = { auto: '跟随系统', light: '浅色模式', dark: '深色模式' };
            const ORDER = ['auto', 'light', 'dark'];
            let currentMode = localStorage.getItem('themeMode') || 'auto';
            if (ORDER.indexOf(currentMode) === -1) currentMode = 'auto';
            function applyMode(mode) {
                localStorage.setItem('themeMode', mode);
                var realDark;
                if (mode === 'dark') realDark = true;
                else if (mode === 'light') realDark = false;
                else realDark = mediaDark.matches;
                html.dataset.theme = realDark ? 'dark' : 'light';
                btn.innerHTML = ICONS[mode];
                btn.title = LABELS[mode] + '（点击切换）';
                btn.setAttribute('aria-label', LABELS[mode]);
                btn.classList.toggle('active', mode !== 'auto');
            }
            applyMode(currentMode);
            mediaDark.addEventListener('change', () => {
                if (localStorage.getItem('themeMode') === 'auto') applyMode('auto');
            });
            btn.addEventListener('click', () => {
                const idx = ORDER.indexOf(currentMode);
                currentMode = ORDER[(idx + 1) % ORDER.length];
                applyMode(currentMode);
            });
        })();

        /* 4.2 Tooltip */
        const tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        tooltip.innerHTML = '<div class="tooltip-title"></div><div class="tooltip-sub"></div>';
        document.body.appendChild(tooltip);

        let tooltipTimer = null;
        let touchStartTime = 0;
        let touchStartX = 0;
        let touchStartY = 0;
        let longPressTriggered = false;
        let currentTooltipCard = null;

        function showTooltip(card, x, y) {
            clearTimeout(tooltipTimer);
            tooltip.querySelector('.tooltip-title').textContent = card.dataset.tooltipTitle || '';
            tooltip.querySelector('.tooltip-sub').textContent = card.dataset.tooltipSub || '';
            tooltip.style.left = (x + 12) + 'px';
            tooltip.style.top = (y + 12) + 'px';
            tooltip.classList.add('show');
            tooltipTimer = setTimeout(hideTooltip, 3500);
        }
        function hideTooltip() { clearTimeout(tooltipTimer); tooltip.classList.remove('show'); currentTooltipCard = null; }
        function isOverflowElement(el) { return el ? el.scrollWidth > el.clientWidth : false; }
        function shouldCardTriggerTooltip(card) {
            return isOverflowElement(card.querySelector('.app-name')) || isOverflowElement(card.querySelector('.app-sub'));
        }
        function updateTooltipMarkers() {
            document.querySelectorAll('.card-item').forEach(card => {
                card.classList.toggle('card-item-tooltip', shouldCardTriggerTooltip(card));
            });
        }
        document.addEventListener('mouseover', function (e) {
            const card = e.target.closest('.card-item');
            if (!card) return;
            if (!card.classList.contains('card-item-tooltip')) { hideTooltip(); return; }
            currentTooltipCard = card;
            showTooltip(card, e.clientX, e.clientY);
        });
        document.addEventListener('mouseout', function (e) {
            const card = e.target.closest('.card-item');
            if (card && card === currentTooltipCard) hideTooltip();
        });
        document.addEventListener('touchstart', function (e) {
            const card = e.target.closest('.card-item');
            if (!card) return;
            touchStartTime = Date.now(); 
            touchStartX = e.touches[0].clientX; 
            touchStartY = e.touches[0].clientY;
            longPressTriggered = false; 
            currentTooltipCard = card;
        }, { passive: true });
        document.addEventListener('touchend', function (e) {
            const card = e.target.closest('.card-item');
            if (!card || card !== currentTooltipCard) return;
            if (Date.now() - touchStartTime >= 500 && !longPressTriggered && card.classList.contains('card-item-tooltip')) {
                longPressTriggered = true; showTooltip(card, touchStartX, touchStartY);
            }
        }, { passive: true });
        document.addEventListener('touchmove', function (e) { 
            if (!touchStartTime) return;
            const dx = e.touches[0].clientX - touchStartX;
            const dy = e.touches[0].clientY - touchStartY;
            if (Math.sqrt(dx * dx + dy * dy) > 10) {
                touchStartTime = 0; 
                longPressTriggered = false; 
            }
        }, { passive: true });

        /* 4.3 卡片文本 */
        function shrinkText(el, maxFont, minFont, step) {
            if (!el) return;
            el.style.fontSize = maxFont + 'px';
            while (el.scrollWidth > el.clientWidth && parseFloat(getComputedStyle(el).fontSize) > minFont) {
                el.style.fontSize = (parseFloat(getComputedStyle(el).fontSize) - step) + 'px';
            }
        }
        
        function processCardText(listEl) {
            if (!listEl) return;
            requestAnimationFrame(() => {
                listEl.querySelectorAll('.app-name').forEach(n => shrinkText(n, 15, 11, 0.4));
                listEl.querySelectorAll('.app-sub').forEach(s => shrinkText(s, 11, 8, 0.3));
                updateTooltipMarkers();
            });
        }

        /* 4.4 分类切换 */
        (function () {
            const tabs = document.querySelectorAll('.tab-item');
            const lists = document.querySelectorAll('.app-list');
            const appEl = document.getElementById('app');
            const tabList = document.getElementById('tabList');
            const indicator = document.getElementById('tabIndicator');

            const IND_WIDTH = 22;
            let indicatorReady = false;

            function updateIndicator(animate) {
                if (!indicator || !tabList) return;
                const activeTab = tabList.querySelector('.tab-item.active');
                if (!activeTab) return;
                const listRect = tabList.getBoundingClientRect();
                const tabRect  = activeTab.getBoundingClientRect();
                const left = tabRect.left - listRect.left + tabRect.width / 2 - IND_WIDTH / 2;
                if (!animate || !indicatorReady) indicator.style.transition = 'none';
                indicator.style.left  = left + 'px';
                indicator.style.width = IND_WIDTH + 'px';
                if (!animate || !indicatorReady) {
                    void indicator.offsetHeight;
                    indicator.style.transition = '';
                }
                indicator.classList.add('ready');
                indicatorReady = true;
            }

            (function initIndicatorWhenReady() {
                function go() { setTimeout(function () { updateIndicator(false); }, 1000); }
                if (document.body.classList.contains('page-ready')) { go(); }
                else {
                    const obs = new MutationObserver(function () {
                        if (document.body.classList.contains('page-ready')) { obs.disconnect(); go(); }
                    });
                    obs.observe(document.body, { attributes: true, attributeFilter: ['class'] });
                }
            })();

            function applyAppVerticalMargin(animate) {
                if (!appEl) return;
                if (isMobileView()) { appEl.style.marginTop = ''; appEl.style.marginBottom = ''; return; }
                const availH = window.innerHeight - 80;
                const appH = appEl.offsetHeight;
                const mt = Math.max(0, (availH - appH) / 2);
                if (!animate) {
                    const savedTransition = appEl.style.transition;
                    appEl.style.transition = 'none';
                    appEl.style.marginTop = mt + 'px';
                    appEl.style.marginBottom = mt + 'px';
                    void appEl.offsetHeight;
                    appEl.style.transition = savedTransition;
                } else {
                    appEl.style.marginTop = mt + 'px';
                    appEl.style.marginBottom = mt + 'px';
                }
            }

            (function initVerticalCenter() {
                function go() { setTimeout(function () { applyAppVerticalMargin(false); }, 300); }
                if (document.body.classList.contains('page-ready')) { go(); }
                else {
                    const obs = new MutationObserver(function () {
                        if (document.body.classList.contains('page-ready')) { obs.disconnect(); go(); }
                    });
                    obs.observe(document.body, { attributes: true, attributeFilter: ['class'] });
                }
            })();

            window.addEventListener('resize', function () {
                updateIndicator(false);
                applyAppVerticalMargin(false);
            });

            function withHeightTransitionMobile(mutate) {
                if (!appEl) { mutate(); return; }
                let oldList = null;
                lists.forEach(list => { if (list.style.display === 'flex') oldList = list; });
                const oldListH = oldList ? oldList.getBoundingClientRect().height : 0;
                const oldAppH = appEl.getBoundingClientRect().height;
                const oldDocHeight = document.documentElement.scrollHeight;
                const startScroll = window.scrollY;
                document.body.style.minHeight = oldDocHeight + 'px';
                mutate();
                let newList = null;
                lists.forEach(list => { if (list.style.display === 'flex') newList = list; });
                if (!newList) { document.body.style.minHeight = ''; return; }
                const newListH = newList.getBoundingClientRect().height;
                const newAppH = appEl.getBoundingClientRect().height;
                const estimatedNewDocHeight = oldDocHeight - oldListH + newListH;
                const newMaxScroll = Math.max(0, estimatedNewDocHeight - window.innerHeight);
                const targetScroll = Math.min(startScroll, newMaxScroll);
                let scrollRaf = null;
                if (Math.abs(targetScroll - startScroll) > 1) {
                    const scrollStartTime = performance.now();
                    const scrollDur = 600;
                    (function scrollTick(now) {
                        const t = Math.min(1, (now - scrollStartTime) / scrollDur);
                        const e = 1 - Math.pow(1 - t, 3);
                        window.scrollTo(0, startScroll + (targetScroll - startScroll) * e);
                        if (t < 1) scrollRaf = requestAnimationFrame(scrollTick);
                        else scrollRaf = null;
                    })(performance.now());
                }
                appEl.classList.add('app-animating');
                const clearGlow = () => appEl.classList.remove('app-animating');
                if (Math.abs(oldListH - newListH) < 1 && Math.abs(oldAppH - newAppH) < 1) {
                    setTimeout(() => {
                        if (scrollRaf) cancelAnimationFrame(scrollRaf);
                        document.body.style.minHeight = '';
                        clearGlow();
                    }, 620);
                    return;
                }
                newList.style.height = oldListH + 'px'; newList.style.overflow = 'hidden';
                void newList.offsetHeight;
                newList.style.height = newListH + 'px';
                let appHeightChanged = false;
                if (Math.abs(oldAppH - newAppH) >= 1) {
                    appEl.style.height = oldAppH + 'px'; appEl.style.overflow = 'hidden';
                    void appEl.offsetHeight;
                    appEl.style.height = newAppH + 'px'; appHeightChanged = true;
                }
                setTimeout(() => {
                    if (scrollRaf) { cancelAnimationFrame(scrollRaf); scrollRaf = null; }
                    newList.style.height = ''; newList.style.overflow = '';
                    if (appHeightChanged) { appEl.style.height = ''; appEl.style.overflow = ''; }
                    const finalDocH = appEl.getBoundingClientRect().bottom + window.scrollY;
                    const finalMax = Math.max(0, finalDocH - window.innerHeight);
                    if (window.scrollY > finalMax) window.scrollTo(0, finalMax);
                    document.body.style.minHeight = '';
                    clearGlow();
                }, 620);
            }

            function withHeightTransitionDesktop(mutate) {
                if (!appEl) { mutate(); return; }
                let oldList = null;
                lists.forEach(list => { if (list.style.display === 'flex') oldList = list; });
                if (!oldList) { mutate(); return; }
                const oldListH = oldList.getBoundingClientRect().height;
                const oldDocHeight = document.documentElement.scrollHeight;
                const startScroll = window.scrollY;
                document.body.style.minHeight = oldDocHeight + 'px';
                mutate();
                let newList = null;
                lists.forEach(list => { if (list.style.display === 'flex') newList = list; });
                if (!newList) { document.body.style.minHeight = ''; return; }
                const newListH = newList.getBoundingClientRect().height;
                const newAppH  = appEl.getBoundingClientRect().height;
                const availH = window.innerHeight - 80;
                const newMarginTop = Math.max(0, (availH - newAppH) / 2);
                const prevMt = appEl.style.marginTop;
                const prevMb = appEl.style.marginBottom;
                const prevTransition = appEl.style.transition;
                appEl.style.transition = 'none';
                appEl.style.marginTop = newMarginTop + 'px';
                appEl.style.marginBottom = newMarginTop + 'px';
                document.body.style.minHeight = '';
                const realNewDocHeight = document.documentElement.scrollHeight;
                document.body.style.minHeight = oldDocHeight + 'px';
                appEl.style.marginTop = prevMt;
                appEl.style.marginBottom = prevMb;
                void appEl.offsetHeight;
                appEl.style.transition = prevTransition;
                const newScrollMax = Math.max(0, realNewDocHeight - window.innerHeight);
                const targetScroll = Math.min(startScroll, newScrollMax);
                const heightChanged = Math.abs(oldListH - newListH) >= 1;
                const scrollChanged = Math.abs(startScroll - targetScroll) > 1;
                if (!heightChanged && !scrollChanged) { document.body.style.minHeight = ''; return; }
                appEl.classList.add('app-animating');
                const clearGlow = () => appEl.classList.remove('app-animating');
                newList.style.height = oldListH + 'px';
                newList.style.overflow = 'hidden';
                void newList.offsetHeight;
                newList.style.height = newListH + 'px';
                appEl.style.marginTop = newMarginTop + 'px';
                appEl.style.marginBottom = newMarginTop + 'px';
                let scrollRaf = null;
                if (scrollChanged) {
                    const scrollStartTime = performance.now();
                    const scrollDur = 620;
                    (function scrollTick(now) {
                        const t = Math.min(1, (now - scrollStartTime) / scrollDur);
                        const e = 1 - Math.pow(1 - t, 3);
                        window.scrollTo(0, startScroll + (targetScroll - startScroll) * e);
                        if (t < 1) scrollRaf = requestAnimationFrame(scrollTick);
                        else scrollRaf = null;
                    })(performance.now());
                }
                setTimeout(() => {
                    if (scrollRaf) { cancelAnimationFrame(scrollRaf); scrollRaf = null; }
                    newList.style.height = '';
                    newList.style.overflow = '';
                    document.body.style.minHeight = '';
                    clearGlow();
                }, 620);
            }

            function withHeightTransition(mutate) {
                if (isMobileView()) { withHeightTransitionMobile(mutate); }
                else { withHeightTransitionDesktop(mutate); }
            }

            function playCardEnterAnimation(listEl) {
                const items = listEl.querySelectorAll('.item');
                items.forEach((it, idx) => { it.classList.remove('item-show'); setTimeout(() => it.classList.add('item-show'), 50 + idx * 30); });
                setTimeout(() => { processCardText(listEl); }, 50 + items.length * 30 + 120);
            }
            function playCardLeaveAnimation(listEl, onComplete) {
                const items = listEl.querySelectorAll('.item');
                items.forEach(it => it.classList.remove('item-show'));
                setTimeout(onComplete, 220);
            }

            lists.forEach(list => { if (list.style.display === 'flex') playCardEnterAnimation(list); });

            tabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    if (this.classList.contains('active')) return;
                    const newCat = tab.dataset.cat;
                    let oldShowList = null;
                    lists.forEach(list => { if (list.style.display === 'flex') oldShowList = list; });
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    updateIndicator(true);
                    const doSwitch = () => {
                        if (oldShowList) oldShowList.style.display = 'none';
                        lists.forEach(list => {
                            if (list.dataset.cat === newCat) { list.style.display = 'flex'; playCardEnterAnimation(list); }
                        });
                    };
                    if (oldShowList) playCardLeaveAnimation(oldShowList, () => withHeightTransition(doSwitch));
                    else withHeightTransition(doSwitch);
                });
            });
        })();

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                const showList = Array.from(document.querySelectorAll('.app-list'))
                    .find(el => el.style.display === 'flex');
                if (showList) {
                    processCardText(showList);
                } else {
                    updateTooltipMarkers();
                }
            }, 250);
        });

        /* 4.5 卡片按压 + 弹窗 */
        (function () {
            const modal = document.getElementById('myModal');
            const modalImg = document.getElementById('modalImage');
            function showImage(src) { if (!src) return; modalImg.src = src; modalImg.style.display = 'block'; modal.style.display = 'flex'; }
            function hideImage() { modal.style.display = 'none'; modalImg.src = ''; modalImg.style.display = 'none'; }
            function clearTiltClasses(el) {
                el.classList.remove('touch-down', 'tilt-top', 'tilt-bottom', 'tilt-left', 'tilt-right', 'tilt-top-left', 'tilt-top-right', 'tilt-bottom-left', 'tilt-bottom-right');
            }
            function updateTilt(item, clientX, clientY) {
                const rect = item.getBoundingClientRect();
                const x = clientX - rect.left, y = clientY - rect.top;
                const cx = rect.width / 2, cy = rect.height / 2;
                clearTiltClasses(item); item.classList.add('touch-down');
                const dx = x - cx, dy = y - cy, threshold = 18;
                if (Math.abs(dx) < threshold && Math.abs(dy) < threshold) return;
                if (dy < -threshold && Math.abs(dx) < threshold) item.classList.add('tilt-top');
                else if (dy > threshold && Math.abs(dx) < threshold) item.classList.add('tilt-bottom');
                else if (dx < -threshold && Math.abs(dy) < threshold) item.classList.add('tilt-left');
                else if (dx > threshold && Math.abs(dy) < threshold) item.classList.add('tilt-right');
                else if (dy < -threshold && dx < -threshold) item.classList.add('tilt-top-left');
                else if (dy < -threshold && dx > threshold) item.classList.add('tilt-top-right');
                else if (dy > threshold && dx < -threshold) item.classList.add('tilt-bottom-left');
                else if (dy > threshold && dx > threshold) item.classList.add('tilt-bottom-right');
            }
            function setPressDown(el) {
                if (!el || !el.classList.contains('card-item')) return;
                el.classList.add('touch-down');
                const outerItem = el.closest('.item'); if (outerItem) outerItem.classList.add('touch-down');
            }
            function setPressUp(el) {
                if (!el || !el.classList.contains('card-item')) return;
                clearTiltClasses(el);
                const outerItem = el.closest('.item'); if (outerItem) outerItem.classList.remove('touch-down');
            }
            document.addEventListener('mousedown', function (e) {
                const item = e.target.closest('.card-item'); if (!item) return;
                setPressDown(item); updateTilt(item, e.clientX, e.clientY);
            });
            document.addEventListener('mousemove', function (e) {
                const item = e.target.closest('.card-item');
                if (!item || !item.classList.contains('touch-down')) return;
                updateTilt(item, e.clientX, e.clientY);
            });
            document.addEventListener('mouseup', function (e) { setPressUp(e.target.closest('.card-item')); });
            document.addEventListener('mouseleave', function (e) { setPressUp(e.target.closest('.card-item')); });
            document.addEventListener('touchstart', function (e) {
                const item = e.target.closest('.card-item'); if (!item) return;
                setPressDown(item); updateTilt(item, e.touches[0].clientX, e.touches[0].clientY);
            }, { passive: true });
            document.addEventListener('touchmove', function (e) {
                const item = e.target.closest('.card-item');
                if (!item || !item.classList.contains('touch-down')) return;
                updateTilt(item, e.touches[0].clientX, e.touches[0].clientY);
            }, { passive: true });
            document.addEventListener('touchend', function (e) { setPressUp(e.target.closest('.card-item')); }, { passive: true });
            document.addEventListener('touchcancel', function (e) { setPressUp(e.target.closest('.card-item')); }, { passive: true });
            document.addEventListener('click', function (e) {
                const item = e.target.closest('.card-item');
                if (item && item.dataset.type === 'image') { e.preventDefault(); showImage(item.dataset.image); }
            });
            document.addEventListener('contextmenu', function (e) {
                if (e.target.id === 'modalImage') {
                    return;
                }
                e.preventDefault();
            });
            document.querySelector('#myModal .close').addEventListener('click', hideImage);
            modal.addEventListener('click', function (e) { if (e.target === modal) hideImage(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display === 'flex') hideImage(); });
        })();

        /* 4.6 头像连点 */
        (function () {
            const logo = document.getElementById('avatarLogo');
            if (!logo) return;
            logo.addEventListener('contextmenu', function (e) { e.preventDefault(); });

            let needClick = parseInt(SITE_CFG.adminClickCount, 10);
            if (isNaN(needClick) || needClick < 3) needClick = 10;
            if (needClick > 30) needClick = 30;
            let windowSec = parseFloat(SITE_CFG.adminClickWindow);
            if (isNaN(windowSec) || windowSec < 1) windowSec = 10;
            if (windowSec > 60) windowSec = 60;
            const CLICK_WINDOW = windowSec * 1000, NEED_CLICK = needClick;
            let clicks = [], globalMouseUpHandler = null;
            function clearLogoPressState() {
                if (globalMouseUpHandler) { document.removeEventListener('mouseup', globalMouseUpHandler); globalMouseUpHandler = null; }
            }
            logo.addEventListener('mousedown', function (e) {
                e.preventDefault(); clearLogoPressState(); logo.classList.add('logo-press');
                globalMouseUpHandler = function () { clearLogoPressState(); logo.classList.remove('logo-press'); };
                document.addEventListener('mouseup', globalMouseUpHandler);
            });
            window.addEventListener('blur', function () { clearLogoPressState(); logo.classList.remove('logo-press'); });
            logo.addEventListener('touchstart', function (e) { e.preventDefault(); logo.classList.add('logo-press'); }, { passive: true });
            function touchEndReset() { logo.classList.remove('logo-press'); }
            logo.addEventListener('touchend', touchEndReset, { passive: true });
            logo.addEventListener('touchcancel', touchEndReset, { passive: true });
            logo.addEventListener('click', function () {
                const now = Date.now();
                clicks = clicks.filter(function (t) { return now - t < CLICK_WINDOW; });
                clicks.push(now);
                if (clicks.length >= NEED_CLICK) { clicks.length = 0; window.location.href = '/admin'; }
            });
        })();

        /* 4.7 间隔 */
        var BG_INTERVAL_MS     = clampInt(SITE_CFG.bgInterval,     3, 60, 10) * 1000;
        var BANNER_INTERVAL_MS = clampInt(SITE_CFG.bannerInterval, 3, 60, 6)  * 1000;

        /* 4.8 三态轮播开关 */
        var LOOP_MODE_KEY = 'autoLoopMode';
        var LOOP_MODES = ['off', 'on', 'fresh'];
        function getLoopMode() {
            try {
                var m = localStorage.getItem(LOOP_MODE_KEY);
                if (LOOP_MODES.indexOf(m) >= 0) return m;
            } catch (e) {}
            return 'off';
        }
        function setLoopMode(m) { try { localStorage.setItem(LOOP_MODE_KEY, m); } catch (e) {} }

        var loopSwitchBtn = document.querySelector('.loop-switch');
        var iconPlay  = loopSwitchBtn.querySelector('.icon-play');
        var iconPause = loopSwitchBtn.querySelector('.icon-pause');
        var iconFresh = loopSwitchBtn.querySelector('.icon-fresh');

        function updateLoopButtonIcon() {
            var mode = getLoopMode();
            iconPlay.style.display  = (mode === 'off') ? 'block' : 'none';
            iconPause.style.display = (mode === 'on')  ? 'block' : 'none';
            if (iconFresh) iconFresh.style.display = (mode === 'fresh') ? 'block' : 'none';
            loopSwitchBtn.classList.toggle('active', mode === 'on');
            loopSwitchBtn.classList.toggle('loop-mode-fresh', mode === 'fresh');
            if (mode === 'off') {
                loopSwitchBtn.title = '轮播已关闭（点击开启）';
                loopSwitchBtn.setAttribute('aria-label', '轮播已关闭（点击开启）');
            } else if (mode === 'on') {
                loopSwitchBtn.title = '轮播已开启（长按3秒进入持续新图）';
                loopSwitchBtn.setAttribute('aria-label', '轮播已开启（长按3秒进入持续新图）');
            } else {
                loopSwitchBtn.title = '持续新图轮播中（点击停止）';
                loopSwitchBtn.setAttribute('aria-label', '持续新图轮播中（点击停止）');
            }
        }

        /* 4.9 背景轮播 */
        var bgLayer1 = document.getElementById('bgLayer1');
        var bgLayer2 = document.getElementById('bgLayer2');
        var bgCurrentLayer = bgLayer1;
        var bgNextLayer    = bgLayer2;

        function buildBgUrl() {
            var base = SITE_CFG.bgApi || '';
            if (!base) return '';
            var sep  = base.indexOf('?') >= 0 ? '&' : '?';
            return base + sep + '_t=' + Date.now() + '&_rand=' + randSuffix();
        }

        async function doBgSwitchWithUrl(url, isFirst) {
            if (isFirst || !bgCurrentLayer.style.backgroundImage) {
                bgCurrentLayer.style.backgroundImage = 'url(' + url + ')';
                bgCurrentLayer.style.opacity = '1';
                bgNextLayer.style.opacity = '0';
                return;
            }
            bgNextLayer.style.backgroundImage = 'url(' + url + ')';
            await waitTwoFrames();
            bgCurrentLayer.classList.add('layer-animate-out');
            bgNextLayer.classList.add('layer-animate-in');
            bgNextLayer.style.opacity = '1';
            await sleep(900);
            var t = bgCurrentLayer;
            bgCurrentLayer = bgNextLayer;
            bgNextLayer = t;
            bgNextLayer.style.backgroundImage = '';
            bgNextLayer.style.opacity = '0';
            bgNextLayer.classList.remove('layer-animate-in', 'layer-animate-out');
            bgCurrentLayer.classList.remove('layer-animate-out', 'layer-animate-in');
        }

        var bgRotator = NuomiPool.create('bg', {
            buildUrl: buildBgUrl,
            interval: BG_INTERVAL_MS,
            switchFn: doBgSwitchWithUrl
        });

        /* 4.10 Banner 轮播 */
        var bannerContainer = document.getElementById('bannerContainer');
        var bannerLayer1 = document.getElementById('bannerLayer1');
        var bannerLayer2 = document.getElementById('bannerLayer2');
        var layersContainer = document.getElementById('bannerLayers');
        var bannerCurrentLayer = bannerLayer1;
        var bannerNextLayer    = bannerLayer2;
        var bannerSlideDir = 'left-to-right';

        if (bannerContainer) bannerContainer.classList.add('banner-empty');

        function buildBannerUrl() {
            var base = SITE_CFG.bannerApi || '';
            if (!base) return '';
            var sep  = base.indexOf('?') >= 0 ? '&' : '?';
            return base + sep + '_t=' + Date.now() + '&_rand=' + randSuffix();
        }

        function resetBannerLayer(layer) {
            layer.style.transition = 'none';
            layer.style.transform = 'translateX(0)';
            layer.style.opacity = '0';
            layer.classList.remove('banner-slide-ltr-out', 'banner-slide-ltr-in', 'banner-slide-rtl-out', 'banner-slide-rtl-in');
            void layer.offsetHeight;
        }

        async function doBannerSwitchWithUrl(url, isFirst) {
            if (isFirst) {
                if (bannerContainer) bannerContainer.classList.remove('banner-empty');
                bannerCurrentLayer.style.backgroundImage = 'url(' + url + ')';
                bannerCurrentLayer.style.transform = 'translateX(0%)';
                bannerCurrentLayer.style.opacity = '1';
                bannerNextLayer.style.opacity = '0';
                return;
            }
            if (!bannerCurrentLayer.style.backgroundImage) {
                bannerNextLayer.style.backgroundImage = 'url(' + url + ')';
                bannerNextLayer.style.display = 'block';
                bannerNextLayer.style.transform = 'translateX(0%)';
                bannerNextLayer.style.transition = 'none';
                bannerNextLayer.style.opacity = '0';
                bannerNextLayer.classList.remove('banner-slide-ltr-out', 'banner-slide-ltr-in', 'banner-slide-rtl-out', 'banner-slide-rtl-in');
                void bannerNextLayer.offsetHeight;
                if (bannerContainer) bannerContainer.classList.remove('banner-empty');
                bannerNextLayer.style.transition = 'opacity 0.7s cubic-bezier(0.4, 0, 0.2, 1)';
                bannerNextLayer.style.opacity = '1';
                await sleep(700);
                var t0 = bannerCurrentLayer;
                bannerCurrentLayer = bannerNextLayer;
                bannerNextLayer = t0;
                resetBannerLayer(bannerNextLayer);
                bannerCurrentLayer.style.transform = 'translateX(0%)';
                bannerCurrentLayer.style.opacity = '1';
                bannerCurrentLayer.style.transition = '';
                return;
            }
            if (bannerContainer) bannerContainer.classList.remove('banner-empty');
            bannerNextLayer.style.backgroundImage = 'url(' + url + ')';
            bannerNextLayer.style.display = 'block';
            bannerNextLayer.style.opacity = '1';
            await waitTwoFrames();
            layersContainer.classList.add('banner-glow');
            setTimeout(() => layersContainer.classList.remove('banner-glow'), 500);
            if (bannerSlideDir === 'left-to-right') {
                bannerCurrentLayer.style.transform = 'translateX(0%)';
                bannerNextLayer.style.transform = 'translateX(-100%)';
                bannerCurrentLayer.classList.remove('banner-slide-ltr-out');
                bannerNextLayer.classList.remove('banner-slide-ltr-in');
                void bannerCurrentLayer.offsetHeight;
                bannerCurrentLayer.classList.add('banner-slide-ltr-out');
                bannerNextLayer.classList.add('banner-slide-ltr-in');
            } else {
                bannerCurrentLayer.style.transform = 'translateX(0%)';
                bannerNextLayer.style.transform = 'translateX(100%)';
                bannerCurrentLayer.classList.remove('banner-slide-rtl-out');
                bannerNextLayer.classList.remove('banner-slide-rtl-in');
                void bannerCurrentLayer.offsetHeight;
                bannerCurrentLayer.classList.add('banner-slide-rtl-out');
                bannerNextLayer.classList.add('banner-slide-rtl-in');
            }
            await sleep(600);
            var t = bannerCurrentLayer;
            bannerCurrentLayer = bannerNextLayer;
            bannerNextLayer = t;
            resetBannerLayer(bannerNextLayer);
            bannerCurrentLayer.style.transform = 'translateX(0%)';
            bannerCurrentLayer.style.opacity = '1';
            bannerSlideDir = bannerSlideDir === 'left-to-right' ? 'right-to-left' : 'left-to-right';
        }

        var bannerRotator = NuomiPool.create('banner', {
            buildUrl: buildBannerUrl,
            interval: BANNER_INTERVAL_MS,
            switchFn: doBannerSwitchWithUrl
        });

        /* 4.10.1 三态按钮 */
        var HOLD_DURATION = 3000;
        var holdStartTime = 0;
        var holdRaf = null;
        var isLongPress = false;

        function applyLoopMode(newMode) {
            setLoopMode(newMode);
            updateLoopButtonIcon();
            if (newMode === 'off') {
                bgRotator.stopLoop();
                bannerRotator.stopLoop();
                return;
            }
            bgRotator.forceSwitch().then(function () {
                if (getLoopMode() !== 'off') bgRotator.startLoop();
            }).catch(function (err) {
                console.warn('[nuomi] bg forceSwitch failed, start loop anyway:', err);
                if (getLoopMode() !== 'off') bgRotator.startLoop();
            });
            setTimeout(function () {
                bannerRotator.forceSwitch().then(function () {
                    if (getLoopMode() !== 'off') bannerRotator.startLoop();
                }).catch(function (err) {
                    console.warn('[nuomi] banner forceSwitch failed, start loop anyway:', err);
                    if (getLoopMode() !== 'off') bannerRotator.startLoop();
                });
            }, STAGGER_DELAY_MS);
        }

        function cancelHold() {
            if (holdRaf) cancelAnimationFrame(holdRaf);
            loopSwitchBtn.classList.remove('holding');
            loopSwitchBtn.style.setProperty('--hold-progress', '0');
        }

        loopSwitchBtn.addEventListener('pointerdown', function(e) {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            e.preventDefault();
            isLongPress = false;
            holdStartTime = performance.now();
            loopSwitchBtn.classList.add('holding');
            loopSwitchBtn.style.setProperty('--hold-progress', '0');
            
            function tick(now) {
                if (!loopSwitchBtn.classList.contains('holding')) return;
                var elapsed = now - holdStartTime;
                var pct = Math.min(100, (elapsed / HOLD_DURATION) * 100);
                loopSwitchBtn.style.setProperty('--hold-progress', pct);
                
                if (elapsed >= HOLD_DURATION) {
                    isLongPress = true;
                    cancelHold();
                    if (navigator.vibrate) navigator.vibrate(30);
                    if (getLoopMode() !== 'fresh') applyLoopMode('fresh');
                    return;
                }
                holdRaf = requestAnimationFrame(tick);
            }
            holdRaf = requestAnimationFrame(tick);
        });

        loopSwitchBtn.addEventListener('pointerup', cancelHold);
        loopSwitchBtn.addEventListener('pointercancel', cancelHold);
        loopSwitchBtn.addEventListener('pointerleave', cancelHold);

        loopSwitchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (isLongPress) {
                isLongPress = false;
                return;
            }
            var oldMode = getLoopMode();
            var newMode = 'off';
            if (oldMode === 'off') newMode = 'on';
            else if (oldMode === 'on') newMode = 'off';
            else if (oldMode === 'fresh') newMode = 'off';
            applyLoopMode(newMode);
        });

        /* 4.11 加载动画选择 */
        (function () {
            var styleEl = document.getElementById('nuomi-preloader-style');
            var picker  = document.getElementById('preloaderPicker');
            var btn     = document.getElementById('preloaderStyleBtn');
            if (!styleEl || !picker || !btn) return;
            var STORAGE_KEY = 'preloader_style_pref';
            (function applyUserPrefAtLoad() {
                var pref = null;
                try { pref = localStorage.getItem(STORAGE_KEY); } catch (e) {}
                if (!pref) return;
                var src = document.querySelector('script[type="text/x-preloader-css"][data-key="' + pref + '"]');
                if (src) styleEl.textContent = src.textContent;
            })();
            var isOpen = false;
            function refreshActive() {
                var pref = null;
                try { pref = localStorage.getItem(STORAGE_KEY); } catch (e) {}
                var current = pref || styleEl.dataset.defaultKey;
                picker.querySelectorAll('.preloader-picker-item').forEach(function (item) {
                    item.classList.toggle('active', item.dataset.key === current);
                });
                var resetBtn = picker.querySelector('.preloader-picker-reset');
                if (resetBtn) resetBtn.classList.toggle('active', !pref);
                btn.classList.toggle('active', !!pref && pref !== styleEl.dataset.defaultKey);
            }
            function updatePickerPosition() {
                if (!isOpen) return;
                var rect = btn.getBoundingClientRect();
                var pickerWidth = picker.offsetWidth || 320;
                var left = rect.left + rect.width / 2 - pickerWidth / 2;
                if (left < 10) left = 10;
                if (left + pickerWidth > window.innerWidth - 10) left = window.innerWidth - pickerWidth - 10;
                picker.style.left = left + 'px';
                var spaceAbove = rect.top;
                var spaceBelow = window.innerHeight - rect.bottom;
                if (spaceAbove > 200 || spaceAbove > spaceBelow) {
                    picker.style.top = 'auto';
                    picker.style.bottom = (window.innerHeight - rect.top + 10) + 'px';
                    picker.style.transformOrigin = 'bottom center';
                    picker.style.maxHeight = Math.max(120, spaceAbove - 20) + 'px';
                } else {
                    picker.style.bottom = 'auto';
                    picker.style.top = (rect.bottom + 10) + 'px';
                    picker.style.transformOrigin = 'top center';
                    picker.style.maxHeight = Math.max(120, spaceBelow - 20) + 'px';
                }
            }
            function openPicker() {
                isOpen = true;
                refreshActive();
                picker.style.visibility = 'hidden';
                picker.classList.add('open');
                updatePickerPosition();
                picker.style.visibility = 'visible';
            }
            function closePicker() {
                isOpen = false;
                picker.classList.remove('open');
                setTimeout(function() {
                    if (!isOpen) {
                        picker.style.left = '';
                        picker.style.top = '';
                        picker.style.bottom = '';
                        picker.style.maxHeight = '';
                    }
                }, 320);
            }
            btn.addEventListener('click', function (e) { e.stopPropagation(); isOpen ? closePicker() : openPicker(); });
            picker.addEventListener('click', function (e) {
                e.stopPropagation();
                var item = e.target.closest('.preloader-picker-item');
                if (item) {
                    var key = item.dataset.key;
                    if (!key) return;
                    try { localStorage.setItem(STORAGE_KEY, key); } catch (err) {}
                    refreshActive(); closePicker();
                    return;
                }
                var resetBtn = e.target.closest('.preloader-picker-reset');
                if (resetBtn) {
                    try { localStorage.removeItem(STORAGE_KEY); } catch (err) {}
                    refreshActive(); closePicker();
                }
            });
            document.addEventListener('click', function (e) {
                if (!isOpen) return;
                if (picker.contains(e.target)) return;
                if (btn.contains(e.target)) return;
                closePicker();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && isOpen) closePicker();
            });
            var scrollRAF = null;
            window.addEventListener('scroll', function() {
                if (!isOpen) return;
                if (scrollRAF) cancelAnimationFrame(scrollRAF);
                scrollRAF = requestAnimationFrame(function() {
                    updatePickerPosition();
                    scrollRAF = null;
                });
            }, { passive: true });
            window.addEventListener('resize', function() {
                if (isOpen) updatePickerPosition();
            });
        })();

        /* 4.12 横竖屏切换：重置轮播失败退避 */
        window.addEventListener('orientationchange', function () {
            bgRotator.resetBackoff();
            bannerRotator.resetBackoff();
        });

        /* 4.13 启动 */
        updateLoopButtonIcon();

        bgRotator.init().then(function () {
            var m = getLoopMode();
            if (m === 'on' || m === 'fresh') {
                bgRotator.startLoop();
            } else {
                bgRotator.prefetchFillLocal().catch(function () {});
            }
        }).catch(function (err) {
            console.warn('[nuomi] bg init failed:', err);
        });

        bannerRotator.init().then(function () {
            var m = getLoopMode();
            if (m === 'on' || m === 'fresh') {
                bannerRotator.startLoop();
            } else {
                bannerRotator.prefetchFillLocal().catch(function () {});
            }
        }).catch(function (err) {
            console.warn('[nuomi] banner init failed:', err);
        });

        window.addEventListener('beforeunload', function () {
            bgRotator.stopLoop();
            bannerRotator.stopLoop();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', main);
    } else {
        main();
    }
})();