// 天气 - 心知天气小部件
(function () {
  'use strict';

  var MATCH_KEY = 'weather';
  var widgetLoaded = false;
  var isRunning = false;

  // 移除容器与 bundle 渲染出的残留浮动节点
  function clearNodes() {
    var container = document.getElementById('tp-weather-widget');
    if (container && container.parentNode) container.parentNode.removeChild(container);

    var children = document.body.children;
    for (var i = children.length - 1; i >= 0; i--) {
      var node = children[i];
      if (!node || !node.className) continue;
      var cls = String(node.className);
      if (cls.indexOf('sw-') === 0 || cls.indexOf('seniverse') >= 0) {
        node.parentNode.removeChild(node);
      }
    }
  }

  function hideWidget() {
    try {
      if (typeof window.SeniverseWeatherWidget === 'function') {
        window.SeniverseWeatherWidget('hide', { container: 'tp-weather-widget' });
      }
    } catch (e) {}
  }

  // 注入样式（仅一次）
  function injectStyle() {
    if (document.getElementById('tp-weather-widget-style')) return;
    var style = document.createElement('style');
    style.id = 'tp-weather-widget-style';
    style.textContent =
      '#tp-weather-widget .sw-container { right: 10px !important; left: unset !important; }\n' +
      '#tp-weather-widget a[href*="seniverse"] { display: none !important; }';
    document.head.appendChild(style);
  }

  // 加载心知天气 bundle
  function loadBundle() {
    (function (a, h, g, f, e, d, c, b) {
      b = function () {
        if (d) return;
        d = h.createElement(g);
        c = h.getElementsByTagName(g)[0];
        d.src = e;
        d.charset = 'utf-8';
        d.async = 1;
        c.parentNode.insertBefore(d, c);
      };

      a['SeniverseWeatherWidgetObject'] = f;
      if (!a[f]) {
        a[f] = function () {
          (a[f].q = a[f].q || []).push(arguments);
        };
        a[f].l = +new Date();
      }

      if (h.readyState === 'complete') {
        b();
      } else if (a.attachEvent) {
        a.attachEvent('onload', b);
      } else {
        a.addEventListener('load', b, false);
        setTimeout(function () { if (!d) b(); }, 3000);
      }
    })(
      window,
      document,
      'script',
      'SeniverseWeatherWidget',
      '//cdn.sencdn.com/widget2/static/js/bundle.js?t=' +
        parseInt((new Date().getTime() / 100000000).toString(), 10)
    );
  }

  function start() {
    if (widgetLoaded && isRunning) return;

    hideWidget();
    clearNodes();
    injectStyle();

    widgetLoaded = true;

    var container = document.createElement('div');
    container.id = 'tp-weather-widget';
    container.style.cssText = 'position:fixed; z-index:9999; top:10px; right:10px;';
    document.body.appendChild(container);

    loadBundle();

    window.SeniverseWeatherWidget('show', {
      flavor: 'bubble',
      location: 'WX4FBXXFKE4F',
      geolocation: true,
      language: 'zh-Hans',
      unit: 'c',
      theme: 'auto',
      token: '26c4103c-986c-4ecc-a1f7-a52b5d18b1a6',
      hover: 'enabled',
      container: 'tp-weather-widget'
    });

    isRunning = true;
  }

  function stop() {
    hideWidget();
    clearNodes();
    widgetLoaded = false;
    isRunning = false;
  }

  // 读取已保存的开关状态，决定是否自动启动
  function shouldAutoStart() {
    try {
      var states = JSON.parse(localStorage.getItem('effects_states') || '{}');
      return states[MATCH_KEY] !== false;
    } catch (e) {
      return true;
    }
  }

  // 暴露接口
  window.initNuomiWeather = start;
  window.destroyNuomiWeather = stop;

  // 注册到 EffectsRegistry
  if (!window.EffectsRegistry) window.EffectsRegistry = [];
  window.EffectsRegistry.push({
    name: MATCH_KEY,
    module: {
      start: start,
      stop: stop,
      get isRunning() { return isRunning; }
    }
  });

  // 启动
  function boot() {
    if (shouldAutoStart()) start();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();