# CHANGELOG

## v1.2.0 — 2026-05-24

### 新增
- **nginx.conf**：Nginx 站点安全配置（推荐），包含 uploads/ 和 inc/ deny 规则、PHP 执行禁止

### 变更
- **默认 Web 服务器**：从 Apache 改为 Nginx（README.md、ready.md 文档更新）
- **README.md**：安装流程新增 Nginx 安全配置步骤；Apache 降为备选方案
- **already.md / issues.md**：#23 标记已修复

### issues.md 修复
- #23 修改默认使用 Nginx 而不是 Apache

---

## v1.1.0 — 2026-05-24

### 新增
- **SITE_URL 动态配置**：`inc/init.php` 定义 `SITE_URL` 常量（从数据库 settings 表 `site_url` 读取，回退到 config.php 的 `BASE_URL`）
- **`site_url()` 辅助函数**：生成完整绝对 URL
- **后台站点 URL 设置**：`admin/settings.php` 新增"站点 URL"设置项，修改后即时生效
- **安装时 URL 写入**：`install.php` 步骤一完成后自动将站点 URL 写入 settings 表
- **防盗链保护**：`serveFileDownload()` 校验 `HTTP_HOST` + `HTTP_REFERER`，仅允许本站域名下载
- **README.md**：完整使用说明文档（安装部署、功能说明、Nginx 配置、常见问题 FAQ）
- **ready.md 更新**：新增"站点 URL 配置"章节

### 变更
- 所有前端 CSS/JS 引用从相对路径改为 `<?= SITE_URL ?>/assets/...`
- 所有前端内链（下载、详情、分页）从 `BASE_URL` 改为 `SITE_URL`
- JS 全局 `BASE_URL` 变量改为从 `SITE_URL` 输出
- `install.sql` settings 新增 `site_url` 设置项

### issues.md 修复
- #21 站点基础 URL 缺失（SITE_URL 动态配置 + 后台可修改）
- #22 下载脚本 Host 头校验（HTTP_HOST + HTTP_REFERER 防盗链）

---

## v1.0.0 — 2026-05-24

### 初始版本
- 完整资源下载网站，支持本地文件和外链资源管理
- 用户系统（注册/登录/注销/角色管理）
- 多级分类管理（无限子分类）
- 实时目录扫描同步（批次处理 + 缓存 TTL）
- VIP 资源独立密码保护
- HTTP Range 断点续传（本地文件）
- AJAX VIP 密码验证
- 后台管理面板（仪表盘/分类/资源/扫描/设置/用户）
- CSRF 保护、MIME 校验、路径遍历防护、相对路径存储
- 响应式前端设计

### issues.md #1-#20 已修复
