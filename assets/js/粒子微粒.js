// 粒子微粒效果（自启停 + 淡入淡出 + EffectsRegistry 兼容）
(function () {
  'use strict';

  let canvas = null;
  let ctx = null;
  let particles = [];
  let resizeHandler = null;

  let width = 0, height = 0, dpr = 1;

  let rafId = null;
  let lastTs = 0;

  // ---- 淡入淡出 ----
  let globalAlpha = 0;
  let targetAlpha = 0;
  const FADE_SPEED = 2.5;   // 越大越快，2.5 ≈ 0.4 秒完成

  // ---- 状态 ----
  let manualStopped = false;
  let hidden = document.hidden;

  const PARTICLE_COUNT = 50;
  const COLOR = '99, 102, 241';

  const MOUSE_RADIUS = 100;
  const MOUSE_FORCE = 0.6;
  const CLICK_RADIUS = 150;
  const CLICK_FORCE = 6;

  const mouse = { x: -9999, y: -9999, active: false };

  // ---- 内部函数 ----

  function resize() {
    if (!canvas) return;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function createParticle() {
    const vx = (Math.random() - 0.5) * 0.35;
    const vy = (Math.random() - 0.5) * 0.35;
    return {
      x: Math.random() * width,
      y: Math.random() * height,
      vx: vx,
      vy: vy,
      baseVx: vx,
      baseVy: vy,
      size: Math.random() * 2 + 0.5,
      opacity: Math.random() * 0.45 + 0.15
    };
  }

  function initParticles() {
    particles = [];
    for (let i = 0; i < PARTICLE_COUNT; i++) {
      particles.push(createParticle());
    }
  }

  function step() {
    for (let i = 0; i < particles.length; i++) {
      const p = particles[i];

      if (mouse.active) {
        const dx = p.x - mouse.x;
        const dy = p.y - mouse.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < MOUSE_RADIUS && dist > 0.5) {
          const force = (1 - dist / MOUSE_RADIUS) * MOUSE_FORCE;
          p.vx += (dx / dist) * force * 0.15;
          p.vy += (dy / dist) * force * 0.15;
        }
      }

      p.vx += (p.baseVx - p.vx) * 0.025;
      p.vy += (p.baseVy - p.vy) * 0.025;

      p.x += p.vx;
      p.y += p.vy;

      if (p.x < 0 || p.x > width) {
        p.vx *= -1;
        p.baseVx *= -1;
        p.x = Math.max(0, Math.min(width, p.x));
      }
      if (p.y < 0 || p.y > height) {
        p.vy *= -1;
        p.baseVy *= -1;
        p.y = Math.max(0, Math.min(height, p.y));
      }

      ctx.beginPath();
      ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(' + COLOR + ', ' + p.opacity + ')';
      ctx.fill();
    }
  }

  function loop(ts) {
    if (rafId === null) return;

    if (!lastTs) lastTs = ts;
    let dt = ts - lastTs;
    lastTs = ts;
    if (dt > 100) dt = 100;

    // ---- 透明度平滑过渡 ----
    const delta = (FADE_SPEED * dt) / 1000;
    if (globalAlpha < targetAlpha) {
      globalAlpha = Math.min(targetAlpha, globalAlpha + delta);
    } else if (globalAlpha > targetAlpha) {
      globalAlpha = Math.max(targetAlpha, globalAlpha - delta);
    }

    ctx.clearRect(0, 0, width, height);

    if (globalAlpha > 0) {
      ctx.globalAlpha = globalAlpha;
      step();
      ctx.globalAlpha = 1;
    }

    // 完全透明且目标也是隐藏 → 停 rAF 省电
    if (targetAlpha === 0 && globalAlpha === 0) {
      cancelAnimationFrame(rafId);
      rafId = null;
      lastTs = 0;
      return;
    }

    rafId = requestAnimationFrame(loop);
  }

  // 启停收敛：只设置 targetAlpha，rAF 由 loop 自己管生命周期
  function sync() {
    const should = !manualStopped && !hidden;
    targetAlpha = should ? 1 : 0;

    if (rafId === null && globalAlpha !== targetAlpha) {
      lastTs = 0;
      rafId = requestAnimationFrame(loop);
    }
  }

  // ---- 事件处理 ----

  function onResize() {
    resize();
  }

  function onMouseMove(e) {
    mouse.x = e.clientX;
    mouse.y = e.clientY;
    mouse.active = true;
  }

  function onMouseLeave() {
    mouse.active = false;
    mouse.x = -9999;
    mouse.y = -9999;
  }

  function onPointerDown(e) {
    const cx = e.clientX;
    const cy = e.clientY;
    for (let i = 0; i < particles.length; i++) {
      const p = particles[i];
      const dx = p.x - cx;
      const dy = p.y - cy;
      const dist = Math.sqrt(dx * dx + dy * dy);
      if (dist < CLICK_RADIUS && dist > 1) {
        const force = (1 - dist / CLICK_RADIUS) * CLICK_FORCE;
        p.vx += (dx / dist) * force;
        p.vy += (dy / dist) * force;
      }
    }
  }

  function onVisibilityChange() {
    hidden = document.hidden;
    sync();
  }

  // ---- 公共接口 ----

  const ParticleDots = {
    start: function () {
      if (!canvas) {
        canvas = document.createElement('canvas');
        canvas.id = 'bksv-particle-canvas';
        canvas.style.cssText =
          'position:fixed;top:0;left:0;width:100vw;height:100vh;' +
          'pointer-events:none;z-index:0;display:block;' +
          'transform:translateZ(0);will-change:transform;';
        document.body.appendChild(canvas);

        ctx = canvas.getContext('2d');
        if (!ctx) return;

        resizeHandler = onResize;
        window.addEventListener('resize', resizeHandler, { passive: true });
        window.addEventListener('pointermove', onMouseMove, { passive: true });
        window.addEventListener('pointerdown', onPointerDown, { passive: true });
        window.addEventListener('pointerleave', onMouseLeave);
        document.addEventListener('pointerleave', onMouseLeave);
        document.addEventListener('visibilitychange', onVisibilityChange);

        resize();
        initParticles();
      }

      manualStopped = false;
      sync();
    },

    stop: function () {
      manualStopped = true;
      sync();
    },

    toggle: function () {
      this.isRunning ? this.stop() : this.start();
    },

    get isRunning() {
      return targetAlpha === 1;
    }
  };

  ParticleDots.start();

  if (!window.EffectsRegistry) {
    window.EffectsRegistry = [];
  }
  window.EffectsRegistry.push({ name: 'particledots', module: ParticleDots });
})();