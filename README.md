# 糯米の引导页 · 个人导航站

[![Live Demo](https://img.shields.io/badge/🚀_在线演示-点击访问-blue?style=for-the-badge)](https://nmrt0316.dpdns.org)

![版本](https://img.shields.io/badge/version-1.1.3--beta.1-blue)
![PHP](https://img.shields.io/badge/PHP-7.3%2B-777bb4)
![License](https://img.shields.io/badge/License-MIT-green)

一个基于 PHP + JSON 的个人引导页/导航站，自带 PHP 后台管理面板。无需手动修改 HTML，即可维护分类、卡片、图片、JS 特效、看板娘、加载页主题、本地背景兜底、UA 伪装、公告、全站统计代码、站点显示配置，并支持**在线一键升级与回滚**。

**遇到问题请先看 [FAQ.md](./FAQ.md)**，所有常见问题都在那里。

---

## 🚀 快速开始

1. 下载源码，上传到 PHP 网站根目录
2. 访问 `https://你的域名/admin`，跟随 4 步安装向导完成设置（约 2 分钟）
3. 登录后台，在「站点设置」里改标题、头像
4. 之后想升级，点击后台顶部栏的「版本号（如 v1.1.3-beta.1）」，即可进入在线升级页

> 无需数据库、无需 Composer、无需构建工具。支持 PHP 7.3+。

---

## 🔄 升级须知

**当前版本：1.1.3-beta.1**（版本号定义在 `admin/defaults.php` 的 `NUOMI_VERSION` 常量，后台顶部栏会自动显示）

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

点击后台顶部栏的「版本号」胶囊按钮 → 进入 `admin/update.php` 独立升级页：

1. **自动检查更新**：对接 GitHub Releases API，5 分钟本地缓存
2. **选择更新通道**（v1.1.2 新增）：
   - `stable`（默认）：请求 `/releases/latest`，只取最新正式版
   - `beta`：请求 `/releases`，取数组第一条（可能是预发布版本）
3. **GitHub Token 认证**（v1.1.2 新增）：在「站点设置 → 在线升级专属」里填 Token 后，API 速率上限从 60 次/小时提升到 5000 次/小时
4. **环境检测**：自动测试 PHP 运行用户能否在项目根目录创建 / 覆盖 / 删除文件
   - ✅ 支持 → 「⬆ 立即升级」
   - ❌ 不支持 → 「🔗 去 GitHub 下载」
5. **下载更新包**：SHA256 校验，解压到 `admin/.update/package/`
6. **应用升级**：先备份当前代码 → 逐个覆盖（跳过保留清单）→ 失败自动回滚
7. **一键回滚**：随时用最近一次备份覆盖当前代码

**v1.1.2 升级覆盖白名单变化**：

- `README.md`、`CHANGELOG.md` **移出保护名单**，允许被升级覆盖
- `assets/js/`、`assets/preloaders/`、`photos/kanban/` 下**允许覆盖同名文件**，但**不删除**你额外添加的其它文件
- **绝对保护**：`admin/data.json`、`admin/.admin.conf`、`admin/.update/`、`photos/` 下所有普通图片、`LICENSE`

**在线升级的其他保护措施**：

- **路径穿越防护**：拒绝 `..`、绝对路径、Windows 盘符
- **下载地址白名单**：只认 `github.com` / `raw.githubusercontent.com` / `objects.githubusercontent.com` / `codeload.github.com`
- **解压后总大小上限**：200MB（防 zip 炸弹）
- **并发锁**：`flock(LOCK_EX | LOCK_NB)`
- **状态原子写入**：`state.json` / `history.json` 用 tmp + rename
- **卡死自愈**：`applying` 状态超过 10 分钟自动转 `failed`

### 方式二：手动覆盖升级

1. 下载新版本压缩包
2. 解压后覆盖上传（**跳过上面「保留」清单中的文件**）
3. 访问 `/admin` 刷新

> **共享主机提示**：在线升级大概率会因 PHP 运行用户 ≠ 文件所有者失败，环境检测不通过时会自动降级为「去 GitHub 下载」。

---

## ✨ 功能特性

### 前台
- 🎨 **双图层动态背景**：背景图自动切换，毛刺淡出 + 弹性淡入动画，支持一键启停轮播
- 🖼️ **Banner 轮播**：顶部横幅自动切换，左右滑动 + 光效扫过；切换前先探测图片可解码，失败时保留旧图不闪白
- 💾 **本地图片缓存**：背景 / Banner 使用 IndexedDB 缓存图片（Blob），回访秒显 + 自动切换一次到上次预取的图，断网也能轮播，缓存节奏跟随后台设置的轮播间隔
- 🔄 **回访连贯性**：`lastShownKey` 只在**每次切换成功后**写入（含轮播切换、回访秒显、回访 1.5s 后切下一张），因此**回访时首屏显示的一定是"上次关闭页面时最后看到的那张图"**，即使是轮播过程中关闭的也一样，视觉完全连贯；回访续播时**优先复用上次预取的图**作为 1.5s 后的切换目标；**API 优先和本地优先共用同一套续播骨架**
- 🔍 **内容指纹去重**：API 池入池前计算图片内容指纹（头 32KB + 尾 32KB 双 FNV 采样），相同内容的图不会重复入池
- 🎯 **待播槽预取**：轮播切换前先在后台预取下一张，在线优先拉新图，失败才用缓存兜底，切换顺滑无等待；**后台补池节流不阻塞预取，预取 Promise 快速 resolve**；每次切换成功后立即触发下一次预取，**待播槽永远有货，不会空转**
- 🆘 **本地背景兜底**：外部 API 挂掉时可用本地图兜底，支持「API 优先 / 本地优先」自由切换；本地图按「桌面端 / 移动端 / 通用」自动分类，前台优先按屏幕方向取图，**方向不匹配或加载失败会自动交叉兜底，彻底杜绝黑屏**；**Banner 兜底则优先使用桌面端横向图，不受手机方向影响；当 Banner 本地图全部被手动标成 mobile 时也会退回使用全部图，绝不因过滤后为空而失效**。
- ✅ **解码验证**：所有取图路径（API / 池 / 本地）在入池、入缓存、切换前都验证解码，失败的图不入队列、不出屏
- 👤 **悬浮圆形头像**：头像半悬浮在 Banner 下方，默认 10 秒内点击 10 次进入后台
- 📱 **完整响应式**：PC / 移动端自适应
- 🔖 **多分类标签页**：自定义分类名称与图标，支持 emoji 或图片
- 🃏 **卡片列表**：支持链接卡片、图片卡片，图片卡片点击弹窗展示大图，**弹窗大图支持长按保存**
- 🎨 **卡片悬停文字渐变**：桌面端鼠标悬停时，卡片名与副标题柔和过渡到蓝色
- 🎯 **分类切换平滑过渡**：切换分类时卡片列表高度平滑过渡，激活横条从旧按钮滑动到新按钮；电脑端先锁文档高度、再让滚动位置与高度/居中 margin 同相缓动
- 🌓 **主题系统**：跟随系统 / 浅色 / 深色模式，前后台共用同一份主题偏好，**内置防闪烁预解析 + 硬件性能自适应**
- 🔄 **三态轮播开关**：点击在 `关闭 / 开启` 模式间切换；**长按 3 秒**（带蓄能环动画）进入 `持续新图` 模式（强制 API 优先，按钮变橙色）
- 👘 **看板娘**：可拖动、记忆位置、切换形象。**支持 `<link rel="preload">` 提前拉取首图，并在加载页结束后延迟执行**；**形象图加载失败会自动重试（最多 2 次），仍失败时降级为圆形 ✨ 悬浮按钮**
- 💬 **看板娘语录**：本地语录 + 远程 API 交替出场，时段问候 + 洗牌队列不重复
- ⚙️ **看板娘控制面板**：点击语录气泡上的齿轮打开
- ✨ **JS 特效**：可上传或引用远程 JS，前台控制面板按需开关。**可控特效即时生效；固定特效刷新开关；若特效脚本已加载但未注册 matchKey，开关会直接禁用并显示 ⚠ 图标**
- 🎨 **加载页主题**：可上传多个 CSS 主题，前台底部独立按钮可让用户自选加载动画
- 📢 **首页公告条**（v1.1.2 移入 `#app` 内部最上方）：最新一条启用的公告显示为紧凑横幅
- 📢 **站内公告页**：独立的 `/announcement.php` 公告页面（列表风格），支持 4 种公告类型
- 🔗 **可配置页脚**：第一行版权信息**支持 HTML**（可放备案号链接）；第二行 `Powered by 糯米の引导页 · GitHub` 为原作者信息硬编码
- 🌐 **全站统计代码**：后台粘贴一次，前台 / 后台所有页面底部统一输出
- 📝 **用户文案安全转义**：用户可编辑内容含特殊字符时不破坏页面结构
- 💬 **文本溢出提示**：卡片名称/副标题溢出时显示 Tooltip
- 🛑 **长按防误触**：**已精准拦截卡片、头像的系统长按菜单，但大图预览依然允许长按保存**
- 🧊 **卡片按压反馈**：卡片支持 3D 倾斜、按压缩放等交互效果
- ⚡ **首屏优化**：配置提前到 `<head>`，首图预热请求先发，加载动画等到图片真正可解码才关闭；**弱网熔断 + 柔和兜底底色，弱网/无网下绝不黑屏**
- 🚪 **未初始化自动引导**：首次部署后访问前台会自动跳转到 `/admin`

### 后台
- 🔐 **安全登录**：密码哈希存储，`.admin.conf` 防直读；生产环境已关闭 `display_errors`
- 🧭 **4 步安装向导**：管理员账号 → 站点信息 → 行为配置 → 完成预览
- 🔄 **在线升级**（v1.1.2 独立为 `admin/updater.php`）：双通道 + GitHub Token + SHA256 校验 + 路径穿越防护 + 失败自动回滚 + 一键回滚
- 🟢 **版本胶囊状态自检**（v1.1.3-beta.1）：登录后台后延迟 1.5 秒自动检查一次更新。发现新版本 → 橙色脉冲；已是最新 → 绿色 + SVG 对勾描边动画（5 秒后恢复初始）；请求异常 → 红色。**Beta 通道下版本号前自动显示淡金色 `· Beta` 标识**
- 📋 **卡片管理**：新增、编辑、删除、拖拽排序；支持链接 / 图片两种类型
- 🗂 **分类管理**：新增、重命名、修改图标、删除；分类下有卡片时禁止删除；支持长按拖动排序
- 🖼 **图片管理**：上传、删除、重命名、排序、预览；**重命名自动同步卡片图标 / 弹窗大图 / 分类图标 / 站点设置**；**删除采用两层确认**
- 🖼️ **缩略图加速**：统一使用服务器生成的缩略图（长边 400px），`content-visibility: auto` 跳过屏幕外元素
- 🖼️ **本地背景分批挂载**：进入「🖼 本地背景」标签页时，图片通过 `requestAnimationFrame` **每帧挂载 4 张**
- ✨ **JS 特效管理**：上传 `.js`、URL 添加远程 JS、启用 / 禁用、排序、编辑；自动识别「可控 / 固定」类型
- 👘 **看板娘管理**：上传 GIF / PNG / WebP / JPG、启用 / 禁用、排序、重命名、删除
- 💬 **语录设置**：通用语录、五个时段的分时段语录、显示时长、远程 API 地址与字段名
- 🎨 **加载页管理**：上传 `.css` 主题、启用 / 禁用、排序、编辑名称 / 描述 / 文件名
- 🖼 **本地背景兜底**：背景 / Banner 独立管理，支持上传、API 抓取、启用 / 禁用、预览 / 删除 / 重命名 / 拖动排序、优先级切换；**「自动分类」一键根据图片宽高重新纠正标签，并自动按方向分组展示**
- 🎭 **UA 伪装管理**：内置移动端 / 桌面端 UA 模板，可新增、编辑、删除自定义模板；背景与 Banner 抓取可分别选择 UA
- 📢 **公告管理**：新增 / 编辑 / 删除 / 置顶 / 启用 / 禁用，4 种公告类型
- 🌐 **全站统计代码**：独立标签页，粘贴一次全站生效
- ⚙ **站点设置**（v1.1.2 四分区）：📌 基础显示 / 🔐 后台安全入口 / 🔗 页脚信息 / 🚀 在线升级专属
- 🔢 **数字步进器**：配置类数字输入框自动带 `− / +` 步进按钮
- 💾 **备份恢复**：导出完整 `data.json`，导入覆盖全部数据
- 🗑 **清除数据**：三种模式——"指定内容 / 清除全部 / 完全清空"
- 🔐 **账号设置**：修改管理员用户名、密码
- 🏷 **版本号显示**：后台顶部栏显示当前版本 `v1.1.3-beta.1`

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
│   ├── update.php            # 在线升级页（独立页面）
│   ├── updater.php           # 在线升级模块（v1.1.2 独立）
│   ├── defaults.php          # 默认值唯一权威来源 + 升级常量
│   ├── data.json             # 数据文件（首次安装生成）
│   ├── .admin.conf           # 管理员账号配置（PHP 防直读）
│   ├── .htaccess             # 保护敏感文件
│   └── .update/              # 在线升级工作目录
│       ├── update.lock
│       ├── github_release_cache.json
│       ├── package.zip
│       ├── package/
│       ├── state.json
│       ├── history.json
│       └── backup/
├── assets/                   # 静态资源
│   ├── style.css             # 前台样式
│   ├── admin.css             # 后台样式
│   ├── frontend.js           # 前台脚本
│   ├── admin.js              # 后台脚本
│   ├── updater.js            # 在线升级公共模块（v1.1.3-beta.1 新增）
│   ├── mascot.js             # 看板娘 + 特效控制器
│   ├── js/                   # JS 特效文件（自动创建）
│   └── preloaders/           # 加载页 CSS 主题（自动创建）
├── photos/                   # 图片存储目录（需写权限）
│   ├── .htaccess             # 禁止执行脚本
│   ├── QQ.jpg                # 默认头像
│   ├── zy.png                # 默认分类图标「导航」
│   ├── lx.png                # 默认分类图标「联系」
│   ├── thumbs/               # 图片管理缩略图（自动创建）
│   ├── kanban/               # 看板娘图片（自动创建）
│   ├── kanban_thumbs/
│   ├── localbg/              # 背景本地兜底
│   ├── localbg_thumbs/
│   ├── localbanner/          # Banner 本地兜底
│   └── localbanner_thumbs/
├── .gitignore
├── CHANGELOG.md
├── FAQ.md                    # 常见问题（另见）
├── LICENSE
└── README.md
```

> 后台会自动创建所有 `_thumbs/`、`kanban/`、`localbg/`、`localbanner/`、`assets/js/`、`assets/preloaders/`、`admin/.update/` 目录。
>
> `admin/defaults.php` 是所有默认值和升级常量的**唯一权威来源**，前后台共用。
>
> `admin/.update/` 是**在线升级工作目录**，只由 `admin/update.php` 与 `admin/updater.php` 读写，**绝不允许被升级包覆盖**。

---

## 🚀 环境要求

- PHP 7.3+（推荐 PHP 7.4+，后台使用 `array_key_first()`）
- 开启 PHP Session
- 支持 JSON 扩展
- 浏览器支持 IndexedDB
- **抓取本地背景需要**：允许 `allow_url_fopen`，或服务器能访问外网
- **在线升级需要**：PHP `zip` 扩展（`ZipArchive`），服务器能访问 `api.github.com` 和 `github.com`
- **PHP 图片扩展（至少其一，用于生成缩略图）**：Imagick（推荐）/ GD / 都不装也不影响使用（只是不生成缩略图）
- 目录写权限：`admin/`、`admin/.update/`、`photos/` 及其子目录、`assets/js/`、`assets/preloaders/`
- PHP 上传限制建议：图片 5MB / JS 2MB / 看板娘 10MB / 加载页 CSS 2MB
- **PHP 内存限制**：后台入口自动 `@ini_set('memory_limit', '512M')`；建议手动调到 512M 以上

---

## 📦 安装部署

### Apache（推荐）

1. **上传文件** 到 PHP 网站根目录
2. **设置权限**：
   ```bash
   chmod -R 755 admin photos assets/js assets/preloaders
   chmod -R 755 photos/thumbs photos/kanban_thumbs
   chmod -R 755 photos/localbg photos/localbg_thumbs
   chmod -R 755 photos/localbanner photos/localbanner_thumbs
   ```
3. **访问 `/admin`** 弹出 4 步安装向导，提交后自动登录
4. **首次进后台会生成缩略图**：每请求最多处理 30 张，图片多可多刷新几次后台
5. **在线升级**：确认服务器装了 PHP `zip` 扩展、能访问 `api.github.com`

### Nginx

`.htaccess` 在 Nginx 下**不生效**，需要手动加：

```nginx
location ~ ^/admin/.*\.(conf|json|bak|tmp|log|md)$ {
    deny all;
    return 404;
}
location ~ ^/admin/\.update/ {
    deny all;
    return 404;
}
location ~ ^/photos/.*\.(php|phtml|php3|php4|php5|php7|php8|phar|pl|py|jsp|asp|aspx|sh|cgi)$ {
    deny all;
    return 404;
}
```

改完后 `nginx -t && nginx -s reload`。**验证**：访问 `/admin/data.json` 应返回 403 或 404。

### 共享主机

大多数支持 Apache，`.htaccess` 自动生效。在线升级大概率因权限不一致失败，会自动降级为「去 GitHub 下载」。

---

## 🎮 使用说明

### 进入后台
- 未初始化时，前台任意页面会自动跳转到 `/admin`
- 初始化完成后：前台首页在配置的时间窗口内连续点击头像达到配置次数，自动跳转后台（默认 10 次 / 10 秒）
- 或直接访问 `/admin`

### 在线升级（点击顶部版本号）

1. **自动检查**：打开页面即自动拉取 GitHub Releases 最新版本
2. **选择通道**（v1.1.2）：在「站点设置 → 在线升级专属」里切换 `stable` / `beta`
3. **环境检测**：显示 PHP 运行用户、文件所有者、是否可写
4. **一键升级**：点「⬆ 立即升级」→ 自动下载 → SHA256 校验 → 解压 → 备份 → 覆盖 → 完成
5. **一键回滚**：升级后如有问题，点「↩ 回滚到上一版本」

**v1.1.2 状态锁死修复**：只要检测到新版本且环境可写，**永远优先展示「⬆ 立即升级」按钮**，无论上次是成功、失败还是已回滚。「重新检查」和「回滚」作为次级按钮放在下方工具区。

**v1.1.3-beta.1 首页版本胶囊自检**：登录后台后延迟 1.5 秒自动请求一次 `update_check`。
- 发现新版本 → 胶囊变橙色并脉冲，提示可点击升级
- 已是最新 → 胶囊变绿色并播放 SVG 对勾描边动画，5 秒后恢复初始状态
- 请求异常 → 胶囊变红色
- 未拿到结果前不改变胶囊颜色，避免闪烁

### 安装向导（4 步）
1. **管理员账号**：用户名 + 密码（至少 6 位）+ 确认密码
2. **站点信息**：标题、副标题、头像、favicon、API、页脚版权、GitHub 链接、Powered by
3. **行为配置**：轮播间隔、缓存数量、连点次数 / 时间窗口
4. **完成预览**

> **留空策略**：任何字段留空 → 回退到 `admin/defaults.php` 的默认值。

### 轮播控制按钮
- **单指点击**：在 `关闭`（播放图标）和 `开启`（暂停图标）之间切换
- **长按 3 秒**：只要当前不处于 `持续新图` 状态，长按 3 秒进入 **「持续新图」** 模式（橙色闪电图标），强制忽略「本地优先」设置，每一轮都强行请求新的 API 图片

### 卡片管理
- 新增 / 编辑 / 拖拽排序 / 删除
- 拖拽排序：按住行首 `⋮⋮` 手柄 350ms 后拖动

### 分类管理
- 添加 / 编辑 / 删除 / 拖拽排序
- 分类下有卡片时禁止删除

### 图片管理
- 上传 / 重命名 / 删除 / 排序 / 预览
- **重命名自动同步所有引用**
- **删除采用两层确认**：第一层防误点，第二层列出所有引用位置
- 列表显示**缩略图**（长边 400px），点击「预览」才加载原图

### JS 特效管理
- 上传 `.js` 或 URL 添加远程 JS
- 启用 / 禁用 / 排序 / 编辑名称 / 图标 / matchKey
- 前台控制面板：看板娘面板或右下角 ✨ 按钮中可开关特效

> **可控特效 vs 固定特效（自动识别）**
> - **可控特效**：模块同时有 `start` 和 `stop`，前台面板开关**即时生效**
> - **固定特效**：没有成对的 `start` / `stop`，前台面板开关走**刷新路径**
>
> **matchKey**：后台只在**首次识别新特效**时自动提取；已存在的值永久保留。编辑弹窗里有「🔄 自动读取」按钮可重新提取。

> 特效 JS 需注册到 `window.EffectsRegistry`：
> ```js
> window.EffectsRegistry = window.EffectsRegistry || [];
> window.EffectsRegistry.push({ name: 'snowfall', module: Snowfall });
> ```

### 看板娘管理
- 上传 GIF / PNG / WebP / JPG（单个 ≤ 10MB）或直接放入 `photos/kanban/`
- 内置形象：`妹妹.gif` / `姐姐.gif` / `喵小娘.gif` / `月薪喵.gif` / `七濑胡桃.gif` / `舞娘娘.gif` / `柠檬妹.gif` / `熊宝宝.gif`
- GIF 会**取第一帧**作为缩略图

### 语录设置
- **默认开启随机语录** / **语录显示时长（秒）** / **通用语录** / **分时段语录（五时段）** / **远程语录 API 地址** / **API 返回的字段名**

### 加载页管理
- 总开关 / 上传 `.css`（单个 ≤ 2MB）/ 排序 / 启用禁用 / 编辑
- **排在首位的主题自动成为默认主题**
- CSS 文件顶部注释：
  ```css
  /* @name 樱花旋转 */
  /* @description 樱花旋转 + 呼吸缩放 + 花瓣错拍闪烁 */
  ```
- 不要包含 `<style>`、`<script>`、`<?php` 等标签

### 🖼 本地背景兜底

- **背景与 Banner 完全独立**：`photos/localbg/` 与 `photos/localbanner/`，分开开关，分开优先级
- **双录入方式**：手动上传 / 从 API 抓取（填张数 1 ~ 20）
- **目标设备自动识别**（`desktop` / `mobile` / `both`）：
  - 宽 > 高 × 1.1 → `desktop`；高 > 宽 × 1.1 → `mobile`；其余 → `both`
  - 「自动分类」按钮一键重算所有图片的 `target`，并按方向分组展示
  - 前台按屏幕方向自动过滤，**交叉兜底防黑屏**
  - **Banner 优先横屏**：只取 `desktop` 和 `both`；如果所有图都被手动改成 `mobile`，退回使用全部图
- **抓取默认 UA**：**背景走 `ua_mobile_default`（移动端抓竖图）、Banner 走 `ua_desktop_default`（桌面端抓横图）**
- **内容去重**：按内容 MD5 去重
- **独立缓存**：本地图写入浏览器 IndexedDB（键前缀 `bg_local:` / `banner_local:`），**回访秒显、零流量**，不参与 FIFO 淘汰
- **优先级可调**：
  - **API 优先**（默认）：API（重试 3 次）→ 池里历史图 → 本地图 → 空白
  - **本地优先**：本地图按顺序循环；如果本地图数量少于缓存池上限，系统会自动在后台用 API 图填满剩余坑位
- **缩略图加速 + 图片分批挂载**（每帧 4 张）

**目录结构：**

```text
photos/
├── localbg/                # 背景本地兜底图（desktop / mobile / both 平铺混放）
├── localbg_thumbs/         # 背景兜底缩略图
├── localbanner/            # Banner 本地兜底图（建议放横图）
└── localbanner_thumbs/     # Banner 兜底缩略图
```

**推荐用法：**

| 你的场景 | 建议操作 |
|---|---|
| 只给手机用 | 默认背景走 `ua_mobile_default` 直接点「立即抓取」，抓一批竖图 |
| 只给 PC 用 | 默认 Banner 走 `ua_desktop_default` 抓一批横图；背景想给 PC 用，去「🎭 UA 伪装」页把背景模板切到 `ua_desktop_default` 再抓 |
| PC + 手机都要 | 背景默认移动端抓一批竖图；Banner 默认桌面端抓一批横图 |
| 不区分方向 | 手动上传接近正方形的图，会自动打 `both` |
| 想纠正打错的标签 | 点「自动分类」按宽高重算 |

### 🎭 UA 伪装

- **内置模板**：`ua_mobile_default`（iOS 18 Safari）/ `ua_desktop_default`（Windows 11 Chrome）
- **可编辑不可删除**
- **自定义模板**：可新增、编辑、删除
- **独立字段**：`site.localBgUaId` / `site.localBannerUaId` 各存一个模板 ID，**这两个字段就是「立即抓取」实际使用的请求头**
- **请求头字段**：`User-Agent` / `Accept` / `Accept-Language` / `Accept-Encoding` / 额外请求头
- **自动回退**：删除正在使用的自定义模板后自动回退默认

### 📢 公告管理

- 标题（必填，≤ 100 字）/ 类型（通知 / 成功 / 警告 / 错误）/ 内容（支持 HTML，不允许 `<script>`）/ 置顶 / 启用
- **公告页**：`https://你的域名/announcement.php`，按「置顶优先 + 创建时间倒序」排序
- **404 页「查看公告」按钮**：跳转到 `announcement.php`

### 页脚配置（v1.1.2 有变化）

进入后台「站点设置 → 前台 → 🔗 页脚信息」：

- **页脚版权信息**（`copyright`）：**支持 HTML**（可放备案号链接等），显示在页脚第一行
- **Powered by / GitHub 链接**（v1.1.2 变化）：**已硬编码为原作者信息**，后台不再提供输入框。区域显示：
  ```
  Powered by 糯米の引导页 · GitHub
  ```
  并附带提示「🔒 开发者和项目链接已锁定，防止二次开发时误改项目归属。」

### 🌐 统计代码

- **作用范围**：前台、后台所有页面的 `</body>` 前统一原样输出
- **支持内容**：百度统计、GA、GTM、Cloudflare Web Analytics、Umami、自建统计等任意 HTML 片段
- **格式校验**：必须包含 HTML 标签，内容含 `<?php` 或 `<?=` 会被拒绝
- **留空即关闭**

> ⚠️ 如果 Cloudflare 等 CDN 已在边缘节点自动注入，**不要**再往这里粘贴同一份代码。

### 站点设置（v1.1.2 四分区）

- **📌 基础显示**：favicon、浏览器标签标题、主标题、副标题、头像
- **🔐 后台安全入口**：进入后台的连点次数 / 时间窗口
- **🔗 页脚信息**：页脚版权（支持 HTML）+ 锁定信息（作者 / GitHub 硬编码）
- **🚀 在线升级专属**：
  - **GitHub Token**（可选）：提升 API 速率上限从 60 次/小时到 5000 次/小时
  - **更新通道**：`stable` / `beta`

### 备份恢复 / 清除数据 / 账号设置
- **导出**：下载完整 `data.json` 备份
- **导入**：上传备份 JSON，**完整覆盖当前全部数据**
- **清除数据**：指定内容（确认词 `RESET`）/ 清除全部（`RESET`）/ 完全清空（`CLEAR`）
- **账号设置**：修改用户名 / 密码（需验证当前密码，新密码至少 6 位）

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
| `copyright` | 页脚版权信息（**支持 HTML**） | `© 2026 糯米の引导页` |
| **`githubToken`**（v1.1.2 新增） | GitHub Token（用于提升 API 限额） | `""` |
| **`updateChannel`**（v1.1.2 新增） | 更新通道（`stable` / `beta`） | `stable` |
| `githubUrl` / `githubEnabled` / `poweredBy` / `poweredByUrl` | **保留字段但不被前台使用**（前台 Powered by 已硬编码） | — |

数据文件顶层还包含：

| 字段 | 说明 |
|------|------|
| `localBg` | `{ enabled: bool, images: [{file, name, fp, target}] }` |
| `localBanner` | `{ enabled: bool, images: [{file, name, fp, target}] }` |
| `uaTemplates` | UA 伪装模板数组，每项含 `id / name / ua / accept / acceptLanguage / acceptEncoding / extraHeaders / builtin` |
| `announcements` | 公告数组，每项含 `id / title / content / type / pinned / enabled / created / updated` |

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

## 🖼️ 缩略图机制

后台的图片管理 / 看板娘 / 本地背景三个页面的网格里，显示的都是**缩略图**，不是原图。

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

- **上传时 / 从 API 抓取时**：立即生成
- **手动放入目录**：刷新后台时自动补生成
- **GIF**：只取**第一帧**作为缩略图
- **每请求上限**：**30 张**

### 缩略图优先使用

按 **Imagick → GD** 顺序尝试：

- **Imagick**（推荐）：用磁盘缓存，处理大图时几乎不吃 PHP 内存
- **GD**：PHP 默认扩展，处理 4000×3000 以内较稳
- **两者都没装**：不生成缩略图，网格里退回显示原图

### 网格渲染优化

```css
.photo-cell {
    content-visibility: auto;
    contain-intrinsic-size: 0 110px;
}
```

---

## ⚡ 首屏加载优化

### 1. 请求提前到解析阶段
`window.NUOMI_CONFIG` 与首图预热 `fetch` **提前到 `<head>`**，比原来的 `DOMContentLoaded` 早 200~500ms 发起请求。预热请求超时 4 秒。

### 2. 加载动画等真正"可见"再收工
- 首次等待上限 **4.5s**，回访等待上限 **2.5s**
- 硬兜底 **4.5s**
- `nuomi-first-paint` 事件改为**等 `img.decode()` 完成**才触发

### 3. 预热结果复用
`__nuomiWarmupBg` / `__nuomiWarmupBanner` 全局预热 Promise 会被首次取图直接消费。

### 4. 修复 `cacheLimit` 失效 bug
原来 `NuomiPool` 的 IIFE 在 `<head>` 里立刻执行，此时 `window.NUOMI_CONFIG` 还没被赋值 → `cacheLimit` 永远读默认值 8。现在配置提前到 `<head>`。

### 5. 修复轮播状态机与内存泄漏
- **并发拦截防丢失**：修复了在网络极快或用户快速点击开启轮播时，并发切换导致 `currentKey` 状态错乱
- **Blob URL 释放**：彻底修复了预取图片进入待播队列时如果发生溢出或切换失败，Blob URL 未释放导致的内存泄漏

### 6. 用户可编辑内容的安全注入
所有注入到 `<script>` 的 JSON 统一带 `JSON_HEX_TAG`：
- 把 `<` `>` 转成 `\u003C` `\u003E`
- 卡片名称里即使填了 `</script>`，也不会提前闭合页面中间注入的配置脚本

### 7. 智能预热：池满 / 本地优先不再白下载
`<head>` 的预热请求加了 `shouldWarmup(type)` 判断：
- **池未满**（`'0'`）→ 照常预热
- **池已满**（`'1'`）→ 跳过预热
- **本地优先** → 跳过预热

**效果**：日常回访流量从 4MB+ 降到接近 0，首次访问和补池体验完全不变。

### 8. 弱网/慢网下的秒级降级与防黑屏
- **请求超时策略**：
  - API 预热超时 **4 秒**
  - 首次取图 API 超时 **2.5 秒**；轮播取图超时 **5 秒**；重试最多 3 次
  - 本地图 **首屏 1.5 秒**、**后续 2.5 秒**超时
- **黑名单智能判定**：弱网超时**不会**将本地图片拉黑
- **首屏加载死锁解除**：加载页硬兜底 **4.5 秒**
- **优雅的底色兜底**：无图时底色由纯黑改为预置的渐变
- **Banner 无图静默**：Banner 彻底加载失败时保持完全透明
- **超时不累加退避**
- **横竖屏切换重置退避**
- **后台补池不阻塞预取**
- **间隔从 tick 开始计时**

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
- **位置记忆**：以 `hx / hy` 归一化比例存储在 `localStorage.board_girl_pos`
- **自适应尺寸**：根据屏幕宽高比自动缩放（横屏 56~96px，竖屏 90~120px）
- **长按拖动**：按住 350ms 后进入拖动模式，松手保存位置
- **点击看板娘**：显示 / 切换随机语录
- **语录气泡**：宽度自适应内容，洗牌队列保证不连续重复
- **气泡上的齿轮按钮**：点击打开控制面板
- **迷你按钮**：看板娘未启用且仍有启用的 JS 特效时，显示 ✨ 迷你按钮
- **图片加载重试**：形象图加载失败时自动重试（最多 2 次，间隔 600ms 递增），重试仍失败降级为圆形 ✨ 悬浮按钮
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

1. 编写一个纯 CSS 文件，只包含样式规则
2. 文件顶部可选写注释：
   ```css
   /* @name 主题名称 */
   /* @description 一句话描述 */
   ```
3. 将文件放入 `assets/preloaders/`，或后台上传
4. 后台会自动识别并生成 `key`（文件名去扩展名）
5. 主题内可使用以下预置结构：
   - `#preloader`：全屏遮罩容器
   - `.preloader-content`：内容容器
   - `.preloader-avatar`：头像容器
   - `.preloader-title`：标题容器
   - `.preloader-progress`：进度条轨道
   - `.preloader-progress-bar`：进度条填充
6. 支持 `html[data-theme="dark"]` 深色模式选择器，以及 `#preloader.is-revisit` 回访状态
7. 正常的 `.a > .b` 子选择器可以放心使用，注入时会被安全转义

> 内置主题：`elegant`（柔光，唯一内置）

---

## 🧩 JS 特效开发指南

1. 编写一个自执行函数，导出 `start`、`stop`、`toggle` 方法：

```js
(function () {
  let running = false;

  const Effect = {
    start() { if (running) return; running = true; },
    stop()  { if (!running) return; running = false; },
    toggle() { running ? this.stop() : this.start(); }
  };

  Effect.start();

  window.EffectsRegistry = window.EffectsRegistry || [];
  window.EffectsRegistry.push({ name: 'myEffect', module: Effect });
})();
```

2. 将文件放入 `assets/js/`，或后台上传 / URL 添加
3. 后台会自动提取 `name` 作为 `matchKey`
4. 特效开关状态保存在 `localStorage.effects_states`
5. **加载顺序**：`frontend.js` → 各特效 JS → `mascot.js`
6. **延迟执行**：所有特效脚本都会在首屏加载页结束后由 `index.php` 中的内联脚本动态注入执行

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

**主题系统**：
- **偏好键**：`localStorage.themeMode`
- **可选值**：`auto` / `light` / `dark`
- **根元素属性**：`html[data-theme="light"]` / `html[data-theme="dark"]`
- **防闪烁**：内联解析逻辑在 `<head>` 最前方

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
- **首页公告条已关闭列表**：`nuomi_home_ann_dismissed`（JSON 数组，最多保留 20 条）

---

## 📌 路径规则

1. `data.json` 中统一存储**网站根目录相对路径**：`photos/xxx.png`、`assets/js/xxx.js`、`photos/kanban/xxx.gif`、`photos/localbg/xxx.jpg`
2. 后台运行在 `admin/` 子目录，PHP 渲染时会自动拼接 `../` 用于显示，**保存 JSON 不会写入 `../`**
3. `http://` 或 `https://` 开头的 CDN 图片 / JS 原样使用，不做路径转换
4. 前台直接使用这些根目录相对路径，不要写成 `../photos/xxx`
5. **默认 favicon / adminAvatar 使用不带前导 `/` 的形式**（`photos/QQ.jpg`），根目录和子目录部署都能正确解析
6. 缩略图路径**不落盘**，由原图路径推导

---

## 📢 公告系统

公告系统由三部分组成：

### 1. 后台「📢 公告管理」标签页
- 增删改查、置顶、启用 / 禁用
- 公告内容支持 HTML，不允许 `<script>`
- 四种类型：通知 / 成功 / 警告 / 错误

### 2. 首页公告条（v1.1.2 移入 `#app` 内部最上方）
- 显示**最新一条启用的公告**
- 排序规则：置顶优先 + 创建时间倒序
- 显示内容：类型徽章 + 标题 + 「查看详情 →」链接 + 「✕」关闭按钮
- **关闭记忆**：点击「✕」后，公告 ID 写入 `localStorage.nuomi_home_ann_dismissed`（JSON 数组，最多保留 20 条），同一条公告不再显示
- **新公告自动恢复**：新公告有新的 ID，会重新显示

### 3. 前台 `/announcement.php`（列表风格）
- 公开访问，无需登录
- 只显示 `enabled: true` 的公告
- 按「置顶优先 + 创建时间倒序」排序
- **列表风格**：时间在左上、类型徽章紧随其后、标题大字、正文紧凑
- 主题自动跟随 `localStorage.themeMode`

### 4. 404 页跳转
`404.php` 的「查看公告」按钮跳转到 `announcement.php`

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

## 🔄 在线升级机制（v1.1.2 架构 + v1.1.3-beta.1 公共模块）

在线升级由四部分组成：

- **`admin/update.php`**：前端页面
- **`admin/updater.php`**（v1.1.2 新增）：后端逻辑模块，包含所有工具函数 + 5 个 API 的统一处理器
- **`assets/updater.js`**（v1.1.3-beta.1 新增）：前端公共请求模块，封装 `window.NuomiUpdater.check()`
- **`admin/.update/`**：工作目录

`admin/index.php` 只在 API 路由里保留 5 个 `update_*` case，**统一委托**给 `updater_handle_action($action)`：

```php
case 'update_check':
case 'update_download':
case 'update_apply':
case 'update_rollback':
case 'update_status':
    require_login();
    updater_handle_action($action);
    break;
```

`assets/updater.js` 暴露的唯一公共方法：

```javascript
window.NuomiUpdater.check({ force: false }).then(function (data) {
    // data.ok / data.has_update / data.latest_version / data.env ...
});
```

后台首页的版本胶囊自检与升级页的 `doCheckUpdate` 都调用同一个方法，**避免重复实现请求逻辑**。

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
- 并发锁 `flock(LOCK_EX | LOCK_NB)`

### GitHub API 对接（v1.1.2 双通道 + Token）

- **接口**：
  - `stable`（默认）：`https://api.github.com/repos/{UPDATE_GITHUB_REPO}/releases/latest`
  - `beta`：`https://api.github.com/repos/{UPDATE_GITHUB_REPO}/releases`，取数组第一条
- **请求头**：
  - 必须带 `User-Agent`（GitHub 强制要求），已内置 `NuomiNav-Updater/1.0`
  - **`site.githubToken` 非空时**：附加 `Authorization: Bearer <token>`
- **版本号**：从 `tag_name` 读，`ltrim($tag, 'vV')` 转成纯版本号
- **下载地址**：从 `assets` 数组里找第一个 `.zip` 结尾的 `browser_download_url`
- **SHA256**：如果 asset 的 `digest` 字段是 `sha256:xxx` 格式，自动校验
- **缓存**：`admin/.update/github_release_cache.json`，按通道分别缓存 5 分钟
- **更新说明**：从 `body` 字段读

### 环境检测

`update_env_check()` 会在项目根目录和 `admin/` 目录分别做一次「创建 → 删除」测试文件：

- ✅ 通过 → 返回 `writable: true`，前端显示「⬆ 立即升级」
- ❌ 失败 → 返回 `writable: false` + `reason`，前端显示「🔗 去 GitHub 下载」

### 安全防护

| 防护 | 说明 |
|---|---|
| 路径穿越 | 拒绝 `..`、绝对路径、Windows 盘符 |
| 下载白名单 | 只认 `github.com` / `raw.githubusercontent.com` / `objects.githubusercontent.com` / `codeload.github.com` |
| 保留清单 | `admin/data.json`、`admin/.admin.conf`、`admin/.update/`、`photos/` 普通图片、`LICENSE` **永不覆盖** |
| 大小限制 | 下载 zip 上限 200MB，解压后总大小上限 200MB |
| 并发锁 | `flock(LOCK_EX \| LOCK_NB)` |
| 原子写入 | `state.json` / `history.json` 用 tmp + rename |

### 升级历史

每次升级成功 / 回滚都会往 `admin/.update/history.json` 里追加一条记录（最多保留 50 条）。

### 修改 GitHub 仓库地址

打开 `admin/defaults.php`：

```php
if (!defined('UPDATE_GITHUB_REPO')) {
    define('UPDATE_GITHUB_REPO', 'nmrt0316/MochiNav');
}
```

把 `nmrt0316/MochiNav` 改成你自己的 `owner/repo` 即可。

---

## 📄 许可证

[MIT License](LICENSE)

---

**糯米の引导页** · 让导航页也充满仪式感 ✨

---

## 📖 更多

- [CHANGELOG.md](./CHANGELOG.md) — 版本变更记录
- [FAQ.md](./FAQ.md) — 常见问题