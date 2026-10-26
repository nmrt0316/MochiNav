# Changelog

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，
版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

---

## [Unreleased]

### 计划中
- 后台数据概览面板
- 首次登录引导
- 备份 ZIP 化（含图片与特效）
- 错误日志查看

---

## [1.1.2] - 2026-10-26

### 新增
- **在线升级双通道**：支持 `stable`（正式版）与 `beta`（尝鲜版）切换。正式版取 `/releases/latest`，尝鲜版取 `/releases` 第一条（含预发布）。
- **GitHub Token 认证**：站点设置可填写 Personal Access Token，速率上限从 60 次/小时提升到 5000 次/小时，并支持访问私有仓库的 Release。
- **后台独立升级模块 `admin/updater.php`**：原 `admin/index.php` 中约 1100 行在线升级逻辑独立成模块，`admin/index.php` 只保留 5 个 `update_*` case 统一委托给 `updater_handle_action()`。
- **站点设置四分区重构**：前台设置页拆成「⚙️ 基础设置 / 🔐 后台安全入口 / 🔗 页脚信息 / 🚀 在线升级专属」四个区块。
- **页脚锁定信息区**：硬编码显示作者信息与 GitHub 链接，并加锁定提示，防止二开误改项目归属。

### 变更
- **版本号**：`1.1.1` → `1.1.2`。
- **默认加载页**：只保留 `elegant`「柔光」，移除 `apple`「苹果」。
- **页脚版权**：允许用户填写 HTML（例如备案号链接），不再强制转义。
- **首页公告条位置**：从 `<div id="app">` 外部移到 `#app` 内部最上方，视觉更统一。
- **前台 Powered by / GitHub 链接硬编码**：不再读取 `site.poweredBy` / `site.githubUrl` / `site.githubEnabled`。
- **升级覆盖白名单重构**：
  - `README.md`、`CHANGELOG.md` 移出保护名单，允许升级覆盖。
  - `assets/js/`、`assets/preloaders/`、`photos/kanban/` 下允许覆盖同名文件（用户新增的不同名文件不受影响）。
  - 绝对保护：`admin/data.json`、`admin/.admin.conf`、`admin/.update/`、`photos/` 下普通图片。

### 修复
- **在线升级状态锁死**：修复"失败"或"已回滚"状态下升级按钮被锁死、必须手动删 `state.json` 才能重试的问题。现在只要检测到新版本且环境允许，永远优先展示「⬆ 立即升级」按钮；「重新检查」和「回滚」作为次级按钮放在下方工具区。
- **升级页浅色模式对比度**：`.update-action-tip` 文字加深为 `#4b5563`，`.update-warning` 文字加深为 `#78350f`。
- **前台横向溢出**：`html` 与 `body` 加 `overflow-x: hidden`，防止天气等第三方组件撑破页面。
- **前台公告条宽度**：`.home-announcement` 去掉固定 `max-width` 与左右 padding，自适应 `#app` 内部宽度。

### 安全
- **升级白名单收敛**：`photos/` 下普通图片、`admin/data.json`、`admin/.admin.conf`、`admin/.update/` 绝对保护。
- **下载地址白名单**：只认 `github.com` / `raw.githubusercontent.com` / `objects.githubusercontent.com` / `codeload.github.com`。
- **Token 按需传递**：`update_fetch_release` 仅在 `site.githubToken` 非空时附加 `Authorization: Bearer` 头，不写入 cache 文件。

---

## [1.1.1] - 2026-10-25

### 新增
- **在线升级**：对接 GitHub Releases API，支持「检查更新 → 环境检测 → 下载更新包 → 应用升级 → 一键回滚」完整闭环。
  - 检查更新：5 分钟缓存，避免触发 GitHub 速率限制。
  - 环境检测：测试 PHP 运行用户写权限，不通过降级为手动下载。
  - 下载更新包：SHA256 校验，解压到 `admin/.update/package/`，路径穿越防护 + 解压体积上限。
  - 应用升级：先备份当前代码再逐个覆盖，失败自动回滚。
  - 一键回滚：用最近一次备份覆盖当前代码，状态转 `rolled_back`。
- **新增 5 个升级 API**：`update_check` / `update_download` / `update_apply` / `update_rollback` / `update_status`。
- **`admin/defaults.php` 新增升级常量**：`UPDATE_GITHUB_REPO` / `UPDATE_DIR` / `UPDATE_MAX_PACKAGE_MB` / `UPDATE_MAX_EXTRACTED_MB` / `UPDATE_BACKUP_KEEP`。
- **后台顶部栏升级入口**：版本号改为可点击的胶囊按钮，点击进入 `admin/update.php`。

### 变更
- **移动端顶栏优化**：恢复单行布局，按钮全部改为高清晰度 SVG 图标。

### 修复
- **在线升级状态自愈**：失败或已回滚状态下不再锁死升级按钮。
- **在线升级容错提示**：对 `zip` 扩展缺失、下载失败等场景提供友好错误提示和「再次尝试」入口。

### 安全
- **在线升级多层防护**：路径穿越防护、下载地址白名单、覆盖白名单与保留清单、解压大小上限 200MB、并发锁、状态原子写入、GitHub API 强制 `User-Agent`。

---

## [1.1.0] - 2026-10-24

### 新增
- 4 步安装向导：管理员账号 → 站点信息 → 行为配置 → 完成预览。
- 站内公告系统：`announcement.php` 公开公告页 + 后台「📢 公告管理」。
- 版本号常量 `NUOMI_VERSION` 定义在 `admin/defaults.php`。
- `.gitignore`、`.htaccess` 敏感文件保护。

### 变更
- 默认值统一到 `admin/defaults.php`。
- 前台站点配置读取：读 `data.json` + `site_defaults()` 空值兜底。

### 修复
- 修复 `site_defaults()` 等函数重复定义导致 fatal error。
- 修复 `admin/.htaccess` 的 `Options -Indexes` 在 `AllowOverride` 未开时触发 500。

### 安全
- `.gitignore` 防止 `data.json` / `.admin.conf` 误提交。
- 公告内容拒绝 `<script>` 标签。
- 安装向导 `init` action 调整为先保存 `data.json` 再写 `.admin.conf`。

---

## [1.0.0] - 2026-10-23

首个正式版本。

### 新增
- 双图层动态背景轮播（毛刺淡出 + 弹性淡入），支持一键启停。
- Banner 轮播（滑动 + 光效扫过）。
- IndexedDB 图片缓存（背景 / Banner 独立缓存池，回访秒显）。
- 内容指纹去重（头尾 32KB 双 FNV 采样）。
- 待播槽预取机制（切换零等待）。
- 本地背景兜底（API 优先 / 本地优先自由切换）。
- 交叉兜底防黑屏。
- 悬浮圆形头像（连点进入后台）。
- 多分类标签页、链接卡片 / 图片卡片。
- 看板娘（可拖动、记忆位置、切换形象）、看板娘语录（本地 + 远程 API 交替）。
- JS 特效管理（可控特效即时生效，固定特效刷新开关）。
- 加载页主题（12 个内置主题，支持自定义 CSS）。
- 全站统计代码、主题系统（跟随系统 / 浅色 / 深色，防闪烁预解析）、硬件性能自适应。
- 三态轮播开关、本地背景与 Banner 独立管理、UA 伪装模板、备份恢复、清除数据。

### 安全
- 管理员密码哈希存储。
- `.admin.conf` 防直读。
- 生产环境关闭 `display_errors`。
- 用户可编辑内容统一 `JSON_HEX_TAG` 转义。
- URL 字段拒绝 `javascript:` 等非 http(s) 协议。
- 统计代码拒绝 PHP 标签。
- 上传文件扩展名白名单 + 图片有效性校验。

---

## 版本号约定

- **主版本号（MAJOR）**：不兼容的 API 变更、数据格式变更
- **次版本号（MINOR）**：向下兼容的功能新增
- **修订号（PATCH）**：向下兼容的 bug 修复

## 升级方式

### 方式一：在线升级（推荐）
后台顶部栏点「版本号」→ 环境检测通过后一键升级。

### 方式二：手动覆盖升级
下载新版压缩包，解压覆盖上传，**务必保留**：

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
5. GitHub 上打 tag（`vx.y.z`）
6. 创建 Release，上传 zip，把本段落内容粘贴到 Release 说明里
   - **注意**：Release 的 zip 必须是**带项目根目录**的压缩包（解压后有 `index.php`、`admin/` 等顶层文件）
   - Release 的 `body` 字段就是升级页「更新说明」的内容
   - 如果 Release 提供了 `digest` 字段（格式 `sha256:xxx`），在线升级会自动做 SHA256 校验