// 雪花飘落效果
(function() {
  'use strict';

  // ---- 私有变量 ----
  let canvas = null;
  let ctx = null;
  let snowList = [];
  let animationId = null;
  let resizeHandler = null;
  let isRunning = false;

  const SNOW_NUM = 70;

  // ---- 内部函数 ----
  function resize() {
    if (!canvas) return;
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  }

  function initSnow() {
    snowList = [];
    for (let i = 0; i < SNOW_NUM; i++) {
      snowList.push({
        x: Math.random() * canvas.width,
        y: Math.random() * -canvas.height,
        r: Math.random() * 2 + 1,
        speedY: Math.random() * 1.2 + 0.4,
        speedX: Math.random() * 0.8 - 0.4,
        opacity: Math.random() * 0.4 + 0.4
      });
    }
  }

  function draw() {
    if (!canvas || !ctx) return;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#b8e1ff';

    for (let s of snowList) {
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.globalAlpha = s.opacity;
      ctx.fill();

      s.y += s.speedY;
      s.x += s.speedX;

      if (s.y > canvas.height) {
        s.y = -10;
        s.x = Math.random() * canvas.width;
      }
    }
    ctx.globalAlpha = 1;

    animationId = requestAnimationFrame(draw);
  }

  // ---- 公共接口 ----
  const Snowfall = {
    start: function() {
      if (isRunning) return;
      if (!canvas) {
        // 创建canvas
        canvas = document.createElement('canvas');
        canvas.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; pointer-events:none; z-index:9999; transform:translateZ(0); will-change:transform;';
        document.body.appendChild(canvas);
        ctx = canvas.getContext('2d');

        resizeHandler = resize;
        window.addEventListener('resize', resizeHandler);
        resize();

        initSnow();
      }
      isRunning = true;
      draw();
    },

    stop: function() {
      if (!isRunning) return;
      if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
      }
      if (canvas) {
        canvas.remove();
        canvas = null;
        ctx = null;
      }
      if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler);
        resizeHandler = null;
      }
      snowList = [];
      isRunning = false;
    },

    toggle: function() {
      isRunning ? this.stop() : this.start();
    }
  };

  // ---- 自动启动 ----
  Snowfall.start();

  // ---- 注册到全局控制器 ----
  if (!window.EffectsRegistry) {
    window.EffectsRegistry = [];
  }
  window.EffectsRegistry.push({ name: 'snowfall', module: Snowfall });
})();