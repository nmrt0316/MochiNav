/* ==========================================================================
   糯米の引导页 - 在线升级公共模块 assets/updater.js
   封装 window.NuomiUpdater.check()，供后台首页版本胶囊与升级页共用
   ========================================================================== */
(function (global) {
    'use strict';

    function buildForm(action, body) {
        var fd = body instanceof FormData ? body : new FormData();
        if (!(body instanceof FormData)) {
            for (var k in body) {
                if (Object.prototype.hasOwnProperty.call(body, k)) {
                    fd.append(k, body[k]);
                }
            }
        }
        fd.append('action', action);
        return fd;
    }

    function post(action, body) {
        var fd = buildForm(action, body);
        return fetch('index.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (res) {
            return res.text().then(function (text) {
                var data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    throw new Error('服务器返回异常：' + res.status + ' ' + res.statusText);
                }
                if (res.status < 200 || res.status >= 300) {
                    throw new Error((data && data.msg) || ('HTTP ' + res.status));
                }
                return data;
            });
        });
    }

    function check(options) {
        options = options || {};
        var body = {};
        if (options.force) {
            body.force = 1;
        }
        return post('update_check', body);
    }

    global.NuomiUpdater = {
        check: check,
        post: post
    };
})(window);