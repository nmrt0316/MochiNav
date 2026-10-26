/* ==========================================================================
   糯米の引导页 - 后台管理脚本 admin.js
   加载位置：<head> 中同步加载（先执行主题防闪烁逻辑，DOMContentLoaded 后执行主逻辑）
   ========================================================================== */
(function () {
    'use strict';

    /* ==========================================================================
       1. 主题初始化 (立即执行，防首屏闪烁)
       ========================================================================== */
    var STORAGE_KEY = 'themeMode';
    var THEME_LABELS = { auto: '跟随系统', light: '浅色模式', dark: '深色模式' };
    var mediaDark = window.matchMedia('(prefers-color-scheme: dark)');

    function getSavedTheme() {
        var s = null;
        try { s = localStorage.getItem(STORAGE_KEY); } catch (e) {}
        if (['auto', 'light', 'dark'].indexOf(s) === -1) return 'auto';
        return s;
    }

    function applyThemeDataset(mode) {
        var real = mode === 'auto' ? (mediaDark.matches ? 'dark' : 'light') : mode;
        document.documentElement.dataset.theme = real;
    }

    applyThemeDataset(getSavedTheme());

    mediaDark.addEventListener('change', function () {
        if (getSavedTheme() === 'auto') applyThemeDataset('auto');
    });

    /* ==========================================================================
       2. 首屏揭幕
       ========================================================================== */
    function initPreloader() {
        var preloader = document.getElementById('preloader');
        if (!preloader) return;

        var bar = document.getElementById('preloaderBar');
        var isRevisit = false;
        try { isRevisit = sessionStorage.getItem('nuomi_admin_seen') === '1'; } catch (e) {}

        var MIN_DISPLAY = isRevisit ? 260 : 900;
        var MAX_DISPLAY = 2400;
        var startTime = Date.now();
        var finished = false;
        var loadDone = false;
        var tracker = { loaded: 0, total: 1, ratio: 0 };
        var keyImages = [];

        document.querySelectorAll('#preloader img, .auth-logo img').forEach(function (el) {
            if (el.tagName === 'IMG' && el.src) keyImages.push(el);
        });
        tracker.total = keyImages.length + 1;

        function updateRatio() { tracker.ratio = Math.min(1, tracker.loaded / tracker.total); }

        keyImages.forEach(function (img) {
            if (img.complete && img.naturalWidth > 0) { tracker.loaded++; }
            else {
                img.addEventListener('load', function () { tracker.loaded++; updateRatio(); });
                img.addEventListener('error', function () { tracker.loaded++; updateRatio(); });
            }
        });
        updateRatio();

        window.addEventListener('load', function () {
            loadDone = true; tracker.loaded = tracker.total; updateRatio();
        }, { once: true });

        var displayRatio = 0;
        (function smoothTick() {
            if (finished) return;
            var target = tracker.ratio;
            if (displayRatio < target) {
                var lead = Math.min(target + 0.08, 1);
                displayRatio += (lead - displayRatio) * 0.12 + 0.002;
                displayRatio = Math.min(displayRatio, lead);
            }
            if (!loadDone) displayRatio = Math.min(displayRatio, 0.92);
            if (bar) bar.style.width = (displayRatio * 100).toFixed(2) + '%';
            requestAnimationFrame(smoothTick);
        })();

        function tryReveal() {
            if (finished) return;
            var elapsed = Date.now() - startTime;
            if (loadDone && elapsed >= MIN_DISPLAY) { reveal(); }
            else { var wait = Math.max(30, MIN_DISPLAY - elapsed); setTimeout(tryReveal, Math.min(wait, 120)); }
        }

        function reveal() {
            if (finished) return;
            finished = true;
            var from = displayRatio;
            var finishStart = performance.now();
            (function finishTick(now) {
                var t = Math.min(1, (now - finishStart) / 320);
                var e = 1 - Math.pow(1 - t, 3);
                var v = from + (1 - from) * e;
                if (bar) bar.style.width = (v * 100).toFixed(2) + '%';
                if (t < 1) requestAnimationFrame(finishTick);
                else actuallyReveal();
            })(performance.now());
        }

        function actuallyReveal() {
            setTimeout(function () {
                if (preloader) preloader.classList.add('hidden');
                try { sessionStorage.setItem('nuomi_admin_seen', '1'); } catch (e) {}
                if (preloader) {
                    setTimeout(function () {
                        if (preloader.parentNode) preloader.parentNode.removeChild(preloader);
                    }, 800);
                }
            }, 180);
        }

        if (document.readyState === 'complete') { loadDone = true; tracker.loaded = tracker.total; updateRatio(); tryReveal(); }
        else { window.addEventListener('load', tryReveal, { once: true }); tryReveal(); }
        setTimeout(function () { if (!finished) reveal(); }, MAX_DISPLAY);
    }

    /* ==========================================================================
       3. 主逻辑
       ========================================================================== */
    function main() {

        /* ======================================================================
           3.1 基础设施
           ====================================================================== */

        function $(sel, root) { return (root || document).querySelector(sel); }
        function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

        var toastTimer = null;
        function toast(msg, type) {
            var t = $('#toast'); if (!t) return;
            t.textContent = msg; t.className = 'toast show' + (type ? ' ' + type : '');
            clearTimeout(toastTimer);
            var dur = (type === 'error' || type === 'warn') ? 4500 : 2600;
            toastTimer = setTimeout(function () { t.className = 'toast'; }, dur);
        }

        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

        async function api(action, body) {
            var fd = body instanceof FormData ? body : new FormData();
            if (!(body instanceof FormData)) { for (var k in body) fd.append(k, body[k]); }
            fd.append('action', action);
            var res = await fetch('index.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            var data;
            try { data = await res.json(); } catch (err) { throw new Error('服务器返回异常：' + res.status + ' ' + res.statusText); }
            return data;
        }

        function smoothReload(msg) {
            if (document.getElementById('nuomi-loading-mask')) return;
            var mask = document.createElement('div');
            mask.id = 'nuomi-loading-mask';
            mask.innerHTML = '<div class="nuomi-loading-spinner"></div><div class="nuomi-loading-text">' + (msg || '数据已保存，正在刷新页面...') + '</div>';
            document.body.appendChild(mask);
            setTimeout(function () {
                mask.style.transition = 'opacity 0.3s ease';
                mask.style.opacity = '0';
                setTimeout(function () { window.location.reload(); }, 300);
            }, 800);
        }

        /* ======================================================================
           3.2 全局交互
           ====================================================================== */

        var THEME_ICONS = {
            auto:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor" stroke="none"/></svg>',
            light: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>',
            dark:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
        };
        function updateThemeButtonIcon(mode) {
            var btn = document.getElementById('themeToggleBtn');
            if (!btn) return;
            btn.innerHTML = THEME_ICONS[mode] || THEME_ICONS.auto;
            btn.title = THEME_LABELS[mode] + '（点击切换）';
            btn.setAttribute('aria-label', THEME_LABELS[mode]);
        }
        function setThemeMode(mode) {
            try { localStorage.setItem(STORAGE_KEY, mode); } catch (e) {}
            applyThemeDataset(mode);
            updateThemeButtonIcon(mode);
            toast('已切换：' + THEME_LABELS[mode], 'success');
        }
        function cycleTheme() {
            var curr = getSavedTheme();
            var next = curr === 'auto' ? 'light' : (curr === 'light' ? 'dark' : 'auto');
            setThemeMode(next);
        }
        var themeBtn = document.getElementById('themeToggleBtn');
        if (themeBtn) { themeBtn.addEventListener('click', cycleTheme); updateThemeButtonIcon(getSavedTheme()); }

        function showModal(el) {
            if (!el) return;
            if (el._hideTimer) { clearTimeout(el._hideTimer); el._hideTimer = null; }
            if (el.style.display !== 'flex') { el.style.display = 'flex'; el.classList.remove('show'); void el.offsetWidth; }
            requestAnimationFrame(function () { requestAnimationFrame(function () { el.classList.add('show'); }); });
        }
        function hideModal(el) {
            if (!el) return;
            if (el._hideTimer) { clearTimeout(el._hideTimer); el._hideTimer = null; }
            el.classList.remove('show');
            el._hideTimer = setTimeout(function () { el._hideTimer = null; if (!el.classList.contains('show')) el.style.display = 'none'; }, 420);
        }

        var confirmOkCb = null, confirmCancelCb = null, confirmResolved = false;
        function openConfirm(title, msg, okCb, cancelCb) {
            $('#confirmTitle').textContent = title; $('#confirmMsg').innerHTML = msg;
            confirmOkCb = okCb || null; confirmCancelCb = cancelCb || null; confirmResolved = false;
            showModal($('#confirmModal'));
        }
        function resolveConfirm(ok) {
            if (confirmResolved) return; confirmResolved = true;
            var cb = ok ? confirmOkCb : confirmCancelCb; confirmOkCb = null; confirmCancelCb = null;
            hideModal($('#confirmModal')); if (cb) setTimeout(cb, 0);
        }
        function closeConfirm() { resolveConfirm(false); }
        var confirmOkBtn = $('#confirmOk'); if (confirmOkBtn) confirmOkBtn.addEventListener('click', function () { resolveConfirm(true); });
        var confirmCloseBtn = $('#confirmCloseOnly'); if (confirmCloseBtn) confirmCloseBtn.addEventListener('click', function () { resolveConfirm(false); });
        var confirmModal = $('#confirmModal'); if (confirmModal) confirmModal.addEventListener('click', function (e) { if (e.target === this) resolveConfirm(false); });

        var previewModalOpen = false;
        function openImagePreview(src) {
            if (previewModalOpen) return; previewModalOpen = true;
            var mask = document.createElement('div'); mask.className = 'modal-mask preview-mask'; mask.style.zIndex = '3000';
            mask.innerHTML = '<button type="button" class="preview-close-btn" aria-label="关闭">✕</button><img src="' + esc(src) + '" class="preview-img" alt="预览">';
            document.body.appendChild(mask);
            function close() { if (!previewModalOpen) return; previewModalOpen = false; mask.classList.remove('show'); document.removeEventListener('keydown', onKey); setTimeout(function () { if (mask.parentNode) mask.parentNode.removeChild(mask); }, 320); }
            function onKey(e) { if (e.key === 'Escape') close(); }
            mask.querySelector('.preview-close-btn').addEventListener('click', function (e) { e.stopPropagation(); close(); });
            mask.addEventListener('click', function (e) { if (e.target === mask || e.target.classList.contains('preview-img')) close(); });
            document.addEventListener('keydown', onKey);
            requestAnimationFrame(function () { requestAnimationFrame(function () { mask.classList.add('show'); }); });
        }

        var activeCellTimer = null;
        var currentActiveCell = null;

        function clearActiveCell() {
            if (currentActiveCell) {
                currentActiveCell.classList.remove('is-active');
                currentActiveCell = null;
            }
            clearTimeout(activeCellTimer);
            activeCellTimer = null;
        }

        function setActiveCell(cell) {
            if (currentActiveCell === cell) {
                clearTimeout(activeCellTimer);
                activeCellTimer = setTimeout(clearActiveCell, 3000);
                return;
            }
            clearActiveCell();
            if (cell) {
                cell.classList.add('is-active');
                currentActiveCell = cell;
                activeCellTimer = setTimeout(clearActiveCell, 3000);
            }
        }

        function refreshActiveTimer() {
            if (currentActiveCell) {
                clearTimeout(activeCellTimer);
                activeCellTimer = setTimeout(clearActiveCell, 3000);
            }
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.photo-cell') && !e.target.closest('.girl-cell')) {
                clearActiveCell();
            }
        });

        window._clearActiveCell = clearActiveCell;
        window._setActiveCell = setActiveCell;
        window._refreshActiveTimer = refreshActiveTimer;

        (function injectSwitchLoadingStyle() {
            var id = 'nuomi-switch-loading-style';
            if (document.getElementById(id)) return;
            var s = document.createElement('style');
            s.id = id;
            s.textContent = `.switch-mini.is-loading { opacity: 0.4; pointer-events: none; cursor: wait; }`;
            document.head.appendChild(s);
        })();

        function setSwitchLoading(input, on) {
            if (!input) return;
            var wrap = input.closest ? input.closest('.switch-mini') : null;
            if (!wrap && input.parentNode && input.parentNode.classList && input.parentNode.classList.contains('switch-mini')) {
                wrap = input.parentNode;
            }
            if (!wrap) return;
            if (on) { input.disabled = true; wrap.classList.add('is-loading'); }
            else { input.disabled = false; wrap.classList.remove('is-loading'); }
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (photoModalOpen) closePhotoModal();
            if (cardModalOpen) closeCardModal();
            if (catEditModal && catEditModal.style.display === 'flex') closeCatModal();
            var effModal = $('#effectEditModal'); if (effModal && effModal.style.display === 'flex') hideModal(effModal);
            var urlModal = $('#effectUrlModal'); if (urlModal && urlModal.style.display === 'flex') hideModal(urlModal);
            var photoEditModal = $('#photoEditModal'); if (photoEditModal && photoEditModal.style.display === 'flex') hideModal(photoEditModal);
            var girlRenameModalEl = $('#girlRenameModal'); if (girlRenameModalEl && girlRenameModalEl.style.display === 'flex') hideModal(girlRenameModalEl);
            var preEditModal = $('#preloaderEditModal'); if (preEditModal && preEditModal.style.display === 'flex') hideModal(preEditModal);
            var localRenameModalEl = $('#localRenameModal'); if (localRenameModalEl && localRenameModalEl.style.display === 'flex') hideModal(localRenameModalEl);
            var uaEdit = $('#uaEditModal'); if (uaEdit && uaEdit.style.display === 'flex') hideModal(uaEdit);
            var uaPick = $('#uaPickModal'); if (uaPick && uaPick.style.display === 'flex') hideModal(uaPick);
            var resetModalEl = $('#resetDataModal'); if (resetModalEl && resetModalEl.style.display === 'flex') hideModal(resetModalEl);
            var annModal = $('#announcementEditModal'); if (annModal && annModal.style.display === 'flex') hideModal(annModal);
            closeConfirm();
        });

        /* ------------------------------------------------------------
           数字步进器
           ------------------------------------------------------------ */

        var __numStepperSeq = 0;

        function wrapNumberInputs() {
            var inputs = document.querySelectorAll('input[type="number"]');
            for (var i = 0; i < inputs.length; i++) wrapOneNumberInput(inputs[i]);
        }

        function wrapOneNumberInput(input) {
            if (!input || input.dataset.numStepper === '1') return;

            if (input.classList.contains('local-bg-fetch-count')) {
                input.dataset.numStepper = '1';
                return;
            }

            input.dataset.numStepper = '1';

            var parent = input.parentNode;
            if (!parent) return;

            if (!input.id) {
                input.id = 'num-input-' + (++__numStepperSeq);
            }

            var closestLabel = input.closest ? input.closest('label') : null;
            if (closestLabel) {
                closestLabel.htmlFor = input.id;
            }

            var stepper = document.createElement('div');
            stepper.className = 'num-stepper';

            var minusBtn = document.createElement('button');
            minusBtn.type = 'button';
            minusBtn.className = 'num-btn num-minus';
            minusBtn.setAttribute('aria-label', '减小');
            minusBtn.setAttribute('tabindex', '-1');
            minusBtn.textContent = '−';

            var plusBtn = document.createElement('button');
            plusBtn.type = 'button';
            plusBtn.className = 'num-btn num-plus';
            plusBtn.setAttribute('aria-label', '增大');
            plusBtn.setAttribute('tabindex', '-1');
            plusBtn.textContent = '+';

            parent.insertBefore(stepper, input);
            stepper.appendChild(minusBtn);
            stepper.appendChild(input);
            stepper.appendChild(plusBtn);

            stepper.addEventListener('click', function (e) {
                if (e.target === input) return;
                if (e.target === minusBtn || e.target === plusBtn) return;
                input.focus();
            });

            function parseAttr(name, fallback) {
                var v = input.getAttribute(name);
                if (v === null || v === '') return fallback;
                var n = parseFloat(v);
                return isNaN(n) ? fallback : n;
            }
            function getStep() { return parseAttr('step', 1); }
            function getMin() { return parseAttr('min', -Infinity); }
            function getMax() { return parseAttr('max', Infinity); }

            function getValue() {
                var v = parseFloat(input.value);
                if (isNaN(v)) {
                    var minAttr = input.getAttribute('min');
                    if (minAttr !== null) {
                        var m = parseFloat(minAttr);
                        if (!isNaN(m)) return m;
                    }
                    return 0;
                }
                return v;
            }

            function syncBtnState() {
                if (input.disabled) {
                    minusBtn.disabled = true;
                    plusBtn.disabled = true;
                    return;
                }
                var cur = getValue();
                minusBtn.disabled = cur <= getMin();
                plusBtn.disabled = cur >= getMax();
            }

            function step(direction) {
                if (input.disabled) return;
                var cur = getValue();
                var stepVal = getStep();
                var min = getMin();
                var max = getMax();
                var next = cur + direction * stepVal;

                var stepStr = String(stepVal);
                var dot = stepStr.indexOf('.');
                var decimals = dot === -1 ? 0 : (stepStr.length - dot - 1);
                if (decimals > 0) next = parseFloat(next.toFixed(decimals));

                if (next < min) next = min;
                if (next > max) next = max;

                if (next !== cur) {
                    input.value = next;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                syncBtnState();
            }

            minusBtn.addEventListener('click', function (e) { e.preventDefault(); step(-1); });
            plusBtn.addEventListener('click', function (e) { e.preventDefault(); step(1); });

            input.addEventListener('input', syncBtnState);
            input.addEventListener('change', syncBtnState);
            input.addEventListener('blur', function () {
                if (input.disabled) return;
                var cur = parseFloat(input.value);
                if (isNaN(cur)) {
                    var minAttr = input.getAttribute('min');
                    input.value = minAttr === null ? 0 : (parseFloat(minAttr) || 0);
                } else {
                    var min = getMin();
                    var max = getMax();
                    if (cur < min) input.value = min;
                    else if (cur > max) input.value = max;
                }
                syncBtnState();
            });

            syncBtnState();
        }

        function setupLabelClickShield() {
            var labels = document.querySelectorAll('label');
            for (var i = 0; i < labels.length; i++) {
                var label = labels[i];
                if (label.dataset.labelShield === '1') continue;

                var hasTextControl = false;
                var controls = label.querySelectorAll('input, textarea, select');
                for (var j = 0; j < controls.length; j++) {
                    var el = controls[j];
                    var tag = (el.tagName || '').toUpperCase();
                    if (tag === 'TEXTAREA' || tag === 'SELECT') {
                        hasTextControl = true;
                        break;
                    }
                    if (tag === 'INPUT') {
                        var tp = (el.type || 'text').toLowerCase();
                        if (tp === 'checkbox' || tp === 'radio' ||
                            tp === 'submit' || tp === 'button' || tp === 'reset' ||
                            tp === 'image' || tp === 'file' ||
                            tp === 'color' || tp === 'range') {
                            continue;
                        }
                        hasTextControl = true;
                        break;
                    }
                }
                if (!hasTextControl) continue;

                label.dataset.labelShield = '1';
                label.addEventListener('click', function (e) {
                    var t = e.target;
                    if (!t || !t.closest) return;
                    if (t.closest('input, button, textarea, select, .num-stepper')) return;
                    e.preventDefault();
                });
            }
        }

        wrapNumberInputs();
        setupLabelClickShield();

        /* ======================================================================
           3.3 布局与导航
           ====================================================================== */

        var tabs = $all('.tab-btn'); var PANEL_KEY = 'nuomi_admin_tab';
        function showTab(name) {
            $all('.tab-btn').forEach(function (b) { b.classList.toggle('active', b.dataset.tab === name); });
            $all('.tab-panel').forEach(function (p) { p.classList.toggle('active', p.id === 'panel-' + name); });
            try { localStorage.setItem(PANEL_KEY, name); } catch (e) {}

            if (name === 'uaprofiles' && typeof window._nuomiRefreshUaButtons === 'function') {
                requestAnimationFrame(function () { window._nuomiRefreshUaButtons(); });
            }

            if (name === 'localbg') {
                hydrateLocalBgImages();
            }
        }
        tabs.forEach(function (b) { b.addEventListener('click', function () { showTab(b.dataset.tab); }); });
        var savedTab = (function () { try { return localStorage.getItem(PANEL_KEY); } catch (e) { return null; } })();
        if (savedTab && $('#panel-' + savedTab)) showTab(savedTab);

        (function () {
            var tabBar = document.querySelector('.admin-tabs');
            if (!tabBar) return;
            tabBar.addEventListener('wheel', function (e) {
                if (tabBar.scrollWidth <= tabBar.clientWidth) return;
                var delta = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
                if (!delta) return;
                e.preventDefault();
                tabBar.scrollLeft += delta;
            }, { passive: false });
        })();

        (function () {
            var subBtns = $all('.sub-tab-btn'); if (!subBtns.length) return;
            var SUB_KEY = 'nuomi_admin_site_subtab';
            function showSub(name) {
                $all('.sub-tab-btn').forEach(function (b) { b.classList.toggle('is-active', b.dataset.subtab === name); });
                $all('.sub-tab-panel').forEach(function (p) { p.classList.toggle('active', p.id === 'subtab-' + name); });
                try { localStorage.setItem(SUB_KEY, name); } catch (e) {}
            }
            subBtns.forEach(function (b) { b.addEventListener('click', function () { showSub(b.dataset.subtab); }); });
            var saved = null; try { saved = localStorage.getItem(SUB_KEY); } catch (e) {}
            if (saved && document.getElementById('subtab-' + saved)) showSub(saved);
        })();

        /* ======================================================================
           3.4 登录
           ====================================================================== */

        function bindAuthForm(id, action, okMsg) {
            var form = $('#' + id); if (!form) return;
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                var btn = form.querySelector('button[type=submit]');
                var old = btn.textContent; btn.disabled = true; btn.textContent = '处理中…';
                try {
                    var data = await api(action, new FormData(form));
                    if (!data.ok) { toast(data.msg || '操作失败', 'error'); return; }
                    smoothReload(okMsg || data.msg || '操作成功，正在刷新页面...');
                } catch (err) { toast(err.message, 'error'); }
                finally { btn.disabled = false; btn.textContent = old; }
            });
        }
        bindAuthForm('loginForm', 'login', '登录成功，正在进入后台...');
        bindAuthForm('pwdForm', 'change_password', '密码已修改，正在刷新页面...');
        bindAuthForm('siteForm', 'save_site', '前台设置已保存，正在刷新...');
        bindAuthForm('siteApiForm', 'save_site', '接口设置已保存，正在刷新...');
        bindAuthForm('adminSiteForm', 'save_site', '后台设置已保存，正在刷新...');
        bindAuthForm('usernameForm', 'change_username', '用户名已修改，正在刷新...');
        bindAuthForm('analyticsForm', 'save_site', '统计代码已保存，正在刷新...');

        var logoutBtn = $('#logoutBtn');
        if (logoutBtn) { logoutBtn.addEventListener('click', async function () { try { await api('logout'); } catch (e) {} smoothReload('已退出登录，正在跳转...'); }); }

        (function () {
            var starsWrap = document.getElementById('authStars');
            if (starsWrap) {
                var starHtml = '';
                for (var i = 0; i < 90; i++) {
                    var size = (Math.random() * 2 + 0.6).toFixed(1), left = (Math.random() * 100).toFixed(2), top = (Math.random() * 100).toFixed(2), dur = (1.8 + Math.random() * 2.4).toFixed(2), delay = (Math.random() * 4).toFixed(2);
                    starHtml += '<div class="auth-star" style="left:' + left + '%;top:' + top + '%;width:' + size + 'px;height:' + size + 'px;animation-duration:' + dur + 's;animation-delay:' + delay + 's"></div>';
                }
                starsWrap.innerHTML = starHtml;
            }
            var sparklesWrap = document.getElementById('authSparkles');
            if (sparklesWrap) {
                var configs = [
                    { top: '16%', left: '10%', size: 38, dur: 3.2, delay: 0 }, { top: '10%', right: '14%', size: 46, dur: 4.0, delay: 0.8 },
                    { top: '44%', left: '6%', size: 30, dur: 2.8, delay: 1.4 }, { bottom: '20%', right: '10%', size: 42, dur: 3.6, delay: 0.4 },
                    { bottom: '14%', left: '22%', size: 34, dur: 3.0, delay: 1.6 }, { top: '64%', right: '8%', size: 26, dur: 2.6, delay: 1.0 }
                ];
                var spHtml = '';
                configs.forEach(function (c) {
                    var style = 'width:' + c.size + 'px;height:' + c.size + 'px;';
                    if (c.top) style += 'top:' + c.top + ';'; if (c.bottom) style += 'bottom:' + c.bottom + ';';
                    if (c.left) style += 'left:' + c.left + ';'; if (c.right) style += 'right:' + c.right + ';';
                    style += 'animation-duration:' + c.dur + 's;animation-delay:' + c.delay + 's;';
                    spHtml += '<span class="auth-sparkle" style="' + style + '"></span>';
                });
                sparklesWrap.innerHTML = spHtml;
            }
        })();

        /* ======================================================================
           3.5 安装向导（4 步：管理员账号 → 站点信息 → 行为配置 → 完成预览）
           ====================================================================== */
        (function () {
            var form = document.getElementById('initForm');
            if (!form) return;
            var steps = form.querySelectorAll('.init-step-panel');
            var dots = document.querySelectorAll('#initSteps .init-step-dot');
            var prevBtn = document.getElementById('initPrevBtn');
            var nextBtn = document.getElementById('initNextBtn');
            var submitBtn = document.getElementById('initSubmitBtn');
            var subtitle = document.getElementById('initSubtitle');
            if (!steps.length || !prevBtn || !nextBtn || !submitBtn) return;

            var SUBTITLES = {
                1: '创建管理员账号，用于登录后台',
                2: '确认前台基础信息，留空则回退默认值，也可稍后在「站点设置」修改',
                3: '设置轮播行为与后台入口，均有默认值，不改可直接下一步',
                4: '确认安装信息，点击完成后将自动登录进入后台'
            };
            var totalSteps = steps.length;
            var currentStep = 1;

            function getVal(name) {
                var el = form.querySelector('[name="' + name + '"]');
                return el ? el.value.trim() : '';
            }

            function showStep(n) {
                currentStep = n;
                steps.forEach(function (p) {
                    p.classList.toggle('active', parseInt(p.dataset.step, 10) === n);
                });
                dots.forEach(function (d) {
                    var s = parseInt(d.dataset.step, 10);
                    d.classList.toggle('active', s === n);
                    d.classList.toggle('done', s < n);
                });
                prevBtn.style.display = n > 1 ? '' : 'none';
                nextBtn.style.display = n < totalSteps ? '' : 'none';
                submitBtn.style.display = n === totalSteps ? '' : 'none';
                if (subtitle) subtitle.textContent = SUBTITLES[n] || '';
                if (n === totalSteps) updateSummary();
            }

            function updateSummary() {
                var userEl    = document.getElementById('sumUser');
                var titleEl   = document.getElementById('sumTitle');
                var apiEl     = document.getElementById('sumApi');
                var ghOnEl    = document.getElementById('sumGithubOn');
                var intervalEl= document.getElementById('sumInterval');
                var clickEl   = document.getElementById('sumClick');

                if (userEl)  userEl.textContent  = getVal('username') || '—';
                if (titleEl) titleEl.textContent = getVal('title') || getVal('pageTitle') || '（使用默认）';

                var bg = getVal('bgApi'), bn = getVal('bannerApi');
                if (apiEl) {
                    if (bg && bn)   apiEl.textContent = '自定义（背景 + Banner）';
                    else if (bg)    apiEl.textContent = '自定义背景 + 默认 Banner';
                    else if (bn)    apiEl.textContent = '默认背景 + 自定义 Banner';
                    else            apiEl.textContent = '使用默认';
                }

                var ghOnInput = form.querySelector('input[name="githubEnabled"][type="checkbox"]');
                var ghOn = ghOnInput ? ghOnInput.checked : true;
                if (ghOnEl) ghOnEl.textContent = ghOn ? '已开启' : '已关闭';

                var bgi = getVal('bgInterval')     || '10';
                var bni = getVal('bannerInterval') || '6';
                if (intervalEl) intervalEl.textContent = '背景 ' + bgi + 's / Banner ' + bni + 's';

                var c = getVal('adminClickCount')  || '10';
                var w = getVal('adminClickWindow') || '10';
                if (clickEl) clickEl.textContent = '连点头像 ' + c + ' 次 / ' + w + ' 秒内';
            }

            function validateStep(n) {
                var panel = form.querySelector('.init-step-panel[data-step="' + n + '"]');
                if (!panel) return true;

                var inputs = panel.querySelectorAll('input[required]');
                for (var i = 0; i < inputs.length; i++) {
                    if (!inputs[i].value.trim()) {
                        toast('请填写必填项', 'error');
                        inputs[i].focus();
                        return false;
                    }
                }

                if (n === 1) {
                    var p1 = getVal('password'), p2 = getVal('password2');
                    if (p1.length < 6) { toast('密码至少需要 6 位', 'error'); return false; }
                    if (p1 !== p2)     { toast('两次输入的密码不一致', 'error'); return false; }
                }

                if (n === 2) {
                    var urlOk = /^https?:\/\//i;
                    var bg = getVal('bgApi'), bn = getVal('bannerApi');
                    var gh = getVal('githubUrl'), pb = getVal('poweredByUrl');
                    if (bg && !urlOk.test(bg)) { toast('背景 API 必须以 http:// 或 https:// 开头', 'error'); return false; }
                    if (bn && !urlOk.test(bn)) { toast('Banner API 必须以 http:// 或 https:// 开头', 'error'); return false; }
                    if (gh && !urlOk.test(gh)) { toast('GitHub 链接必须以 http:// 或 https:// 开头', 'error'); return false; }
                    if (pb && !urlOk.test(pb)) { toast('Powered by 链接必须以 http:// 或 https:// 开头', 'error'); return false; }
                }
                return true;
            }

            nextBtn.addEventListener('click', function () {
                if (!validateStep(currentStep)) return;
                if (currentStep < totalSteps) showStep(currentStep + 1);
            });
            prevBtn.addEventListener('click', function () {
                if (currentStep > 1) showStep(currentStep - 1);
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                for (var i = 1; i <= totalSteps; i++) {
                    if (!validateStep(i)) { showStep(i); return; }
                }
                var old = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = '安装中…';
                try {
                    var data = await api('init', new FormData(form));
                    if (!data.ok) { toast(data.msg || '安装失败', 'error'); return; }
                    smoothReload('安装完成，正在进入后台...');
                } catch (err) {
                    toast(err.message, 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = old;
                }
            });

            showStep(1);
        })();

        /* ======================================================================
           3.6 通用组件
           ====================================================================== */

        function getAdminImageSrc(path) {
            var p = (path || '').trim(); if (!p) return '';
            if (p.indexOf('http://') === 0 || p.indexOf('https://') === 0) return p;
            if (p.indexOf('//') === 0) return p; if (p.charAt(0) === '/') return p;
            if (p.indexOf('photos/') === 0) return '../' + p; return '';
        }

        function updateCardIconPreview(rawVal) {
            var wrap = $('#cardIconPreviewWrap'), img = $('#cardIconPreviewImg'); if (!wrap || !img) return;
            var src = getAdminImageSrc(rawVal);
            if (src) { wrap.style.display = 'flex'; img.src = src; img.style.display = 'block'; }
            else { wrap.style.display = 'none'; img.src = ''; img.style.display = 'none'; }
        }
        function updateCardBigImagePreview(rawVal) {
            var previewWrap = $('#cardImagePreview'); if (!previewWrap) return;
            var img = previewWrap.querySelector('img'); var src = getAdminImageSrc(rawVal);
            if (src) { previewWrap.style.display = 'flex'; img.src = src; img.style.display = 'block'; }
            else { previewWrap.style.display = 'none'; img.src = ''; img.style.display = 'none'; }
        }

        function updateSiteAvatarPreview(inputId, wrapId, imgId) {
            var wrap = document.getElementById(wrapId), img = document.getElementById(imgId), inp = document.getElementById(inputId);
            if (!wrap || !img || !inp) return;
            var src = getAdminImageSrc(inp.value.trim());
            if (src) { wrap.style.display = 'flex'; img.src = src; img.style.display = 'block'; }
            else { wrap.style.display = 'none'; img.src = ''; img.style.display = 'none'; }
        }
        function updateFaviconPreview(inputId, wrapId, imgId) {
            var wrap = document.getElementById(wrapId), img = document.getElementById(imgId), inp = document.getElementById(inputId);
            if (!wrap || !img || !inp) return;
            var src = getAdminImageSrc(inp.value.trim());
            if (src) { wrap.style.display = 'flex'; img.src = src; img.style.display = 'block'; }
            else { wrap.style.display = 'none'; img.src = ''; img.style.display = 'none'; }
        }

        function setupSortable(opts) {
            var container = $(opts.container); if (!container) return;
            var itemSelector = opts.itemSelector, handleSelector = opts.handleSelector || '.drag-handle-mini', ignoreSelector = opts.ignoreSelector || '';
            var onSave = opts.onSave, onChange = opts.onChange, getKey = opts.getKey;
            var HOLD_MS = typeof opts.holdMs === 'number' ? opts.holdMs : 350;
            var MOVE_CANCEL_PX = typeof opts.moveTolerance === 'number' ? opts.moveTolerance : 30;
            var FLIP_DURATION = 220, RETURN_DURATION = 280;
            var dragState = null, holdTimer = null;

            var axis = opts.axis;
            if (!axis) {
                if (opts.layout === 'grid') axis = 'grid';
                else if (opts.layout === 'chips') axis = 'x';
                else if (opts.layout === 'list') axis = 'y';
                else { var disp = getComputedStyle(container).display; axis = (disp === 'grid' || disp === 'inline-grid') ? 'grid' : 'y'; }
            }
            function collectKeys() {
                var keys = [], children = container.children;
                for (var i = 0; i < children.length; i++) {
                    var child = children[i];
                    if (!child.matches || !child.matches(itemSelector)) continue;
                    var k = getKey(child); if (k) keys.push(k);
                }
                return keys;
            }
            function flipAnimate(items, beforeRects) {
                items.forEach(function (el, i) {
                    var r = el.getBoundingClientRect(), dx = beforeRects[i].left - r.left, dy = beforeRects[i].top - r.top;
                    if (dx === 0 && dy === 0) return;
                    el.style.transition = 'none'; el.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';
                    void el.offsetWidth;
                    el.style.transition = 'transform ' + FLIP_DURATION + 'ms cubic-bezier(.2, .8, .3, 1)'; el.style.transform = '';
                    setTimeout(function () { el.style.transition = ''; el.style.transform = ''; }, FLIP_DURATION + 30);
                });
            }
            container.addEventListener('dragstart', function (e) { if (e.target.closest && e.target.closest(itemSelector)) e.preventDefault(); });
            container.addEventListener('selectstart', function (e) { if (dragState) e.preventDefault(); });
            container.addEventListener('pointerdown', function (e) {
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                if (dragState) return;
                if (ignoreSelector && e.target.closest(ignoreSelector)) return;
                var handle = e.target.closest(handleSelector), item = handle && handle.closest(itemSelector);
                if (!item || item.parentNode !== container) return;
                if (item.classList.contains('photo-cell') || item.classList.contains('girl-cell')) {
                    window._setActiveCell(item);
                }
                dragState = { item: item, active: false, cancelled: false, startX: e.clientX, startY: e.clientY };
                item.classList.add('pressing');
                clearTimeout(holdTimer);
                holdTimer = setTimeout(function () { if (dragState && !dragState.cancelled) activateDrag(dragState.startX, dragState.startY); }, HOLD_MS);
                document.addEventListener('pointermove', onMove, { passive: false });
                document.addEventListener('pointerup', onEnd);
                document.addEventListener('pointercancel', onEnd);
            });
            container.addEventListener('contextmenu', function (e) { if (dragState) e.preventDefault(); });
            function activateDrag(cx, cy) {
                var state = dragState; if (!state || state.active) return; state.active = true;
                state.originalKeys = collectKeys();
                if (state.item) state.item.classList.remove('pressing');
                var item = state.item, rect = item.getBoundingClientRect();
                state.floatOffsetX = cx - rect.left; state.floatOffsetY = cy - rect.top;
                var ph = document.createElement('div'); ph.className = 'sort-placeholder'; ph.style.width = rect.width + 'px'; ph.style.height = rect.height + 'px';
                item.parentNode.insertBefore(ph, item); state.placeholder = ph;
                item.parentNode.removeChild(item); document.body.appendChild(item);
                item.classList.add('sorting-float');
                item.style.position = 'fixed'; item.style.left = rect.left + 'px'; item.style.top = rect.top + 'px'; item.style.width = rect.width + 'px'; item.style.height = rect.height + 'px'; item.style.margin = '0'; item.style.zIndex = '9999'; item.style.pointerEvents = 'none'; item.style.transition = 'none';
                document.body.style.userSelect = 'none'; document.body.style.webkitUserSelect = 'none';
                if (navigator.vibrate) { try { navigator.vibrate(10); } catch (err) {} }
            }
            function onMove(e) {
                if (!dragState || dragState.cancelled) return;
                if (!dragState.active) {
                    var dx0 = e.clientX - dragState.startX, dy0 = e.clientY - dragState.startY;
                    if (Math.sqrt(dx0 * dx0 + dy0 * dy0) > MOVE_CANCEL_PX) {
                        clearTimeout(holdTimer); dragState.cancelled = true;
                        if (dragState.item) dragState.item.classList.remove('pressing');
                        cleanup(); dragState = null;
                    }
                    return;
                }
                if (e.cancelable) e.preventDefault();
                var state = dragState, item = state.item, ph = state.placeholder, px = e.clientX, py = e.clientY;
                item.style.left = (px - state.floatOffsetX) + 'px'; item.style.top = (py - state.floatOffsetY) + 'px';
                var probe = document.elementFromPoint(px, py); if (!probe) return;
                var hoverItem = probe.closest(itemSelector);
                if (!hoverItem || hoverItem === ph || hoverItem === item || hoverItem.parentNode !== container) return;
                var tr = hoverItem.getBoundingClientRect(), after;
                if (axis === 'grid') { var relX = px - (tr.left + tr.width / 2), relY = py - (tr.top + tr.height / 2); after = Math.abs(relY) > Math.abs(relX) ? (relY > 0) : (relX > 0); }
                else if (axis === 'x') { after = px > (tr.left + tr.width / 2); }
                else { after = py > (tr.top + tr.height / 2); }
                if ((after && hoverItem.nextElementSibling === ph) || (!after && hoverItem.previousElementSibling === ph)) return;
                var others = [], children = container.children;
                for (var i = 0; i < children.length; i++) { var c = children[i]; if (c.matches && c.matches(itemSelector) && c !== ph) others.push(c); }
                var beforeRects = others.map(function (el) { var r = el.getBoundingClientRect(); return { left: r.left, top: r.top }; });
                container.insertBefore(ph, after ? hoverItem.nextElementSibling : hoverItem);
                flipAnimate(others, beforeRects);
            }
            function onEnd() {
                clearTimeout(holdTimer); cleanup();
                if (!dragState) return;
                var state = dragState; dragState = null;
                if (state.item) state.item.classList.remove('pressing');
                document.body.style.userSelect = ''; document.body.style.webkitUserSelect = '';
                if (!state.active) return;
                var item = state.item, ph = state.placeholder;
                suppressNextClick(item);
                var floatRect = item.getBoundingClientRect();
                item.classList.remove('sorting-float');
                item.style.position = ''; item.style.left = ''; item.style.top = ''; item.style.width = ''; item.style.height = ''; item.style.margin = ''; item.style.zIndex = ''; item.style.pointerEvents = ''; item.style.transition = '';
                if (ph && ph.parentNode) { ph.parentNode.insertBefore(item, ph); ph.parentNode.removeChild(ph); }
                else { container.appendChild(item); }
                var newRect = item.getBoundingClientRect(), dx = floatRect.left - newRect.left, dy = floatRect.top - newRect.top;
                if (dx !== 0 || dy !== 0) {
                    item.style.transition = 'none'; item.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';
                    void item.offsetWidth;
                    item.style.transition = 'transform ' + RETURN_DURATION + 'ms cubic-bezier(.2, .8, .3, 1)'; item.style.transform = '';
                    setTimeout(function () { item.style.transition = ''; item.style.transform = ''; }, RETURN_DURATION + 40);
                }
                var keys = collectKeys();
                var orderChanged = false;
                if (state.originalKeys && state.originalKeys.length === keys.length) {
                    for (var i = 0; i < keys.length; i++) {
                        if (keys[i] !== state.originalKeys[i]) { orderChanged = true; break; }
                    }
                } else {
                    orderChanged = true;
                }
                if (typeof onChange === 'function') onChange(keys, orderChanged);
                if (orderChanged && typeof onSave === 'function') onSave(keys);
            }
            function suppressNextClick(el) {
                var handler = function (ev) {
                    document.removeEventListener('click', handler, true);
                    if (el && el.contains && el.contains(ev.target)) {
                        ev.stopPropagation();
                        ev.preventDefault();
                    }
                };
                document.addEventListener('click', handler, true);
                setTimeout(function () { document.removeEventListener('click', handler, true); }, 400);
            }
            function cleanup() {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onEnd);
                document.removeEventListener('pointercancel', onEnd);
            }
        }

        function hydrateLocalBgImages() {
            var panel = document.getElementById('panel-localbg');
            if (!panel) return;
            var imgs = panel.querySelectorAll('img[data-src]');
            if (imgs.length === 0) return;

            var idx = 0;
            var BATCH = 4;

            function step() {
                var end = Math.min(idx + BATCH, imgs.length);
                for (; idx < end; idx++) {
                    var img = imgs[idx];
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                }
                if (idx < imgs.length) {
                    requestAnimationFrame(step);
                }
            }
            requestAnimationFrame(step);
        }

        /* ======================================================================
           3.7 面板功能
           ====================================================================== */

        /* ------------------------------------------------------------
           3.7.1 卡片管理
           ------------------------------------------------------------ */
        var currentType = 'link'; var cardModalOpen = false; var photoPickTarget = null;
        function openCardModal(card) {
            cardModalOpen = true;
            $('#cardModalTitle').textContent = card ? '编辑卡片' : '新增卡片';
            $('#f_id').value = card ? card.id : ''; $('#f_name').value = card ? card.name : '';
            $('#f_sub').value = card ? (card.sub || '') : ''; $('#f_icon').value = card ? (card.icon || '') : '';
            $('#f_href').value = card ? (card.href || '') : ''; $('#f_image').value = card ? (card.image || '') : '';
            var iconImg = $('#cardIconPreviewImg'), bigImg = document.querySelector('.edit-card-big-preview');
            if (iconImg) { iconImg.style.display = 'none'; iconImg.src = ''; }
            if (bigImg) { bigImg.style.display = 'none'; bigImg.src = ''; }
            var sel = $('#f_category'); sel.innerHTML = '';
            $all('.cat-chip').forEach(function (chip) { var opt = document.createElement('option'); opt.value = chip.dataset.name; opt.textContent = chip.dataset.name; sel.appendChild(opt); });
            if (card && card.category) sel.value = card.category;
            setCardType(card && card.type === 'image' ? 'image' : 'link');
            showModal($('#cardModal'));
            requestAnimationFrame(function () { updateCardIconPreview($('#f_icon').value.trim()); updateCardBigImagePreview($('#f_image').value.trim()); });
        }
        function closeCardModal() { cardModalOpen = false; hideModal($('#cardModal')); updateCardIconPreview(''); updateCardBigImagePreview(''); }
        function setCardType(type) { currentType = type; $all('.type-btn[data-type]').forEach(function (b) { b.classList.toggle('is-active', b.dataset.type === type); }); $('#group_href').style.display = type === 'link' ? '' : 'none'; $('#group_image').style.display = type === 'image' ? '' : 'none'; }
        $all('.type-btn[data-type]').forEach(function (b) { b.addEventListener('click', function () { setCardType(b.dataset.type); }); });
        var addCardBtn = $('#addCardBtn'); if (addCardBtn) addCardBtn.addEventListener('click', function () { openCardModal(null); });
        var cardModalClose = $('#cardModalClose'); if (cardModalClose) cardModalClose.addEventListener('click', closeCardModal);
        var cardCancel = $('#cardCancel'); if (cardCancel) cardCancel.addEventListener('click', closeCardModal);
        var cardModal = $('#cardModal'); if (cardModal) cardModal.addEventListener('click', function (e) { if (e.target === this) closeCardModal(); });
        var cardForm = $('#cardForm');
        if (cardForm) {
            cardForm.addEventListener('submit', async function (e) {
                e.preventDefault(); var btn = $('#cardSave'); btn.disabled = true; btn.textContent = '保存中…';
                try {
                    var fd = new FormData(); fd.append('id', $('#f_id').value); fd.append('type', currentType); fd.append('category', $('#f_category').value); fd.append('name', $('#f_name').value); fd.append('sub', $('#f_sub').value); fd.append('icon', $('#f_icon').value); fd.append('href', $('#f_href').value); fd.append('image', $('#f_image').value);
                    var data = await api('save_card', fd); if (!data.ok) { toast(data.msg, 'error'); return; }
                    closeCardModal(); smoothReload((data.msg || '卡片已保存') + '，正在刷新...');
                } catch (err) { toast(err.message, 'error'); } finally { btn.disabled = false; btn.textContent = '保存'; }
            });
        }
        var cardRows = $('#cardRows');
        if (cardRows) {
            cardRows.addEventListener('click', function (e) {
                var row = e.target.closest('.card-row'); if (!row) return;
                if (e.target.closest('.edit-card')) { openCardModal(JSON.parse(row.dataset.card)); }
                else if (e.target.closest('.del-card')) {
                    var id = row.dataset.id; var nameText = $('.row-name', row).textContent;
                    openConfirm('删除卡片', '确定要删除「' + esc(nameText) + '」吗？删除后不可恢复。', async function () {
                        try { var data = await api('delete_card', { id: id }); if (!data.ok) { toast(data.msg, 'error'); return; } smoothReload('卡片已删除，正在刷新...'); } catch (err) { toast(err.message, 'error'); }
                    });
                }
            });
        }
        var fIconInput = $('#f_icon'); if (fIconInput) fIconInput.addEventListener('input', function () { updateCardIconPreview(this.value.trim()); });
        var fImageInput = $('#f_image'); if (fImageInput) fImageInput.addEventListener('input', function () { updateCardBigImagePreview(this.value.trim()); });
        $all('.pick-photo-icon').forEach(function (btn) { btn.addEventListener('click', function () { if (!cardModalOpen) openCardModal(null); photoPickTarget = 'icon'; openPhotoModal(); }); });
        $all('.pick-photo-image').forEach(function (btn) { btn.addEventListener('click', function () { if (!cardModalOpen) openCardModal(null); photoPickTarget = 'image'; openPhotoModal(); }); });

        var catEditModal = $('#catEditModal'), catModalTitle = $('#catModalTitle'), catModalName = $('#catModalName'), catModalIcon = $('#catModalIcon'), catModalIconPreview = $('#catModalIconPreview'), catPreviewWrap = $('#catPreviewWrap'), catModalClose = $('#catModalClose'), catModalCancel = $('#catModalCancel'), catModalSave = $('#catModalSave');
        var catModalMode = 'add', editCatOldName = '';
        var openAddCatModalBtn = $('#openAddCatModalBtn');
        if (openAddCatModalBtn) {
            openAddCatModalBtn.addEventListener('click', function () {
                catModalMode = 'add'; catModalTitle.textContent = '新增分类'; catModalName.value = ''; catModalIcon.value = '';
                showModal(catEditModal); catPreviewWrap.style.display = 'none'; catModalIconPreview.src = ''; catModalIconPreview.style.display = 'none';
            });
        }
        function openEditCatDialog(oldName, oldIcon) {
            catModalMode = 'edit'; editCatOldName = oldName; catModalTitle.textContent = '修改分类';
            catModalName.value = oldName; catModalIcon.value = oldIcon || ''; showModal(catEditModal);
            var iconVal = catModalIcon.value.trim();
            if (iconVal.indexOf('photos/') === 0) { catPreviewWrap.style.display = 'block'; catModalIconPreview.src = '../' + iconVal; catModalIconPreview.style.display = 'block'; }
            else { catPreviewWrap.style.display = 'none'; catModalIconPreview.src = ''; catModalIconPreview.style.display = 'none'; }
        }
        function closeCatModal() { hideModal(catEditModal); editCatOldName = ''; }
        if (catModalClose) catModalClose.addEventListener('click', closeCatModal); if (catModalCancel) catModalCancel.addEventListener('click', closeCatModal);
        if (catEditModal) catEditModal.addEventListener('click', function (e) { if (e.target === catEditModal) closeCatModal(); });
        $all('.pick-cat-icon-modal').forEach(function (btn) { btn.addEventListener('click', function () { photoPickTarget = 'catModalIcon'; openPhotoModal(); }); });
        if (catModalIcon) {
            catModalIcon.addEventListener('input', function () {
                var val = this.value.trim();
                if (val.indexOf('photos/') === 0) { catPreviewWrap.style.display = 'block'; catModalIconPreview.src = '../' + val; catModalIconPreview.style.display = 'block'; }
                else { catPreviewWrap.style.display = 'none'; catModalIconPreview.src = ''; catModalIconPreview.style.display = 'none'; }
            });
        }
        if (catModalSave) {
            catModalSave.addEventListener('click', async function () {
                var name = catModalName.value.trim(), icon = catModalIcon.value.trim(); if (!name) { toast('分类名称不能为空', 'error'); return; }
                try {
                    if (catModalMode === 'add') { var data = await api('add_category', { name: name, icon: icon }); if (!data.ok) { toast(data.msg, 'error'); return; } closeCatModal(); smoothReload('分类已添加，正在刷新...'); }
                    else { var d2 = await api('rename_category', { old_name: editCatOldName, new_name: name, new_icon: icon }); if (!d2.ok) { toast(d2.msg, 'error'); return; } closeCatModal(); smoothReload('分类已修改，正在刷新...'); }
                } catch (err) { toast(err.message, 'error'); }
            });
        }
        var catChips = $('#catChips');
        if (catChips) {
            catChips.addEventListener('click', async function (e) {
                var chip = e.target.closest('.cat-chip'); if (!chip) return;
                var name = chip.dataset.name, icon = chip.dataset.icon || '';
                if (e.target.closest('.cat-edit')) { openEditCatDialog(name, icon); }
                else if (e.target.closest('.cat-del')) {
                    openConfirm('删除分类', '确定要删除分类「' + esc(name) + '」吗？', async function () {
                        try { var data = await api('delete_category', { name: name }); if (!data.ok) { toast(data.msg, 'error'); return; } smoothReload('分类已删除，正在刷新...'); } catch (err) { toast(err.message, 'error'); }
                    });
                }
            });
        }

        setupSortable({ container: '#catChips', itemSelector: '.cat-chip', handleSelector: '.cat-chip', ignoreSelector: 'button', layout: 'chips', holdMs: 350, moveTolerance: 30,
            getKey: function (el) { return el.dataset.name; },
            onSave: function (names) { api('save_category_sort', { names: JSON.stringify(names) }).then(function (data) { if (!data.ok) { toast(data.msg, 'error'); return; } toast('分类顺序已保存，前台已同步', 'success'); }).catch(function (err) { toast(err.message, 'error'); }); }
        });

        var cardRowsEl = $('#cardRows');
        var saveSortBtn = $('#saveSortBtn');
        var cancelSortBtn = $('#cancelSortBtn');
        var savedCardOrder = cardRowsEl
            ? $all('.card-row', cardRowsEl).map(function (r) { return r.dataset.id; })
            : [];

        function getCurrentCardOrder() {
            return cardRowsEl ? $all('.card-row', cardRowsEl).map(function (r) { return r.dataset.id; }) : [];
        }
        function isCardOrderChanged() {
            var cur = getCurrentCardOrder();
            if (cur.length !== savedCardOrder.length) return true;
            for (var i = 0; i < cur.length; i++) {
                if (cur[i] !== savedCardOrder[i]) return true;
            }
            return false;
        }
        function refreshCardSortBar() {
            var dirty = isCardOrderChanged();
            if (saveSortBtn) saveSortBtn.style.display = dirty ? '' : 'none';
            if (cancelSortBtn) cancelSortBtn.style.display = dirty ? '' : 'none';
        }

        setupSortable({ container: '#cardRows', itemSelector: '.card-row', handleSelector: '.drag-handle', layout: 'list',
            getKey: function (el) { return el.dataset.id; },
            onChange: function () { refreshCardSortBar(); }
        });

        if (saveSortBtn) {
            saveSortBtn.addEventListener('click', async function () {
                if (!cardRowsEl) return;
                var ids = getCurrentCardOrder();
                var btn = this; btn.disabled = true;
                try {
                    var data = await api('save_sort', { ids: JSON.stringify(ids) });
                    if (!data.ok) { toast(data.msg, 'error'); return; }
                    savedCardOrder = ids.slice();
                    refreshCardSortBar();
                    toast(data.msg || '排序已保存', 'success');
                } catch (err) { toast(err.message, 'error'); } finally { btn.disabled = false; }
            });
        }
        if (cancelSortBtn) {
            cancelSortBtn.addEventListener('click', function () {
                if (cardRowsEl) {
                    var map = {};
                    $all('.card-row', cardRowsEl).forEach(function (r) { map[r.dataset.id] = r; });
                    savedCardOrder.forEach(function (id) { if (map[id]) cardRowsEl.appendChild(map[id]); });
                }
                refreshCardSortBar();
                toast('已恢复原顺序', 'success');
            });
        }

        /* ------------------------------------------------------------
           3.7.2 公告管理
           ------------------------------------------------------------ */
        (function () {
            var rows = document.getElementById('announcementRows');
            if (!rows) return;

            var editModal = document.getElementById('announcementEditModal');
            var editTitle = document.getElementById('announcementEditTitle');
            var editId    = document.getElementById('announcementEditId');
            var editName  = document.getElementById('announcementEditName');
            var editContent = document.getElementById('announcementEditContent');
            var editPinned  = document.getElementById('announcementEditPinned');
            var editEnabled = document.getElementById('announcementEditEnabled');
            var currentType = 'info';
            var isEditMode  = false;

            var typeBtns = editModal ? editModal.querySelectorAll('.announcement-type-btn') : [];
            typeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    currentType = btn.dataset.type;
                    typeBtns.forEach(function (b) { b.classList.toggle('is-active', b.dataset.type === currentType); });
                });
            });

            function setType(type) {
                currentType = type;
                typeBtns.forEach(function (b) { b.classList.toggle('is-active', b.dataset.type === type); });
            }

            function openEdit(ann) {
                if (!editModal) return;
                isEditMode = !!ann;
                editTitle.textContent = ann ? '编辑公告' : '新增公告';
                editId.value    = ann ? ann.id : '';
                editName.value  = ann ? ann.title : '';
                editContent.value = ann ? ann.content : '';
                editPinned.checked  = ann ? (ann.pinned === '1' || ann.pinned === true) : false;
                editEnabled.checked = ann ? (ann.enabled === '1' || ann.enabled === true) : true;
                setType(ann ? ann.type : 'info');
                showModal(editModal);
                setTimeout(function () { editName.focus(); }, 120);
            }

            function closeEdit() { hideModal(editModal); }

            var addBtn = document.getElementById('addAnnouncementBtn');
            if (addBtn) addBtn.addEventListener('click', function () { openEdit(null); });

            var closeBtn = document.getElementById('announcementEditClose');
            if (closeBtn) closeBtn.addEventListener('click', closeEdit);
            var cancelBtn = document.getElementById('announcementEditCancel');
            if (cancelBtn) cancelBtn.addEventListener('click', closeEdit);
            if (editModal) editModal.addEventListener('click', function (e) { if (e.target === editModal) closeEdit(); });

            var saveBtn = document.getElementById('announcementEditSave');
            if (saveBtn) {
                saveBtn.addEventListener('click', async function () {
                    var name = editName.value.trim();
                    if (!name) { toast('公告标题不能为空', 'error'); return; }
                    var btn = this, old = btn.textContent;
                    btn.disabled = true; btn.textContent = '保存中…';
                    try {
                        var payload = {
                            id: editId.value,
                            title: name,
                            content: editContent.value,
                            type: currentType,
                            pinned: editPinned.checked ? 1 : 0,
                            enabled: editEnabled.checked ? 1 : 0
                        };
                        var data = await api('save_announcement', payload);
                        if (!data.ok) { toast(data.msg, 'error'); return; }
                        closeEdit();
                        smoothReload('公告已保存，正在刷新...');
                    } catch (err) {
                        toast(err.message, 'error');
                    } finally {
                        btn.disabled = false; btn.textContent = old;
                    }
                });
            }

            rows.addEventListener('click', function (e) {
                var row = e.target.closest('.announcement-row');
                if (!row) return;
                var id = row.dataset.id;

                if (e.target.closest('.edit-announcement')) {
                    openEdit({
                        id: id,
                        title: row.dataset.title,
                        content: row.dataset.content,
                        type: row.dataset.type,
                        pinned: row.dataset.pinned === '1',
                        enabled: row.dataset.enabled === '1'
                    });
                    return;
                }

                if (e.target.closest('.del-announcement')) {
                    var title = row.dataset.title || '';
                    openConfirm('删除公告', '确定删除「' + esc(title) + '」吗？删除后不可恢复。', async function () {
                        try {
                            var data = await api('delete_announcement', { id: id });
                            if (!data.ok) { toast(data.msg, 'error'); return; }
                            smoothReload('公告已删除，正在刷新...');
                        } catch (err) { toast(err.message, 'error'); }
                    });
                    return;
                }

                if (e.target.closest('.announcement-pin')) {
                    var isPinned = row.dataset.pinned === '1';
                    api('toggle_announcement_pin', { id: id, pinned: isPinned ? 0 : 1 }).then(function (data) {
                        if (!data.ok) { toast(data.msg, 'error'); return; }
                        smoothReload('已更新置顶状态，正在刷新...');
                    }).catch(function (err) { toast(err.message, 'error'); });
                    return;
                }
            });

            rows.addEventListener('change', function (e) {
                var t = e.target;
                if (!t.classList.contains('announcement-toggle')) return;
                var id = t.dataset.id;
                var enabled = t.checked;
                setSwitchLoading(t, true);
                api('toggle_announcement', { id: id, enabled: enabled ? 1 : 0 }).then(function (data) {
                    if (!data.ok) { toast(data.msg, 'error'); t.checked = !enabled; return; }
                    toast(enabled ? '已启用' : '已禁用', 'success');
                }).catch(function (err) { toast(err.message, 'error'); t.checked = !enabled; })
                  .then(function () { setSwitchLoading(t, false); });
            });
        })();

        /* ------------------------------------------------------------
           3.7.3 图片管理
           ------------------------------------------------------------ */
        var uploadZone = $('#uploadZone'), photoInput = $('#photoInput');
        function uploadPhotoFiles(files, onComplete) {
            var fileArr = Array.prototype.slice.call(files || []); if (!fileArr.length) return;
            for (var i = 0; i < fileArr.length; i++) {
                var f = fileArr[i];
                if (!/\.(jpe?g|png|gif|webp)$/i.test(f.name)) { toast('仅支持 jpg/png/gif/webp', 'error'); return; }
                if (f.size > 5 * 1024 * 1024) { toast('文件超过 5MB 限制', 'error'); return; }
            }
            var uploadedPaths = []; var total = fileArr.length, idx = 0;
            if (total === 1) toast('上传中…'); else toast('批量上传中 (0/' + total + ')…');
            function next() {
                if (idx >= total) { toast('已上传 ' + uploadedPaths.length + ' 张图片', 'success'); if (typeof onComplete === 'function') onComplete(uploadedPaths); return; }
                var file = fileArr[idx++]; var fd = new FormData(); fd.append('file', file);
                api('upload', fd).then(function (data) {
                    if (data.ok) { if (data.photos) renderPhotoGrids(data.photos); if (data.path) uploadedPaths.push(data.path); }
                    else { toast(data.msg, 'error'); }
                    if (total > 1) toast('批量上传中 (' + idx + '/' + total + ')…'); next();
                }).catch(function (err) { toast(err.message, 'error'); next(); });
            }
            next();
        }
        if (uploadZone && photoInput) {
            uploadZone.addEventListener('click', function () { photoInput.click(); });
            photoInput.addEventListener('change', function () { uploadPhotoFiles(this.files); this.value = ''; });
            ['dragenter', 'dragover'].forEach(function (ev) { uploadZone.addEventListener(ev, function (e) { e.preventDefault(); uploadZone.classList.add('drag-over'); }); });
            ['dragleave', 'drop'].forEach(function (ev) { uploadZone.addEventListener(ev, function (e) { e.preventDefault(); uploadZone.classList.remove('drag-over'); }); });
            uploadZone.addEventListener('drop', function (e) { uploadPhotoFiles(e.dataTransfer.files); });
        }
        function renderPhotoGrids(photos) {
            function norm(p) {
                if (p && typeof p === 'object') {
                    return { file: p.file || '', thumb: p.thumb || p.file || '' };
                }
                return { file: String(p || ''), thumb: String(p || '') };
            }
            var grid = $('#photoGrid');
            if (grid) {
                if (!photos || photos.length === 0) {
                    grid.innerHTML = '<div class="empty-tip empty-photo-tip" id="emptyPhotos">🖼 还没有图片，点击上方虚线框上传，或直接把图片放入 <code>photos/</code> 目录后刷新本页面。</div>';
                } else {
                    grid.innerHTML = photos.map(function (raw) {
                        var p = norm(raw);
                        return '<div class="photo-cell" data-path="' + esc(p.file) + '"><div class="drag-handle-photo" title="长按拖动排序">⋮⋮</div><button type="button" class="photo-edit" title="重命名">✎</button><div class="photo-cell-image"><img src="../' + esc(p.thumb) + '" alt="" loading="lazy" decoding="async"></div><div class="photo-cell-bar"><button type="button" class="photo-preview">预览</button><button type="button" class="photo-del">✕</button></div></div>';
                    }).join('');
                }
            }
            var mg = $('#photoModalGrid');
            if (mg) {
                mg.innerHTML = (photos || []).map(function (raw) {
                    var p = norm(raw);
                    return '<div class="photo-cell" data-path="' + esc(p.file) + '"><div class="photo-cell-image"><img src="../' + esc(p.thumb) + '" alt="" loading="lazy" decoding="async"></div></div>';
                }).join('');
            }
        }
        var photoModalOpen = false;
        function openPhotoModal() {
            photoModalOpen = true;
            if (catEditModal && catEditModal.style.display === 'flex') { $('#photoModal').style.zIndex = '3500'; }
            else { $('#photoModal').style.zIndex = ''; }
            showModal($('#photoModal'));
        }
        function closePhotoModal() { photoModalOpen = false; hideModal($('#photoModal')); setTimeout(function () { if (!photoModalOpen) $('#photoModal').style.zIndex = ''; }, 320); photoPickTarget = null; }

        var PHOTO_PICK_MAP = {
            icon:         '#f_icon',
            image:        '#f_image',
            catModalIcon: '#catModalIcon',
            siteAvatar:   '#siteAvatarInput',
            adminAvatar:  '#adminAvatarInput',
            siteFavicon:  '#siteFaviconInput'
        };
        function applyPhotoPick(path) {
            var sel = PHOTO_PICK_MAP[photoPickTarget];
            if (!sel) return false;
            var el = $(sel);
            if (el) {
                el.value = path;
                el.dispatchEvent(new Event('input'));
                toast('已填入', 'success');
            }
            closePhotoModal();
            return true;
        }

        function bindPhotoClick(root, allowEdit) {
            if (!root) return;
            root.addEventListener('click', function (e) {
                var cell = e.target.closest('.photo-cell'); if (!cell) return;
                var path = cell.dataset.path;
                var previewBtn = e.target.closest('.photo-preview'), delBtn = e.target.closest('.photo-del'), editBtn = e.target.closest('.photo-edit');
                if (e.target.closest('.drag-handle-photo')) return;
                if (e.target.closest('.photo-cell-image') || e.target === cell) { window._setActiveCell(cell); }
                if (editBtn && allowEdit) { e.stopPropagation(); openPhotoEditDialog(path); return; }
                if (delBtn) {
                    e.stopPropagation();
                    openConfirm('删除图片', '确定删除图片「' + esc(path.split('/').pop()) + '」吗？', async function () {
                        try {
                            var data = await api('delete_photo', { path: path });
                            if (data.ok) {
                                toast('图片已删除', 'success');
                                renderPhotoGrids(data.photos || []);
                                return;
                            }
                            if (data.msg === '被使用中' && Array.isArray(data.usages) && data.usages.length > 0) {
                                var usageHtml = data.usages.map(function (u) { return '· ' + esc(u); }).join('<br>');
                                openConfirm(
                                    '图片被使用中',
                                    '图片「' + esc(path.split('/').pop()) + '」正在以下位置使用：<br>' +
                                    '<div style="margin:8px 0 8px 8px;line-height:1.8">' + usageHtml + '</div>' +
                                    '<b class="warn">删除后这些引用会被清空（头像 / 网站图标会回退到默认值），是否继续删除？</b>',
                                    async function () {
                                        try {
                                            var d2 = await api('delete_photo', { path: path, force: 1 });
                                            if (!d2.ok) { toast(d2.msg, 'error'); return; }
                                            toast('图片已删除', 'success');
                                            renderPhotoGrids(d2.photos || []);
                                        } catch (err) { toast(err.message, 'error'); }
                                    }
                                );
                                return;
                            }
                            toast(data.msg || '删除失败', 'error');
                        } catch (err) { toast(err.message, 'error'); }
                    });
                    return;
                }
                if (previewBtn) { e.stopPropagation(); openImagePreview('../' + path); return; }
                if (photoPickTarget) { e.stopPropagation(); applyPhotoPick(path); }
            });
            root.addEventListener('mouseover', function (e) {
                var cell = e.target.closest('.photo-cell');
                if (cell) { window._setActiveCell(cell); window._refreshActiveTimer(); }
            });
            root.addEventListener('mousemove', function (e) {
                var cell = e.target.closest('.photo-cell');
                if (cell && cell.classList.contains('is-active')) { window._refreshActiveTimer(); }
            });
        }
        bindPhotoClick($('#photoGrid'), true); bindPhotoClick($('#photoModalGrid'), false);

        var photoModalUploadBtn = $('#photoModalUploadBtn');
        if (photoModalUploadBtn) {
            photoModalUploadBtn.addEventListener('click', function () {
                var inp = document.createElement('input'); inp.type = 'file'; inp.accept = '.jpg,.jpeg,.png,.gif,.webp'; inp.multiple = true;
                inp.onchange = function () {
                    var files = this.files; if (!files || !files.length) return;
                    uploadPhotoFiles(files, function (paths) { if (paths.length > 0 && photoPickTarget) applyPhotoPick(paths[paths.length - 1]); });
                }; inp.click();
            });
        }

        var photoModalClose = $('#photoModalClose'); if (photoModalClose) photoModalClose.addEventListener('click', closePhotoModal);
        var photoModal = $('#photoModal'); if (photoModal) photoModal.addEventListener('click', function (e) { if (e.target === this) closePhotoModal(); });

        function openPhotoEditDialog(path) {
            $('#photoEditPath').value = path; $('#photoEditPreviewImg').src = '../' + path;
            var filename = path.split('/').pop() || ''; $('#photoEditName').value = filename.replace(/\.[^.]+$/, '');
            showModal($('#photoEditModal')); setTimeout(function () { var inp = $('#photoEditName'); if (inp) { inp.focus(); inp.select(); } }, 120);
        }
        function closePhotoEditModal() { hideModal($('#photoEditModal')); }
        var photoEditClose = $('#photoEditClose'); if (photoEditClose) photoEditClose.addEventListener('click', closePhotoEditModal);
        var photoEditCancel = $('#photoEditCancel'); if (photoEditCancel) photoEditCancel.addEventListener('click', closePhotoEditModal);
        var photoEditModalEl = $('#photoEditModal'); if (photoEditModalEl) photoEditModalEl.addEventListener('click', function (e) { if (e.target === this) closePhotoEditModal(); });
        var photoEditSaveBtn = $('#photoEditSave');
        if (photoEditSaveBtn) {
            photoEditSaveBtn.addEventListener('click', async function () {
                var path = $('#photoEditPath').value, newName = $('#photoEditName').value.trim(); if (!newName) { toast('名称不能为空', 'error'); return; }
                var btn = this, oldText = btn.textContent; btn.disabled = true; btn.textContent = '保存中…';
                try { var data = await api('rename_photo', { path: path, name: newName }); if (!data.ok) { toast(data.msg, 'error'); return; } closePhotoEditModal(); smoothReload('图片已重命名，正在刷新...'); } catch (err) { toast(err.message, 'error'); } finally { btn.disabled = false; btn.textContent = oldText; }
            });
        }
        var photoEditNameInput = $('#photoEditName'); if (photoEditNameInput) photoEditNameInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var sv = $('#photoEditSave'); if (sv) sv.click(); } });

        setupSortable({ container: '#photoGrid', itemSelector: '.photo-cell', handleSelector: '.drag-handle-photo', layout: 'grid', holdMs: 350, moveTolerance: 30,
            getKey: function (el) { return el.dataset.path; },
            onSave: function (paths) { api('save_photo_sort', { paths: JSON.stringify(paths) }).then(function (data) { if (!data.ok) { toast(data.msg, 'error'); return; } toast('图片顺序已保存', 'success'); }).catch(function (err) { toast(err.message, 'error'); }); }
        });

        /* ------------------------------------------------------------
           3.7.4 看板娘
           ------------------------------------------------------------ */
        (function () {
            var grid = $('#girlGrid'), toggle = $('#boardGirlEnabled'), zone = $('#girlUploadZone'), input = $('#girlInput');
            if (!grid) return;
            if (toggle) {
                toggle.addEventListener('change', function () {
                    var self = this, willEnable = self.checked;
                    setSwitchLoading(self, true);
                    api('toggle_board_girl', { enabled: willEnable ? 1 : 0 }).then(function (data) {
                        if (!data.ok) { toast(data.msg, 'error'); self.checked = !willEnable; return; }
                        toast(willEnable ? '已开启：看板娘' : '已关闭：看板娘', 'success');
                    }).catch(function (err) { toast(err.message, 'error'); self.checked = !willEnable; }).then(function () { setSwitchLoading(self, false); });
                });
            }
            function doGirlUpload(file) {
                if (!file) return;
                if (!/\.(gif|png|webp|jpe?g)$/i.test(file.name)) { toast('仅支持 gif/png/webp', 'error'); return; }
                if (file.size > 10 * 1024 * 1024) { toast('文件超过 10MB 限制', 'error'); return; }
                var fd = new FormData(); fd.append('file', file); toast('上传中…');
                api('upload_girl', fd).then(function (data) { if (!data.ok) { toast(data.msg, 'error'); return; } smoothReload('看板娘已上传，正在刷新...'); }).catch(function (err) { toast(err.message, 'error'); });
            }
            if (zone && input) {
                zone.addEventListener('click', function () { input.click(); });
                input.addEventListener('change', function () { Array.prototype.forEach.call(input.files, doGirlUpload); input.value = ''; });
                ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('drag-over'); }); });
                ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('drag-over'); }); });
                zone.addEventListener('drop', function (e) { Array.prototype.forEach.call(e.dataTransfer.files, doGirlUpload); });
            }
            var renameModal = $('#girlRenameModal');
            function openGirlRenameModal(file) {
                if (!renameModal) return;
                $('#girlRenameFile').value = file;
                $('#girlRenameName').value = (file.split('/').pop() || '').replace(/\.[^.]+$/, '');
                $('#girlRenamePreviewImg').src = '../' + file;
                showModal(renameModal); setTimeout(function () { var inp = $('#girlRenameName'); if (inp) { inp.focus(); inp.select(); } }, 120);
            }
            function closeGirlRenameModal() { if (renameModal) hideModal(renameModal); }
            var girlRenameClose = $('#girlRenameClose'); if (girlRenameClose) girlRenameClose.addEventListener('click', closeGirlRenameModal);
            var girlRenameCancel = $('#girlRenameCancel'); if (girlRenameCancel) girlRenameCancel.addEventListener('click', closeGirlRenameModal);
            if (renameModal) renameModal.addEventListener('click', function (e) { if (e.target === renameModal) closeGirlRenameModal(); });
            var girlRenameSaveBtn = $('#girlRenameSave');
            if (girlRenameSaveBtn) {
                girlRenameSaveBtn.addEventListener('click', async function () {
                    var file = $('#girlRenameFile').value, newName = $('#girlRenameName').value.trim();
                    if (!newName) { toast('名称不能为空', 'error'); return; }
                    var btn = this, oldText = btn.textContent; btn.disabled = true; btn.textContent = '保存中…';
                    try { var data = await api('rename_girl', { file: file, name: newName }); if (!data.ok) { toast(data.msg, 'error'); return; } closeGirlRenameModal(); smoothReload('看板娘已重命名，正在刷新...'); } catch (err) { toast(err.message, 'error'); } finally { btn.disabled = false; btn.textContent = oldText; }
                });
            }
            var girlRenameNameInput = $('#girlRenameName'); if (girlRenameNameInput) girlRenameNameInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var sv = $('#girlRenameSave'); if (sv) sv.click(); } });
            grid.addEventListener('click', function (e) {
                var cell = e.target.closest('.girl-cell'); if (!cell) return;
                var file = cell.dataset.file, name = cell.dataset.name || '';
                if (e.target.closest('.drag-handle-girl')) return;
                if (e.target.closest('.girl-cell-image') || e.target === cell) { window._setActiveCell(cell); }
                if (e.target.closest('.girl-rename')) { openGirlRenameModal(file); return; }
                if (e.target.closest('.girl-del')) {
                    openConfirm('删除看板娘', '确定删除「' + esc(name) + '」吗？<br><b class="warn">对应的图片文件也会一并删除。</b>', async function () {
                        try { var data = await api('delete_girl', { file: file }); if (!data.ok) { toast(data.msg, 'error'); return; } smoothReload('看板娘已删除，正在刷新...'); } catch (err) { toast(err.message, 'error'); }
                    }); return;
                }
                if (e.target.closest('.girl-preview')) { openImagePreview('../' + file); return; }
            });
            grid.addEventListener('mouseover', function (e) {
                var cell = e.target.closest('.girl-cell');
                if (cell) { window._setActiveCell(cell); window._refreshActiveTimer(); }
            });
            grid.addEventListener('mousemove', function (e) {
                var cell = e.target.closest('.girl-cell');
                if (cell && cell.classList.contains('is-active')) { window._refreshActiveTimer(); }
            });

            setupSortable({ container: '#girlGrid', itemSelector: '.girl-cell', handleSelector: '.drag-handle-girl', layout: 'grid', holdMs: 350, moveTolerance: 30,
                getKey: function (el) { return el.dataset.file; },
                onSave: function (files) { api('save_girl_sort', { files: JSON.stringify(files) }).then(function (data) { if (!data.ok) { toast(data.msg, 'error'); return; } toast('看板娘顺序已保存', 'success'); }).catch(function (err) { toast(err.message, 'error'); }); }
            });

            var tipsForm = document.getElementById('tipsForm');
            if (tipsForm) {
                tipsForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    var btn = tipsForm.querySelector('button[type=submit]'), oldText = btn.textContent;
                    btn.disabled = true; btn.textContent = '保存中…';
                    try {
                        var fd = new FormData();
                        fd.append('enabled', tipsForm.querySelector('[name=tips_enabled]').checked ? 1 : 0);
                        fd.append('duration', tipsForm.querySelector('[name=tips_duration]').value);
                        fd.append('texts', tipsForm.querySelector('[name=tips_texts]').value);
                        fd.append('period_night', tipsForm.querySelector('[name=tips_period_night]').value);
                        fd.append('period_morning', tipsForm.querySelector('[name=tips_period_morning]').value);
                        fd.append('period_noon', tipsForm.querySelector('[name=tips_period_noon]').value);
                        fd.append('period_afternoon', tipsForm.querySelector('[name=tips_period_afternoon]').value);
                        fd.append('period_evening', tipsForm.querySelector('[name=tips_period_evening]').value);
                        fd.append('api', tipsForm.querySelector('[name=tips_api]').value);
                        fd.append('apiField', tipsForm.querySelector('[name=tips_apiField]').value);
                        var data = await api('save_tips', fd);
                        if (!data.ok) { toast(data.msg || '保存失败', 'error'); return; }
                        smoothReload('语录设置已保存，正在刷新...');
                    } catch (err) { toast(err.message, 'error'); } finally { btn.disabled = false; btn.textContent = oldText; }
                });
            }
        })();

        /* ------------------------------------------------------------
           3.7.5 JS 特效
           ------------------------------------------------------------ */
        (function () {
            var rows = $('#effectRows'); if (!rows) return;
            var uploadBtn = $('#uploadJsBtn'), jsInput = $('#jsInput');
            if (uploadBtn && jsInput) {
                uploadBtn.addEventListener('click', function () { jsInput.click(); });
                jsInput.addEventListener('change', function () {
                    var file = this.files[0]; this.value = '';
                    if (!file) return;
                    if (!/\.js$/i.test(file.name)) { toast('仅支持 .js 文件', 'error'); return; }
                    if (file.size > 2 * 1024 * 1024) { toast('文件超过 2MB 限制', 'error'); return; }
                    var fd = new FormData(); fd.append('file', file); toast('上传中…');
                    api('upload_js', fd).then(function (data) { if (!data.ok) { toast(data.msg, 'error'); return; } smoothReload('特效已上传，正在刷新...'); }).catch(function (err) { toast(err.message, 'error'); });
                });
            }
            var urlModal = $('#effectUrlModal');
            function openUrlModal() { if (!urlModal) return; $('#effectUrlInput').value = ''; $('#effectUrlName').value = ''; $('#effectUrlIcon').value = '✨'; $('#effectUrlMatchKey').value = ''; showModal(urlModal); setTimeout(function () { $('#effectUrlInput').focus(); }, 120); }
            function closeUrlModal() { if (urlModal) hideModal(urlModal); }
            var addUrlBtn = $('#addEffectUrlBtn'); if (addUrlBtn) addUrlBtn.addEventListener('click', openUrlModal);
            var effectUrlClose = $('#effectUrlClose'); if (effectUrlClose) effectUrlClose.addEventListener('click', closeUrlModal);
            var effectUrlCancel = $('#effectUrlCancel'); if (effectUrlCancel) effectUrlCancel.addEventListener('click', closeUrlModal);
            if (urlModal) urlModal.addEventListener('click', function (e) { if (e.target === urlModal) closeUrlModal(); });
            var effectUrlSaveBtn = $('#effectUrlSave');
            if (effectUrlSaveBtn) {
                effectUrlSaveBtn.addEventListener('click', async function () {
                    var url = $('#effectUrlInput').value.trim(), name = $('#effectUrlName').value.trim(), icon = $('#effectUrlIcon').value.trim() || '✨', matchKey = $('#effectUrlMatchKey').value.trim();
                    if (!url) { toast('请输入 URL', 'error'); return; }
                    if (!/^https?:\/\//i.test(url)) { toast('URL 必须以 http:// 或 https:// 开头', 'error'); return; }
                    var btn = this, oldText = btn.textContent; btn.disabled = true; btn.textContent = '添加中…';
                    try { var data = await api('add_js_effect_url', { url: url, name: name, icon: icon, matchKey: matchKey }); if (!data.ok) { toast(data.msg, 'error'); return; } closeUrlModal(); smoothReload('远程特效已添加，正在刷新...'); } catch (err) { toast(err.message, 'error'); } finally { btn.disabled = false; btn.textContent = oldText; }
                });
            }
            function doEffectToggle(t) {
                var row = t.closest('.effect-row'); var name = row ? (row.dataset.name || '') : '';
                var willEnable = t.checked; var prefix = willEnable ? '已开启：' : '已关闭：';
                setSwitchLoading(t, true);
                api('toggle_js_effect', { id: t.dataset.id, enabled: willEnable ? 1 : 0 }).then(function (data) {
                    if (!data.ok) { toast(data.msg, 'error'); t.checked = !willEnable; return; }
                    toast(name ? (prefix + name) : (willEnable ? '已开启' : '已关闭'), 'success');
                }).catch(function (err) { toast(err.message, 'error'); t.checked = !willEnable; }).then(function () { setSwitchLoading(t, false); });
            }
            rows.addEventListener('change', function (e) {
                var t = e.target; if (!t.classList.contains('effect-toggle')) return;
                var row = t.closest('.effect-row'); if (!row) return;
                var canToggle = row.dataset.autotoggle === '1';
                if (!t.checked && !canToggle) {
                    var name = row.dataset.name || '';
                    openConfirm('确认禁用', '您正在禁用特效「' + esc(name) + '」。<br>该特效<b>不支持自启停</b>，禁用后将从首页及控制面板中移除，访客无法手动启用。<br><br>是否确认禁用？',
                        function () { doEffectToggle(t); }, function () { t.checked = true; }
                    ); return;
                }
                doEffectToggle(t);
            });
            rows.addEventListener('click', function (e) {
                var row = e.target.closest('.effect-row'); if (!row) return;
                var id = row.dataset.id;
                if (e.target.closest('.edit-effect')) {
                    $('#effectEditId').value = id; $('#effectEditName').value = row.dataset.name || '';
                    $('#effectEditIcon').value = row.dataset.icon || '✨'; $('#effectEditFile').value = row.dataset.file || '';
                    $('#effectEditMatchKey').value = row.dataset.matchkey || '';
                    var fnInput = $('#effectEditFilename'); var isRemote = row.dataset.remote === '1';
                    if (fnInput) {
                        if (isRemote) { fnInput.value = ''; fnInput.disabled = true; fnInput.style.opacity = '.6'; fnInput.style.cursor = 'not-allowed'; }
                        else { var f = row.dataset.file || ''; fnInput.value = (f.split('/').pop() || '').replace(/\.js$/i, ''); fnInput.disabled = false; fnInput.style.opacity = ''; fnInput.style.cursor = ''; }
                    }
                    showModal($('#effectEditModal')); setTimeout(function () { $('#effectEditName').focus(); }, 120);
                } else if (e.target.closest('.del-effect')) {
                    var name = row.dataset.name || '', isRemote = row.dataset.remote === '1';
                    var delMsg = isRemote ? '确定删除「' + esc(name) + '」吗？<br><b class="warn">该特效引用的远程 URL 不会被删除。</b>' : '确定删除「' + esc(name) + '」吗？<br><b class="warn">对应的 .js 文件也会一并删除。</b>';
                    openConfirm('删除特效', delMsg, async function () {
                        try { var data = await api('delete_js_effect', { id: id }); if (!data.ok) { toast(data.msg, 'error'); return; } smoothReload('特效已删除，正在刷新...'); } catch (err) { toast(err.message, 'error'); }
                    });
                }
            });
            var em = $('#effectEditModal');
            function closeEffectModal() { if (em) hideModal(em); }
            var effectEditClose = $('#effectEditClose'); if (effectEditClose) effectEditClose.addEventListener('click', closeEffectModal);
            var effectEditCancel = $('#effectEditCancel'); if (effectEditCancel) effectEditCancel.addEventListener('click', closeEffectModal);
            if (em) em.addEventListener('click', function (e) { if (e.target === em) closeEffectModal(); });

            var readMatchKeyBtn = $('#effectReadMatchKeyBtn');
            if (readMatchKeyBtn) {
                readMatchKeyBtn.addEventListener('click', async function () {
                    var file = $('#effectEditFile').value;
                    if (!file) { toast('文件路径为空', 'error'); return; }
                    var btn = this, oldText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = '读取中…';
                    try {
                        var data = await api('read_js_matchkey', { file: file });
                        if (!data.ok) { toast(data.msg, 'error'); return; }
                        $('#effectEditMatchKey').value = data.matchKey;
                        toast('已读取 matchKey：' + data.matchKey, 'success');
                    } catch (err) {
                        toast(err.message, 'error');
                    } finally {
                        btn.disabled = false;
                        btn.textContent = oldText;
                    }
                });
            }

            var effectEditSaveBtn = $('#effectEditSave');
            if (effectEditSaveBtn) {
                effectEditSaveBtn.addEventListener('click', async function () {
                    var id = $('#effectEditId').value, name = $('#effectEditName').value.trim(), icon = $('#effectEditIcon').value.trim() || '✨';
                    if (!name) { toast('显示名称不能为空', 'error'); return; }
                    var row = rows.querySelector('.effect-row[data-id="' + id + '"]');
                    var enabled = row && row.querySelector('.effect-toggle') && row.querySelector('.effect-toggle').checked ? 1 : 0;
                    var payload = { id: id, name: name, icon: icon, enabled: enabled };
                    var fnInput = $('#effectEditFilename');
                    if (row && row.dataset.remote !== '1' && fnInput && !fnInput.disabled) { var newFn = fnInput.value.trim(); if (newFn !== '') payload.new_filename = newFn; }
                    var mkInput = $('#effectEditMatchKey');
                    if (mkInput) { var mk = mkInput.value.trim(); if (mk !== '') payload.matchKey = mk; }
                    try { var data = await api('save_js_effect', payload); if (!data.ok) { toast(data.msg, 'error'); return; } closeEffectModal(); smoothReload('特效已保存，正在刷新...'); } catch (err) { toast(err.message, 'error'); }
                });
            }

            setupSortable({ container: '#effectRows', itemSelector: '.effect-row', handleSelector: '.drag-handle-mini', layout: 'list', holdMs: 350, moveTolerance: 30,
                getKey: function (el) { return el.dataset.id; },
                onSave: function (ids) { api('save_effect_sort', { ids: JSON.stringify(ids) }).then(function (data) { if (!data.ok) { toast(data.msg, 'error'); return; } toast('特效顺序已保存', 'success'); }).catch(function (err) { toast(err.message, 'error'); }); }
            });
        })();

        /* ------------------------------------------------------------
           3.7.6 加载页主题
           ------------------------------------------------------------ */
        (function () {
            var rows = document.getElementById('preloaderRows');
            if (!rows) return;

            var enabledToggle = document.getElementById('preloaderEnabledToggle');
            if (enabledToggle) {
                enabledToggle.addEventListener('change', function () {
                    var self = this;
                    var willEnable = self.checked;
                    setSwitchLoading(self, true);
                    api('save_site', { preloader: willEnable ? '1' : '0' }).then(function (data) {
                        if (!data.ok) { toast(data.msg, 'error'); self.checked = !willEnable; return; }
                        toast(willEnable ? '已开启：首屏加载动画' : '已关闭：首屏加载动画', 'success');
                    }).catch(function (err) { toast(err.message, 'error'); self.checked = !willEnable; })
                      .then(function () { setSwitchLoading(self, false); });
                });
            }

            var zone = document.getElementById('preloaderUploadZone');
            var input = document.getElementById('preloaderInput');

            function doPreloaderUpload(files) {
                var fileArr = Array.prototype.slice.call(files || []);
                if (!fileArr.length) return;
                for (var i = 0; i < fileArr.length; i++) {
                    var f = fileArr[i];
                    if (!/\.css$/i.test(f.name)) { toast('仅支持 .css 文件', 'error'); return; }
                    if (f.size > 2 * 1024 * 1024) { toast('文件超过 2MB 限制', 'error'); return; }
                }
                var total = fileArr.length, idx = 0, okCount = 0, lastError = '';
                toast(total === 1 ? '上传中…' : '批量上传中 (0/' + total + ')…');
                function next() {
                    if (idx >= total) {
                        if (okCount > 0) {
                            smoothReload('已上传 ' + okCount + ' 个主题，正在刷新...');
                        } else {
                            toast(lastError || '没有成功上传任何主题', 'error');
                        }
                        return;
                    }
                    var file = fileArr[idx++];
                    var fd = new FormData(); fd.append('file', file);
                    api('upload_preloader', fd).then(function (data) {
                        if (data.ok) { okCount++; } else { lastError = data.msg; }
                        if (total > 1 && idx < total) toast('批量上传中 (' + idx + '/' + total + ')…');
                        next();
                    }).catch(function (err) { lastError = err.message; next(); });
                }
                next();
            }

            if (zone && input) {
                zone.addEventListener('click', function () { input.click(); });
                input.addEventListener('change', function () { doPreloaderUpload(this.files); this.value = ''; });
                ['dragenter', 'dragover'].forEach(function (ev) {
                    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('drag-over'); });
                });
                ['dragleave', 'drop'].forEach(function (ev) {
                    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('drag-over'); });
                });
                zone.addEventListener('drop', function (e) { doPreloaderUpload(e.dataTransfer.files); });
            }

            rows.addEventListener('change', function (e) {
                var t = e.target;
                if (!t.classList.contains('preloader-toggle')) return;
                var key = t.dataset.key;
                var enabled = t.checked;
                setSwitchLoading(t, true);
                api('toggle_preloader', { key: key, enabled: enabled ? 1 : 0 }).then(function (data) {
                    if (!data.ok) { toast(data.msg, 'error'); t.checked = !enabled; return; }
                    toast(enabled ? '已启用' : '已禁用', 'success');
                }).catch(function (err) { toast(err.message, 'error'); t.checked = !enabled; })
                  .then(function () { setSwitchLoading(t, false); });
            });

            rows.addEventListener('click', function (e) {
                var row = e.target.closest('.effect-row');
                if (!row) return;
                var key = row.dataset.key;

                if (e.target.closest('.edit-preloader')) {
                    document.getElementById('preloaderEditKey').value = key;
                    document.getElementById('preloaderEditName').value = row.dataset.name || '';
                    document.getElementById('preloaderEditDesc').value = row.dataset.desc || '';
                    var fname = (row.dataset.file || '').split('/').pop() || '';
                    document.getElementById('preloaderEditFilename').value = fname.replace(/\.css$/i, '');
                    showModal(document.getElementById('preloaderEditModal'));
                    setTimeout(function () { document.getElementById('preloaderEditName').focus(); }, 120);
                } else if (e.target.closest('.del-preloader')) {
                    var isDefault = row.dataset.default === '1';
                    var msg = '确定删除主题「' + esc(row.dataset.name) + '」吗？<br><b class="warn">对应的 .css 文件也会一并删除。</b>';
                    if (isDefault) msg += '<br><b class="warn">该主题当前是默认主题，删除后会自动切换为列表中的第一个。</b>';
                    openConfirm('删除主题', msg, function () {
                        api('delete_preloader', { key: key }).then(function (data) {
                            if (!data.ok) { toast(data.msg, 'error'); return; }
                            smoothReload('主题已删除，正在刷新...');
                        }).catch(function (err) { toast(err.message, 'error'); });
                    });
                }
            });

            var em = document.getElementById('preloaderEditModal');
            function closeEditModal() { if (em) hideModal(em); }
            var closeBtn = document.getElementById('preloaderEditClose');
            if (closeBtn) closeBtn.addEventListener('click', closeEditModal);
            var cancelBtn = document.getElementById('preloaderEditCancel');
            if (cancelBtn) cancelBtn.addEventListener('click', closeEditModal);
            if (em) em.addEventListener('click', function (e) { if (e.target === em) closeEditModal(); });

            var saveBtn = document.getElementById('preloaderEditSave');
            if (saveBtn) {
                saveBtn.addEventListener('click', async function () {
                    var key = document.getElementById('preloaderEditKey').value;
                    var name = document.getElementById('preloaderEditName').value.trim();
                    var desc = document.getElementById('preloaderEditDesc').value.trim();
                    var newFn = document.getElementById('preloaderEditFilename').value.trim();
                    if (!name) { toast('名称不能为空', 'error'); return; }
                    var payload = { key: key, name: name, description: desc };
                    if (newFn) payload.new_filename = newFn;
                    try {
                        var data = await api('save_preloader', payload);
                        if (!data.ok) { toast(data.msg, 'error'); return; }
                        closeEditModal();
                        smoothReload('主题已保存，正在刷新...');
                    } catch (err) { toast(err.message, 'error'); }
                });
            }

            setupSortable({
                container: '#preloaderRows', itemSelector: '.effect-row', handleSelector: '.drag-handle-mini', layout: 'list',
                holdMs: 350, moveTolerance: 30,
                getKey: function (el) { return el.dataset.key; },
                onSave: function (keys) {
                    api('save_preloader_sort', { keys: JSON.stringify(keys) }).then(function (data) {
                        if (!data.ok) { toast(data.msg, 'error'); return; }
                        toast('顺序已保存，首个主题已设为默认', 'success');
                        smoothReload('顺序已保存，正在刷新...');
                    }).catch(function (err) { toast(err.message, 'error'); });
                }
            });
        })();

        /* ------------------------------------------------------------
           3.7.7 本地背景兜底
           ------------------------------------------------------------ */
        (function () {
            var panel = document.getElementById('panel-localbg');
            if (!panel) return;

            function findUaName(id) {
                var rows = document.querySelectorAll('.ua-row');
                for (var i = 0; i < rows.length; i++) {
                    if (rows[i].dataset.id === id) return rows[i].dataset.name || '';
                }
                return '';
            }

            function refreshUaButtons() {
                panel.querySelectorAll('.ua-pick-btn').forEach(function (btn) {
                    var tplId = btn.dataset.currentUa;
                    var name = findUaName(tplId);
                    btn.title = name ? ('当前抓取 UA：' + name) : '选择抓取 UA';
                });
            }

            function refreshUaRowBadges() {
                var bgBtn = panel.querySelector('.ua-pick-btn[data-local-type="bg"]');
                var bannerBtn = panel.querySelector('.ua-pick-btn[data-local-type="banner"]');
                var bgId = bgBtn ? bgBtn.dataset.currentUa : '';
                var bannerId = bannerBtn ? bannerBtn.dataset.currentUa : '';

                document.querySelectorAll('.ua-row').forEach(function (row) {
                    var id = row.dataset.id;
                    var nameEl = row.querySelector('.effect-row-name');
                    if (!nameEl) return;

                    nameEl.querySelectorAll('.badge-usage').forEach(function (el) { el.remove(); });

                    if (id === bgId) {
                        var s1 = document.createElement('span');
                        s1.className = 'effect-badge badge-toggle badge-usage';
                        s1.textContent = '背景使用中';
                        nameEl.appendChild(s1);
                    }
                    if (id === bannerId) {
                        var s2 = document.createElement('span');
                        s2.className = 'effect-badge badge-url badge-usage';
                        s2.textContent = 'Banner 使用中';
                        nameEl.appendChild(s2);
                    }
                });
            }

            window._nuomiRefreshUaButtons = function () {
                refreshUaButtons();
                refreshUaRowBadges();
            };

            refreshUaButtons();
            refreshUaRowBadges();

            panel.querySelectorAll('.auto-detect-btn').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    var type = btn.dataset.localType;
                    var oldText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = '分类中…';
                    try {
                        var data = await api('auto_detect_local_bg', { type: type });
                        if (!data.ok) {
                            toast(data.msg, 'error');
                            return;
                        }
                        var stats = data.stats || {};
                        var failed = stats.failed || 0;
                        var toastType = failed > 0 ? 'warn' : 'success';
                        toast(data.msg, toastType);
                        renderLocalGrid(type, data.images || []);
                    } catch (err) {
                        toast(err.message, 'error');
                    } finally {
                        btn.disabled = false;
                        btn.textContent = oldText;
                    }
                });
            });

            function renderLocalGrid(type, images) {
                var grid = panel.querySelector('.local-bg-grid[data-local-type="' + type + '"]');
                if (!grid) return;
                if (!images || images.length === 0) {
                    grid.innerHTML = '<div class="empty-tip local-bg-empty" style="grid-column:1/-1;padding:16px;font-size:12px;">暂无本地兜底图片。</div>';
                    return;
                }

                var groups = { mobile: [], desktop: [], both: [] };
                images.forEach(function (item) {
                    var t = item.target || 'both';
                    if (!groups[t]) groups[t] = [];
                    groups[t].push(item);
                });

                var groupDefs = [
                    { key: 'mobile',  label: '📱 移动端（竖屏）' },
                    { key: 'desktop', label: '🖥 桌面端（横屏）' },
                    { key: 'both',    label: '🌐 通用' }
                ];

                var html = '';
                groupDefs.forEach(function (def) {
                    var items = groups[def.key] || [];
                    if (items.length === 0) return;
                    html += '<div class="local-bg-group-title">' + def.label + ' · ' + items.length + ' 张</div>';
                    html += items.map(function (item) {
                        var thumb = item.thumb || item.file;
                        var t = item.target || 'both';
                        return '<div class="photo-cell local-bg-cell" data-type="' + type + '" data-file="' + esc(item.file) + '" data-thumb="' + esc(thumb) + '" data-name="' + esc(item.name || '') + '" data-target="' + t + '">' +
                            '<div class="drag-handle-photo" title="长按拖动排序">⋮⋮</div>' +
                            '<button type="button" class="photo-edit" title="编辑">✎</button>' +
                            '<div class="photo-cell-image"><img data-src="../' + esc(thumb) + '" alt="" loading="lazy" decoding="async"></div>' +
                            '<div class="photo-cell-bar">' +
                                '<button type="button" class="photo-preview">预览</button>' +
                                '<button type="button" class="photo-del">✕</button>' +
                            '</div>' +
                        '</div>';
                    }).join('');
                });

                grid.innerHTML = html;
                hydrateLocalBgImages();
            }

            function uploadLocalFiles(type, files) {
                var arr = Array.prototype.slice.call(files || []);
                if (!arr.length) return;
                for (var i = 0; i < arr.length; i++) {
                    if (!/\.(jpe?g|png|gif|webp)$/i.test(arr[i].name)) { toast('仅支持 jpg/png/gif/webp', 'error'); return; }
                    if (arr[i].size > 5 * 1024 * 1024) { toast('文件超过 5MB 限制', 'error'); return; }
                }

                var idx = 0, total = arr.length;
                var okCount = 0;
                var failMsgs = [];
                var lastImages = null;

                if (total === 1) toast('上传中…');
                else toast('批量上传中 (0/' + total + ')…');

                function finish() {
                    if (lastImages) renderLocalGrid(type, lastImages);

                    if (failMsgs.length === 0) {
                        toast('已上传 ' + okCount + ' 张', 'success');
                        return;
                    }
                    if (total === 1 && failMsgs.length === 1) {
                        toast(failMsgs[0], 'error');
                        return;
                    }
                    if (okCount === 0) {
                        var firstMsg = failMsgs[0] || '上传失败';
                        if (failMsgs.length === 1) toast(firstMsg, 'error');
                        else toast(failMsgs.length + ' 张全部失败：' + firstMsg, 'error');
                        return;
                    }
                    toast('成功 ' + okCount + ' 张，失败 ' + failMsgs.length + ' 张（' + failMsgs[0] + '）', 'warn');
                }

                function next() {
                    if (idx >= total) { finish(); return; }
                    var file = arr[idx++];
                    var fd = new FormData();
                    fd.append('file', file); fd.append('type', type);
                    api('upload_local_bg', fd).then(function (data) {
                        if (!data.ok) {
                            failMsgs.push(data.msg || '上传失败');
                        } else {
                            okCount++;
                            if (data.images) lastImages = data.images;
                        }
                        if (total > 1) toast('批量上传中 (' + idx + '/' + total + ')…');
                        next();
                    }).catch(function (err) {
                        failMsgs.push(err.message || '网络错误');
                        next();
                    });
                }
                next();
            }

            panel.querySelectorAll('.local-bg-zone').forEach(function (zone) {
                var type = zone.dataset.localType;
                var input = zone.querySelector('.local-bg-input');
                zone.addEventListener('click', function () { input.click(); });
                input.addEventListener('change', function () { uploadLocalFiles(type, this.files); this.value = ''; });
                ['dragenter', 'dragover'].forEach(function (ev) {
                    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('drag-over'); });
                });
                ['dragleave', 'drop'].forEach(function (ev) {
                    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('drag-over'); });
                });
                zone.addEventListener('drop', function (e) { uploadLocalFiles(type, e.dataTransfer.files); });
            });

            panel.querySelectorAll('.fetch-local-bg-btn').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    var type = btn.dataset.localType;
                    var countInput = panel.querySelector('.local-bg-fetch-count[data-local-type="' + type + '"]');
                    var count = parseInt(countInput.value, 10) || 5;
                    if (count < 1) count = 1;
                    if (count > 20) count = 20;
                    var uaBtn = panel.querySelector('.ua-pick-btn[data-local-type="' + type + '"]');
                    var uaTemplateId = uaBtn ? uaBtn.dataset.currentUa : '';
                    var oldText = btn.textContent;
                    btn.disabled = true; btn.textContent = '抓取中…';
                    try {
                        var payload = { type: type, count: count };
                        if (uaTemplateId) payload.uaTemplateId = uaTemplateId;
                        var data = await api('fetch_local_bg_from_api', payload);
                        if (!data.ok) { toast(data.msg, 'error'); return; }

                        var hasWarning = /失败|去重/.test(data.msg);
                        toast(data.msg, hasWarning ? 'warn' : 'success');

                        renderLocalGrid(type, data.images || []);
                    } catch (err) { toast(err.message, 'error'); }
                    finally { btn.disabled = false; btn.textContent = oldText; }
                });
            });

            ['bg', 'banner'].forEach(function (type) {
                var toggle = panel.querySelector(type === 'bg' ? '#localBgEnabled' : '#localBannerEnabled');
                if (!toggle) return;
                toggle.addEventListener('change', function () {
                    var self = this, enabled = self.checked;
                    setSwitchLoading(self, true);
                    api('toggle_local_bg', { type: type, enabled: enabled ? 1 : 0 }).then(function (data) {
                        if (!data.ok) { toast(data.msg, 'error'); self.checked = !enabled; return; }
                        toast(enabled ? '已启用' : '已禁用', 'success');
                    }).catch(function (err) { toast(err.message, 'error'); self.checked = !enabled; })
                      .then(function () { setSwitchLoading(self, false); });
                });
            });

            ['bg', 'banner'].forEach(function (type) {
                var name = (type === 'bg') ? 'localBgPriority' : 'localBannerPriority';
                var radios = panel.querySelectorAll('input[name="' + name + '"]');
                if (!radios.length) return;
                radios.forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        if (!this.checked) return;
                        var val = this.value;
                        var payload = {};
                        payload[name] = val;
                        api('save_site', payload).then(function (data) {
                            if (!data.ok) { toast(data.msg, 'error'); return; }
                            toast(val === 'local' ? '已切换为「本地优先」' : '已切换为「API 优先」', 'success');
                        }).catch(function (err) { toast(err.message, 'error'); });
                    });
                });
            });

            var renameModal = document.getElementById('localRenameModal');
            var currentLocalTarget = 'auto';

            function openLocalRename(type, file, name, target) {
                if (!renameModal) return;
                
                var imgEl = document.getElementById('localRenamePreviewImg');
                imgEl.src = ''; 
                imgEl.style.display = 'none';

                document.getElementById('localRenameType').value = type;
                document.getElementById('localRenameFile').value = file;
                document.getElementById('localRenameName').value = name;
                
                var tempImg = new Image();
                tempImg.onload = function() {
                    imgEl.src = tempImg.src;
                    imgEl.style.display = 'block';
                };
                tempImg.src = '../' + file; 

                currentLocalTarget = (['desktop', 'mobile', 'both'].indexOf(target) === -1) ? 'auto' : target;
                var btns = renameModal.querySelectorAll('.target-btn');
                btns.forEach(function(b) {
                    b.classList.toggle('is-active', b.dataset.target === currentLocalTarget);
                });

                showModal(renameModal);
                
                setTimeout(function () {
                    var inp = document.getElementById('localRenameName');
                    if (inp) { 
                        inp.focus(); 
                        inp.select(); 
                        setTimeout(function() {
                            inp.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }, 300);
                    }
                }, 120);
            }
            
            if (renameModal) {
                renameModal.querySelectorAll('.target-btn').forEach(function(b) {
                    b.addEventListener('click', function() {
                        currentLocalTarget = this.dataset.target;
                        renameModal.querySelectorAll('.target-btn').forEach(function(btn) {
                            btn.classList.toggle('is-active', btn.dataset.target === currentLocalTarget);
                        });
                    });
                });
            }

            function closeLocalRename() { if (renameModal) hideModal(renameModal); }
            var rc = document.getElementById('localRenameClose');  if (rc) rc.addEventListener('click', closeLocalRename);
            var rcancel = document.getElementById('localRenameCancel'); if (rcancel) rcancel.addEventListener('click', closeLocalRename);
            if (renameModal) renameModal.addEventListener('click', function (e) { if (e.target === renameModal) closeLocalRename(); });
            var rsave = document.getElementById('localRenameSave');
            if (rsave) {
                rsave.addEventListener('click', async function () {
                    var type = document.getElementById('localRenameType').value;
                    var file = document.getElementById('localRenameFile').value;
                    var name = document.getElementById('localRenameName').value.trim();
                    
                    var targetVal = currentLocalTarget; 
                    
                    if (!name) { toast('名称不能为空', 'error'); return; }
                    var btn = this, oldText = btn.textContent;
                    btn.disabled = true; btn.textContent = '保存中…';
                    try {
                        var payload = { type: type, file: file, name: name, target: targetVal };
                        var data = await api('rename_local_bg', payload);
                        if (!data.ok) { toast(data.msg, 'error'); return; }
                        closeLocalRename();
                        toast('已保存', 'success');
                        renderLocalGrid(type, data.images || []);
                    } catch (err) { toast(err.message, 'error'); }
                    finally { btn.disabled = false; btn.textContent = oldText; }
                });
            }

            panel.querySelectorAll('.local-bg-grid').forEach(function (grid) {
                var type = grid.dataset.localType;
                grid.addEventListener('click', function (e) {
                    var cell = e.target.closest('.local-bg-cell');
                    if (!cell) return;
                    var file = cell.dataset.file, name = cell.dataset.name || '', target = cell.dataset.target || 'both';
                    if (e.target.closest('.drag-handle-photo')) return;
                    if (e.target.closest('.photo-cell-image') || e.target === cell) {
                        window._setActiveCell(cell);
                    }
                    if (e.target.closest('.photo-preview')) { openImagePreview('../' + file); return; }
                    if (e.target.closest('.photo-edit')) {
                        openLocalRename(type, file, name || (file.split('/').pop() || '').replace(/\.[^.]+$/, ''), target);
                        return;
                    }
                    if (e.target.closest('.photo-del')) {
                        openConfirm('删除本地图片', '确定删除「' + esc(file.split('/').pop()) + '」吗？<br><b class="warn">磁盘文件也会一并删除。</b>', function () {
                            api('delete_local_bg', { type: type, file: file }).then(function (data) {
                                if (!data.ok) { toast(data.msg, 'error'); return; }
                                toast('已删除', 'success');
                                renderLocalGrid(type, data.images || []);
                            }).catch(function (err) { toast(err.message, 'error'); });
                        });
                        return;
                    }
                });
                grid.addEventListener('mouseover', function (e) {
                    var cell = e.target.closest('.local-bg-cell');
                    if (cell) { window._setActiveCell(cell); window._refreshActiveTimer(); }
                });
                grid.addEventListener('mousemove', function (e) {
                    var cell = e.target.closest('.local-bg-cell');
                    if (cell && cell.classList.contains('is-active')) window._refreshActiveTimer();
                });
            });

            ['bg', 'banner'].forEach(function (type) {
                setupSortable({
                    container: '.local-bg-grid[data-local-type="' + type + '"]',
                    itemSelector: '.local-bg-cell',
                    handleSelector: '.drag-handle-photo',
                    layout: 'grid',
                    holdMs: 350,
                    moveTolerance: 30,
                    getKey: function (el) { return el.dataset.file; },
                    onSave: function (files) {
                        api('save_local_bg_sort', { type: type, files: JSON.stringify(files) })
                            .then(function (data) {
                                if (!data.ok) { toast(data.msg, 'error'); return; }
                                toast('顺序已保存', 'success');
                            }).catch(function (err) { toast(err.message, 'error'); });
                    }
                });
            });

            ['bg', 'banner'].forEach(function (type) {
                var grid = panel.querySelector('.local-bg-grid[data-local-type="' + type + '"]');
                if (!grid) return;
                var cells = grid.querySelectorAll('.local-bg-cell');
                if (cells.length === 0) return;
                var images = [];
                cells.forEach(function (cell) {
                    images.push({
                        file: cell.dataset.file,
                        name: cell.dataset.name,
                        target: cell.dataset.target,
                        thumb: cell.dataset.thumb || cell.dataset.file
                    });
                });
                renderLocalGrid(type, images);
            });
        })();

        /* ------------------------------------------------------------
           3.7.8 UA 伪装管理
           ------------------------------------------------------------ */
        (function () {
            var panel = document.getElementById('panel-uaprofiles');
            var rows  = document.getElementById('uaRows');
            if (!panel || !rows) return;

            var editModal = document.getElementById('uaEditModal');
            var editTitle = document.getElementById('uaEditTitle');
            var editId    = document.getElementById('uaEditId');
            var editName  = document.getElementById('uaEditName');
            var editUa    = document.getElementById('uaEditUa');
            var editAccept = document.getElementById('uaEditAccept');
            var editLang  = document.getElementById('uaEditLang');
            var editEnc   = document.getElementById('uaEditEnc');
            var editExtra = document.getElementById('uaEditExtra');

            function openEdit(tpl) {
                if (tpl) {
                    editTitle.textContent = '编辑 UA 模板';
                    editId.value    = tpl.id || '';
                    editName.value  = tpl.name || '';
                    editUa.value    = tpl.ua || '';
                    editAccept.value = tpl.accept || '';
                    editLang.value  = tpl.acceptLanguage || '';
                    editEnc.value   = tpl.acceptEncoding || '';
                    editExtra.value = tpl.extraHeaders || '';
                } else {
                    editTitle.textContent = '新增 UA 模板';
                    editId.value = '';
                    editName.value = '';
                    editUa.value = '';
                    editAccept.value = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
                    editLang.value = 'zh-CN,zh;q=0.9';
                    editEnc.value = 'gzip, deflate, br';
                    editExtra.value = 'Sec-Fetch-Mode: navigate';
                }
                showModal(editModal);
                setTimeout(function () { editName.focus(); }, 120);
            }
            function closeEdit() { hideModal(editModal); }

            var addBtn = document.getElementById('addUaBtn');
            if (addBtn) addBtn.addEventListener('click', function () { openEdit(null); });

            var editCloseBtn = document.getElementById('uaEditClose');
            if (editCloseBtn) editCloseBtn.addEventListener('click', closeEdit);
            var editCancelBtn = document.getElementById('uaEditCancel');
            if (editCancelBtn) editCancelBtn.addEventListener('click', closeEdit);
            if (editModal) editModal.addEventListener('click', function (e) { if (e.target === editModal) closeEdit(); });

            var editSaveBtn = document.getElementById('uaEditSave');
            if (editSaveBtn) {
                editSaveBtn.addEventListener('click', async function () {
                    var id = editId.value.trim();
                    var name = editName.value.trim();
                    var ua = editUa.value.trim();
                    if (!name) { toast('模板名称不能为空', 'error'); return; }
                    if (!ua)   { toast('User-Agent 不能为空', 'error'); return; }
                    var payload = {
                        name: name, ua: ua,
                        accept: editAccept.value.trim(),
                        acceptLanguage: editLang.value.trim(),
                        acceptEncoding: editEnc.value.trim(),
                        extraHeaders: editExtra.value,
                    };
                    var action = id ? 'save_ua_template' : 'add_ua_template';
                    if (id) payload.id = id;
                    var btn = this, oldText = btn.textContent;
                    btn.disabled = true; btn.textContent = '保存中…';
                    try {
                        var data = await api(action, payload);
                        if (!data.ok) { toast(data.msg, 'error'); return; }
                        closeEdit();
                        smoothReload('UA 模板已保存，正在刷新...');
                    } catch (err) { toast(err.message, 'error'); }
                    finally { btn.disabled = false; btn.textContent = oldText; }
                });
            }

            rows.addEventListener('click', function (e) {
                var row = e.target.closest('.ua-row');
                if (!row) return;
                if (e.target.closest('.edit-ua')) {
                    openEdit({
                        id: row.dataset.id,
                        name: row.dataset.name,
                        ua: row.dataset.ua,
                        accept: row.dataset.accept,
                        acceptLanguage: row.dataset.lang,
                        acceptEncoding: row.dataset.enc,
                        extraHeaders: row.dataset.extra,
                    });
                    return;
                }
                if (e.target.closest('.del-ua')) {
                    openConfirm('删除 UA 模板',
                        '确定删除「' + esc(row.dataset.name) + '」吗？<br><b class="warn">如果背景或 Banner 正在使用此模板，将自动回退到默认模板。</b>',
                        function () {
                            api('delete_ua_template', { id: row.dataset.id }).then(function (data) {
                                if (!data.ok) { toast(data.msg, 'error'); return; }
                                smoothReload('模板已删除，正在刷新...');
                            }).catch(function (err) { toast(err.message, 'error'); });
                        });
                }
            });

            var pickModal = document.getElementById('uaPickModal');
            var pickList  = document.getElementById('uaPickList');
            var pickTitle = document.getElementById('uaPickTitle');
            var pickType  = '';
            var pickBtnRef = null;

            function openPick(btn) {
                pickBtnRef = btn;
                pickType = btn.dataset.localType;
                pickTitle.textContent = '选择' + (pickType === 'bg' ? '背景' : 'Banner') + '抓取 UA 模板';

                var currentId = btn.dataset.currentUa;
                var html = '';
                document.querySelectorAll('.ua-row').forEach(function (row) {
                    var id = row.dataset.id;
                    var name = row.dataset.name || '未命名';
                    var ua = row.dataset.ua || '';
                    var isCur = (id === currentId);
                    html += '<div class="ua-pick-item' + (isCur ? ' is-current' : '') + '" data-id="' + esc(id) + '" data-name="' + esc(name) + '">' +
                        '<span class="ua-pick-item-check">✓</span>' +
                        '<div class="ua-pick-item-info">' +
                            '<div class="ua-pick-item-name">' + esc(name) + '</div>' +
                            '<div class="ua-pick-item-ua">' + esc(ua.length > 70 ? ua.slice(0, 70) + '…' : ua) + '</div>' +
                        '</div>' +
                    '</div>';
                });
                if (!html) {
                    html = '<div class="empty-tip" style="padding:16px;font-size:12px;">暂无 UA 模板，请到「🎭 UA 伪装」标签页添加。</div>';
                }
                pickList.innerHTML = html;
                showModal(pickModal);
            }
            function closePick() { hideModal(pickModal); }

            var pickCloseBtn = document.getElementById('uaPickClose');
            if (pickCloseBtn) pickCloseBtn.addEventListener('click', closePick);
            var pickCancelBtn = document.getElementById('uaPickCancel');
            if (pickCancelBtn) pickCancelBtn.addEventListener('click', closePick);
            if (pickModal) pickModal.addEventListener('click', function (e) { if (e.target === pickModal) closePick(); });

            pickList.addEventListener('click', async function (e) {
                var item = e.target.closest('.ua-pick-item');
                if (!item) return;
                var id = item.dataset.id;
                var name = item.dataset.name;
                if (!pickType) return;
                try {
                    var data = await api('set_fetch_ua', { type: pickType, templateId: id });
                    if (!data.ok) { toast(data.msg, 'error'); return; }

                    document.querySelectorAll('.ua-pick-btn[data-local-type="' + pickType + '"]').forEach(function (b) {
                        b.dataset.currentUa = id;
                    });
                    if (typeof window._nuomiRefreshUaButtons === 'function') {
                        window._nuomiRefreshUaButtons();
                    }

                    closePick();
                    toast('已切换 UA 模板：' + name, 'success');
                } catch (err) { toast(err.message, 'error'); }
            });

            document.querySelectorAll('.ua-pick-btn').forEach(function (btn) {
                btn.addEventListener('click', function () { openPick(btn); });
            });
        })();

        /* ------------------------------------------------------------
           3.7.9 统计代码
           ------------------------------------------------------------ */
        (function () {
            var ta = document.getElementById('analyticsCodeInput');
            if (!ta) return;

            var MIN_H = 120, MAX_H = 400;

            function autoResize() {
                if (ta.offsetParent === null) return;
                ta.style.height = 'auto';
                var h = ta.scrollHeight;
                if (h < MIN_H) h = MIN_H;
                if (h > MAX_H) h = MAX_H;
                ta.style.height = h + 'px';
                ta.style.overflowY = (ta.scrollHeight > MAX_H) ? 'auto' : 'hidden';
            }

            ta.addEventListener('input', autoResize);

            if (ta.offsetParent !== null) autoResize();

            var analyticsTabBtn = document.querySelector('[data-tab="analytics"]');
            if (analyticsTabBtn) {
                analyticsTabBtn.addEventListener('click', function () {
                    requestAnimationFrame(autoResize);
                });
            }
        })();

        /* ------------------------------------------------------------
           3.7.10 站点设置
           ------------------------------------------------------------ */
        $all('.pick-site-avatar').forEach(function (btn) { btn.addEventListener('click', function () { photoPickTarget = 'siteAvatar'; openPhotoModal(); }); });
        $all('.pick-admin-avatar').forEach(function (btn) { btn.addEventListener('click', function () { photoPickTarget = 'adminAvatar'; openPhotoModal(); }); });
        $all('.pick-site-favicon').forEach(function (btn) { btn.addEventListener('click', function () { photoPickTarget = 'siteFavicon'; openPhotoModal(); }); });
        var siteAvatarInput = $('#siteAvatarInput');
        if (siteAvatarInput) { siteAvatarInput.addEventListener('input', function () { updateSiteAvatarPreview('siteAvatarInput', 'siteAvatarPreviewWrap', 'siteAvatarPreviewImg'); }); updateSiteAvatarPreview('siteAvatarInput', 'siteAvatarPreviewWrap', 'siteAvatarPreviewImg'); }
        var adminAvatarInput = $('#adminAvatarInput');
        if (adminAvatarInput) { adminAvatarInput.addEventListener('input', function () { updateSiteAvatarPreview('adminAvatarInput', 'adminAvatarPreviewWrap', 'adminAvatarPreviewImg'); }); updateSiteAvatarPreview('adminAvatarInput', 'adminAvatarPreviewWrap', 'adminAvatarPreviewImg'); }
        var siteFaviconInput = $('#siteFaviconInput');
        if (siteFaviconInput) { siteFaviconInput.addEventListener('input', function () { updateFaviconPreview('siteFaviconInput', 'siteFaviconPreviewWrap', 'siteFaviconPreviewImg'); }); updateFaviconPreview('siteFaviconInput', 'siteFaviconPreviewWrap', 'siteFaviconPreviewImg'); }

        /* ------------------------------------------------------------
           3.7.11 备份恢复
           ------------------------------------------------------------ */
        var importForm = $('#importForm');
        if (importForm) {
            importForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var file = $('#importFile').files[0];
                if (!file) { toast('请先选择备份文件', 'error'); return; }
                openConfirm('导入备份', '将使用所选备份<b>完整覆盖</b>当前全部数据（卡片、分类、图片、看板娘、语录设置、JS 特效、加载页主题、本地背景、UA 模板、站点设置、统计代码、公告），此操作不可撤销。建议先导出备份。确定继续？', async function () {
                    try { var fd = new FormData(); fd.append('backup', file); var data = await api('import', fd); if (!data.ok) { toast(data.msg, 'error'); return; } smoothReload('数据已导入，正在刷新...'); } catch (err) { toast(err.message, 'error'); }
                });
            });
        }

        (function () {
            var resetBtn = $('#resetDataBtn'), modal = $('#resetDataModal'); if (!resetBtn || !modal) return;
            var CONFIRM_WORDS = { partial: 'RESET', all: 'RESET', full: 'CLEAR' };
            var MODULES = [
                { key: 'cards', icon: '🃏', label: '卡片与分类', desc: '所有卡片及所属分类' },
                { key: 'photos', icon: '🖼️', label: '图片', desc: 'photos/ 目录下的所有图片' },
                { key: 'girls', icon: '👘', label: '看板娘', desc: 'photos/kanban/ 下的所有形象' },
                { key: 'tips', icon: '💬', label: '语录设置', desc: '看板娘语录恢复默认' },
                { key: 'effects', icon: '✨', label: 'JS 特效', desc: 'assets/js/ 目录下的所有特效' },
                { key: 'preloaders', icon: '🎨', label: '加载页', desc: 'assets/preloaders/ 下的所有主题' },
                { key: 'localbg', icon: '🖼️', label: '本地背景兜底', desc: 'photos/localbg/ 与 photos/localbanner/ 下的所有图片' },
                { key: 'ua', icon: '🎭', label: 'UA 模板', desc: '仅保留内置的两个模板' },
                { key: 'announcements', icon: '📢', label: '公告', desc: '所有公告全部清空' },
                { key: 'site', icon: '⚙️', label: '站点设置', desc: '所有标题、头像、接口、统计代码等恢复默认' }
            ];
            var modeBtns = $all('.type-btn[data-reset-mode]', modal);
            var partialGroup = $('#resetPartialGroup'), allHint = $('#resetAllHint'), fullHint = $('#resetFullHint'), moduleListWrap = $('#resetModuleList');
            var confirmLabel = $('#resetConfirmLabel'), confirmInput = $('#resetConfirmInput'), submitBtn = $('#resetDataSubmit'), closeBtn = $('#resetDataClose'), cancelBtn = $('#resetDataCancel');
            var currentMode = 'partial', selectedModules = {};
            function getConfirmWord() { return CONFIRM_WORDS[currentMode] || 'RESET'; }
            function switchMode(mode) {
                currentMode = mode;
                modeBtns.forEach(function (b) { b.classList.toggle('is-active', b.dataset.resetMode === mode); });
                if (partialGroup) partialGroup.classList.toggle('hidden', mode !== 'partial');
                if (allHint) allHint.classList.toggle('hidden', mode !== 'all');
                if (fullHint) fullHint.classList.toggle('hidden', mode !== 'full');
                var word = getConfirmWord();
                if (confirmLabel) confirmLabel.innerHTML = '二次确认：请输入 <code>' + word + '</code>';
                if (confirmInput) { confirmInput.value = ''; confirmInput.placeholder = '请输入 ' + word; }
                refreshSubmitState();
            }
            function refreshSubmitState() {
                if (!submitBtn || !confirmInput) return;
                var word = getConfirmWord(), resetOk = confirmInput.value.trim() === word, moduleOk = true;
                if (currentMode === 'partial') moduleOk = Object.keys(selectedModules).length > 0;
                submitBtn.disabled = !(resetOk && moduleOk);
            }
            function buildModuleList() {
                if (!moduleListWrap) return;
                moduleListWrap.innerHTML = ''; selectedModules = {};
                MODULES.forEach(function (m) {
                    var item = document.createElement('div'); item.className = 'reset-module-item'; item.dataset.key = m.key;
                    var iconEl = document.createElement('span'); iconEl.className = 'reset-module-icon'; iconEl.textContent = m.icon;
                    var infoEl = document.createElement('div'); infoEl.className = 'reset-module-info';
                    var labelEl = document.createElement('div'); labelEl.className = 'reset-module-label'; labelEl.textContent = m.label;
                    var descEl = document.createElement('div'); descEl.className = 'reset-module-desc'; descEl.textContent = m.desc;
                    infoEl.appendChild(labelEl); infoEl.appendChild(descEl);
                    var checkEl = document.createElement('span'); checkEl.className = 'reset-module-check'; checkEl.textContent = '✓';
                    item.appendChild(iconEl); item.appendChild(infoEl); item.appendChild(checkEl);
                    item.addEventListener('click', function () {
                        var k = item.dataset.key;
                        if (selectedModules[k]) { delete selectedModules[k]; item.classList.remove('active'); }
                        else { selectedModules[k] = true; item.classList.add('active'); }
                        refreshSubmitState();
                    });
                    moduleListWrap.appendChild(item);
                });
            }
            function openResetModal() { buildModuleList(); switchMode('partial'); if (confirmInput) confirmInput.value = ''; refreshSubmitState(); showModal(modal); setTimeout(function () { if (confirmInput) confirmInput.focus(); }, 150); }
            function closeResetModal() { hideModal(modal); }
            resetBtn.addEventListener('click', openResetModal);
            if (closeBtn) closeBtn.addEventListener('click', closeResetModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeResetModal);
            modal.addEventListener('click', function (e) { if (e.target === modal) closeResetModal(); });
            modeBtns.forEach(function (b) { b.addEventListener('click', function () { switchMode(b.dataset.resetMode); }); });
            if (confirmInput) confirmInput.addEventListener('input', refreshSubmitState);
            var form = $('#resetDataForm');
            if (form) {
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    var word = getConfirmWord();
                    if (confirmInput.value.trim() !== word) { toast('请输入 ' + word + ' 以确认', 'error'); return; }
                    if (currentMode === 'partial' && Object.keys(selectedModules).length === 0) { toast('请至少选择一个要清除的内容', 'error'); return; }
                    var btn = submitBtn, oldText = btn.textContent;
                    btn.disabled = true; btn.textContent = '处理中…';
                    try {
                        var fd = new FormData(); fd.append('mode', currentMode);
                        if (currentMode === 'partial') fd.append('modules', JSON.stringify(Object.keys(selectedModules)));
                        var data = await api('reset_data', fd);
                        if (!data.ok) { toast(data.msg, 'error'); return; }
                        closeResetModal();
                        smoothReload('数据已清除，正在重新加载系统...');
                    } catch (err) { toast(err.message, 'error'); } finally { btn.disabled = false; btn.textContent = oldText; refreshSubmitState(); }
                });
            }
        })();

        /* ------------------------------------------------------------
           3.7.12 后台首页版本号胶囊自检
           ------------------------------------------------------------ */
        (function () {
            var btn = document.getElementById('headerVersionBtn');
            var textEl = document.getElementById('headerVerText');
            if (!btn || !textEl || !window.NuomiUpdater) return;

            var baseVerText = textEl.textContent; // 例如 "v1.1.3 · Beta"
            var timers = [];
            var hasCache = false;

            // 核心优化：文字淡出 -> 换字 -> 淡入。让宽度在透明期自然平滑伸缩
            function animateText(newText) {
                textEl.classList.add('fade-out');
                timers.push(setTimeout(function () {
                    textEl.textContent = newText;
                    textEl.classList.remove('fade-out');
                }, 250)); // 250ms 对应 CSS 里的 opacity 过渡时间
            }

            try { hasCache = sessionStorage.getItem('nuomi_has_update') === '1'; } catch(e) {}

            if (hasCache) {
                btn.classList.add('has-update');
            } else {
                // 1.5 秒后开始检查
                timers.push(setTimeout(function () {
                    btn.classList.add('is-checking');
                    
                    window.NuomiUpdater.check().then(function (data) {
                        btn.classList.remove('is-checking');
                        
                        if (!data || !data.ok) {
                            // 检查失败
                            btn.classList.add('is-error');
                            animateText('检查更新失败');
                            
                            timers.push(setTimeout(function () {
                                animateText(baseVerText);
                                timers.push(setTimeout(function () {
                                    btn.classList.remove('is-error');
                                }, 500)); // 文字变回后再停留 0.5s 才褪色
                            }, 2500));
                            return;
                        }

                        if (data.has_update) {
                            // 发现新版本
                            try { sessionStorage.setItem('nuomi_has_update', '1'); } catch(e) {}
                            btn.classList.add('has-update');
                            
                            var latest = data.latest_version || '';
                            animateText('发现新版本');
                            
                            timers.push(setTimeout(function () {
                                animateText('最新 v' + latest);
                                
                                timers.push(setTimeout(function () {
                                    animateText(baseVerText);
                                    // 文字变回后，橙色底色和呼吸光晕永久保留
                                }, 2500));
                            }, 2500));

                        } else {
                            // 已是最新
                            btn.classList.add('is-latest');
                            animateText('已是最新版本');
                            
                            timers.push(setTimeout(function () {
                                animateText(baseVerText);
                                timers.push(setTimeout(function () {
                                    btn.classList.remove('is-latest');
                                }, 500)); // 文字变回后再停留 0.5s 才褪色
                            }, 2500));
                        }
                    }).catch(function () {
                        btn.classList.remove('is-checking');
                        btn.classList.add('is-error');
                        animateText('检查更新失败');
                        
                        timers.push(setTimeout(function () {
                            animateText(baseVerText);
                            timers.push(setTimeout(function () {
                                btn.classList.remove('is-error');
                            }, 500));
                        }, 2500));
                    });
                }, 1500));
            }
        })();

    }

    /* ==========================================================================
       4. 入口调度
       ========================================================================== */
    function onDOMReady() {
        initPreloader();
        main();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onDOMReady);
    } else {
        onDOMReady();
    }
})();