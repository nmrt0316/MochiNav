# Changelog

本项目所有重要变更都记录在此文件。

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

## [1.1.1] - 2026-10-25

### 新增
- **在线升级（对接 GitHub Releases API）**：新增独立升级页 `admin/update.php`，支持「检查更新 → 环境检测 → 下载更新包 → 应用升级 → 一键回滚」完整闭环。
  - 检查更新：对接 GitHub API，5 分钟缓存，避免触发 GitHub 速率限制。
  - 环境检测：自动测试 PHP 运行用户写权限，不通过降级手动下载。
  - 下载更新包：SHA256 校验，解压到 `admin/.update/package/`，支持防路径穿越、解压体积上限控制。
  - 应用升级：先备份当前代码，再逐个覆盖，失败自动回滚。
  - 一键回滚：用最近一次备份覆盖当前代码，状态转为 `rolled_back`。
- **新增 5 个后台 API action**：`update_check` / `update_download` / `update_apply` / `update_rollback` / `update_status`。
- **`admin/defaults.php` 新增升级常量**：`UPDATE_GITHUB_REPO` / `UPDATE_DIR` / `UPDATE_MAX_PACKAGE_MB` / `UPDATE_MAX_EXTRACTED_MB` / `UPDATE_BACKUP_KEEP`。
- **`admin.css` 新增第 27 章「在线升级页样式」**。
- **后台顶部栏升级入口**：右上角版本号改造为可点击的胶囊样式（带 SVG 刷新图标），点击进入 `admin/update.php`。

### 变更
- **移动端顶栏深度优化**：恢复单行布局，返回首页、自动读取等按钮全面替换为高清晰度 SVG 图标，告别 Emoji 渲染差异，界面更优雅统一。
- **`README.md` 补充**：更新在线升级说明、Nginx 保护 `admin/.update/` 目录、常见问题等说明。

### 修复
- **在线升级状态自愈**：修复在线升级在「失败」或「已回滚」状态下，升级按钮被锁死、必须手动删除 `state.json` 才能重试的体验问题。现在只要检查到新版本且环境允许，始终优先展示「立即升级」按钮。
- **在线升级容错提示**：对 `zip` 扩展缺失、下载失败等场景提供友好的错误提示和「再次尝试」入口，避免升级失败后卡死。

### 安全
- **在线升级多层防护**：
  - 路径穿越防护（拒绝 `..`、绝对路径、Windows 盘符）。
  - 下载地址白名单（仅认 GitHub 官方域名）。
  - 覆盖白名单与保留清单（`data.json` / `.admin.conf` / `photos/` 等永不覆盖）。
  - 解压大小上限 200MB（防 Zip 炸弹）。
  - 并发锁与状态原子写入。
  - GitHub API 请求强制携带 `User-Agent`。

---

## [1.1.0] - 2026-10-24

### 新增
- **4 步安装向导**：管理员账号 → 站点信息 → 行为配置 → 完成预览。
- **页脚两行显示**：第一行版权信息，第二行 `Powered by 项目名 · GitHub 链接`。
- **GitHub 链接独立开关**。
- **站内公告系统**：`announcement.php` 公开公告页 + 后台「📢 公告管理」标签页。
- **版本号常量**：`NUOMI_VERSION` 定义在 `admin/defaults.php`，后台顶部栏显示。
- **`.gitignore`、`.htaccess`** 敏感文件保护。

### 变更
- **默认值统一到 `admin/defaults.php`**。
- **前台站点配置读取**：读 `data.json` + `site_defaults()` 空值兜底。

### 修复
- 修复 `site_defaults()` 等函数重复定义导致 fatal error 的问题。
- 修复 `admin/.htaccess` 的 `Options -Indexes` 在 `AllowOverride` 未开时触发 500 的问题。

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
- 多分类标签页。
- 链接卡片 / 图片卡片。
- 看板娘（可拖动、记忆位置、切换形象）。
- 看板娘语录（本地 + 远程 API 交替出场）。
- JS 特效管理（可控特效即时生效，固定特效刷新开关）。
- 加载页主题（12 个内置主题，支持自定义 CSS）。
- 全站统计代码。
- 主题系统（跟随系统 / 浅色 / 深色，防闪烁预解析）。
- 硬件性能自适应。
- 三态轮播开关。
- 本地背景与 Banner 独立管理。
- UA 伪装模板。
- 备份恢复。
- 清除数据。

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
点击后台顶部栏的「版本号（如 v1.1.1）」→ 环境检测通过后即可一键升级，详见 README「升级须知 → 方式一：在线升级」。

### 方式二：手动覆盖升级
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