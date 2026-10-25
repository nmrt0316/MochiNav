# 糯米の引导页 · 个人导航站

![版本](https://img.shields.io/badge/version-1.1.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.3%2B-777bb4)
![License](https://img.shields.io/badge/License-MIT-green)

一个基于 PHP + JSON 的个人引导页/导航站，自带 PHP 后台管理面板。无需手动修改 HTML，即可维护分类、卡片、图片、JS 特效、看板娘、加载页主题、本地背景兜底、UA 伪装、公告、全站统计代码、站点显示配置，并支持**在线一键升级与回滚**。

---

## 🚀 快速开始

1. 下载源码，上传到 PHP 网站根目录
2. 访问 `https://你的域名/admin`，跟随 4 步安装向导完成设置（约 2 分钟）
3. 登录后台，在「站点设置」里改标题、头像
4. 之后想升级，点击后台顶部栏的「版本号（如 v1.1.0）」，即可进入在线升级页（需服务器支持在线覆盖代码）

> 无需数据库、无需 Composer、无需构建工具。支持 PHP 7.3+。

---

## 🔄 升级须知

**当前版本：1.1.0**（版本号定义在 `admin/defaults.php` 的 `NUOMI_VERSION` 常量，后台顶部栏会自动显示）

覆盖新版本代码前，**必须保留**以下文件（它们是您的私有数据）：

| 保留 | 说明 |
|---|---|
| `admin/data.json` | 卡片、分类、站点配置、公告等全部数据 |
| `admin/.admin.conf` | 管理员账号 |
| `admin/.update/` | 在线升级缓存、更新包、代码备份、升级状态 |
| `photos/` | 所有上传的图片（含头像、图标、看板娘、本地背景） |
| `assets/js/` | 上传的 JS 特效 |
| `assets/preloaders/` | 上传的加载页主题 |

其余文件（`*.php` / `*.css` / `*.js` / `*.md`）可直接覆盖。

### 方式一：在线升级（推荐）

点击后台顶部栏的「版本号（如 v1.1.0）」胶囊按钮 → 进入 `admin/update.php` 独立升级页：

1. **自动检查更新**：对接 GitHub Releases API，5 分钟本地缓存（避免触发 GitHub 60 次/小时速率限制），用 `version_compare` 比较版本号
2. **环境检测**：自动测试 PHP 运行用户能否在项目根目录创建 / 覆盖 / 删除文件
   - ✅ 支持 → 显示「⬆ 立即升级」按钮
   - ❌ 不支持（共享主机 / 文件所有者与 PHP 运行用户不一致）→ 显示「🔗 去 GitHub 下载」按钮，跳转 Releases 页面手动覆盖
3. **下载更新包**：从 Release 的 `assets` 数组里找第一个 `.zip`，自动 SHA256 校验（如果 Release 提供了 `digest`），解压到 `admin/.update/package/`
4. **应用升级**：
   - 先把当前代码备份到 `admin/.update/backup/<时间戳>/`
   - 逐个覆盖，**跳过保留清单里的所有路径**
   - 失败自动回滚
   - 成功后写入升级历史
5. **一键回滚**：随时用最近一次备份覆盖当前代码（`state.json` 转为 `rolled_back`）

**在线升级绝不动这些路径**（即使 Release 包里带了也不覆盖）：

- `admin/data.json`
- `admin/.admin.conf`
- `admin/.update/`
- `photos/`
- `assets/js/`
- `assets/preloaders/`
- `README.md`
- `LICENSE`
- `CHANGELOG.md`

**在线升级的其他保护措施**：

- **路径穿越防护**：拒绝 `..`、绝对路径、Windows 盘符
- **下载地址白名单**：只认 `github.com` / `raw.githubusercontent.com` / `objects.githubusercontent.com` / `codeload.github.com`
- **覆盖白名单**：仅允许覆盖 `admin/`、`assets/`、`index.php`、`404.php`、`announcement.php`、`analytics.php`
- **解压后总大小上限**：200MB（防 zip 炸弹）
- **并发锁**：`flock(LOCK_EX | LOCK_NB)`，防止两次升级同时进行
- **状态原子写入**：`state.json` / `history.json` 用 tmp + rename 原子写入
- **卡死自愈**：`applying` 状态超过 10 分钟自动转 `failed`

### 方式二：手动覆盖升级

如果服务器不支持在线升级（环境检测未通过），或您想完全手动控制：

1. 下载新版本压缩包（到项目 Releases 页面）
2. 解压后覆盖上传（**跳过上面「保留」清单中的文件**）
3. 访问 `/admin` 刷新，后台顶部栏会显示新的版本号

> **提示**：共享主机常见的「不支持在线升级」原因是 PHP 运行用户（如 `www-data`）与文件所有者（如 `nobody`）不一致。解决方案：
> - 让主机商把项目目录所有者改为 PHP 运行用户
> - 或给项目目录设置 `775` 权限并确保两者在同一用户组
> - 或不折腾，直接手动覆盖升级

---

## ✨ 功能特性

### 前台
- 🎨 **双图层动态背景**：背景图自动切换，毛刺淡出 + 弹性淡入动画，支持一键启停轮播
- 🖼️ **Banner 轮播**：顶部横幅自动切换，左右滑动 + 光效扫过；切换前先探测图片可解码，失败时保留旧图不闪白，从"无图"恢复到"有图"走 0.7s 淡入
- 💾 **本地图片缓存**：背景 / Banner 使用 IndexedDB 缓存图片（Blob），回访秒显 + 自动切换一次到上次预取的图，断网也能轮播，缓存节奏跟随后台设置的轮播间隔
- 🔄 **回访连贯性**：`lastShownKey` 只在**每次切换成功后**写入（含轮播切换、回访秒显、回访 1.5s 后切下一张），因此**回访时首屏显示的一定是"上次关闭页面时最后看到的那张图"**，即使是轮播过程中关闭的也一样，视觉完全连贯；回访续播时**优先复用上次预取的图**作为 1.5s 后的切换目标；**API 优先和本地优先共用同一套续播骨架**
- 🔍 **内容指纹去重**：API 池入池前计算图片内容指纹（头 32KB + 尾 32KB 双 FNV 采样），相同内容的图不会重复入池
- 🎯 **待播槽预取**：轮播切换前先在后台预取下一张，在线优先拉新图，失败才用缓存兜底，切换顺滑无等待；**后台补池节流不阻塞预取，预取 Promise 快速 resolve**；每次切换成功后立即触发下一次预取，**待播槽永远有货，不会空转**
- 🆘 **本地背景兜底**：外部 API 挂掉时可用本地图兜底，支持「API 优先 / 本地优先」自由切换；本地图按「桌面端 / 移动端 / 通用」自动分类，前台优先按屏幕方向取图，**方向不匹配或加载失败会自动交叉兜底，彻底杜绝黑屏**；**Banner 兜底则优先使用桌面端横向图，不受手机方向影响；当 Banner 本地图全部被手动标成 mobile 时也会退回使用全部图，绝不因过滤后为空而失效**。
- ✅ **解码验证**：所有取图路径（API / 池 / 本地）在入池、入缓存、切换前都验证解码，失败的图不入队列、不出屏
- 👤 **悬浮圆形头像**：头像半悬浮在 Banner 下方，默认 10 秒内点击 10 次进入后台（次数与时间窗口可在后台配置）
- 📱 **完整响应式**：PC / 移动端自适应，移动端顶部固定、底部贴边
- 🔖 **多分类标签页**：自定义分类名称与图标，支持 emoji 或图片
- 🃏 **卡片列表**：支持链接卡片、图片卡片，图片卡片点击弹窗展示大图，**弹窗大图支持长按保存**
- 🎨 **卡片悬停文字渐变**：桌面端鼠标悬停时，卡片名与副标题柔和过渡到蓝色（深浅色模式各自适配）
- 🔖 **分类菜单悬停光晕**：未激活菜单悬停时浮起并亮起蓝色背景与光晕，与激活态视觉一致；激活态未悬停时不额外浮起，高度与未激活对齐
- 🎯 **分类切换平滑过渡**：切换分类时卡片列表高度平滑过渡，激活横条从旧按钮滑动到新按钮；电脑端先锁文档高度、再让滚动位置与高度/居中 margin 同相缓动，从"卡片多切到卡片少"时页面同步平滑上滚，无跳帧
- 🌓 **主题系统**：跟随系统 / 浅色 / 深色模式，前后台共用同一份主题偏好，配置保存在浏览器本地存储，**内置防闪烁预解析**。**内置硬件性能自适应探测，自动识别 GPU 与设备性能，高配设备享受丝滑过渡，低端或纯 CPU 渲染设备自动降级保证流畅**。
- 🔄 **三态轮播开关**：点击在 `关闭 / 开启` 模式间切换；**只要当前不处于 `持续新图` 状态，长按 3 秒**（带蓄能环动画）即可进入 `持续新图` 模式（强制 API 优先，按钮变橙色）。再次点击或长按可退出。
- 👘 **看板娘**：可拖动、记忆位置、切换形象，支持多个 GIF / PNG / WebP / JPG。**支持 `<link rel="preload">` 提前拉取首图，并在加载页结束后延迟执行，防止气泡计时被吃掉**；**形象图加载失败会自动重试（最多 2 次），仍失败时降级为圆形 ✨ 悬浮按钮（点击直接打开控制面板）**。
- 💬 **看板娘语录**：本地语录 + 远程 API 交替出场，时段问候 + 洗牌队列不重复
- ⚙️ **看板娘控制面板**：点击语录气泡上的齿轮打开，或当语录关闭时点击看板娘打开
- ✨ **JS 特效**：可上传或引用远程 JS，前台控制面板按需开关。**特效脚本在加载页结束后由 `page-ready` 触发动态注入执行**。**可控特效（同时暴露 `start` / `stop`）在面板里开关即时生效；固定特效（无成对 `start` / `stop`）面板里同样可以开关，但每次开关会先写 cookie 再刷新页面。若特效脚本已加载但未注册 matchKey，开关会直接禁用并显示 ⚠ 图标，不再触发刷新死循环**。
- 🎨 **加载页主题**：可上传多个 CSS 主题，前台底部独立按钮可让用户自选加载动画
- 📢 **首页公告条**：最新一条启用的公告会在首页顶部显示为紧凑横幅（类型徽章 + 标题 + 查看详情链接），点击「✕」可关闭，关闭后同一公告不再显示
- 📢 **站内公告页**：独立的 `/announcement.php` 公告页面（列表风格：时间在左上、标题大字、正文紧凑），404 页「查看公告」跳转到该页，支持 4 种公告类型（通知 / 成功 / 警告 / 错误），后台「📢 公告管理」标签页可增删改查
- 🔗 **可配置页脚**：两行显示——第一行版权信息，第二行 `Powered by 项目名 · GitHub 链接`，全部后台可配，GitHub 链接有独立开关
- 🌐 **全站统计代码**：后台粘贴一次，前台 / 后台所有页面底部统一输出
- 📝 **用户文案安全转义**：卡片名称、副标题、分类名、看板娘名称、加载页名称/描述、UA 模板名等用户可编辑内容，含 `<` `>` 等特殊字符时不会破坏页面结构；即使文案里填了 `</script>`，也只会被当作普通字符展示
- 💬 **文本溢出提示**：卡片名称/副标题溢出时显示 Tooltip，移动端长按精准触发（自带 10px 滑动容错，手抖也不易取消）
- 🛑 **长按防误触**：优化了移动端长按页面空白区域时，系统选区手柄计算错位导致残影的 Bug，同时对纯视觉容器精准隔离选中，**不影响正常长按复制文字**。**已精准拦截卡片、头像的系统长按菜单，但大图预览依然允许长按保存，体验媲美原生 App**。
- 🧊 **卡片按压反馈**：卡片支持 3D 倾斜、按压缩放等交互效果
- ⚡ **首屏优化**：配置提前到 `<head>`，首图预热请求先发，加载动画等到图片真正可解码才关闭；**弱网熔断 + 柔和兜底底色，弱网/无网下绝不黑屏**
- 🚪 **未初始化自动引导**：首次部署后访问前台会自动跳转到 `/admin`，弹出安装向导

### 后台
- 🔐 **安全登录**：首次访问初始化管理员，密码哈希存储，`.admin.conf` 防直读；**生产环境已关闭 `display_errors` 并开启 `log_errors`，PHP 报错写入日志而不暴露给访问者**
- 🧭 **4 步安装向导**：管理员账号 → 站点信息 → 行为配置 → 完成预览，首次安装约 2 分钟，所有字段留空则回退默认值
- 🔄 **在线升级**：点击后台顶部栏的「版本号（如 v1.1.0）」胶囊按钮，进入 `admin/update.php` 独立升级页，对接 GitHub Releases API，5 分钟缓存，自动检查版本 → 环境检测 → 下载更新包（SHA256 校验 + 路径穿越防护）→ 应用升级（先备份再覆盖，失败自动回滚）→ 一键回滚。**环境不支持时自动降级为「去 GitHub 下载」按钮**
- 📋 **卡片管理**：新增、编辑、删除、拖拽排序；支持链接 / 图片两种类型
- 🗂 **分类管理**：新增、重命名、修改图标、删除；分类下有卡片时禁止删除；支持长按拖动排序
- 🖼 **图片管理**：上传、删除、重命名、排序、预览；**重命名自动同步卡片图标 / 弹窗大图 / 分类图标，以及站点设置里的头像 / favicon / 后台头像**（带前导 `/` 与不带 `/` 两种形式都覆盖）；**删除采用两层确认**：先弹常规确认（防误点）→ 后端检测引用 → 若图片正在被卡片、分类或站点设置使用，再弹第二个弹窗列出所有引用位置，确认后带 `force` 重试并同步清空对应引用（头像 / 网站图标回退到默认值），避免留下裂图
- 🖼️ **缩略图加速**：图片管理 / 看板娘 / 本地背景的网格显示统一使用服务器生成的缩略图（长边 400px），点击「预览」才加载原图；网格里的 `photo-cell` 加了 `content-visibility: auto`，浏览器会跳过屏幕外元素的布局与绘制；切 tab 从「等几十张大图解码」变成「秒开」
- 🖼️ **本地背景分批挂载**：进入「🖼 本地背景」标签页时，图片通过 `requestAnimationFrame` **每帧挂载 4 张**，避免一次性挂满导致主线程卡顿
- ✨ **JS 特效管理**：上传 `.js`、URL 添加远程 JS、启用 / 禁用、排序、编辑显示名称 / 图标；列表里以「可控 / 固定」徽章自动区分特效类型；**matchKey 只在首次识别新特效时自动提取，手动修改后的值永久保留**；编辑弹窗里提供「🔄 自动读取」按钮，可从脚本内容重新提取 matchKey；后台禁用固定特效时会弹出确认框
- 👘 **看板娘管理**：上传 GIF / PNG / WebP / JPG、启用 / 禁用、排序、重命名、删除
- 💬 **语录设置**：通用语录、五个时段的分时段语录、显示时长、远程 API 地址与字段名
- 🎨 **加载页管理**：上传 `.css` 主题、启用 / 禁用、排序、编辑名称 / 描述 / 文件名；注入时统一转义特殊字符，`.a > .b` 子选择器不会破坏页面结构
- 🖼 **本地背景兜底**：背景 / Banner 独立管理，支持上传、API 抓取、启用 / 禁用、预览 / 删除 / 重命名 / 拖动排序、优先级切换；**「自动分类」按钮一键根据图片宽高重新纠正桌面端/移动端标签，并自动按「移动端（竖屏） / 桌面端（横屏） / 通用」分组展示，每组带标题与数量，视觉清晰**；分类结果通过三种颜色的 toast 提示：**全部成功 = 绿色，部分成功（有识别失败） = 黄色，全部失败 = 红色**
- 🎭 **UA 伪装管理**：内置移动端 / 桌面端 UA 模板，可新增、编辑、删除自定义模板；背景与 Banner 抓取可分别选择 UA；**这里的模板就是「立即抓取」实际使用的请求头**——**默认背景走移动端模板（抓竖图）、Banner 走桌面端模板（抓横图）**
- 📢 **公告管理**：新增 / 编辑 / 删除 / 置顶 / 启用 / 禁用，4 种公告类型（通知 / 成功 / 警告 / 错误），公告内容支持 HTML 但不允许 `<script>`
- 🌐 **全站统计代码**：独立标签页，粘贴一次全站生效，支持任意 HTML 片段
- ⚙ **站点设置**：标题 / 副标题 / 头像 / favicon / API / 轮播间隔 / 缓存数量 / 连点次数 / 页脚版权 / GitHub 链接（含开关） / Powered by 项目名与链接等
- 🔢 **数字步进器**：配置类数字输入框自动带 `− / +` 步进按钮，支持 `min / max / step`（本地背景抓取数量等紧凑输入框保留原始外观，避免撑宽挤压按钮）
- 🖱 **拖拽排序修复**：拖拽卡片 / 图片 / 分类后，页面其他按钮的点击不再被误吞（旧版会 400ms 内拦截所有点击），单击拖拽元素自身仍然被安全拦截
- 💾 **备份恢复**：导出完整 `data.json`，导入覆盖全部数据
- 🗑 **清除数据**：三种模式——"指定内容 / 清除全部 / 完全清空"
- 🔐 **账号设置**：修改管理员用户名、密码
- 🏷 **版本号显示**：后台顶部栏显示当前版本 `v1.1.0`

---

## 📁 目录结构

```text
网站根目录
├── index.php                 # 前台首页，读取 admin/data.json 渲染
├── announcement.php          # 站内公告页（公开访问）
├── analytics.php             # 全站统计代码输出模块（前台/后台共用）
├── 404.php                   # 自定义 404 页面（「查看公告」跳转到 announcement.php）
├── admin/                    # 后台管理目录
│   ├── index.php             # 后台管理入口
│   ├── update.php            # 在线升级页（独立页面，不嵌入标签栏）
│   ├── defaults.php          # 默认值唯一权威来源（前后台共用 + 升级常量）
│   ├── data.json             # 数据文件（首次安装生成）
│   ├── .admin.conf           # 管理员账号配置（PHP 防直读，密码哈希）
│   ├── .htaccess             # 保护 .admin.conf / data.json 不被直接下载
│   └── .update/              # 在线升级工作目录（自动创建）
│       ├── update.lock       # 并发锁
│       ├── github_release_cache.json  # GitHub Release 5 分钟缓存
│       ├── package.zip       # 下载的更新包
│       ├── package/          # 解压后的更新包
│       ├── state.json        # 升级状态机（tmp+rename 原子写入）
│       ├── history.json      # 升级历史（最多保留 50 条）
│       └── backup/           # 代码备份（按时间戳分目录，保留最近 3 份）
├── assets/                   # 静态资源
│   ├── style.css             # 前台样式
│   ├── admin.css             # 后台样式（含第 27 章在线升级页样式）
│   ├── frontend.js           # 前台脚本
│   ├── admin.js              # 后台脚本
│   ├── mascot.js             # 看板娘 + 特效控制器
│   ├── js/                   # JS 特效文件（自动创建）
│   └── preloaders/           # 加载页 CSS 主题（自动创建）
│       ├── sakura-spin.css   # 内置主题：樱花旋转
│       ├── elegant.css       # 内置主题：浮光
│       ├── apple.css         # 内置主题：Apple
│       ├── tech.css          # 内置主题：脉冲
│       ├── cyberpunk.css     # 内置主题：赛博朋克
│       ├── ink.css           # 内置主题：水墨
│       ├── minimal.css       # 内置主题：极简
│       ├── nature.css        # 内置主题：自然
│       ├── neon.css          # 内置主题：霓虹
│       ├── sakura.css        # 内置主题：樱花
│       ├── starry.css        # 内置主题：星空
│       └── vaporwave.css     # 内置主题：蒸汽波
├── photos/                   # 图片存储目录（需写权限）
│   ├── .htaccess             # 禁止在此目录执行脚本
│   ├── QQ.jpg                # 默认头像
│   ├── zy.png                # 默认分类图标「导航」
│   ├── lx.png                # 默认分类图标「联系」
│   ├── thumbs/               # 图片管理缩略图（自动创建）
│   ├── kanban/               # 看板娘图片目录（自动创建）
│   │   ├── 妹妹.gif
│   │   ├── 姐姐.gif
│   │   ├── 喵小娘.gif
│   │   ├── 月薪喵.gif
│   │   ├── 七濑胡桃.gif
│   │   ├── 舞娘娘.gif
│   │   ├── 柠檬妹.gif
│   │   └── 熊宝宝.gif
│   ├── kanban_thumbs/        # 看板娘缩略图（自动创建）
│   ├── localbg/              # 背景本地兜底图目录（自动创建）
│   ├── localbg_thumbs/       # 背景兜底缩略图（自动创建）
│   ├── localbanner/          # Banner 本地兜底图目录（自动创建）
│   └── localbanner_thumbs/   # Banner 兜底缩略图（自动创建）
├── .gitignore                # 排除运行时数据
├── CHANGELOG.md              # 版本变更记录
├── LICENSE                   # MIT 协议
└── README.md
```

> 后台会自动创建 `photos/`、`photos/thumbs/`、`photos/kanban/`、`photos/kanban_thumbs/`、`photos/localbg/`、`photos/localbg_thumbs/`、`photos/localbanner/`、`photos/localbanner_thumbs/`、`assets/js/`、`assets/preloaders/`、`admin/.update/` 目录（如果不存在）。  
> `admin/defaults.php` 是所有默认值（`site_defaults()` / `tips_defaults()` / `factory_defaults()` / `ua_builtin_templates()`）和升级常量（`UPDATE_GITHUB_REPO` / `UPDATE_DIR` / `UPDATE_MAX_PACKAGE_MB` / `UPDATE_MAX_EXTRACTED_MB` / `UPDATE_BACKUP_KEEP`）的**唯一权威来源**，前后台共用，避免重复定义和配置漂移。  
> `admin/data.json` 与 `admin/.admin.conf` 会在首次安装 / 保存时生成。  
> `assets/preloaders/` 中实际存在的 `.css` 会被后台自动扫描，内置主题数量以目录为准。  
> `photos/localbg/` 与 `photos/localbanner/` 是**平铺目录**：桌面端 / 移动端 / 通用的图**混合存放**在同一目录，靠 `data.json` 里的 `target` 字段区分（前台按方向过滤，后台可直接一键分类并按方向分组展示）。  
> `admin/.update/` 是**在线升级工作目录**，只由 `admin/update.php` 与 `admin/index.php` 的 `update_*` API 读写，**绝不允许被升级包覆盖**。

---

## 🚀 环境要求

- PHP 7.3+（推荐 PHP 7.4+，后台使用 `array_key_first()`）
- 开启 PHP Session
- 支持 JSON 扩展
- 浏览器支持 IndexedDB（现代浏览器均支持）
- **抓取本地背景需要**：允许 `allow_url_fopen`，或服务器能访问外网
- **在线升级需要**：PHP `zip` 扩展（`ZipArchive`），服务器能访问 `api.github.com` 和 `github.com`
- **PHP 图片扩展（至少其一，用于生成缩略图）**：
  - **Imagick**（推荐）：处理大图用磁盘缓存，几乎不吃 PHP 内存
  - **GD**：PHP 默认扩展，大部分主机自带；内存受限时单张处理 4000×3000 以内较稳
  - **两者都没有也不影响使用**，只是不生成缩略图（自动退回原图显示），其它功能完全正常
- 目录写权限：
  - `admin/`：写入 `data.json` 和 `.admin.conf`
  - `admin/.update/`：在线升级工作目录（自动创建）
  - `photos/`：上传图片
  - `photos/thumbs/`：图片管理缩略图（自动创建）
  - `photos/kanban/`：上传看板娘，自动创建
  - `photos/kanban_thumbs/`：看板娘缩略图（自动创建）
  - `photos/localbg/`、`photos/localbg_thumbs/`：背景本地兜底，自动创建
  - `photos/localbanner/`、`photos/localbanner_thumbs/`：Banner 本地兜底，自动创建
  - `assets/js/`：上传 JS 特效，自动创建
  - `assets/preloaders/`：上传加载页 CSS，自动创建
- PHP 上传限制建议不低于：
  - 图片：5MB
  - JS：2MB
  - 看板娘：10MB
  - 加载页 CSS：2MB
- **PHP 内存限制**：后台入口会自动 `@ini_set('memory_limit', '512M')`；如果主机禁止覆盖（`disable_functions` 等），建议手动在 PHP 配置里调到 **512M** 以上（生成缩略图需要）；使用 Imagick 时 PHP 内存基本不是瓶颈

---

## 📦 安装部署

### Apache（推荐）

1. **上传文件**  
   将全部项目文件上传至 PHP 网站根目录。

2. **设置权限**  
   ```bash
   chmod -R 755 admin photos assets/js assets/preloaders
   chmod -R 755 photos/thumbs photos/kanban_thumbs
   chmod -R 755 photos/localbg photos/localbg_thumbs
   chmod -R 755 photos/localbanner photos/localbanner_thumbs
   ```

3. **访问后台初始化**  
   - **未初始化时**，直接访问前台 `/index.php` 会自动跳转到 `/admin`，无需手动输入后台地址
   - 或直接访问 `/admin`（或 `/admin/index.php`）
   - 首次访问会弹出 **4 步安装向导**：
     1. 管理员用户名与密码（至少 6 位）
     2. 站点信息（标题、副标题、头像、favicon、API、页脚版权 / GitHub / Powered by）
     3. 行为配置（轮播间隔、缓存数量、连点次数 / 时间窗口）
     4. 完成预览
   - 提交后自动登录进入后台

4. **配置站点**  
   登录后台 →「站点设置」中修改标题、头像、API、轮播间隔等；需要统计代码时到「🌐 统计代码」标签页粘贴；想要 API 挂掉时兜底就到「🖼 本地背景」标签页配置；需要伪装抓取 UA 就到「🎭 UA 伪装」标签页配置。

5. **首次进后台会生成缩略图**  
   首次登录后会为已有的所有图片生成缩略图，**每个请求最多处理 30 张**，防止 PHP 内存爆掉。图片多的话可以**多刷新几次后台**，缩略图会逐渐补齐。缩略图生成期间不影响后台的其它操作。

6. **想在线升级的话**  
   确认服务器装了 PHP `zip` 扩展、能访问 `api.github.com`；点击后台顶部栏的版本号，环境检测通过后就能在线升级。环境不通过时点「🔗 去 GitHub 下载」手动覆盖即可。

### Nginx

`.htaccess` 在 Nginx 下**不生效**，需要在站点配置里手动加以下规则，否则 `.admin.conf` / `data.json` 可能被直接下载，`photos/` 里的脚本可能被执行：

```nginx
# 保护后台敏感文件
location ~ ^/admin/.*\.(conf|json|bak|tmp|log|md)$ {
    deny all;
    return 404;
}

# 保护在线升级工作目录
location ~ ^/admin/\.update/ {
    deny all;
    return 404;
}

# 禁止图片目录执行脚本
location ~ ^/photos/.*\.(php|phtml|php3|php4|php5|php7|php8|phar|pl|py|jsp|asp|aspx|sh|cgi)$ {
    deny all;
    return 404;
}
```

改完后 `nginx -t && nginx -s reload` 生效。

**验证是否生效**：浏览器访问 `https://你的域名/admin/data.json`，应返回 403 或 404。

### 共享主机

大多数共享主机支持 Apache，`.htaccess` 自动生效。**共享主机用户在线升级大概率会因权限不一致失败**（PHP 运行用户 ≠ 文件所有者），环境检测不通过时会自动降级为「去 GitHub 下载」，请按 README 手动覆盖升级。

---

## 🎮 使用说明

### 进入后台
- 未初始化时，前台任意页面会自动跳转到 `/admin` 进行安装
- 初始化完成后：前台首页在配置的时间窗口内连续点击头像达到配置次数，自动跳转 `/admin/index.php`（默认 10 次 / 10 秒）
- 或直接访问 `/admin`

### 在线升级（点击顶部版本号）

点击后台顶部栏的版本号胶囊进入 `admin/update.php`：

1. **自动检查**：打开页面即自动拉取 GitHub Releases 最新版本，显示当前版本 / 最新版本 / 是否有更新
2. **环境检测**：显示 PHP 运行用户、文件所有者、是否可写
   - ✅ 可写 → 显示「⬆ 立即升级」按钮
   - ❌ 不可写 → 显示「🔗 去 GitHub 下载」按钮，并展示具体原因
3. **一键升级**：点「立即升级」→ 自动下载 → SHA256 校验 → 解压 → 备份 → 覆盖 → 完成
4. **一键回滚**：升级完成后，如果新版本有问题，点「↩ 回滚到上一版本」即可

**注意**：
- 在线升级只换代码文件，`data.json`、`photos/`、`assets/js/`、`assets/preloaders/` 永远不动
- 升级前会备份当前代码到 `admin/.update/backup/<时间戳>/`，默认保留最近 3 份
- 如果升级过程中遇到网络问题导致卡住，10 分钟后状态会自动转 `failed`，重新进升级页即可继续
- 升级成功后记得 **Ctrl+F5 强制刷新** 后台，让浏览器加载新版本的 `admin.js` / `admin.css`

### 安装向导（4 步）
1. **管理员账号**：用户名 + 密码（至少 6 位）+ 确认密码
2. **站点信息**：浏览器标签标题、主标题、副标题、头像、favicon、背景 API、Banner API、页脚版权、GitHub 链接 + 开关、Powered by 项目名 + 链接
3. **行为配置**：背景轮播间隔、Banner 轮播间隔、缓存图片数量、头像连点次数、点击时间窗口
4. **完成预览**：显示所有已填信息 + 「已预置 2 个默认分类（导航 / 联系）与 2 个加载页主题（优雅 / 苹果）」

> **留空策略**：任何字段留空 → 回退到 `admin/defaults.php` 的默认值。API 字段留空也一样会回退到 loliapi。

### 轮播控制按钮
- **单指点击**：在 `关闭`（播放图标）和 `开启`（暂停图标）之间切换；在 `持续新图`（橙色闪电）状态下点击则回到 `关闭`。
- **长按 3 秒**：**只要当前不处于 `持续新图` 状态**，长按 3 秒都会触发蓄能光环动画，松开后进入 **「持续新图」** 模式（橙色闪电图标）。
- **持续新图模式**：强制忽略后台的「本地优先」设置，每一轮轮播都强行请求新的 API 图片。

### 卡片管理
- 新增 / 编辑卡片：选择类型（链接 / 图片）、分类、名称、副标题、图标、跳转网址或弹窗大图
- 拖拽排序：按住行首 `⋮⋮` 手柄 350ms 后拖动，拖动后行内出现「保存排序 / 取消」按钮；顺序未变时不显示按钮
- 删除卡片：点击行尾 🗑 按钮

### 分类管理
- 添加分类：点击「＋ 添加分类」
- 编辑分类：点击分类标签上的 ✎，可修改名称和图标（emoji 或图片路径）
- 删除分类：点击 ✕，若分类下还有卡片则无法删除
- 拖拽排序：长按分类标签 350ms 后拖动，松手自动保存

### 图片管理
- 上传：点击虚线框或拖拽图片到区域，支持 jpg / jpeg / png / gif / webp，单张 ≤ 5MB
- 也可直接将图片放入 `photos/` 目录，刷新后台自动出现
- 排序：长按 `⋮⋮` 拖动，松手自动保存
- 重命名：悬停图片点击 ✎，扩展名不变，自动更新卡片 / 分类中的引用**以及站点设置里的头像 / favicon / 后台头像**
- 删除：悬停图片点击 ✕
  - **第一层确认**：任何删除都会先弹「确定删除图片「xxx」吗？」（防误点）
  - 点确定后，后端检测引用情况：
    - 若图片**未被引用** → 直接删除，toast 提示"图片已删除"
    - 若图片**正在被使用**（卡片图标 / 卡片弹窗大图 / 分类图标 / 前台头像 / 网站图标 / 后台头像）→ **第二层确认**弹窗列出所有引用位置，再点确定才会真正删除，同时**清空对应引用**（头像 / 网站图标回退到 `site_defaults()` 里的默认值），避免留下裂图

> 🖼 列表里显示的是**缩略图**（长边 400px），点击「预览」才加载**原图**。  
> 缩略图在上传时自动生成，也可以把原图丢进 `photos/` 后刷新后台自动补生成。  
> 删除 / 重命名原图时，缩略图会一并处理。

### JS 特效管理
- 上传 `.js` 文件：点击「＋ 上传特效」，文件 ≤ 2MB
- URL 添加：点击「🔗 URL 添加」，输入远程 JS 地址
- 启用 / 禁用：开关控制
- 排序：长按 `⋮⋮` 拖动，松手自动保存
- 编辑：点击 ✎ 修改前台显示名称、图标，以及 matchKey
- 前台控制面板：看板娘面板或右下角 ✨ 按钮中可开关特效
- 也可直接将 `.js` 放入 `assets/js/`，刷新后台自动识别

> **可控特效 vs 固定特效（后台自动识别）**
>
> `admin/index.php` 同步 JS 文件时会检测模块是否同时暴露 `start` / `stop`，并写入 `jsEffects[].autoToggle`：
>
> - **可控特效**：模块同时有 `start` 和 `stop`，后台列表显示「可控」徽章。前台面板中开关**即时生效**（直接调用 `start()` / `stop()`，不刷新页面）。
> - **固定特效**：没有成对的 `start` / `stop`（或只写了 `Effect.start()`），后台列表显示「固定」徽章。**前台面板里同样可以开关**，但因为缺少"原地停止"的能力，每次开关会走**刷新路径** —— 先把 `matchKey` 写进 `localStorage.effects_states` 和 `nuomi_effects_off` cookie，再刷新页面；刷新后 `index.php` 根据 cookie 决定是否把该脚本 `<script>` 注入。

> **后台禁用的语义差异**
>
> - 对**可控特效**：后台禁用后，该特效从「默认启用」列表里消失；但前台面板里仍保留开关（`showInPanel = cfgEnabled || runtimeToggle`），访客自己可以打开。
> - 对**固定特效**：后台禁用时会弹出确认框，提示「该特效不支持自启停，禁用后将从首页及控制面板中移除，访客无法手动启用」。确认后该特效会从前台面板里**完全消失**，需要后台重新启用才能恢复。

> **matchKey 的语义**
>
> - 后台只在**首次识别新特效**时自动从脚本内容提取 matchKey；已经存在的值（无论自动提取的还是手动修改的）**永久保留**，刷新后台不会再覆盖。
> - 编辑弹窗里有一个「🔄 自动读取」按钮，可以随时从当前 JS 文件内容重新提取一次，确认后手动保存。
> - 如果脚本已经加载到前台、但 `EffectsRegistry` 里注册的 `name` 与 `data.json` 里的 `matchKey` 对不上，前台面板里点开关会**直接禁用开关并显示 ⚠ 图标**（不会再触发刷新死循环）。此时去后台点「🔄 自动读取」或手动修正 matchKey，保存后刷新前台即可正常使用。

> 特效 JS 需注册到 `window.EffectsRegistry`：
> ```js
> window.EffectsRegistry = window.EffectsRegistry || [];
> window.EffectsRegistry.push({ name: 'snowfall', module: Snowfall });
> ```

### 看板娘管理
- 上传：点击虚线框或拖拽 GIF / PNG / WebP / JPG，单个 ≤ 10MB
- 也可直接放入 `photos/kanban/`，刷新后台自动出现（内置 `妹妹.gif` / `姐姐.gif` / `喵小娘.gif` / `月薪喵.gif` / `七濑胡桃.gif` / `舞娘娘.gif` / `柠檬妹.gif` / `熊宝宝.gif` 可作为默认形象）
- 启用 / 禁用：右上角开关
- 排序：长按 `⋮⋮` 拖动，松手自动保存
- 重命名：悬停点击 ✎，磁盘文件与显示名称同步修改

> 🖼 GIF 会**取第一帧**作为缩略图，所以网格里不会再播动态图，切 tab 更顺。  
> 点击「预览」时加载的仍然是原 GIF（动图正常播放）。

### 语录设置
进入后台「看板娘」标签页，页面底部有「💬 语录设置」区块，可配置：

- **默认开启随机语录**：关闭后，用户仍可在看板娘面板里手动打开
- **语录显示时长**：秒，范围 0.5 ~ 60，默认 3 秒（存储为毫秒）
- **通用语录**：每行一条，随时段随机抽取（与分时段语录一起洗牌）
- **分时段语录**：五个时段各一组，按当前时间自动匹配
  - 🌙 深夜（0:00 - 5:00）
  - ☀️ 早上（5:00 - 11:00）
  - 🍚 中午（11:00 - 13:00）
  - ☕ 下午（13:00 - 17:00）
  - 🌆 晚上（17:00 - 23:00）
- **远程语录 API 地址**：可选，如 `https://v1.hitokoto.cn/?c=b`
- **API 返回的字段名**：如 `hitokoto`（默认值 `text`）

### 加载页管理
进入后台「加载页」标签页，可管理首屏加载动画主题：

- **总开关**：控制前台是否启用首屏加载遮罩
- **上传**：点击虚线框或拖拽 `.css` 文件，单个 ≤ 2MB，可批量
- **自动识别**：也可直接将 `.css` 放入 `assets/preloaders/`，刷新后台自动出现
- **启用 / 禁用**：每个主题独立开关
- **排序**：长按 `⋮⋮` 拖动，松手自动保存，**排在首位的主题自动成为默认主题**
- **编辑**：点击 ✎ 修改显示名称、描述、文件名（磁盘文件同步重命名）
- **CSS 文件规范**：
  - 文件顶部可写注释定义名称和描述：
    ```css
    /* @name 樱花旋转 */
    /* @description 樱花旋转 + 呼吸缩放 + 花瓣错拍闪烁 */
    ```
  - 不要包含 `<style>`、`<script>`、`</script>`、`<?php` 等标签；上传与后台扫描时会拒绝 `<?php`、`<?=`、`</style>`、`<script`、`</script`
  - CSS 里正常的 `.a > .b` 子选择器不会破坏页面结构，注入到 `<script>` 时会自动转义

### 🖼 本地背景兜底

进入后台「🖼 本地背景」标签页，为背景 / Banner 配置 API 挂掉时的兜底图。

**核心特点：**

- **完全独立**：背景与 Banner 分开存储（`photos/localbg/` 与 `photos/localbanner/`），分开开关，分开优先级
- **双录入方式**：
  - 手动上传：点击虚线框或拖拽图片，支持 jpg / png / gif / webp，单张 ≤ 5MB
  - 从 API 抓取：填张数（1 ~ 20），点「立即抓取」→ 服务器从当前配置的 API 拉图并存到本地
- **目标设备自动识别（desktop / mobile / both）**：
  - 上传或抓取时，系统会读取图片宽高自动打标：宽 > 高 × 1.1 → `desktop`；高 > 宽 × 1.1 → `mobile`；其余 → `both`
  - 后台面板顶部的 **「自动分类」** 按钮，可一键按宽高重算所有图片的 `target` 字段，纠正历史打错的标签
  - **自动分类后网格会按设备方向分组展示**：依次显示「📱 移动端（竖屏） · N 张」「🖥 桌面端（横屏） · N 张」「🌐 通用 · N 张」三个分组，每组带分隔标题和数量，方便一眼看清各方向的图片数量；分组内仍然支持拖拽排序、编辑、预览、删除
  - **自动分类的结果提示按成功程度区分颜色**：
    - ✅ **全部成功**：绿色 toast
    - ⚠️ **部分成功（有图片读取失败）**：黄色 toast
    - ❌ **全部失败（没有一张能识别）**：红色 toast
  - 单张图片点 ✎ 编辑时，可在弹窗里手动指定：✨ 自动识别 / 🖥 桌面端 / 📱 移动端 / 🌐 通用
  - 前台按屏幕方向（`innerWidth >= innerHeight` → desktop）自动过滤：`target === 'both'` 或 `target === 当前方向` 的图优先参与轮播
  - **交叉兜底防黑屏**：如果当前方向没有图，或者某张图加载失败，前台会自动拿相反方向的图顶替，保证绝不黑屏
  - **Banner 优先横屏**：Banner 区域本身是横向轮播，前台优先只取 `desktop` 和 `both` 的横图。**如果所有 Banner 本地图都被手动改成了 `mobile`，前台会退回使用全部图**，不会因为过滤后为空导致 Banner 本地兜底彻底失效。
- **抓取默认 UA**：点「立即抓取」时，服务器会读 `site.localBgUaId` / `site.localBannerUaId` 里保存的模板 ID 拼请求头。**默认背景走移动端模板（抓竖图）、Banner 走桌面端模板（抓横图）**——想换方向去「🎭 UA 伪装」页切换对应模板即可。
- **内容去重**：上传与抓取都会按内容 MD5 去重，同一张图不会存两份
- **独立缓存**：本地图会写入浏览器 IndexedDB（键前缀 `bg_local:` / `banner_local:`），**回访秒显、零流量**
- **隔离存储**：与 API 池的缓存键完全隔离，**不参与 FIFO 淘汰**，不挤占池空间
- **优先级可调**（每类独立）：
  - **API 优先**（默认）：API（重试 3 次）→ 池里历史图 → 本地图 → 空白
  - **本地优先**：本地图按顺序循环；如果本地图数量少于缓存池上限，系统会自动在后台用 API 图填满剩余坑位
- **本地优先队列**：本地优先模式下，本地图与 API 池会按顺序循环取图，游标保存在 `localStorage.nuomi_queue_cursor_<type>`
- **缩略图加速**：网格显示缩略图（长边 400px，自动生成到 `photos/localbg_thumbs/` / `photos/localbanner_thumbs/`），点击「预览」才加载原图
- **图片分批挂载**：进入「🖼 本地背景」标签页后，图片用 `requestAnimationFrame` 每帧挂 4 张
- **操作完整**：上传 / 预览 / 删除 / 重命名（磁盘同步，弹窗内可选自动识别 / 桌面端 / 移动端 / 通用） / 拖动排序

**目录结构：**

```text
photos/
├── localbg/                # 背景本地兜底图（desktop / mobile / both 平铺混放）
├── localbg_thumbs/         # 背景兜底缩略图（自动生成）
├── localbanner/            # Banner 本地兜底图（建议放横图）
└── localbanner_thumbs/     # Banner 兜底缩略图（自动生成）
```

> 也可以直接把图片丢进这两个目录，刷新后台自动识别，缩略图会自动补生成。  
> **手动放进去的图会按宽高自动识别 target**，想要 `both` 就放接近正方形的图，或者进后台点「自动分类」重新算。

**推荐用法：**

| 你的场景 | 建议操作 |
|---|---|
| 只给手机用 | 默认背景走 `ua_mobile_default` 直接点「立即抓取」，抓一批竖图 |
| 只给 PC 用 | 默认 Banner 走 `ua_desktop_default` 抓一批横图；背景想给 PC 用，去「🎭 UA 伪装」页把背景模板切到 `ua_desktop_default` 再抓 |
| PC + 手机都要 | 背景默认移动端抓一批竖图；Banner 默认桌面端抓一批横图；也可手动上传对应方向的图 |
| 不区分方向 | 手动上传接近正方形的图，会自动打 `both` |
| 想纠正打错的标签 | 点「自动分类」按宽高重算，网格会立刻按新方向分组展示 |

### 🎭 UA 伪装

进入后台「🎭 UA 伪装」标签页，可维护抓取本地背景时携带的请求头。

**核心特点：**

- **内置模板**：
  - `ua_mobile_default`：iOS 18 Safari 移动端
  - `ua_desktop_default`：Windows 11 Chrome 桌面端
- **可编辑不可删除**：内置模板可以改内容，但不能删除。
- **自定义模板**：可新增、编辑、删除自定义 UA 模板。
- **独立字段**：`site.localBgUaId` / `site.localBannerUaId` 各存一个模板 ID，**这两个字段就是「立即抓取」实际使用的请求头**。
- **请求头字段**：
  - `User-Agent`
  - `Accept`
  - `Accept-Language`
  - `Accept-Encoding`
  - 额外请求头，每行一个，格式 `Name: value`
- **自动回退**：如果删除正在使用的自定义模板，会自动回退到对应默认模板。
- **默认值语义**：
  - `localBgUaId` 默认是 `ua_mobile_default` → **背景抓取默认走移动端 UA，抓竖图**
  - `localBannerUaId` 默认是 `ua_desktop_default` → **Banner 抓取默认走桌面端 UA，抓横图**

### 📢 公告管理

进入后台「📢 公告管理」标签页：

- **新增公告**：点击右上角「＋ 新增公告」
- **公告字段**：
  - 标题（必填，最多 100 字）
  - 类型：ℹ️ 通知 / ✅ 成功 / ⚠️ 警告 / 🚫 错误，分别对应蓝 / 绿 / 黄 / 红四种配色
  - 内容：支持 HTML 标签（`<p>` / `<strong>` / `<a>` / `<ul>` / `<li>` / `<code>` / `<img>` 等），**不允许 `<script>` 标签**
  - 置顶：置顶的公告会排在最上方
  - 启用：禁用的公告不会显示在公告页
- **列表操作**：启用 / 禁用开关、📌 置顶按钮、✎ 编辑、🗑 删除
- **公告页**：`https://你的域名/announcement.php`，自动按「置顶优先 + 创建时间倒序」排序，主题跟随 `localStorage.themeMode`
- **404 页「查看公告」按钮**：跳转到 `announcement.php`

### 页脚配置

进入后台「站点设置 → 前台 → 基础设置」，页脚相关字段：

- **页脚版权信息**（`copyright`）：显示在页脚第一行，留空则不显示
- **页脚 GitHub 链接**（`githubUrl`）：显示在页脚第二行，留空则不显示
- **显示 GitHub 链接**（`githubEnabled`）：独立开关，关闭后保留地址但不显示
- **页脚 Powered by 项目名**（`poweredBy`）：显示在页脚第二行，前面自动加「Powered by 」
- **页脚 Powered by 链接**（`poweredByUrl`）：留空则 Powered by 只显示为纯文本

**默认值**（可在 `admin/defaults.php` 里修改）：
- `copyright`：`© 2026 糯米の引导页`
- `githubUrl`：`https://github.com/nmrt0316/MochiNav`（占位符，请替换为真实仓库地址）
- `githubEnabled`：`1`（开启）
- `poweredBy`：`糯米の引导页`
- `poweredByUrl`：同上占位符

### 🌐 统计代码

进入后台「🌐 统计代码」标签页，可粘贴全站统计代码：

- **作用范围**：前台、后台所有页面的 `</body>` 前统一原样输出
- **支持内容**：百度统计、Google Analytics、Google Tag Manager、Cloudflare Web Analytics、Umami、自建统计等任意 HTML 片段
- **格式校验**：必须包含 HTML 标签，否则保存会被拒绝
- **PHP 拦截**：内容包含 `<?php` 或 `<?=` 会被拒绝
- **留空即关闭**：留空保存则不输出任何统计代码
- **CDN 重复注入提醒**：如果统计服务商已由 Cloudflare 等 CDN 在边缘节点自动注入，**不要**再往这里粘贴同一份代码

### 站点设置

- **前台**：
  - 网站图标 favicon、浏览器标签标题、主标题、副标题、头像
  - **图片路径支持两种形式**：`photos/QQ.jpg`（推荐，子目录部署也能正确解析）或 `/photos/QQ.jpg`（根目录部署可用）
  - 背景轮播 API、Banner 轮播 API
  - **背景轮播间隔（秒，3~60，默认 10）**
  - **Banner 轮播间隔（秒，3~60，默认 6）**
  - **缓存图片数量（每池上限，2~20，默认 8）**
  - 进入后台的连点次数与时间窗口
  - 首屏加载动画开关
  - 页脚版权信息、GitHub 链接（含开关）、Powered by 项目名与链接
- **后台**：登录页头像、登录页标题、登录页副标题、后台浏览器标签标题、后台顶部栏文字

> ⚠️ 站点设置里的所有改动**保存后需要刷新前台页面才生效**。

### 备份恢复
- **导出**：下载完整 `data.json` 备份（含卡片、分类、特效、看板娘、语录、站点设置、统计代码、公告）
- **导入**：上传备份 JSON，**完整覆盖当前全部数据**

### 清除数据
- **指定内容**：可多选清除「卡片与分类 / 图片 / JS 特效 / 看板娘 / 语录设置 / 站点设置 / 加载页主题 / 本地背景兜底 / UA 模板 / 公告」，确认词 `RESET`
- **清除全部**：恢复出厂设置，保留 `photos/` 图片与管理员账号，确认词 `RESET`
- **完全清空**：清空全部配置、所有图片目录、管理员账号，确认词 `CLEAR`，操作后需要重新初始化

> 「指定内容」勾选「UA 模板」会清除所有自定义模板，仅保留两个内置模板；勾选「公告」会清空所有公告；「清除全部」/「完全清空」会重置或清空 UA 配置与公告。  
> 清除图片 / 看板娘 / 本地背景时，对应的缩略图目录也会一并清空。  
> 清除数据**不会**清除 `admin/.update/` 在线升级工作目录（升级缓存、备份、状态都保留）。

### 账号设置
- 修改用户名：需验证当前密码
- 修改密码：需验证当前密码，新密码至少 6 位

---

## ⚙️ 站点配置项

`admin/data.json` 中的 `site` 对象保存了所有站点配置。

| 字段 | 说明 | 默认值 |
|------|------|--------|
| `pageTitle` | 前台浏览器标签标题 | `糯米の引导页` |
| `title` | 前台主标题 | `糯米の引导页` |
| `subtitle` | 前台副标题 | `又一个引导页` |
| `avatar` | 前台头像 | QQ 头像 URL |
| `favicon` | 网站图标 | `photos/QQ.jpg` |
| `bgApi` | 背景轮播 API | `https://www.loliapi.com/acg/` |
| `bannerApi` | Banner 轮播 API | `https://www.loliapi.com/acg/pc/` |
| `bgInterval` | 背景轮播间隔（秒，3 ~ 60） | `10` |
| `bannerInterval` | Banner 轮播间隔（秒，3 ~ 60） | `6` |
| `cacheLimit` | 每个缓存池上限张数（2 ~ 20） | `8` |
| `adminAvatar` | 后台登录页头像 | `photos/QQ.jpg` |
| `adminTitle` | 后台登录页标题 | `后台登录` |
| `adminSubtitle` | 后台登录页副标题 | `糯米の引导页管理` |
| `adminPageTitle` | 后台浏览器标签标题 | `糯米の引导页 · 后台管理` |
| `adminHeaderTitle` | 后台顶部栏文字 | `管理后台` |
| `adminClickCount` | 头像连点进入后台所需次数（3 ~ 30） | `10` |
| `adminClickWindow` | 头像连点时间窗口（秒，1 ~ 60） | `10` |
| `preloader` | 前台首屏加载遮罩开关 | `1` |
| `preloaderStyle` | 默认加载页主题 key | `elegant` |
| `analyticsCode` | 全站统计代码 | `""` |
| `localBgPriority` | 背景兜底优先级（`api` / `local`） | `api` |
| `localBannerPriority` | Banner 兜底优先级（`api` / `local`） | `api` |
| `localBgUaId` | 背景抓取使用的 UA 模板 ID | `ua_mobile_default` |
| `localBannerUaId` | Banner 抓取使用的 UA 模板 ID | `ua_desktop_default` |
| **`copyright`** | **页脚版权信息** | **`© 2026 糯米の引导页`** |
| **`githubUrl`** | **页脚 GitHub 链接** | **`https://github.com/nmrt0316`** |
| **`githubEnabled`** | **GitHub 链接开关（`1` / `0`）** | **`1`** |
| **`poweredBy`** | **页脚 Powered by 项目名** | **`糯米の引导页`** |
| **`poweredByUrl`** | **页脚 Powered by 链接** | **同上** |

> **默认路径约定**：`favicon` / `adminAvatar` 默认值是**不带前导 `/` 的相对路径**（`photos/QQ.jpg`），根目录和子目录部署都能正确解析。老版本数据里如果存的是 `/photos/QQ.jpg` 也照常使用，不受影响。

> **默认 UA 约定**：`localBgUaId` 默认 `ua_mobile_default`（背景抓取抓竖图）、`localBannerUaId` 默认 `ua_desktop_default`（Banner 抓取抓横图）。想换方向，去「🎭 UA 伪装」页切换即可。

数据文件顶层还包含：

| 字段 | 说明 |
|------|------|
| `localBg` | `{ enabled: bool, images: [{file, name, fp, target}] }` |
| `localBanner` | `{ enabled: bool, images: [{file, name, fp, target}] }` |
| `uaTemplates` | UA 伪装模板数组，每项含 `id / name / ua / accept / acceptLanguage / acceptEncoding / extraHeaders / builtin` |
| **`announcements`** | **公告数组，每项含 `id / title / content / type / pinned / enabled / created / updated`** |

`localBg.images[]` / `localBanner.images[]` 中每一项的字段：

| 字段 | 说明 |
|------|------|
| `file` | 网站根目录相对路径，如 `photos/localbg/xxx.jpg` |
| `name` | 显示名称（可重命名） |
| `fp` | 内容 MD5 指纹（用于去重） |
| `target` | 目标设备：`desktop` / `mobile` / `both`（缺省视为 `both`） |

> **关于缩略图路径**：`thumb` 字段是**运行时计算的派生字段**，不会写入 `data.json`。

---

## 🌐 统计代码机制

统计代码独立于站点设置的其它字段，由**公共模块 `analytics.php`** 统一负责输出。

### 输出位置

前台 `index.php`、`admin/index.php`、`announcement.php`、`404.php` 的 `</body>` 前都有一行：

```php
<?php include __DIR__ . '/analytics.php'; ?>
```

后台使用：

```php
<?php include __DIR__ . '/../analytics.php'; ?>
```

`analytics.php` 读取 `admin/data.json` 里的 `site.analyticsCode`，非空则原样 echo。

### 与 CDN 自动注入的关系

很多 CDN（例如 Cloudflare 的 Web Analytics）会在边缘节点**自动往 HTML 的 `</body>` 前注入**自己的探针脚本。这跟你在后台手填的代码是**两套独立通道**：

- 你手填的：由 `analytics.php` 输出
- CDN 注入的：由 CDN 边缘节点插入

**已经在用 CDN 自动注入的**：不要往后台统计框再填一遍。**想在后台统一管理**：到 CDN 后台关掉自动注入，改为在后台手填。

---

## 💾 图片缓存机制

前台背景与 Banner 图片使用 **IndexedDB**（数据库名 `nuomi_img_cache`，对象仓库 `images`）缓存图片 **Blob**，带来三个好处：

1. **回访秒显**：刷新页面立即显示上次缓存的图
2. **断网兜底**：API 挂掉 / 网络不通时，仍能显示缓存图
3. **离线轮播**：开轮播时若拉不到新图，可从缓存池里按顺序取图切换

### 缓存池规则

| 项 | 说明 |
|----|------|
| 池子数量 | 背景、Banner 各一个独立池子 |
| 上限 | 由 `cacheLimit` 控制（默认 8 张，可后台改 2~20） |
| 淘汰策略 | FIFO（新进旧出，超上限删最旧的） |
| 内容去重 | 入池前按内容指纹比较，相同 `fp` 的图不会重复入池 |
| 未开轮播（池未满） | 每次回访拉 1 张新图入池，池慢慢变厚 |
| 未开轮播（池已满） | 不拉新图，从池里按顺序取一张填槽 |
| 开轮播（池未满或已满） | 每次切换拉 1 张新图；池满则 FIFO 覆盖最旧一张 |
| 池取图顺序 | **按时间升序顺序循环**（A→B→C→…→A），一圈内不重复 |

### 缓存池隔离规则

`nuomi_img_cache` / `images` 对象仓库中，**三类 key 严格隔离**：

| 类型 | Key 格式 | FIFO 淘汰 | 参与顺序游标 |
|---|---|---|---|
| API 池 | `bg:<时间戳>:<rand>:<fp>` / `banner:<时间戳>:<rand>:<fp>` | ✅ 是 | ✅ 是 |
| 本地图缓存 | `bg_local:<filepath>` / `banner_local:<filepath>` | ❌ 否 | ❌ 否 |
| 坏图黑名单 | `localStorage.nuomi_bad_local_<type>_<signature>` | ❌ 否 | ❌ 否 |

隔离依赖字典序：`':'` (0x3A) < `'_'` (0x5F)，所以 `bg_local:...` 永远不在 `['bg:', 'bg:\uffff']` 扫描范围内。

> ⚠️ 将来重构时不要把本地 key 前缀写成 `bg:` 开头（例如 `bg:local:xxx`），否则会污染池的 FIFO 计数。

**智能黑名单**：弱网环境（网络超时）**不会**将图片加入黑名单，只有 404 / 解码失败等实质性损坏才会拉黑。

### 内容指纹去重

API 池入池前会计算图片内容指纹 `fp`：

- 取 Blob **头 32KB** 与 **尾 32KB**，分别做 FNV 哈希
- 组合成 `fp = <size(36进制)>_<headHash(36进制)>_<tailHash(36进制)>`
- 写入 key 末尾：`bg:<时间戳>:<rand>:<fp>`
- 入池前扫描池内已有 key，若存在相同 `fp` 则丢弃新图，不入池

### 待播槽预取机制

轮播不是"到点才去拉图"，而是维护一个只存 1 张图的「待播槽」，切换时直接取槽，零等待。

**关键：`lastShownKey` 只在切换成功后写入**  
`lastShownKey` 记录的是"**上次关闭页面时最后实际显示在屏幕上的那张图**"，**不是**"上次预取的那张"，也**不是**"回访刚开始秒显的那张"。它只在这三种时机更新：

1. 轮播切换成功后（`doActualApply` 的 `usePick` 里，`doSwitch` 返回 `ok` 后）
2. 回访首屏秒显成功后（`init` 里 `doSwitch(r.pick, true)` 返回 `ok` 后）
3. 回访 1.5s 后切待播槽成功后（`doSwitchAfterDelay` 里 `doSwitch(p, false)` 返回 `ok` 后）

**未开轮播时的行为（每次回访都会跑，API 优先 / 本地优先共用同一套续播骨架）：**

```text
回访：
  ⓪ <head> 预热前判断 localStorage.nuomi_pool_full_<type>
     —— '1'（池满）或本地优先 → 不发预热请求
  ① showKey = lastShownKey（上次关闭页面时最后实际显示的那张）→ 秒显（无动画）
  ② 秒显成功后，setLastShownKey(showKey)（保持幂等，避免后续刷新丢状态）
  ③ 确定待播槽的图（nextReady）：
     - 若 lastPrefetchKey（或 localPrefetchId）仍在池中且不同于 showKey
       → 直接复用，不重复拉取
     - 否则调 prefetchNext()：从缓存进待播队列的池子里取一张放进待播槽
  ④ 等 preloader 消失（body.page-ready）
  ⑤ 再等 1.5s（让用户看清当前背景）
  ⑥ 平滑切换到待播槽里的那张（带动画）
  ⑦ 切换成功后，setLastShownKey(刚切到的那张)
  ⑧ 立即 prefetchNext()：再取一张放进待播槽
  ⑨ 停住，等用户下次回访或开轮播
```

**开轮播时的行为：**

- 每次切换完 → `setLastShownKey(刚切到的那张)` → 立刻拉 1 张新图替换槽
- 拉到新图 → 切换时用新图（池满则 FIFO 覆盖最旧）
- 拉不到（网络波动 / 断网） → 用池里的图按顺序循环

### 解码验证

所有取图路径（API 拉新、池出图、本地图缓存命中、本地图 fetch）在**入池 / 入缓存 / 切换前**都会做一次解码验证：

- 验证不过的图 → **不入池、不入缓存、不进轮播队列**
- 池里历史遗留的坏图 → 首次读到时会**自动从 IndexedDB 删除**，并跳过该张
- 本地图解码失败 → 跳过该张，尝试下一张，不推游标

**保证用户在前台永远看不到解码失败的图。**

### 缓存对加载速度的影响

**基本无感知。** 缓存操作都是异步的，不阻塞页面渲染：

- 首屏图显示后，缓存 / 预取都在后台跑
- 背景图用 `background-image` 而非 `<img>`，不参与 `window.load` 事件
- preloader 会等待首屏背景 / Banner 图就位后再消失（上限回访 2.5s / 首次 4.5s / 硬兜底 4.5s）

---

## 🖼️ 缩略图机制

后台的图片管理 / 看板娘 / 本地背景三个页面的网格里，显示的都是**缩略图**，不是原图。

### 为什么要缩略图

原图可能是 4K 大图（10MB 以上），网格里只需显示 110×110 的小方块。直接加载原图会导致：

- 切 tab 时浏览器要**同时解码几十张大图**，主线程阻塞，感觉明显卡顿
- 内存 / GPU 占用暴涨

### 缩略图规格

| 项 | 值 |
|---|---|
| 长边 | **400px**（短边按比例缩放，**不放大**） |
| 格式 | 保持原格式（jpg / png / gif / webp） |
| 质量 | JPEG / WebP 85%，PNG 压缩级别 8 |
| 存储位置 | 与原图同层的 `_thumbs` 目录，同名文件 |
| 大小 | 一般 20~50KB（原图的 5%~10%） |

### 目录映射规则

| 原图目录 | 缩略图目录 |
|---|---|
| `photos/` | `photos/thumbs/` |
| `photos/kanban/` | `photos/kanban_thumbs/` |
| `photos/localbg/` | `photos/localbg_thumbs/` |
| `photos/localbanner/` | `photos/localbanner_thumbs/` |

### 缩略图生成

- **上传时**：立即生成
- **从 API 抓取时**：立即生成
- **手动放入目录**：刷新后台时自动补生成
- **GIF**：只取**第一帧**作为缩略图
- **每请求上限**：**30 张**，防止一次性生成太多把 PHP 内存打爆

### 缩略图优先使用

生成缩略图时，程序按 **Imagick → GD** 顺序尝试：

- **Imagick**（推荐）：用磁盘缓存，处理大图时几乎不吃 PHP 内存
- **GD**：PHP 默认扩展，处理 4000×3000 以内较稳；再大会主动跳过（避免 OOM）
- **两者都没装**：不生成缩略图，网格里退回显示原图

### 缩略图的生命周期

- 上传 / 抓取 → 生成
- 删除原图 → 缩略图一并删除
- 重命名原图 → 缩略图同步重命名
- 清除数据 → 缩略图目录一并清空
- 手动删除缩略图目录 → 下次刷新后台自动重建

### 网格渲染优化

后台 CSS 里给网格单元加了原生性能优化：

```css
.photo-cell {
    content-visibility: auto;
    contain-intrinsic-size: 0 110px;
}
```

浏览器会自动跳过屏幕外元素的布局与绘制，缩略图多时滚动更顺滑。

---

## ⚡ 首屏加载优化

### 1. 请求提前到解析阶段

`window.NUOMI_CONFIG` 与首图预热 `fetch` **提前到 `<head>`**，比原来的 `DOMContentLoaded` 早 200~500ms 发起请求。预热请求超时 4 秒。

### 2. 加载动画等真正"可见"再收工

- 首次等待上限 **4.5s**（`FP_TIMEOUT = 4500`），回访等待上限 **2.5s**
- 硬兜底 **4.5s**
- `nuomi-first-paint` 事件改为**等 `img.decode()` 完成**才触发

### 3. 预热结果复用

`__nuomiWarmupBg` / `__nuomiWarmupBanner` 全局预热 Promise 会被首次取图直接消费。

### 4. 修复 `cacheLimit` 失效 bug

原来 `NuomiPool` 的 IIFE 在 `<head>` 里立刻执行，此时 `window.NUOMI_CONFIG` 还没被赋值 → `cacheLimit` 永远读默认值 8。现在配置提前到 `<head>`。

### 5. 修复轮播状态机与内存泄漏

- **并发拦截防丢失**：修复了在网络极快或用户快速点击开启轮播时，并发切换导致 `currentKey` 状态错乱，进而误删正在显示的图片的 Bug。
- **Blob URL 释放**：彻底修复了预取图片进入待播队列时如果发生溢出或切换失败，Blob URL 未释放导致的内存泄漏问题。

### 6. 用户可编辑内容的安全注入

所有注入到 `<script>` 的 JSON 统一带 `JSON_HEX_TAG`：

- 把 `<` `>` 转成 `\u003C` `\u003E`，`JSON.parse` 之后值完全一致
- 卡片名称、副标题、分类名、语录、看板娘名称、加载页名称/描述、UA 模板名里即使填了 `</script>`，也不会提前闭合页面中间注入的配置脚本
- 加载页 CSS 里正常的 `.a > .b` 子选择器不会破坏页面结构

### 7. 智能预热：池满 / 本地优先不再白下载

`<head>` 的预热请求加了 `shouldWarmup(type)` 判断，由 `localStorage.nuomi_pool_full_<type>` 标记驱动：

- **池未满**（`'0'`）→ 照常预热
- **池已满**（`'1'`）→ 跳过预热
- **本地优先** → 跳过预热

**效果**：日常回访流量从 4MB+ 降到接近 0，首次访问和补池体验完全不变。

### 8. 弱网/慢网下的秒级降级与防黑屏

- **请求超时策略**：
  - API 预热超时 **4 秒**
  - 首次取图 API 超时 **2.5 秒**；轮播取图超时 **5 秒**；重试最多 3 次
  - 本地图 **首屏 1.5 秒**、**后续 2.5 秒**超时
- **本地图终结死等**：弱网卡住时按上述超时强制中断，立刻回退到 API 或池里的图。
- **黑名单智能判定**：弱网超时**不会**将本地图片拉黑，只有确认损坏、404 或解码失败时才会拉黑。
- **连续超时/失败保护**：`poolPickSequential` 连续超时 2 次即放弃、`pickNextFromQueue` / `getLocalFallbackPick` 连续失败 3 次即放弃并推进 cursor。
- **首屏加载死锁解除**：加载页硬兜底 **4.5 秒**。
- **优雅的底色兜底**：无图时底色由纯黑改为预置的渐变。
- **Banner 无图静默**：Banner 彻底加载失败时保持完全透明。
- **超时不累加退避**：`verifyDecodable` / `doSwitch` / `applyNext` 全部返回 `{ ok, timeout }` 结构化结果。超时**不累加 failCount**。
- **横竖屏切换重置退避**：`orientationchange` 时重置 `bgRotator` / `bannerRotator` 的 `failCount`。
- **后台补池不阻塞预取**：`backgroundFillPool` 有独立 `bgFillPending` 节流标志。
- **间隔从 tick 开始计时**：`scheduleNext` 记录 `tickStartAt`。

---

## 💬 语录配置结构

`admin/data.json` 中的 `tips` 对象保存了看板娘语录配置：

```json
{
  "tips": {
    "enabled": true,
    "duration": 3000,
    "texts": ["你好呀，我是这里的小助手～", "..."],
    "periodTexts": {
      "night": ["夜深了，早点休息哦 🌙", "..."],
      "morning": ["早上好呀，今天也是新的一天 ☀️", "..."],
      "noon": ["中午好，记得吃午饭哦 🍚", "..."],
      "afternoon": ["下午好呀，喝杯水休息一下～", "..."],
      "evening": ["晚上好呀，辛苦一天啦～", "..."]
    },
    "api": "https://v1.hitokoto.cn/?c=b",
    "apiField": "hitokoto"
  }
}
```

| 字段 | 说明 |
|------|------|
| `enabled` | 是否默认开启随机语录 |
| `duration` | 语录显示时长（毫秒） |
| `texts` | 通用语录数组 |
| `periodTexts` | 分时段语录 |
| `api` | 可选，远程语录 API 地址 |
| `apiField` | 从 API 返回 JSON 中取哪个字段作为语录文本（默认 `text`） |

### 本地 / 远程语录的显示规则

前台 `mascot.js` 采用「本地 + 远程交替出场」的策略：

1. **首次显示**：走本地
2. **之后**：本地 → 远程 → 本地 → 远程 …… 严格交替
3. **远程失败或超时（1.5 秒）**：自动回退本地
4. **未配置 API**：始终走本地

### 关于一言 API

`https://v1.hitokoto.cn/` 的响应头**没有** `Access-Control-Allow-Origin`，浏览器在前台直接 `fetch` 它会被同源策略拦截。推荐的做法：

**方案一：换一个支持 CORS 的镜像**

- `https://60s.viki.moe/v2/hitokoto`（字段名 `hitokoto`）
- `https://hitokoto.mayx.eu.org`

**方案二：自己搭一个 PHP 代理**

在网站根目录放一个 `tips_proxy.php`：

```php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
$url = 'https://v1.hitokoto.cn/?c=b&encode=json';
$ctx = stream_context_create(['http' => ['timeout' => 5]]);
$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) { echo json_encode(['error' => 'fetch failed']); exit; }
echo $raw;
```

后台语录 API 填 `https://你的域名/tips_proxy.php`，字段名填 `hitokoto`。

---

## 👘 看板娘功能详解

### 前台显示与交互
- **位置记忆**：右下角锚点方案，位置以 `hx / hy` 归一化比例存储在 `localStorage.board_girl_pos`
- **自适应尺寸**：根据屏幕宽高比自动缩放（横屏 56~96px，竖屏 90~120px）
- **长按拖动**：按住 350ms 后进入拖动模式，松手保存位置
- **点击看板娘**：显示 / 切换随机语录；若面板已打开则关闭面板
- **语录气泡**：宽度自适应内容，中文断行优化，洗牌队列保证不连续重复，点击气泡可切换下一条
- **气泡上的齿轮按钮**：点击打开控制面板
- **迷你按钮**：看板娘未启用且仍有启用的 JS 特效时，前台右下角显示 ✨ 迷你按钮
- **图片加载重试**：形象图加载失败时会自动重试（最多 2 次，间隔 600ms 递增），重试仍失败则降级为圆形 ✨ 悬浮按钮
- **延迟加载机制**：看板娘脚本通过 `preload` 提前下载，但执行时机被推迟到首屏加载页完全结束（`page-ready`）后

### 控制面板
- 🎀 看板娘显隐开关
- ✨ JS 特效开关列表
- 💬 随机语录开关
- 👘 切换形象
- 📍 重置看板娘位置

### 添加看板娘
- 将 GIF / PNG / WebP / JPG 放入 `photos/kanban/`，刷新后台自动识别
- 或在后台「看板娘」标签页上传
- 内置可用形象：`妹妹.gif` / `姐姐.gif` / `喵小娘.gif` / `月薪喵.gif` / `七濑胡桃.gif` / `舞娘娘.gif` / `柠檬妹.gif` / `熊宝宝.gif`

---

## 🎨 加载页主题开发

1. 编写一个纯 CSS 文件，只包含样式规则，不要有 `<style>`、`<script>`、`<?php` 等标签。
2. 文件顶部可选写注释：
   ```css
   /* @name 主题名称 */
   /* @description 一句话描述 */
   ```
3. 将文件放入 `assets/preloaders/`，或后台上传。
4. 后台会自动识别并生成 `key`（文件名去扩展名）。
5. 主题内可使用以下预置结构：
   - `#preloader`：全屏遮罩容器
   - `.preloader-content`：内容容器
   - `.preloader-avatar`：头像容器
   - `.preloader-title`：标题容器
   - `.preloader-progress`：进度条轨道
   - `.preloader-progress-bar`：进度条填充
6. 支持 `html[data-theme="dark"]` 深色模式选择器，以及 `#preloader.is-revisit` 回访状态。
7. 正常的 `.a > .b` 子选择器可以放心使用，注入时会被安全转义。

> 内置主题：`sakura-spin`、`elegant`、`apple`、`tech`、`cyberpunk`、`ink`、`minimal`、`nature`、`neon`、`sakura`、`starry`、`vaporwave`。

---

## 🧩 JS 特效开发指南

1. 编写一个自执行函数，导出 `start`、`stop`、`toggle` 方法：

```js
(function () {
  let running = false;

  const Effect = {
    start() {
      if (running) return;
      running = true;
    },
    stop() {
      if (!running) return;
      running = false;
    },
    toggle() {
      running ? this.stop() : this.start();
    }
  };

  Effect.start();

  window.EffectsRegistry = window.EffectsRegistry || [];
  window.EffectsRegistry.push({
    name: 'myEffect',
    module: Effect
  });
})();
```

2. 将文件放入 `assets/js/`，或后台上传 / URL 添加。
3. 后台会自动提取 `name` 作为 `matchKey`。
4. 特效开关状态保存在 `localStorage.effects_states`。
5. **加载顺序**：`frontend.js` → 各特效 JS → `mascot.js`。
6. **延迟执行**：所有特效脚本都会在首屏加载页结束后（`body.page-ready`）由 `index.php` 中的内联脚本动态注入执行。

### 内置特效示例

| 文件 | `name` | 效果 | 类型 |
|------|--------|------|------|
| `粒子微粒.js` | `particledots` | 粒子微粒漂浮，鼠标排斥、点击爆破 | 可控 |
| `天气.js` | `weather` | 心知天气气泡小部件 | 可控 |
| `雪花飘落.js` | `snowfall` | 雪花自上而下飘落 | 可控 |
| `烟花波纹.js` | `firework` | 点击 / 长按生成烟花粒子 | 可控 |
| `樱花飘落.js` | `sakura` | 樱花花瓣随风飘落 | 可控 |

---

## 🌓 主题与本地存储

**主题系统**（前台 / 后台共用同一份偏好）：

- **偏好键**：`localStorage.themeMode`
- **可选值**：`auto` / `light` / `dark`
- **根元素属性**：`html[data-theme="light"]` / `html[data-theme="dark"]`
- **防闪烁**：内联解析逻辑在 `<head>` 最前方，CSS 解析前即确定主题

其余本地存储：

- **轮播模式**：`localStorage.autoLoopMode` = `off` / `on` / `fresh`
- **图片缓存（IndexedDB）**：数据库 `nuomi_img_cache`，对象仓库 `images`
- **上次预取 key**：`nuomi_last_prefetch_bg` / `nuomi_last_prefetch_banner`
- **上次停留 key**：`nuomi_last_shown_bg` / `nuomi_last_shown_banner`
- **顺序取池游标**：`nuomi_last_pool_bg` / `nuomi_last_pool_banner`
- **本地优先队列游标**：`nuomi_queue_cursor_bg` / `nuomi_queue_cursor_banner`
- **本地优先显示 ID**：`nuomi_local_shown_bg` / `nuomi_local_shown_banner`
- **本地优先预取 ID**：`nuomi_local_prefetch_bg` / `nuomi_local_prefetch_banner`
- **本地兜底游标**：`nuomi_local_fallback_idx_bg` / `nuomi_local_fallback_idx_banner`
- **坏本地图黑名单**：`nuomi_bad_local_bg_<signature>` / `nuomi_bad_local_banner_<signature>`
- **池满标记**：`nuomi_pool_full_bg` / `nuomi_pool_full_banner` = `'0'` / `'1'`
- **看板娘位置**：`board_girl_pos`
- **看板娘形象索引**：`board_girl_index`
- **看板娘语录开关**：`board_girl_tip_enabled`
- **看板娘显隐**：`board_girl_visible`
- **特效开关状态**：`effects_states`
- **特效关闭 Cookie**：`nuomi_effects_off`
- **加载页主题偏好**：`preloader_style_pref`
- **后台当前标签**：`nuomi_admin_tab`
- **后台站点设置子标签**：`nuomi_admin_site_subtab`
- **首页公告条已关闭列表**：`nuomi_home_ann_dismissed`（JSON 数组，记录已关闭的公告 ID，最多保留 20 条）

---

## 📌 路径规则

1. `data.json` 中统一存储**网站根目录相对路径**：`photos/xxx.png`、`assets/js/xxx.js`、`photos/kanban/xxx.gif`、`photos/localbg/xxx.jpg`。
2. 后台运行在 `admin/` 子目录，PHP 渲染时会自动拼接 `../` 用于显示，**保存 JSON 不会写入 `../`**。
3. `http://` 或 `https://` 开头的 CDN 图片 / JS 原样使用，不做路径转换。
4. 前台直接使用这些根目录相对路径，不要写成 `../photos/xxx`。
5. **默认 favicon / adminAvatar 使用不带前导 `/` 的形式**（`photos/QQ.jpg`），根目录和子目录部署都能正确解析。老版本存成 `/photos/QQ.jpg` 也照常使用。
6. 缩略图路径**不落盘**，由原图路径推导。

---

## 🔄 请求流程图

### API 优先（默认）

```text
首屏
 ├── 池有历史图？
 │    ├── 有 → 显示 lastShownKey（秒显）
 │    │        秒显成功后 setLastShownKey(showKey)（幂等）
 │    │        确定待播槽（nextReady）：
 │    │          ├── lastPrefetchKey 仍在池中且不同于 showKey → 直接复用它
 │    │          └── 否则 prefetchNext() 从池里取一张进待播槽
 │    │        等 preloader 消失 → 再等 1.5s → 平滑切到待播槽里的那张
 │    │        切换成功后 setLastShownKey(刚切到的那张)
 │    │        立即 prefetchNext() 再取一张进待播槽（永远有下一张）
 │    └── 无 → 走 fetchWithRetry
 │              ├── 预热命中 → 验证 → 入池 → 显示
 │              ├── API 成功 → 验证 → 入池 → 显示
 │              └── API 重试耗尽 → 本地兜底 → 空白（深灰蓝底色）
轮播中
 └── 每次切换 → doSwitch 验证 → 从待播槽取图
                ├── 切换成功后 setLastShownKey(刚切到的那张)
                ├── 立即 prefetchNext() 补下一张进待播槽
                └── 槽为空 → API 拉新 → 失败则从池里顺序取
```

### 本地优先

```text
首屏
 ├── 本地按顺序取（优先取 target === 'both' 或 target === 当前屏幕方向的图）
 │    ├── 交叉兜底：当前方向没图时，自动拉取反方向的图顶上
 │    ├── 缓存命中 → 秒显
 │    ├── 缓存未命中 → fetch (首屏 1.5s / 后续 2.5s 超时) → 缓存 → 显示
 │    └── 弱网熔断：连续超时失败后立刻回退 API
轮播中
 └── 每次切换 → doSwitch 验证 → 本地按顺序取下一张
                ├── 切换成功后 setLocalShownId(刚切到的那张)
                └── 本地全挂 → 回退 API
```

### 持续新图（fresh）

强制忽略后台「本地优先」设置，等同于 API 优先的轮播行为，每轮都尽量拉新图。

---

## 📢 公告系统

公告系统由三部分组成：

### 1. 后台「📢 公告管理」标签页

- 增删改查、置顶、启用 / 禁用
- 公告内容支持 HTML，不允许 `<script>`
- 四种类型：通知 / 成功 / 警告 / 错误

### 2. 首页公告条

- 在首页顶部（`.header` 之前）显示**最新一条启用的公告**
- 排序规则与公告页一致：置顶优先 + 创建时间倒序
- 显示内容：类型徽章 + 标题 + 「查看详情 →」链接 + 「✕」关闭按钮
- **关闭记忆**：点击「✕」后，公告 ID 写入 `localStorage.nuomi_home_ann_dismissed`（JSON 数组，最多保留 20 条），同一条公告不再显示
- **新公告自动恢复**：新公告有新的 ID，会重新显示
- **移动端**：隐藏类型徽章，更紧凑
- **主题**：浅色为白底 + 蓝紫左边框，深色为深灰底 + 浅紫左边框

### 3. 前台 `/announcement.php`（列表风格）

- 公开访问，无需登录
- 只显示 `enabled: true` 的公告
- 按「置顶优先 + 创建时间倒序」排序
- **列表风格**：时间在左上、类型徽章紧随其后、标题大字、正文紧凑，接近 api.sretna.cn 的阅读体验
- 主题自动跟随 `localStorage.themeMode`（浅色 / 深色）
- 页脚显示站点名 + Powered by
- 空状态显示「📭 暂无公告」

### 4. 404 页跳转

`404.php` 的「查看公告」按钮跳转到 `announcement.php`（使用相对路径，子目录部署兼容）。

### 数据结构

```json
{
  "announcements": [
    {
      "id": "ann_xxx",
      "title": "欢迎使用糯米の引导页",
      "content": "<p>这是站点公告，支持简单的 HTML。</p>",
      "type": "info",
      "pinned": true,
      "enabled": true,
      "created": "2026-10-25 14:30:00",
      "updated": "2026-10-25 14:30:00"
    }
  ]
}
```

---

## 🔄 在线升级机制

在线升级由 `admin/update.php`（前端页面）+ `admin/index.php` 的 5 个 `update_*` API（后端逻辑）+ `admin/.update/`（工作目录）三部分组成。

### API 一览

| Action | 说明 |
|---|---|
| `update_check` | 拉取 GitHub Releases 最新版本（5 分钟缓存），返回版本对比 + 更新说明 + 下载地址 + 环境检测结果 |
| `update_download` | 下载 zip → SHA256 校验 → 解压到 `admin/.update/package/`，状态转 `downloaded` |
| `update_apply` | 备份当前代码 → 逐个覆盖 → 成功则 `success`，失败自动回滚 |
| `update_rollback` | 用最近一次备份覆盖当前代码，状态转 `rolled_back` |
| `update_status` | 返回当前状态机、是否有可回滚备份、当前版本 |

### 状态机

```text
idle → downloading → downloaded → applying → success
                                          ↘ failed
                                          ↘ rolled_back（手动触发）
```

- `applying` 超过 10 分钟自动转 `failed`
- `state.json` / `history.json` 用 tmp + rename 原子写入
- 并发锁 `flock(LOCK_EX | LOCK_NB)`，拿不到锁时直接返回「已有升级进程正在进行」

### GitHub API 对接

- **接口**：`https://api.github.com/repos/{UPDATE_GITHUB_REPO}/releases/latest`
- **请求头**：必须带 `User-Agent`（GitHub 强制要求），已内置 `NuomiNav-Updater/1.0`
- **版本号**：从 `tag_name` 读，`ltrim($tag, 'vV')` 转成纯版本号（兼容 `v1.2.0` 和 `1.2.0`）
- **下载地址**：从 `assets` 数组里找第一个 `.zip` 结尾的 `browser_download_url`
- **SHA256**：如果 asset 的 `digest` 字段是 `sha256:xxx` 格式，自动校验
- **缓存**：`admin/.update/github_release_cache.json`，5 分钟内不重复请求（避免触发 GitHub 60 次/小时速率限制）
- **更新说明**：从 `body` 字段读（Markdown 格式，前端按纯文本展示）

### 环境检测

`update_env_check()` 会在项目根目录和 `admin/` 目录分别做一次「创建 → 删除」测试文件，判断 PHP 运行用户是否有写权限：

- ✅ 通过 → 返回 `writable: true`，前端显示「⬆ 立即升级」
- ❌ 失败 → 返回 `writable: false` + `reason`（含 PHP 运行用户 / 文件所有者），前端显示「🔗 去 GitHub 下载」

### 安全防护

| 防护 | 说明 |
|---|---|
| 路径穿越 | 拒绝 `..`、绝对路径（`/` 开头）、Windows 盘符（`C:`） |
| 下载白名单 | 只认 `github.com` / `raw.githubusercontent.com` / `objects.githubusercontent.com` / `codeload.github.com` |
| 覆盖白名单 | 仅允许覆盖 `admin/`、`assets/`、`index.php`、`404.php`、`announcement.php`、`analytics.php` |
| 保留清单 | `admin/data.json`、`admin/.admin.conf`、`admin/.update/`、`photos/`、`assets/js/`、`assets/preloaders/`、`README.md`、`LICENSE`、`CHANGELOG.md` **永不覆盖** |
| 大小限制 | 下载 zip 上限 200MB（`UPDATE_MAX_PACKAGE_MB`），解压后总大小上限 200MB（`UPDATE_MAX_EXTRACTED_MB`） |
| 并发锁 | `flock(LOCK_EX \| LOCK_NB)` |
| 原子写入 | `state.json` / `history.json` 用 tmp + rename |
| 后台 JS 隔离 | 升级页面的 JS 只调用自己的 5 个 `update_*` API，不直接请求 GitHub |

### 升级历史

每次升级成功 / 回滚都会往 `admin/.update/history.json` 里追加一条记录（最多保留 50 条），包含：时间、从哪个版本、到哪个版本、状态、应用文件数、备份目录。

### 修改 GitHub 仓库地址

默认对接 `nmrt0316/MochiNav`。想改成自己的仓库：打开 `admin/defaults.php`，找到：

```php
if (!defined('UPDATE_GITHUB_REPO')) {
    define('UPDATE_GITHUB_REPO', 'nmrt0316/MochiNav');
}
```

把 `nmrt0316/MochiNav` 改成你自己的 `owner/repo` 即可。

---

## ❓ 常见问题

**Q：未安装时访问前台会怎样？**  
A：会自动 302 到 `/admin` 弹出 4 步安装向导。

**Q：后台无法保存数据？**  
A：检查 `admin/` 目录是否有写权限，`data.json` 是否可写。

**Q：图片上传失败？**  
A：检查 `photos/` 目录写权限，以及 PHP 上传大小限制（默认 5MB）。

**Q：为什么后台白屏，报 `Cannot redeclare site_defaults()`？**  
A：`admin/index.php` 里的旧版四个 `*_defaults()` 函数定义未删除干净。确保 `admin/index.php` 只 `require_once __DIR__ . '/defaults.php';`，不再重复定义。

**Q：为什么后台白屏，报 `Call to undefined function site_defaults()`？**  
A：`admin/defaults.php` 未被正确 `require`。检查 `admin/index.php` 顶部有 `require_once __DIR__ . '/defaults.php';`。

**Q：.admin.conf / data.json 能被直接下载吗？**  
A：Apache 用户上传 `.htaccess` 后自动保护。**Nginx 用户需要在站点配置里手动加 location 规则**（见上方「Nginx」章节）。验证：浏览器访问 `/admin/data.json`，应返回 403 或 404。

**Q：如何替换默认的 GitHub 链接？**  
A：打开 `admin/defaults.php`，找到 `'githubUrl'` 和 `'poweredByUrl'`，把 `YOUR_USERNAME/YOUR_REPO` 换成真实仓库地址。**已安装的用户**：到后台「站点设置」手动改一次即可。

**Q：公告内容支持 Markdown 吗？**  
A：不支持。公告内容直接作为 HTML 输出（管理员信任模型），可使用 `<p>` / `<strong>` / `<a>` / `<ul>` / `<li>` / `<code>` / `<pre>` / `<img>` 等标签。`<script>` 标签被拒绝。

**Q：404 页「查看公告」跳到哪？**  
A：跳转到站内 `/announcement.php`。需在 Apache / Nginx 中配置 `ErrorDocument 404 /404.php` 才能让 404.php 生效。

**Q：切「图片管理 / 看板娘 / 本地背景」时还是有点卡？**  
A：检查：
1. `photos/*/_thumbs/` 目录里是否有对应的缩略图文件
2. 如果没有，多刷新几次后台（每请求最多生成 30 张）
3. 如果一直生不成，说明 PHP 内存限制太紧，去面板把 `memory_limit` 调到 512M，或安装 Imagick 扩展

**Q：缩略图长边可以改吗？**  
A：可以。打开 `admin/index.php`，找到 `define('THUMB_MAX_SIZE', 400);` 改数字即可（推荐 300~600）。

**Q：为什么有些图没有缩略图，网格里显示的还是原图？**  
A：说明这些图**太大了**，超过了 PHP 能处理的内存 / 时间上限，程序主动跳过了。

**Q：缩略图删了原图还能显示吗？**  
A：不能。缩略图是**派生物**，原图被删后下次刷新会清理掉对应缩略图；反过来也一样，删掉缩略图文件夹后原图照样显示。

**Q：删除图片时提示"被使用中"是怎么回事？**  
A：说明这张图片正在被以下位置引用之一：卡片图标、卡片弹窗大图、分类图标、站点设置里的前台头像 / 网站图标 / 后台头像。删除流程分两层确认，第二层会列出所有引用位置，确认后才会真正删除（同时清空对应引用）。

**Q：安装了 Imagick 后还是报内存错误？**  
A：检查 Imagick 是否真的在 PHP 里生效。临时放一个 `phpinfo.php`（内容 `<?php phpinfo();`）访问它，搜索 `imagick`，看有没有出现对应的 section。用完记得删除。

**Q：看板娘不显示？**  
A：检查后台「看板娘」是否启用，`photos/kanban/` 中是否有图片。如果形象图加载失败，会自动降级为圆形 ✨ 按钮。

**Q：看板娘语录不显示？**  
A：检查控制面板中「随机语录」开关是否打开，或检查 `localStorage.board_girl_tip_enabled` 是否为 `0`。

**Q：远程语录 API 填了但没有效果？**  
A：两个常见原因：
1. **字段名不对**：一言接口的正文在 `hitokoto` 字段里，不是 `text`
2. **CORS 跨域被拦截**：换用支持 CORS 的镜像，或自己搭 PHP 代理

**Q：JS 特效不生效？**  
A：依次确认：
1. JS 文件已注册到 `window.EffectsRegistry`，`matchKey` 与脚本里的 `name` 一致
2. 后台「JS 特效」标签页里该特效处于启用状态
3. 浏览器控制台（F12）里能搜到该特效的注册对象
4. 用户**没有**在前台控制面板里关掉过它

**Q：为什么开关某些 JS 特效时会刷新页面？**  
A：`mascot.js` 按模块是否同时暴露 `start` 和 `stop` 区分两类特效：
- **可控特效**（同时有 `start` / `stop`）：面板里的开关直接调用方法，即时开停、不刷新
- **固定特效**（无成对方法）：面板里的开关走刷新路径，先写 cookie 再刷新

**Q：后台手动改了 matchKey，为什么刷新后台后又被改回去了？**  
A：**现在不会了。** 当前版本只在 matchKey **为空**时才自动提取（首次识别新特效），已有值永久保留。如果觉得当前值不对，点编辑弹窗里的「🔄 自动读取」按钮主动从脚本重新提取一次。

**Q：点开特效开关后，特效名旁边出现红色 ⚠ 图标，开关点不动？**  
A：说明脚本已经加载到前台，但 `EffectsRegistry` 里注册的 `name` 与 `data.json` 里的 `matchKey` 对不上。修复步骤：去后台「✨ JS特效」标签页，点开对应特效的编辑弹窗，点「🔄 自动读取」按钮，保存后刷新前台。

**Q：背景/Banner 图不换 / 卡顿？**  
A：检查后台「站点设置 → 前台 → 轮播接口」中：
- API 地址是否有效
- 轮播间隔是否合理（3~60 秒）
- 若拉不到新图，会从缓存池按顺序取图兜底

**Q：前台一直显示本地图，不显示 API 图？**  
A：检查后台「🖼 本地背景」的优先级是否误设为「本地优先」，或前台轮播模式是否为 `持续新图`。

**Q：为什么弱网下不显示本地图，而是显示了 API 图 / 渐变底色？**  
A：为了极速秒开，本地图的超时被严格限制在 **首屏 1.5 秒 / 后续 2.5 秒**。超时失败**不会**把本地图拉黑，只有确认 404 / 解码失败才会拉黑。

**Q：为什么本地优先模式下，切图间隔有时比后台设置的要慢很多？**  
A：老版本有 3 个原因叠加，**当前版本已修复**：
- 后台补池用独立节流标志，不阻塞预取
- 超时不累加退避，仍按 `interval` 走
- `scheduleNext` 从 tick 开始计时

**Q：从 API 抓取本地背景失败？**  
A：可能原因：
1. 站点设置里没填背景 / Banner API 地址
2. 服务器无法访问外网（`allow_url_fopen` 关闭或防火墙）
3. API 本身不稳定，抓取时正好挂了
4. 当前 UA 模板被 API 拒绝

**Q：抓取本地背景时默认用的是什么 UA？**  
A：默认 **背景走 `ua_mobile_default`（移动端，抓竖图）**，**Banner 走 `ua_desktop_default`（桌面端，抓横图）**。

**Q：我在 PC 上抓取本地背景，抓到的都是竖屏？**  
A：这是**默认行为**——背景抓取默认走 `ua_mobile_default`。想让背景也抓横图，去「🎭 UA 伪装」页把背景使用的模板切到 `ua_desktop_default`。

**Q：Banner 抓取为什么默认是横屏？**  
A：因为 `localBannerUaId` 默认是 `ua_desktop_default`。Banner 区域是横向轮播，只展示 `desktop` / `both` 的横图。

**Q：如何让同一张图在 PC 和手机都显示？**  
A：手动上传宽高接近正方形的图（会自动打 `both`），或上传后点图片的 ✎，把 target 手动设为 `both`。

**Q：点「自动分类」后，网格里的图片顺序会变吗？**  
A：不会改变 `data.json` 里 `images[]` 的原始存储顺序。只是**展示层按设备方向分组显示**。

**Q：点「自动分类」后网格里为什么分成了三块？**  
A：为了让桌面端、移动端、通用三类图片一目了然。每个分组前面有一行小标题，分组内部依旧支持拖拽排序。

**Q：点「自动分类」后 toast 提示是黄色 / 红色是怎么回事？**  
A：**绿色** = 全部成功；**黄色** = 部分成功（有图片读取失败）；**红色** = 全部失败。识别失败的图**不会被改动 `target`**。

**Q：我以前抓的图没有 `target` 字段怎么办？**  
A：完全兼容。老数据缺 `target` 字段时自动视为 `both`。

**Q：我手动把某张 Banner 本地图的 target 改成了 `mobile`，会有什么问题？**  
A：前台 Banner 区域优先只取 `desktop` 和 `both` 的图。如果**所有** Banner 本地图都被改成 `mobile`，系统会退回使用全部图。

**Q：本地背景上传或抓取提示重复？**  
A：系统会按内容 MD5 去重。

**Q：轮播按钮为什么有三种状态？怎么进入第三个状态？**  
A：
- `关闭`：不轮播（播放图标）
- `开启`：点击开启，按后台优先级轮播（暂停图标）
- `持续新图`：**长按该按钮 3 秒**进入，强制 API 优先

**Q：池满后回访还会请求 API 吗？**  
A：**不会**。`<head>` 预热会读 `localStorage.nuomi_pool_full_<type>`，池满时直接跳过预热。

**Q：为什么清空浏览器数据后，第一次刷新没有预热？**  
A：`localStorage` 的标记被清空，但 IndexedDB 的池也同时被清空，此时 `init()` 发现池空会走 `fetchWithRetry()` 直接拉图，功能不受影响。

**Q：本地图占多少空间？**  
A：每张按 500KB 估算，若背景 20 张 + Banner 20 张，约 20MB。

**Q：想清空本地图的浏览器缓存怎么办？**  
A：F12 → Application → IndexedDB → `nuomi_img_cache` → `images`，删除以 `bg_local:` / `banner_local:` 开头的键。

**Q：本地图换了新文件，前台还显示旧图？**  
A：缓存 key 是 `filepath`，改文件名 → 新 key → 前台下次走新路径拉图。

**Q：为什么切到本地优先后，池里之前的 API 图还占着空间？**  
A：API 池的 FIFO 只在**下次入池时**触发淘汰。

**Q：图片缓存占多少空间？**  
A：每个池上限由 `cacheLimit` 控制（默认 8），背景 / Banner 各一个池。按单图 500KB 估算，8 张两池约 8MB。

**Q：想清空图片缓存怎么办？**  
A：直接在浏览器里「清除站点数据」，或把后台缓存数量改小，刷新前台后 FIFO 会自动淘汰超出的旧图。

**Q：前台和后台的深色/浅色模式不同步？**  
A：正常情况下两者共用 `localStorage.themeMode` 键。若发现不同步，检查：
1. 浏览器是否阻止了 localStorage 写入
2. 前后台是否用了不同域名 / 子域名
3. 是否在两个标签页同时打开（切换后需要刷新另一侧才同步）

**Q：电脑端切换分类时为什么会先"上滚一下"？**  
A：这是预期行为。当从"卡片多"的分类切到"卡片少"的分类时，页面新高度会变短。现在锁住文档高度，让滚动位置与卡片高度、垂直居中 margin 一起走 620ms 缓动。

**Q：为什么在手机端长按页面底部，不会再出现奇怪的选字光标或深色方块了？**  
A：在 `style.css` 中，精准锁定了 `#app`、`.bg-stage` 等纯 UI 容器的 `user-select: none;`。同时 `.app-name` 等文字类元素依然保留了 `user-select: text;`。

**Q：卡片名 / 语录里填了 `<script>` 会怎样？**  
A：会被安全转义成 `\u003Cscript\u003E`，不会执行，也不会破坏页面结构。

**Q：后台拖拽排序后点击其他按钮没反应？**  
A：旧版本会在拖拽结束后的 400ms 内拦截**整个页面**的点击，导致误吞。现在已修复：只拦截拖拽元素内部的点击。

**Q：忘记后台密码？**  
A：删除 `admin/.admin.conf`，重新访问后台进行初始化。

**Q：清除数据的确认词是什么？**  
A：指定内容 / 清除全部输入 `RESET`；完全清空输入 `CLEAR`。

**Q：清除数据会清掉 UA 模板 / 公告吗？**  
A：指定内容模式勾选「UA 模板」会清除自定义模板，仅保留内置模板；勾选「公告」会清空所有公告。清除全部 / 完全清空也会重置或清空 UA 配置与公告。

**Q：内置 UA 模板能删除吗？**  
A：不能，只能编辑内容。自定义模板可以删除，删除后若正在使用会自动回退默认模板。

**Q：为什么 404 页面主题和前台不同步？**  
A：`404.php` 使用独立的 `localStorage.theme`，前台和后台使用 `localStorage.themeMode`。

**Q：如何让本地 JS 特效被后台识别？**  
A：直接把 `.js` 文件放入 `assets/js/`，刷新后台页面即可自动扫描。

**Q：加载页主题不生效？**  
A：检查：
1. 后台「加载页」标签页中，主题是否已启用
2. `assets/preloaders/` 目录下是否存在对应的 `.css` 文件
3. CSS 文件是否包含非法标签
4. 前台用户是否在底部加载动画按钮中选择过其他主题
5. 站点设置中首屏加载动画开关是否开启

**Q：为什么上传的加载页 CSS 没有出现在列表中？**  
A：后台只识别 `assets/preloaders/` 下的 `.css` 文件，且文件内容不能包含 PHP 标签或 `</style>`、`<script`、`</script`。

**Q：preloader 是什么？**  
A：preloader 是**首屏加载动画**——页面打开时先显示的那个全屏遮罩。它会等首屏背景 / Banner 图就位后再消失。

**Q：全站统计代码有什么限制？**  
A：只有三条：
1. 必须包含 HTML 标签
2. 不能包含 `<?php` 或 `<?=`
3. 无长度限制

**Q：统计代码为什么没在前台生效？**  
A：检查：
1. 后台「🌐 统计代码」标签页里是否已保存内容
2. 前台页面 HTML 源码的 `</body>` 前是否有你保存的代码
3. 浏览器 F12 Console 是否有报错
4. 是否被 CDN 的边缘缓存了旧版本 HTML

**Q：Cloudflare 已经在自动注入统计代码了，还能在后台填吗？**  
A：**不要同时填**。二选一。

**Q：本地兜底图会不会拖慢轮播？**  
A：不会。本地图只在 API 重试全败后启用，且首次加载后会写入 IndexedDB 缓存。

**Q：解码验证会不会消耗性能？**  
A：不会。每张图多一次 `new Image()` + `onload`，浏览器命中内存解码缓存，实测 <5ms。

**Q：为什么我点了后台的"自动分类"，前台还是有一些图不显示？**  
A：自动分类会按图片的宽高比例自动打上 `desktop` 或 `mobile` 标签。如果当前是电脑端，且后台全是竖图，前台会触发**交叉兜底**把竖图拉过来显示。

**Q：为什么生产环境访问出错时看不到 PHP 报错详情？**  
A：`admin/index.php` 顶部已关闭 `display_errors` 并开启 `log_errors`，PHP 报错只写入服务器错误日志。排查问题时去 PHP 错误日志找，或临时把 `display_errors` 改回 `'1'` 调试完再改回来。

**Q：子目录部署时默认图标为什么会 404？**  
A：老版本 `favicon` / `adminAvatar` 默认值写的是 `/photos/QQ.jpg`（带前导 `/`）。**当前版本默认值已改为 `photos/QQ.jpg`（不带前导 `/`）**，根目录和子目录部署都能正确解析。

**Q：删除图片时如何避免误删被引用的图？**  
A：删除流程有**两层确认**：
1. 第一层（常规确认）：防误点
2. 第二层（引用确认）：如果图片正在被引用，后端会拒绝首次删除并返回引用列表，前端自动弹第二个确认框

**Q：数据文件损坏怎么办？**  
A：从备份恢复，或临时把 `admin/data.json` 重命名为 `data.json.bak`，系统会重建空数据。

**Q：如何修改版本号？**  
A：打开 `admin/defaults.php`，修改 `define('NUOMI_VERSION', '1.1.0');` 这一行。后台顶部栏会自动显示新版本。

**Q：升级时保留哪些文件？**  
A：见上方「升级须知」章节。**必须保留**：`admin/data.json`、`admin/.admin.conf`、`admin/.update/`、`photos/`、`assets/js/`、`assets/preloaders/`。

**Q：`.gitignore` 里为什么排除了 `photos/*`？**  
A：因为 `photos/` 里大多数是用户上传的图片，不应提交到仓库。`photos/QQ.jpg`、`photos/zy.png`、`photos/lx.png` 通过 `!` 白名单保留。

**Q：首页公告条怎么关闭？关闭后还能恢复吗？**  
A：点击公告条右侧的「✕」即可关闭。关闭后会记录在 `localStorage.nuomi_home_ann_dismissed` 里，**同一条公告**不再显示。如果后台修改了公告内容（ID 不变）仍然不显示；如果是新公告（新 ID），会重新显示。想恢复某条已关闭的公告，清除浏览器站点数据即可。

**Q：首页公告条和公告页显示的是同一条公告吗？**  
A：是的，首页公告条只取**最新一条启用的公告**（置顶优先 + 创建时间倒序），公告页显示**所有启用的公告**。如果有多条公告，首页只展示第一条，其余需到 `announcement.php` 查看。

**Q：公告页为什么从卡片风格改成了列表风格？**  
A：列表风格更接近主流公告页的阅读体验——时间在左上、标题大字、正文紧凑，长文阅读不累。4 种类型仍通过左侧徽章和颜色区分。

**Q：怎么进入在线升级页？**  
A：后台顶部栏（登录后）点「版本号（如 v1.1.0）」胶囊按钮，或直接访问 `https://你的域名/admin/update.php`。

**Q：在线升级提示「环境不支持」怎么办？**  
A：说明 PHP 运行用户无法在项目根目录创建 / 覆盖 / 删除文件。常见于共享主机（PHP 运行用户 `www-data` ≠ 文件所有者 `nobody`）。三种解决方案：
1. 让主机商把项目目录所有者改为 PHP 运行用户
2. 给项目目录设置 `775` 权限并确保两者在同一用户组
3. 直接点「🔗 去 GitHub 下载」按钮，手动覆盖升级

**Q：在线升级会不会覆盖我的 data.json / 照片？**  
A：**不会**。升级过程会自动跳过 `admin/data.json`、`admin/.admin.conf`、`admin/.update/`、`photos/`、`assets/js/`、`assets/preloaders/`、`README.md`、`LICENSE`、`CHANGELOG.md`，即使 Release 包里带了这些路径也不会覆盖。

**Q：在线升级失败了怎么办？**  
A：升级失败会自动回滚到升级前的状态。如果回滚也失败，可以手动点「↩ 回滚到上一版本」用最近一次备份覆盖。备份默认保留最近 3 份（`UPDATE_BACKUP_KEEP`），存放在 `admin/.update/backup/<时间戳>/`。

**Q：升级成功但顶部栏版本号还是旧版本？**  
A：升级完成后按 **Ctrl+F5** 强制刷新后台，让浏览器加载新版本的 `admin.js` / `admin.css`。如果还是旧版本，说明服务器/CDN 缓存了旧 HTML，需要清除缓存。

**Q：在线升级请求 GitHub API 会不会触发速率限制？**  
A：不会。`update_check` 有 5 分钟本地缓存（`admin/.update/github_release_cache.json`），5 分钟内重复检查不会重新请求 GitHub。GitHub 未认证请求的速率限制是 60 次/小时，正常使用完全够。

**Q：能配置成自己的 GitHub 仓库吗？**  
A：可以。打开 `admin/defaults.php`，找到 `UPDATE_GITHUB_REPO` 常量，把 `nmrt0316/MochiNav` 改成你自己的 `owner/repo` 即可。

**Q：升级后想回退到旧版本怎么办？**  
A：进 `admin/update.php`，点「↩ 回滚到上一版本」按钮，会用最近一次备份覆盖当前代码。**注意**：回滚只回滚代码，不回滚 `data.json` / `photos/`（这些升级过程本来就没动过）。

---

## 📄 许可证

[MIT License](LICENSE)

---

**糯米の引导页** · 让导航页也充满仪式感 ✨