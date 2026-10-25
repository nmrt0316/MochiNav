# Changelog

本项目所有重要变更都记录在此文件。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，
版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

---

## [Unreleased]

### 新增
- **在线升级（对接 GitHub Releases API）**：新增独立升级页 `admin/update.php`（后台顶部栏「🔄 检查更新」按钮进入），支持「检查更新 → 环境检测 → 下载更新包 → 应用升级 → 一键回滚」完整闭环
  - **检查更新**：对接 `https://api.github.com/repos/{OWNER}/{REPO}/releases/latest`，5 分钟本地缓存（`admin/.update/github_release_cache.json`），用 `version_compare` 比较版本号，兼容 `v1.2.0` 与 `1.2.0` 两种 tag 格式
  - **环境检测**：自动测试 PHP 运行用户能否在项目根目录创建 / 覆盖 / 删除文件，不通过时返回具体原因（PHP 运行用户 vs 文件所有者），并自动降级为「🔗 去 GitHub 下载」按钮
  - **下载更新包**：从 Release 的 `assets` 数组里找第一个 `.zip`，自动 SHA256 校验（若 Release 提供了 `digest`），解压到 `admin/.update/package/`
  - **应用升级**：先备份当前代码到 `admin/.update/backup/<时间戳>/`，再逐个覆盖，跳过保留清单中的路径，失败自动回滚
  - **一键回滚**：用户手动触发，用最近一次备份覆盖当前代码，`state.json` 转为 `rolled_back`
  - **状态机**：`idle` / `downloading` / `downloaded` / `applying` / `success` / `failed` / `rolled_back`，`state.json` / `history.json` 用 tmp + rename 原子写入，`applying` 超过 10 分钟自动转 `failed`
  - **并发锁**：`flock(LOCK_EX | LOCK_NB)` 防止两次升级同时进行
- **新增 5 个后台 API action**：`update_check` / `update_download` / `update_apply` / `update_rollback` / `update_status`（在 `admin/index.php` 中实现）
- **`admin/defaults.php` 新增升级常量**：`UPDATE_GITHUB_REPO`（默认 `nmrt0316/MochiNav`）/ `UPDATE_DIR` / `UPDATE_MAX_PACKAGE_MB`（200）/ `UPDATE_MAX_EXTRACTED_MB`（200）/ `UPDATE_BACKUP_KEEP`（3）
- **`admin.css` 新增第 27 章「在线升级页样式」**：`.update-page` / `.update-container` / `.update-card` / `.update-version-*` / `.update-env-*` / `.update-release-*` / `.update-progress-*` / `.update-state-*` / `.update-warning` 等
- **后台顶部栏「🔄 检查更新」按钮**：链接到 `admin/update.php`

### 计划中
- 后台数据概览面板
- 首次登录引导
- 备份 ZIP 化（含图片与特效）
- 错误日志查看

### 安全
- **在线升级**的多层防护：
  - **路径穿越防护**：拒绝 `..`、绝对路径（`/` 开头）、Windows 盘符（`C:`）
  - **下载地址白名单**：只认 `github.com` / `raw.githubusercontent.com` / `objects.githubusercontent.com` / `codeload.github.com`
  - **覆盖白名单**：仅允许覆盖 `admin/`、`assets/`、`index.php`、`404.php`、`announcement.php`、`analytics.php`
  - **保留清单**：`admin/data.json`、`admin/.admin.conf`、`admin/.update/`、`photos/`、`assets/js/`、`assets/preloaders/`、`README.md`、`LICENSE`、`CHANGELOG.md` **永不覆盖**
  - **大小限制**：下载 zip 上限 200MB，解压后总大小上限 200MB（防 zip 炸弹）
  - **后台 JS 隔离**：升级页面的 JS 只调用自己的 5 个 `update_*` API，不直接请求 GitHub
  - **GitHub API 请求带 User-Agent**：`NuomiNav-Updater/1.0`（GitHub 强制要求）

### 变更
- `admin/defaults.php` 在文件开头新增升级常量定义区
- `README.md`「升级须知」章节新增「方式一：在线升级」完整说明，覆盖检查更新 / 环境检测 / 下载 / 应用 / 回滚 / 安全防护 / 常见问题
- `README.md` 目录结构章节补充 `admin/update.php` 与 `admin/.update/` 目录
- `README.md` 环境要求章节补充「在线升级需要 PHP `zip` 扩展、能访问 `api.github.com` 和 `github.com`」
- `README.md` 功能特性章节补充「🔄 在线升级」
- `README.md` 新增「🔄 在线升级机制」章节，包含 API 一览、状态机、GitHub API 对接、环境检测、安全防护、升级历史
- `README.md` 常见问题新增 9 条在线升级相关问答
- `README.md` Nginx 章节补充 `location ~ ^/admin/\.update/` 保护规则

### 修复
- 无

---

## [1.1.0] - 2026-10-25

### 新增
- **4 步安装向导**：管理员账号 → 站点信息 → 行为配置 → 完成预览，首次安装约 2 分钟
- **页脚两行显示**：第一行版权信息，第二行 `Powered by 项目名 · GitHub 链接`，全部可在后台配置
- **GitHub 链接独立开关**：可在后台关闭 GitHub 显示但保留地址
- **站内公告系统**：`announcement.php` 公开公告页 + 后台「📢 公告管理」标签页，支持增删改查、置顶、启用/禁用、四种公告类型（info / success / warning / error）
- **版本号常量**：`NUOMI_VERSION` 定义在 `admin/defaults.php`，后台顶部栏显示
- **图片管理空状态**：没有图片时显示引导提示
- **`.gitignore`**：排除运行时数据（`data.json` / `.admin.conf` / 上传的图片），保留内置资源
- **`admin/.htaccess`**：保护 `.admin.conf` / `data.json` 不被直接下载
- **`photos/.htaccess`**：禁止图片目录执行脚本，防止上传 shell 被解析
- **Nginx 部署说明**：README 补充 Nginx 敏感文件保护配置

### 变更
- **默认值统一到 `admin/defaults.php`**：`site_defaults()` / `tips_defaults()` / `factory_defaults()` / `ua_builtin_templates()` 四个函数从 `admin/index.php` 抽取到独立文件，前后台共用
- **前台站点配置读取**：改为「读 `data.json` + `site_defaults()` 空值兜底」，删除前台硬编码的 19 字段副本
- **前台分类兜底**：改为读取 `factory_defaults()['categories']`，删除前台硬编码的「导航 / 联系」
- **前台 API 兜底**：`bgApi` / `bannerApi` 为空时不再偷偷使用 loliapi，而是明确走本地兜底
- **后台 `save_site`**：`githubEnabled` 从 `$fields` 数组移除，改为独立处理，避免重复写入
- **404 页「查看公告」**：链接从 `/notice.html` 改为 `announcement.php`

### 修复
- 修复 `site_defaults()` 等四个函数在 `admin/index.php` 和 `admin/defaults.php` 中重复定义导致 `Cannot redeclare` fatal error 的问题
- 修复前台 `$siteCfgFallback` 只判断字段是否存在、不判断字段是否为空的问题（用户清空 `bgApi` 后无法回退默认值）
- 修复 `admin/.htaccess` 的 `Options -Indexes` 在 `AllowOverride` 未开 `Options` 时触发 500 的问题（用 `<IfModule mod_autoindex.c>` 包裹）
- 修复 404 页「查看公告」链接指向不存在的 `/notice.html` 的问题

### 安全
- `.gitignore` 防止 `data.json` / `.admin.conf` / 用户图片被误提交到仓库
- `admin/.htaccess` 防止 Apache 默认配置下 `.admin.conf` / `data.json` 被直接下载
- `photos/.htaccess` 禁止图片目录执行 PHP 等脚本，防止上传 `shell.php.jpg` 被解析
- 公告内容拒绝 `<script>` 标签，防止管理员误贴脚本
- 页脚 `githubUrl` / `poweredByUrl` 拒绝非 http(s) 协议，防止 `javascript:` 注入
- 安装向导 `init` action 调整为先保存 `data.json` 再写 `.admin.conf`，避免账号写入成功但数据初始化失败导致无法重新安装

---

## [1.0.0] - 2026-10-24

首个正式版本。

### 新增
- 双图层动态背景轮播（毛刺淡出 + 弹性淡入），支持一键启停
- Banner 轮播（滑动 + 光效扫过），支持左右交替方向
- IndexedDB 图片缓存（背景 / Banner 独立缓存池，回访秒显）
- 内容指纹去重（头尾 32KB 双 FNV 采样）
- 待播槽预取机制（切换零等待）
- 本地背景兜底（API 优先 / 本地优先自由切换）
- 交叉兜底防黑屏（桌面端 / 移动端自动识别）
- 悬浮圆形头像（连点进入后台，次数与时间窗口可配）
- 多分类标签页（自定义名称与图标）
- 链接卡片 / 图片卡片（图片弹窗支持长按保存）
- 看板娘（可拖动、记忆位置、切换形象）
- 看板娘语录（本地 + 远程 API 交替出场，分时段问候）
- JS 特效管理（可控特效即时生效，固定特效刷新开关）
- 加载页主题（12 个内置主题，支持自定义 CSS）
- 全站统计代码（前后台统一输出）
- 主题系统（跟随系统 / 浅色 / 深色，防闪烁预解析）
- 硬件性能自适应（自动识别 GPU，低端设备降级）
- 三态轮播开关（关闭 / 开启 / 持续新图）
- 本地背景与 Banner 独立管理（上传 / API 抓取 / 自动分类）
- UA 伪装模板（背景走移动端 UA，Banner 走桌面端 UA）
- 备份恢复（导出 / 导入 `data.json`）
- 清除数据（指定内容 / 清除全部 / 完全清空）

### 安全
- 管理员密码哈希存储（`password_hash`）
- `.admin.conf` 防直读（`<?php exit; ?>` 前缀）
- 生产环境关闭 `display_errors`，PHP 报错写入日志
- 用户可编辑内容统一 `JSON_HEX_TAG` 转义
- URL 字段拒绝 `javascript:` 等非 http(s) 协议
- 统计代码拒绝 PHP 标签
- 上传文件扩展名白名单 + 图片有效性校验

---

## 版本号约定

- **主版本号（MAJOR）**：不兼容的 API 变更、数据格式变更
- **次版本号（MINOR）**：向下兼容的功能新增
- **修订号（PATCH）**：向下兼容的 bug 修复

## 升级方式

### 方式一：在线升级（推荐，v1.2.0 起）
后台顶部栏点「🔄 检查更新」→ 环境检测通过后即可一键升级，详见 README「升级须知 → 方式一：在线升级」。

### 方式二：手动覆盖升级（当前推荐）
1. 下载新版本压缩包
2. 解压后覆盖上传
3. **务必保留**以下文件（它们是您的数据）：

| 保留 | 说明 |
|---|---|
| `admin/data.json` | 卡片、分类、站点配置 |
| `admin/.admin.conf` | 管理员账号 |
| `admin/.update/` | 在线升级缓存、更新包、代码备份、升级状态 |
| `photos/` | 所有上传的图片 |
| `assets/js/` | 上传的 JS 特效 |
| `assets/preloaders/` | 上传的加载页主题 |

其余文件（`*.php` / `*.css` / `*.js`）可直接覆盖。

---

## 关于发版流程（维护者参考）

1. 改 `admin/defaults.php` 的 `NUOMI_VERSION` 常量
2. 把本文件 `[Unreleased]` 下的内容剪切到新的 `[x.y.z] - YYYY-MM-DD` 段落
3. `[Unreleased]` 留空
4. 打包，压缩包命名 `nuomi-x.y.z.zip`
5. 在 GitHub 上打 tag（`vx.y.z`）
6. 创建 Release，上传 zip，把本段落内容粘贴到 Release 说明里
   - **注意**：Release 的 zip 必须是**带项目根目录**的压缩包（即解压后有 `index.php`、`admin/` 等顶层文件），不能只打包成一层目录
   - Release 的 body 字段就是升级页「更新说明」的内容，建议用 Markdown 写清楚本次变更
   - 如果 Release 提供了 `digest` 字段（格式 `sha256:xxx`），在线升级会自动做 SHA256 校验