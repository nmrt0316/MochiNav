// 点击烟花特效
(function() {
  'use strict';

  // ==========================================
  // 1. 常量配置 (方便统一调整手感)
  // ==========================================
  const LONG_PRESS_MS = 500;   // 长按触发时间(毫秒)
  const MOVE_TOLERANCE = 10;   // 触摸滑动容差(超过此距离视为滑动页面)
  const COLOURS = ["#F73859", "#14FFEC", "#00E0FF", "#FF99FE", "#FAF15D"];

  // ==========================================
  // 2. 私有变量
  // ==========================================
  let canvas = null;
  let ctx = null;
  let pointer = null;
  let balls = [];
  let animationId = null;
  let resizeHandler = null;
  let longPressTimer = null;
  
  let isRunning = false;
  let longPressed = false;
  let multiplier = 0;
  let width = 0;
  let height = 0;
  let origin = { x: 0, y: 0 };
  let normal = { x: 0, y: 0 };

  let isTouching = false;
  let isTouchMoving = false;
  let touchStartTime = 0;
  let touchStartX = 0;
  let touchStartY = 0;
  let hasExploded = false;

  // ---- 工具函数 ----
  function randBetween(min, max) {
    return Math.floor(Math.random() * max) + min;
  }

  // ---- 尺寸更新 ----
  function updateSize() {
    if (!canvas) return;
    const w = window.innerWidth;
    const h = window.innerHeight;
    canvas.width = w * 2;
    canvas.height = h * 2;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    ctx.scale(2, 2);
    width = w;
    height = h;
    origin = { x: w / 2, y: h / 2 };
    normal = { x: w / 2, y: h / 2 };
  }

  // ---- Ball 类 ----
  function Ball(x, y, isLong) {
    this.x = x;
    this.y = y;
    this.angle = Math.PI * 2 * Math.random();
    if (isLong) {
      this.multiplier = randBetween(14 + multiplier, 15 + multiplier);
    } else {
      this.multiplier = randBetween(6, 12);
    }
    this.vx = (this.multiplier + Math.random() * 0.5) * Math.cos(this.angle);
    this.vy = (this.multiplier + Math.random() * 0.5) * Math.sin(this.angle);
    this.r = randBetween(8, 12) + 3 * Math.random();
    this.color = COLOURS[Math.floor(Math.random() * COLOURS.length)];
  }

  Ball.prototype.update = function() {
    this.x += this.vx - normal.x;
    this.y += this.vy - normal.y;
    normal.x = -2 / window.innerWidth * Math.sin(this.angle);
    normal.y = -2 / window.innerHeight * Math.cos(this.angle);
    this.r -= 0.3;
    this.vx *= 0.9;
    this.vy *= 0.9;
  };

  // ---- 生成烟花 ----
  function pushBalls(count, x, y, isLong) {
    for (let i = 0; i < count; i++) {
      balls.push(new Ball(x, y, isLong));
    }
  }

  // ---- 清除超出范围的球 ----
  function removeBall() {
    for (let i = 0; i < balls.length; i++) {
      const b = balls[i];
      if (b.x + b.r < 0 || b.x - b.r > width ||
          b.y + b.r < 0 || b.y - b.r > height || b.r < 0) {
        balls.splice(i, 1);
        i--;
      }
    }
  }

  // ---- 动画循环 ----
  function loop() {
    if (!isRunning) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (let i = 0; i < balls.length; i++) {
      const b = balls[i];
      if (b.r < 0) continue;
      ctx.fillStyle = b.color;
      ctx.beginPath();
      ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2, false);
      ctx.fill();
      b.update();
    }

    if (longPressed) {
      multiplier += 0.2;
    } else if (!longPressed && multiplier >= 0) {
      multiplier -= 0.4;
    }

    removeBall();
    animationId = requestAnimationFrame(loop);
  }

  // ---- 电脑端事件 (鼠标) ----
  function onMouseDown(e) {
    if (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) return;

    pushBalls(randBetween(10, 20), e.clientX, e.clientY, false);
    document.body.classList.add("is-pressed");
    
    clearTimeout(longPressTimer);
    longPressTimer = setTimeout(function() {
      document.body.classList.add("is-longpress");
      longPressed = true;
    }, LONG_PRESS_MS);
  }

  function onMouseUp(e) {
    if (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) return;

    clearTimeout(longPressTimer);
    if (longPressed) {
      document.body.classList.remove("is-longpress");
      pushBalls(
        randBetween(50 + Math.ceil(multiplier), 100 + Math.ceil(multiplier)),
        e.clientX, e.clientY, true
      );
      longPressed = false;
    }
    document.body.classList.remove("is-pressed");
  }

  function onMouseMove(e) {
    if (!pointer) return;
    pointer.style.top = e.clientY + "px";
    pointer.style.left = e.clientX + "px";
  }

  // ---- 手机端事件 (触摸) ----
  function onTouchStart(e) {
    if (isTouching) return;
    isTouching = true;
    hasExploded = false;
    isTouchMoving = false;
    
    const t = e.touches[0];
    touchStartTime = performance.now();
    touchStartX = t.clientX;
    touchStartY = t.clientY;

    pushBalls(randBetween(10, 20), t.clientX, t.clientY, false);
    document.body.classList.add("is-pressed");
    
    clearTimeout(longPressTimer);
    longPressTimer = setTimeout(function() {
      document.body.classList.add("is-longpress");
      longPressed = true;
    }, LONG_PRESS_MS);
  }

  function onTouchMove(e) {
    if (!isTouching) return;
    const t = e.touches[0];
    const dx = t.clientX - touchStartX;
    const dy = t.clientY - touchStartY;
    
    if (Math.sqrt(dx * dx + dy * dy) > MOVE_TOLERANCE) {
      isTouchMoving = true;
      clearTimeout(longPressTimer);
      longPressed = false;
      document.body.classList.remove("is-pressed");
      document.body.classList.remove("is-longpress");
    }
  }

  function onTouchEnd(e) {
    if (!isTouching) return;
    isTouching = false;
    clearTimeout(longPressTimer);

    if (!isTouchMoving && !hasExploded) {
      const t = e.changedTouches[0];
      const elapsed = performance.now() - touchStartTime;
      
      if (elapsed >= LONG_PRESS_MS) {
        pushBalls(
          randBetween(50 + Math.ceil(multiplier), 100 + Math.ceil(multiplier)),
          t.clientX, t.clientY, true
        );
        hasExploded = true;
      }
    }
    
    longPressed = false;
    document.body.classList.remove("is-pressed");
    document.body.classList.remove("is-longpress");
  }

  function onTouchCancel(e) {
    if (!isTouching) return;
    isTouching = false;
    clearTimeout(longPressTimer);
    
    const elapsed = performance.now() - touchStartTime;
    if (!isTouchMoving && !hasExploded && elapsed >= LONG_PRESS_MS) {
      pushBalls(
        randBetween(50 + Math.ceil(multiplier), 100 + Math.ceil(multiplier)),
        touchStartX, touchStartY, true
      );
      hasExploded = true;
    }

    longPressed = false;
    document.body.classList.remove("is-pressed");
    document.body.classList.remove("is-longpress");
  }

  function onContextMenu(e) {
    e.preventDefault();
    return false;
  }

  // ---- 公共接口 ----
  const Firework = {
    start: function() {
      if (isRunning) return;

      canvas = document.createElement("canvas");
      canvas.style.cssText = 'width:100%; height:100%; top:0; left:0; z-index:9999; position:fixed; pointer-events:none; transform:translateZ(0); will-change:transform;';
      document.body.appendChild(canvas);
      ctx = canvas.getContext("2d");

      pointer = document.createElement("span");
      pointer.classList.add("pointer");
      pointer.style.cssText = 'position:fixed; pointer-events:none; z-index:9999;';
      document.body.appendChild(pointer);

      updateSize();
      resizeHandler = updateSize;
      window.addEventListener('resize', resizeHandler);

      window.addEventListener("mousedown", onMouseDown);
      window.addEventListener("mouseup", onMouseUp);
      window.addEventListener("mousemove", onMouseMove);

      window.addEventListener("touchstart", onTouchStart, { passive: true });
      window.addEventListener("touchmove", onTouchMove, { passive: true });
      window.addEventListener("touchend", onTouchEnd);
      window.addEventListener("touchcancel", onTouchCancel);
      window.addEventListener("contextmenu", onContextMenu);

      isRunning = true;
      loop();
    },

    stop: function() {
      if (!isRunning) return;

      if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
      }

      window.removeEventListener("mousedown", onMouseDown);
      window.removeEventListener("mouseup", onMouseUp);
      window.removeEventListener("mousemove", onMouseMove);
      
      window.removeEventListener("touchstart", onTouchStart);
      window.removeEventListener("touchmove", onTouchMove);
      window.removeEventListener("touchend", onTouchEnd);
      window.removeEventListener("touchcancel", onTouchCancel);
      window.removeEventListener("contextmenu", onContextMenu);

      if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler);
        resizeHandler = null;
      }

      if (longPressTimer) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
      }

      if (canvas) {
        canvas.remove();
        canvas = null;
        ctx = null;
      }
      if (pointer) {
        pointer.remove();
        pointer = null;
      }

      balls = [];
      longPressed = false;
      multiplier = 0;
      isRunning = false;
    },

    toggle: function() {
      isRunning ? this.stop() : this.start();
    }
  };

  // ---- 自动启动 ----
  Firework.start();

  // ---- 注册到全局控制器 ----
  if (!window.EffectsRegistry) {
    window.EffectsRegistry = [];
  }
  window.EffectsRegistry.push({ name: 'firework', module: Firework });
})();