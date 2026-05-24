# resDl 项目进度记录

## 构建日期：2026-05-24（更新：域名支持 + 防盗链）

---

## 已完成文件清单

### 1. 数据库设计
| 文件 | 说明 |
|------|------|
| `install.sql` | 完整数据库建表脚本（4 张核心表 + CSRF tokens 表），含默认分类、系统设置初始值（含 `site_url`）、性能索引 |

### 2. 核心公共文件（inc/）
| 文件 | 说明 |
|------|------|
| `inc/config.php` | 站点配置（数据库连接、BASE_URL、SITE_ROOT、CSRF 配置） |
| `inc/db.php` | PDO 单例连接（utf8mb4，错误异常模式） |
| `inc/auth.php` | 用户认证（登录/注册/注销/权限校验）+ CSRF 令牌生成与验证 |
| `inc/functions.php` | 所有业务逻辑函数（目录扫描、分页、下载、文件上传、分类、防盗链） |
| `inc/init.php` | 统一入口加载器（session 启动、设置缓存、SITE_URL 动态加载、默认分类查询） |

### 3. 安装程序 + 定时任务
| 文件 | 说明 |
|------|------|
| `install.php` | 两步骤 Web 安装向导（数据库配置 → 创建管理员），安装时自动写入 `site_url` 到数据库 |
| `cron_scan.php` | 定时扫描脚本（用于 cron 计划任务） |

### 4. 前端页面
| 文件 | 说明 |
|------|------|
| `index.php` | 首页资源列表（搜索、分类筛选、分页、VIP 密码模态框），所有资源链接使用 SITE_URL |
| `detail.php` | 资源详情页（完整元信息展示、VIP 状态提示） |
| `download.php` | 下载处理（VIP 密码门控、外链跳转保护、断点续传、防盗链校验） |
| `upload.php` | 用户上传（登录要求、扩展名白名单、MIME 校验） |
| `edit_resource.php` | 资源编辑（上传者可编辑基本信息，管理员可额外设置 VIP） |
| `login.php` | 用户登录（CSRF 保护、登录后重定向） |
| `register.php` | 用户注册（开关控制、密码确认） |
| `logout.php` | 用户注销（清除 session） |
| `profile.php` | 个人中心（信息查看、密码修改、我的资源管理/删除） |
| `ajax_verify_vip.php` | AJAX VIP 密码验证接口（返回 JSON） |

### 5. 后台管理（admin/）
| 文件 | 说明 |
|------|------|
| `admin/index.php` | 仪表盘（资源数/用户数/下载数统计卡片、系统状态） |
| `admin/categories.php` | 分类管理（树形展示、添加/编辑/删除、排序） |
| `admin/resources.php` | 资源管理（列表/搜索/筛选/编辑/删除、添加外链资源） |
| `admin/scans.php` | 扫描目录管理（固定/指定模式切换、手动扫描、批处理设置） |
| `admin/settings.php` | 系统设置（站点名、**站点 URL**、每页条数、上传白名单、注册开关） |
| `admin/users.php` | 用户管理（角色变更、密码重置、用户删除及资源转移） |

### 6. 静态资源
| 文件 | 说明 |
|------|------|
| `assets/css/style.css` | 完整响应式样式表（布局/卡片/表单/分页/模态框/管理面板/移动适配） |
| `assets/js/main.js` | 前端交互（VIP 密码模态框、AJAX 验证、确认对话框、搜索清除） |

### 7. 安全与服务器配置
| 文件 | 说明 |
|------|------|
| `.htaccess` | Apache 重写规则（禁止直接访问 uploads/ 和 inc/） |
| `uploads/.htaccess` | 禁止 PHP 执行 + 禁用目录索引 |
| `uploads/index.html` | 空白占位页，防止目录列表 |

### 8. 文档
| 文件 | 说明 |
|------|------|
| `README.md` | 详细使用说明（安装部署、功能说明、常见问题、Nginx 配置） |
| `CHANGELOG.md` | 修改日志 |
| `already.md` | 本文件 — 项目构建进度记录 |

---

## issues.md 问题修复情况

| # | 问题 | 修复方式 |
|---|------|---------|
| 1 | 用户请求触发扫描超时 | 批次扫描机制（`scan_batch_size` 设置，默认 200 文件/批）；TTL 缓存避免重复扫描 |
| 2 | 外链 VIP 密码可被绕过 | `download.php` 对 VIP 外链显示中间页，验证确认后展示链接（不直接 302 跳转） |
| 3 | 扫描覆盖用户字段 | `updateResourceFileInfo()` 仅更新 `file_size`，不触碰 `uploader_id`/`description`/`title` |
| 4 | 扫描一次性加载所有文件 | `recursiveScanDirectory()` 支持 offset/limit 分页扫描 |
| 5 | 缺少关键索引 | 添加 `idx_type_status`、`idx_status_created`、`idx_local_path`、`idx_category_status` |
| 6 | 深分页慢 | 延迟关联（deferred join）：先查 ID 子查询，再 JOIN 主表 |
| 7 | 绝对路径不可移植 | 存储相对路径（相对于 `SITE_ROOT`），运行时通过 `resolvePath()` 转换 |
| 8 | 下载脚本路径遍历 | `isPathWithinScanDirs()` 验证真实路径必须在扫描目录范围内 |
| 9 | 上传安全性弱 | `validateUploadedFile()` 使用 `finfo` 校验真实 MIME 类型 |
| 10 | 缺少 CSRF 防护 | 所有 POST 表单均包含 CSRF token，`require_csrf()` 验证 |
| 11 | 默认管理员密码哈希错误 | `install.php` 使用 PHP `password_hash()` 动态生成正确哈希 |
| 12 | "立即扫描"同步等待超时 | 批次扫描 + 进度跟踪（`$_SESSION['scan_batch_offset']`） |
| 13 | 删除逻辑混乱 | 统一物理删除：本地文件删除磁盘文件 + DB 记录；外链仅删记录 |
| 14 | 存储目录可被 Web 直接访问 | `uploads/.htaccess` 禁止直接访问 + 站点根 `.htaccess` 拦截 |
| 15 | 分类查询未递归子分类 | `getCategoryChildren()` 递归收集所有子分类 ID，用于筛选 |
| 16 | 文件名冲突 | 上传文件名前缀 `{user_id}_{timestamp}_` 防覆盖 |
| 17 | status 字段设计冗余 | 简化：仅 `active`/`deleted`，删除时物理删除记录 |
| 18 | 多线程下载计数虚高 | 仅非 Range 请求递增下载计数 |
| 19 | VIP 密码修改后旧 session 仍有效 | session 存储密码哈希值，每次验证时比对当前数据库哈希 |
| 20 | 默认分类 ID 硬编码为 1 | `getDefaultCategoryId()` 动态查询，存储于 settings 表 |
| 21 | 站点基础 URL 缺失 | `SITE_URL` 常量（init.php 从 settings 读取）+ `site_url()` 辅助函数；所有链接使用绝对 URL |
| 22 | 下载脚本 Host 头校验缺失 | `serveFileDownload()` 校验 `HTTP_HOST` + `HTTP_REFERER`，拒绝盗链 |

---

## v1.1 更新内容（域名支持 + 防盗链）

1. **SITE_URL 动态配置** — `inc/init.php` 从数据库 settings 表读取 `site_url`，支持运行时修改
2. **后台站点 URL 设置** — `admin/settings.php` 新增"站点 URL"设置项
3. **安装时自动写入** — `install.php` 步骤一完成后将站点 URL 写入 settings 表
4. **全站绝对链接** — 所有 CSS/JS 引用、下载链接、详情页链接均使用 `SITE_URL` 生成绝对路径
5. **防盗链校验** — `serveFileDownload()` 检查 `HTTP_HOST` 和 `HTTP_REFERER`，仅允许本站域名
6. **README.md** — 详细使用说明文档（安装部署、功能说明、Nginx 配置、常见问题）
7. **ready.md** — 新增"站点 URL 配置"章节

---

## 下一步计划

全部 32 个文件已完成构建。下一步可选：

1. **部署测试** — 将代码上传到宝塔面板站点，运行 `install.php` 完成安装
2. **功能测试** — 验证注册/登录、文件上传、VIP 密码、目录扫描等核心流程
3. **性能调优** — 根据实际文件数量调整 `sync_cache_ttl` 和 `scan_batch_size`
4. **安全加固** — 部署后删除 `install.php`；设置 `uploads/` 目录权限为 755
5. **计划任务** — 配置 cron 定期执行 `php /path/to/cron_scan.php` 避免用户请求时扫描
