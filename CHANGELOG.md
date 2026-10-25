# Changelog

本项目所有重要变更都记录在此文件。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，
版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

---

## [Unreleased]

### 计划中
- 在线升级（对接 GitHub Releases API，支持一键回滚）
- 后台数据概览面板
- 首次登录引导
- 备份 ZIP 化（含图片与特效）
- 错误日志查看

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

### 方式一：在线升级（计划中）
待 `1.2.0` 版本实现，届时后台「🔄 系统更新」页支持一键升级 + 回滚。

### 方式二：手动覆盖升级（当前推荐）
1. 下载新版本压缩包
2. 解压后覆盖上传
3. **务必保留**以下文件（它们是您的数据）：

| 保留 | 说明 |
|---|---|
| `admin/data.json` | 卡片、分类、站点配置 |
| `admin/.admin.conf` | 管理员账号 |
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