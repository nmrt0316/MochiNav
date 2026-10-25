<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404-Error</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=ZCOOL+KuaiLe&display=swap" rel="stylesheet">
  <style>
    /* ==========================================================================
       1. 主题变量
       ========================================================================== */
    :root, [data-theme="light"] {
      --bg: linear-gradient(135deg, #fefefe 0%, #fef7f7 40%, #fff5f7 100%);
      --card-bg: rgba(255,255,255,0.65);
      --card-border: rgba(255,183,197,0.5);
      --card-shadow: 0 8px 48px rgba(255,107,130,0.12), inset 0 1px 0 rgba(255,255,255,0.9);
      --num-grad: linear-gradient(135deg, #ff6b82 0%, #ff9aae 30%, #ffb7c5 60%, #ffc8d4 100%);
      --num-glow-text: 0 0 20px rgba(255,107,130,0.6), 0 0 40px rgba(255,107,130,0.4), 0 0 60px rgba(255,107,130,0.2);
      --title-color: #475569;
      --sub-color: #94a3b8;
      --line-color: #f9a8d4;
      --dot-color: #f472b6;
      --glow-circle: rgba(255,183,197,0.3);
      --btn-primary: linear-gradient(135deg, #ff6b82 0%, #ff9aae 50%, #ffb7c5 100%);
      --btn-primary-glow: 0 8px 32px rgba(255,107,130,0.4);
      --btn-ghost-bg: rgba(255,255,255,0.95);
      --btn-ghost-border: rgba(255,154,174,0.4);
      --btn-ghost-color: #e88fa0;
      --footer-color: rgba(249,168,212,0.7);
      --focus-color: rgba(255,107,130,0.7);
    }
    [data-theme="dark"] {
      --bg: linear-gradient(135deg, #0f0c29 0%, #1a1053 40%, #12082b 70%, #0a0a1a 100%);
      --card-bg: rgba(10,6,35,0.75);
      --card-border: rgba(168,85,247,0.25);
      --card-shadow: 0 8px 48px rgba(139,92,246,0.25), inset 0 1px 0 rgba(168,85,247,0.15);
      --num-grad: linear-gradient(135deg, #a855f7 0%, #c084fc 40%, #e879f9 70%, #c084fc 100%);
      --num-glow-text: 0 0 20px rgba(168,85,247,0.8), 0 0 40px rgba(168,85,247,0.5), 0 0 80px rgba(168,85,247,0.3);
      --title-color: #e2d9f3;
      --sub-color: rgba(196,181,253,0.7);
      --line-color: rgba(168,85,247,0.5);
      --dot-color: #a855f7;
      --glow-circle: rgba(139,92,246,0.25);
      --btn-primary: linear-gradient(135deg, #7c3aed 0%, #a855f7 50%, #c084fc 100%);
      --btn-primary-glow: 0 8px 32px rgba(139,92,246,0.5);
      --btn-ghost-bg: rgba(255,255,255,0.06);
      --btn-ghost-border: rgba(168,85,247,0.35);
      --btn-ghost-color: #c084fc;
      --footer-color: rgba(196,181,253,0.5);
      --focus-color: rgba(168,85,247,0.8);
    }
    [data-theme="light"] .dark-only { display: none !important; }
    [data-theme="dark"]  .light-only { display: none !important; }

    /* ==========================================================================
       2. 全局重置
       ========================================================================== */
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      min-height: 100vh;
      background: var(--bg);
      display: flex; align-items: center; justify-content: center;
      font-family: 'ZCOOL KuaiLe', 'Comic Sans MS', 'PingFang SC', cursive;
      overflow: hidden; position: relative;
    }

    /* ==========================================================================
       3. 动画
       ========================================================================== */
    @keyframes twinkle-star { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.3; transform:scale(.6); } }
    @keyframes float        { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-18px); } }
    @keyframes float2       { 0%,100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-10px) rotate(-5deg); } }
    @keyframes twinkle      { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.5; transform:scale(.8); } }
    @keyframes wiggle       { 0%,100% { transform: rotate(-3deg); } 50% { transform: rotate(3deg); } }
    @keyframes pulse        { 0%,100% { transform: scale(1); } 50% { transform: scale(1.1); } }
    @keyframes slideUp      { from { opacity:0; transform: translateY(30px); } to { opacity:1; transform: translateY(0); } }

    /* ==========================================================================
       4. 星空与漂浮装饰
       ========================================================================== */
    #stars-bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .star { position: absolute; border-radius: 50%; background: #fff; }
    .float-item { position: fixed; pointer-events: none; z-index: 2; }
    .animate-float   { animation: float 3s ease-in-out infinite; }
    .animate-float2  { animation: float2 4s ease-in-out infinite; }
    .animate-twinkle { animation: twinkle 2s ease-in-out infinite; }
    .animate-wiggle  { animation: wiggle .5s ease-in-out infinite; }

    /* ==========================================================================
       5. 中央卡片
       ========================================================================== */
    .content {
      position: relative; z-index: 10; text-align: center;
      max-width: 540px; width: 92%;
      animation: slideUp .6s ease-out forwards;
      background: var(--card-bg);
      backdrop-filter: blur(24px) saturate(180%);
      -webkit-backdrop-filter: blur(24px) saturate(180%);
      border: 1px solid var(--card-border);
      box-shadow: var(--card-shadow);
      border-radius: 28px;
      padding: 40px 36px;
    }
    .deco-line { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 16px; }
    .deco-line-bar { height: 2px; width: 48px; border-radius: 2px; }

    .code-wrap { position: relative; display: inline-block; margin-bottom: 24px; }
    .code-glow { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
    .code-glow-circle { width: 256px; height: 256px; border-radius: 50%; background: var(--glow-circle); filter: blur(48px); animation: pulse 2s ease-in-out infinite; }
    .code-main {
      font-size: clamp(90px, 18vw, 150px); font-weight: 900; line-height: 1;
      user-select: none;
      background: var(--num-grad);
      -webkit-background-clip: text; background-clip: text;
      -webkit-text-fill-color: transparent;
      text-shadow: var(--num-glow-text);
      position: relative;
    }
    .code-deco { position: absolute; }

    .card-title { font-size: clamp(1.5rem, 5vw, 2rem); font-weight: 800; color: var(--title-color); margin-bottom: 12px; letter-spacing: .05em; }
    .card-sub { font-size: .875rem; color: var(--sub-color); margin-bottom: 32px; line-height: 1.7; }
    .emoji-row { display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 24px; font-size: 1.5rem; }

    .btn-group { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 32px; }
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 12px 32px; border-radius: 16px;
      font-size: .875rem; font-weight: 700;
      font-family: 'ZCOOL KuaiLe', 'PingFang SC', cursive;
      text-decoration: none; cursor: pointer; border: none; outline: none;
      -webkit-tap-highlight-color: transparent;
      transition: transform .15s ease, box-shadow .3s ease, filter .3s ease, border-color .3s ease;
    }
    .btn:active { transform: scale(.94); }
    .btn:hover, .btn:focus-visible { box-shadow: 0 0 20px var(--focus-color); filter: brightness(1.06); }
    .btn-primary { background: var(--btn-primary); color: #fff; box-shadow: var(--btn-primary-glow); }
    .btn-primary:hover, .btn-primary:focus-visible { box-shadow: var(--btn-primary-glow), 0 0 20px var(--focus-color); }
    .btn-ghost { background: var(--btn-ghost-bg); color: var(--btn-ghost-color); border: 2px solid var(--btn-ghost-border) !important; }
    .btn-ghost:hover, .btn-ghost:focus-visible { border-color: var(--dot-color); }

    .tip { font-size: .78rem; color: var(--sub-color); margin-bottom: 16px; opacity: .75; }
    .footer-line {
      position: fixed; bottom: 32px; left: 50%; transform: translateX(-50%);
      display: flex; align-items: center; gap: 8px;
      font-size: .75rem; color: var(--footer-color);
      font-family: 'ZCOOL KuaiLe', 'PingFang SC', cursive;
      white-space: nowrap;
    }
  </style>
</head>
<body>

  <!-- 主题初始化（防闪烁，必须最早执行） -->
  <script>
    (function () {
      var t = localStorage.getItem('theme');
      var effective = (!t || t === 'auto')
        ? ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light')
        : (t === 'dark' ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', effective);
    })();
  </script>

  <!-- 星空背景 -->
  <div id="stars-bg">
    <div class="star" style="left:1.2%;top:42.5%;width:1.7px;height:1.7px;animation:twinkle-star 3.2s 3.285631069909213s ease-in-out infinite"></div>
    <div class="star" style="left:72.9%;top:13.1%;width:2.7px;height:2.7px;animation:twinkle-star 2.6s 1.0445469566187375s ease-in-out infinite"></div>
    <div class="star" style="left:9.2%;top:88.4%;width:1.3px;height:1.3px;animation:twinkle-star 2.3s 1.7763804218838102s ease-in-out infinite"></div>
    <div class="star" style="left:96.2%;top:95.3%;width:0.8px;height:0.8px;animation:twinkle-star 1.8s 3.1504002105928457s ease-in-out infinite"></div>
    <div class="star" style="left:65.0%;top:97.8%;width:2.3px;height:2.3px;animation:twinkle-star 2.6s 0.3268744204107117s ease-in-out infinite"></div>
    <div class="star" style="left:2.2%;top:38.9%;width:1.6px;height:1.6px;animation:twinkle-star 3.5s 0.5145240742974533s ease-in-out infinite"></div>
    <div class="star" style="left:73.4%;top:18.1%;width:1.2px;height:1.2px;animation:twinkle-star 2.8s 0.25850937229099813s ease-in-out infinite"></div>
    <div class="star" style="left:87.2%;top:59.9%;width:2.3px;height:2.3px;animation:twinkle-star 3.0s 2.1695244988765703s ease-in-out infinite"></div>
    <div class="star" style="left:76.2%;top:34.5%;width:2.5px;height:2.5px;animation:twinkle-star 2.4s 3.1605402817476156s ease-in-out infinite"></div>
    <div class="star" style="left:24.3%;top:67.4%;width:2.9px;height:2.9px;animation:twinkle-star 1.6s 0.05250072299903774s ease-in-out infinite"></div>
    <div class="star" style="left:58.0%;top:18.3%;width:2.6px;height:2.6px;animation:twinkle-star 1.6s 1.149143071581257s ease-in-out infinite"></div>
    <div class="star" style="left:22.7%;top:82.0%;width:2.4px;height:2.4px;animation:twinkle-star 2.2s 2.6267412837236916s ease-in-out infinite"></div>
    <div class="star" style="left:1.1%;top:35.2%;width:1.2px;height:1.2px;animation:twinkle-star 2.2s 1.5303251737370251s ease-in-out infinite"></div>
    <div class="star" style="left:21.1%;top:75.7%;width:1.2px;height:1.2px;animation:twinkle-star 1.9s 1.5611762584757907s ease-in-out infinite"></div>
    <div class="star" style="left:44.1%;top:89.4%;width:0.8px;height:0.8px;animation:twinkle-star 2.6s 1.683835832083325s ease-in-out infinite"></div>
    <div class="star" style="left:17.2%;top:56.8%;width:1.0px;height:1.0px;animation:twinkle-star 3.1s 2.5977888577452566s ease-in-out infinite"></div>
    <div class="star" style="left:9.1%;top:85.9%;width:1.9px;height:1.9px;animation:twinkle-star 1.9s 3.334479571765918s ease-in-out infinite"></div>
    <div class="star" style="left:60.5%;top:36.0%;width:1.2px;height:1.2px;animation:twinkle-star 2.0s 2.227260396947907s ease-in-out infinite"></div>
    <div class="star" style="left:79.4%;top:20.3%;width:1.1px;height:1.1px;animation:twinkle-star 1.7s 1.1512061256890425s ease-in-out infinite"></div>
    <div class="star" style="left:76.7%;top:72.1%;width:2.0px;height:2.0px;animation:twinkle-star 2.7s 0.5641312788068884s ease-in-out infinite"></div>
    <div class="star" style="left:14.8%;top:56.8%;width:0.7px;height:0.7px;animation:twinkle-star 2.9s 2.574654211050417s ease-in-out infinite"></div>
    <div class="star" style="left:64.9%;top:67.8%;width:2.7px;height:2.7px;animation:twinkle-star 3.4s 3.841135271500752s ease-in-out infinite"></div>
    <div class="star" style="left:22.0%;top:40.4%;width:2.8px;height:2.8px;animation:twinkle-star 2.3s 1.7831606768411348s ease-in-out infinite"></div>
    <div class="star" style="left:44.9%;top:54.5%;width:0.8px;height:0.8px;animation:twinkle-star 2.0s 1.3892831562790358s ease-in-out infinite"></div>
    <div class="star" style="left:41.3%;top:10.4%;width:1.2px;height:1.2px;animation:twinkle-star 2.1s 1.0780463751193254s ease-in-out infinite"></div>
    <div class="star" style="left:7.0%;top:32.3%;width:0.6px;height:0.6px;animation:twinkle-star 2.8s 2.4710348880584236s ease-in-out infinite"></div>
    <div class="star" style="left:11.5%;top:32.4%;width:1.2px;height:1.2px;animation:twinkle-star 2.3s 2.160572815868785s ease-in-out infinite"></div>
    <div class="star" style="left:91.1%;top:3.5%;width:1.4px;height:1.4px;animation:twinkle-star 2.6s 0.291168918307644s ease-in-out infinite"></div>
    <div class="star" style="left:88.0%;top:32.2%;width:2.3px;height:2.3px;animation:twinkle-star 2.9s 2.866265473486809s ease-in-out infinite"></div>
    <div class="star" style="left:12.5%;top:39.6%;width:1.2px;height:1.2px;animation:twinkle-star 3.0s 0.5622262964175908s ease-in-out infinite"></div>
    <div class="star" style="left:47.0%;top:8.5%;width:0.8px;height:0.8px;animation:twinkle-star 2.7s 0.5140071339072554s ease-in-out infinite"></div>
    <div class="star" style="left:82.4%;top:43.4%;width:2.6px;height:2.6px;animation:twinkle-star 3.4s 0.3478530050225226s ease-in-out infinite"></div>
    <div class="star" style="left:61.6%;top:42.2%;width:2.9px;height:2.9px;animation:twinkle-star 2.1s 0.03865078398532873s ease-in-out infinite"></div>
    <div class="star" style="left:40.6%;top:54.9%;width:1.5px;height:1.5px;animation:twinkle-star 2.2s 2.629930642991482s ease-in-out infinite"></div>
    <div class="star" style="left:53.7%;top:56.6%;width:1.6px;height:1.6px;animation:twinkle-star 1.6s 0.02703237511089629s ease-in-out infinite"></div>
    <div class="star" style="left:67.3%;top:3.9%;width:2.9px;height:2.9px;animation:twinkle-star 2.2s 1.1930731254766282s ease-in-out infinite"></div>
    <div class="star" style="left:73.0%;top:25.2%;width:0.5px;height:0.5px;animation:twinkle-star 2.9s 3.6399381921692084s ease-in-out infinite"></div>
    <div class="star" style="left:9.5%;top:1.1%;width:1.2px;height:1.2px;animation:twinkle-star 1.6s 1.9476586495900499s ease-in-out infinite"></div>
    <div class="star" style="left:51.9%;top:65.8%;width:0.9px;height:0.9px;animation:twinkle-star 3.3s 3.5728670974231242s ease-in-out infinite"></div>
    <div class="star" style="left:58.2%;top:11.3%;width:1.4px;height:1.4px;animation:twinkle-star 2.2s 2.5442189589741036s ease-in-out infinite"></div>
    <div class="star" style="left:32.3%;top:80.6%;width:2.2px;height:2.2px;animation:twinkle-star 3.3s 2.5999478230554587s ease-in-out infinite"></div>
    <div class="star" style="left:99.1%;top:53.8%;width:0.7px;height:0.7px;animation:twinkle-star 1.9s 0.039359859078335724s ease-in-out infinite"></div>
    <div class="star" style="left:29.0%;top:81.0%;width:2.8px;height:2.8px;animation:twinkle-star 3.2s 0.10343462311000984s ease-in-out infinite"></div>
    <div class="star" style="left:57.8%;top:52.1%;width:2.3px;height:2.3px;animation:twinkle-star 2.9s 0.9098439416632891s ease-in-out infinite"></div>
    <div class="star" style="left:88.2%;top:8.6%;width:2.8px;height:2.8px;animation:twinkle-star 2.3s 1.9957859612290116s ease-in-out infinite"></div>
    <div class="star" style="left:21.0%;top:91.4%;width:1.3px;height:1.3px;animation:twinkle-star 2.1s 1.1745209393545757s ease-in-out infinite"></div>
    <div class="star" style="left:49.7%;top:73.1%;width:2.7px;height:2.7px;animation:twinkle-star 1.8s 1.9371238380213072s ease-in-out infinite"></div>
    <div class="star" style="left:34.9%;top:76.7%;width:1.1px;height:1.1px;animation:twinkle-star 2.0s 2.890718117012904s ease-in-out infinite"></div>
    <div class="star" style="left:38.1%;top:56.1%;width:1.7px;height:1.7px;animation:twinkle-star 2.5s 2.666255161897075s ease-in-out infinite"></div>
    <div class="star" style="left:98.4%;top:33.5%;width:1.2px;height:1.2px;animation:twinkle-star 2.5s 3.1354369336007295s ease-in-out infinite"></div>
    <div class="star" style="left:43.1%;top:61.0%;width:1.0px;height:1.0px;animation:twinkle-star 2.1s 0.4760330579829821s ease-in-out infinite"></div>
    <div class="star" style="left:15.6%;top:25.5%;width:0.8px;height:0.8px;animation:twinkle-star 2.3s 2.8768667474790934s ease-in-out infinite"></div>
    <div class="star" style="left:21.2%;top:84.7%;width:1.0px;height:1.0px;animation:twinkle-star 2.1s 2.873310739202605s ease-in-out infinite"></div>
    <div class="star" style="left:55.9%;top:71.4%;width:2.4px;height:2.4px;animation:twinkle-star 1.8s 3.5424163700201055s ease-in-out infinite"></div>
    <div class="star" style="left:16.6%;top:54.8%;width:2.7px;height:2.7px;animation:twinkle-star 3.4s 2.9622534890078436s ease-in-out infinite"></div>
    <div class="star" style="left:40.3%;top:57.8%;width:2.0px;height:2.0px;animation:twinkle-star 3.1s 3.0147403728964166s ease-in-out infinite"></div>
    <div class="star" style="left:45.3%;top:49.5%;width:2.1px;height:2.1px;animation:twinkle-star 2.0s 3.6021609431305874s ease-in-out infinite"></div>
    <div class="star" style="left:1.5%;top:5.0%;width:2.1px;height:2.1px;animation:twinkle-star 2.8s 1.7660507466745035s ease-in-out infinite"></div>
    <div class="star" style="left:68.3%;top:89.4%;width:2.5px;height:2.5px;animation:twinkle-star 3.3s 3.2009968238464053s ease-in-out infinite"></div>
    <div class="star" style="left:35.1%;top:14.0%;width:2.2px;height:2.2px;animation:twinkle-star 1.6s 1.8995065292460476s ease-in-out infinite"></div>
    <div class="star" style="left:38.1%;top:40.3%;width:2.5px;height:2.5px;animation:twinkle-star 3.0s 3.4134710126106036s ease-in-out infinite"></div>
    <div class="star" style="left:69.7%;top:2.8%;width:1.0px;height:1.0px;animation:twinkle-star 3.5s 0.9818754228930526s ease-in-out infinite"></div>
    <div class="star" style="left:99.0%;top:88.3%;width:2.2px;height:2.2px;animation:twinkle-star 2.0s 1.6357861205953448s ease-in-out infinite"></div>
    <div class="star" style="left:37.5%;top:94.3%;width:1.9px;height:1.9px;animation:twinkle-star 3.5s 3.950468306361162s ease-in-out infinite"></div>
    <div class="star" style="left:89.5%;top:65.7%;width:1.9px;height:1.9px;animation:twinkle-star 3.2s 0.9055733754800022s ease-in-out infinite"></div>
    <div class="star" style="left:79.6%;top:72.1%;width:1.8px;height:1.8px;animation:twinkle-star 2.4s 1.6869308182400777s ease-in-out infinite"></div>
    <div class="star" style="left:30.0%;top:8.7%;width:0.9px;height:0.9px;animation:twinkle-star 2.7s 0.02563915055802113s ease-in-out infinite"></div>
    <div class="star" style="left:88.7%;top:6.7%;width:2.6px;height:2.6px;animation:twinkle-star 2.7s 2.1417709634748556s ease-in-out infinite"></div>
    <div class="star" style="left:95.4%;top:57.1%;width:1.1px;height:1.1px;animation:twinkle-star 2.6s 1.8098172498782699s ease-in-out infinite"></div>
    <div class="star" style="left:43.4%;top:75.2%;width:2.9px;height:2.9px;animation:twinkle-star 2.6s 1.8889876871893154s ease-in-out infinite"></div>
    <div class="star" style="left:26.1%;top:50.2%;width:2.3px;height:2.3px;animation:twinkle-star 2.2s 3.7853115917276323s ease-in-out infinite"></div>
    <div class="star" style="left:25.5%;top:57.1%;width:2.0px;height:2.0px;animation:twinkle-star 2.6s 2.9822443623620227s ease-in-out infinite"></div>
    <div class="star" style="left:18.3%;top:79.7%;width:0.7px;height:0.7px;animation:twinkle-star 3.0s 1.1127732167970645s ease-in-out infinite"></div>
    <div class="star" style="left:92.6%;top:41.0%;width:1.9px;height:1.9px;animation:twinkle-star 1.9s 2.368111136847226s ease-in-out infinite"></div>
    <div class="star" style="left:95.0%;top:87.6%;width:1.9px;height:1.9px;animation:twinkle-star 3.1s 3.540464194276275s ease-in-out infinite"></div>
    <div class="star" style="left:43.6%;top:73.1%;width:2.9px;height:2.9px;animation:twinkle-star 1.8s 1.375966589546536s ease-in-out infinite"></div>
    <div class="star" style="left:57.1%;top:97.2%;width:1.1px;height:1.1px;animation:twinkle-star 2.1s 1.729698581431971s ease-in-out infinite"></div>
    <div class="star" style="left:41.6%;top:29.4%;width:1.0px;height:1.0px;animation:twinkle-star 3.4s 3.129714335595037s ease-in-out infinite"></div>
    <div class="star" style="left:76.3%;top:4.6%;width:0.8px;height:0.8px;animation:twinkle-star 2.6s 3.2048172279063785s ease-in-out infinite"></div>
    <div class="star" style="left:56.7%;top:21.4%;width:1.1px;height:1.1px;animation:twinkle-star 3.3s 0.1474091991398252s ease-in-out infinite"></div>
  </div>

  <!-- 漂浮装饰 -->
  <div class="float-item animate-float" style="top:96px;left:64px;animation-delay:0s">
    <svg class="light-only" width="32" height="32" viewBox="0 0 24 24" fill="#ff9aae"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    <svg class="dark-only" width="32" height="32" viewBox="0 0 24 24" fill="#c4b5fd"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  </div>
  <div class="float-item animate-float2 light-only" style="top:128px;right:80px;animation-delay:0.5s">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="#ff6b82"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
  </div>
  <div class="float-item animate-float2 dark-only" style="top:128px;right:80px;animation-delay:0.5s">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="#fde68a"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
  </div>
  <div class="float-item animate-twinkle" style="top:50%;left:48px;animation-delay:0.3s">
    <svg class="light-only" width="20" height="20" viewBox="0 0 24 24" fill="#ffb7c5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    <svg class="dark-only" width="20" height="20" viewBox="0 0 24 24" fill="#e879f9"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  </div>
  <div class="float-item animate-float" style="bottom:160px;right:64px;animation-delay:1s">
    <svg class="light-only" width="24" height="24" viewBox="0 0 24 24" fill="#ffc8d4"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
    <svg class="dark-only" width="28" height="28" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" fill="rgba(124,58,237,0.6)" stroke="#a855f7" stroke-width="1.5"/><ellipse cx="12" cy="12" rx="12" ry="5" fill="none" stroke="rgba(192,132,252,0.5)" stroke-width="1"/></svg>
  </div>
  <div class="float-item animate-float2" style="bottom:128px;left:80px;animation-delay:0.8s">
    <svg class="light-only" width="40" height="28" viewBox="0 0 40 28" fill="#fce7f3"><ellipse cx="12" cy="18" rx="12" ry="10" opacity="0.8"/><ellipse cx="28" cy="18" rx="12" ry="10" opacity="0.8"/><ellipse cx="20" cy="12" rx="14" ry="12" opacity="0.9"/></svg>
    <svg class="dark-only" width="40" height="28" viewBox="0 0 40 28" fill="rgba(139,92,246,0.25)"><ellipse cx="12" cy="18" rx="12" ry="10" opacity="0.8"/><ellipse cx="28" cy="18" rx="12" ry="10" opacity="0.8"/><ellipse cx="20" cy="12" rx="14" ry="12" opacity="0.9"/></svg>
  </div>
  <div class="float-item animate-float" style="top:64px;right:128px;animation-delay:1.2s">
    <svg class="light-only" width="36" height="26" viewBox="0 0 40 28" fill="#fce7f3"><ellipse cx="12" cy="18" rx="12" ry="10" opacity="0.7"/><ellipse cx="28" cy="18" rx="12" ry="10" opacity="0.7"/><ellipse cx="20" cy="12" rx="14" ry="12" opacity="0.8"/></svg>
    <svg class="dark-only" width="36" height="26" viewBox="0 0 40 28" fill="rgba(139,92,246,0.25)"><ellipse cx="12" cy="18" rx="12" ry="10" opacity="0.7"/><ellipse cx="28" cy="18" rx="12" ry="10" opacity="0.7"/><ellipse cx="20" cy="12" rx="14" ry="12" opacity="0.8"/></svg>
  </div>

  <!-- 中央 404 卡片 -->
  <div class="content">
    <div class="deco-line">
      <div class="deco-line-bar" style="background:linear-gradient(to right,transparent,var(--line-color))"></div>
      <span style="color:var(--dot-color);font-size:.875rem">• • •</span>
      <div class="deco-line-bar" style="background:linear-gradient(to left,transparent,var(--line-color))"></div>
    </div>

    <div class="code-wrap" style="animation:float 2s ease-in-out infinite">
      <div class="code-glow"><div class="code-glow-circle"></div></div>
      <div style="position:relative;display:inline-block">
        <span class="code-main">404</span>
        <div class="code-deco animate-wiggle" style="top:-8px;left:-16px">
          <svg class="light-only" width="20" height="20" viewBox="0 0 24 24" fill="#ff9aae"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
          <svg class="dark-only" width="20" height="20" viewBox="0 0 24 24" fill="#a855f7"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        </div>
        <div class="code-deco animate-wiggle" style="top:-4px;right:-24px;animation-delay:0.2s">
          <svg class="light-only" width="16" height="16" viewBox="0 0 24 24" fill="#ffb7c5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <svg class="dark-only" width="16" height="16" viewBox="0 0 24 24" fill="#e879f9"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
      </div>
    </div>

    <h1 class="card-title">哎呀，迷路了~</h1>
    <p class="card-sub">这个页面跑去玩耍啦<br>找不到回家的路</p>

    <div class="emoji-row light-only">
      <span class="animate-float" style="animation-delay:0s">🌸</span>
      <span class="animate-float" style="animation-delay:0.3s">✨</span>
      <span class="animate-float" style="animation-delay:0.6s">💫</span>
      <span class="animate-float" style="animation-delay:0.9s">🌷</span>
    </div>
    <div class="emoji-row dark-only">
      <span class="animate-float" style="animation-delay:0s">🌌</span>
      <span class="animate-float" style="animation-delay:0.3s">✨</span>
      <span class="animate-float" style="animation-delay:0.6s">🔮</span>
      <span class="animate-float" style="animation-delay:0.9s">🌙</span>
    </div>

    <div class="btn-group">
      <a href="./" class="btn btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        返回首页
      </a>
      <a href="announcement.php" class="btn btn-ghost">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
        查看公告
      </a>
    </div>

    <p class="tip">💡 目标页面可能已迁移或地址输入有误</p>

    <div class="deco-line" style="margin-bottom:0">
      <div class="deco-line-bar" style="background:linear-gradient(to right,transparent,var(--line-color))"></div>
      <span style="color:var(--dot-color);font-size:.875rem">• • •</span>
      <div class="deco-line-bar" style="background:linear-gradient(to left,transparent,var(--line-color))"></div>
    </div>
  </div>

  <div class="footer-line">
    <span>星辰科技</span><span>·</span><span>2026</span>
  </div>

  <!-- 主题同步 -->
  <script>
    (function () {
      function getEffectiveTheme() {
        var t = localStorage.getItem('theme');
        if (t === 'dark') return 'dark';
        if (t === 'light') return 'light';
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
      }

      function applyTheme() {
        document.documentElement.dataset.theme = getEffectiveTheme();
      }

      applyTheme();

      // 其它标签页切换主题时同步
      window.addEventListener('storage', function (e) {
        if (e.key === 'theme') applyTheme();
      });

      // 系统主题变化时（auto 模式）同步
      if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
          var t = localStorage.getItem('theme');
          if (!t || t === 'auto') applyTheme();
        });
      }
    })();
  </script>

  <?php include __DIR__ . '/analytics.php'; ?>
</body>
</html>