# resDl — 资源下载网站

类清华开源镜像站的资源下载网站，支持本地文件和外链资源的管理与下载。提供用户系统、后台管理、多级分类、搜索、下载计数、VIP 资源独立密码、断点续传、实时目录扫描等功能。

## 环境要求

| 依赖 | 版本 |
|------|------|
| PHP | 8.1+ |
| MySQL | 5.7+ 或 MariaDB 10.2+ |
| Web 服务器 | Apache（推荐）或 Nginx |
| PHP 扩展 | PDO, mysqli, json, session, fileinfo |

字符集统一使用 **utf8mb4**（utf8mb4_unicode_ci）。

## 快速安装

### 1. 宝塔面板部署

1. 在宝塔面板新建站点，选择 **PHP 8.1**，创建 MySQL 数据库（字符集 utf8mb4）
2. 将 `resDl/` 目录下所有文件上传到网站根目录
3. 浏览器访问 `http://你的域名/install.php`，进入安装向导
4. 步骤一：填写数据库连接信息 + 站点 URL
5. 步骤二：设置管理员账号密码
6. 安装完成后，**立即删除 `install.php`**（安全要求）
7. 设置 `uploads/` 目录权限为 **755**，所有者为 Web 服务器运行用户（如 `www`）

### 2. 手动安装

如果无法使用 Web 安装向导：

1. 修改 `inc/config.php` 中的数据库连接参数：
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'resdl');
   define('DB_USER', 'root');
   define('DB_PASS', '你的密码');
   define('BASE_URL', 'https://你的域名.com');
   ```

2. 导入 `install.sql` 到数据库：
   ```bash
   mysql -u root -p < install.sql
   ```

3. 手动创建管理员（使用 PHP）：
   ```php
   php -r "echo password_hash('你的密码', PASSWORD_DEFAULT);"
   ```
   将生成的哈希值插入 `users` 表：
   ```sql
   INSERT INTO users (username, password, email, role) VALUES ('admin', '哈希值', 'admin@example.com', 'admin');
   ```

4. 同样更新 `site_url` 设置：
   ```sql
   UPDATE settings SET setting_value = 'https://你的域名.com' WHERE setting_key = 'site_url';
   ```

### 3. Nginx 配置

如果使用 Nginx 代替 Apache，添加以下重写规则：

```nginx
# 禁止直接访问 uploads 目录
location /uploads/ {
    deny all;
    return 403;
}

# 禁止访问 inc 目录
location /inc/ {
    deny all;
    return 403;
}
```

## 首次使用

1. 访问 `http://你的域名/admin/` 登录后台（默认管理员账号密码为安装时设置）
2. 进入 **扫描目录** 页面，配置扫描模式：
   - **固定目录模式**：设置一个固定扫描目录（如 `uploads`）
   - **指定目录模式**：添加一个或多个目录路径
3. 点击 **立即扫描** 执行首次目录同步
4. 进入 **系统设置** 页面，根据需要调整：
   - 站点名称
   - 站点 URL（用于 HTTPS 切换、反向代理等场景）
   - 每页显示资源数
   - 允许上传的扩展名
   - 注册/上传开关
5. 可选：添加分类（**分类管理**页面）

## 目录结构

```
resDl/
├── index.php              # 首页 — 资源列表、搜索、分类筛选
├── detail.php             # 资源详情页
├── download.php           # 下载处理（VIP 门控、断点续传、防盗链）
├── upload.php             # 用户上传文件
├── edit_resource.php      # 编辑资源信息
├── login.php              # 用户登录
├── register.php           # 用户注册
├── logout.php             # 用户注销
├── profile.php            # 个人中心
├── ajax_verify_vip.php    # AJAX VIP 密码验证
├── cron_scan.php          # 定时扫描脚本（用于 cron）
├── install.php            # Web 安装向导（用完即删）
├── install.sql            # 数据库初始化脚本
│
├── inc/                   # 核心库
│   ├── config.php         # 数据库/站点配置
│   ├── db.php             # PDO 数据库连接
│   ├── auth.php           # 认证 + CSRF 保护
│   ├── functions.php      # 业务逻辑函数
│   └── init.php           # 统一入口 + 设置管理
│
├── admin/                 # 后台管理
│   ├── index.php          # 仪表盘
│   ├── categories.php     # 分类管理
│   ├── resources.php      # 资源管理
│   ├── scans.php          # 扫描目录管理
│   ├── settings.php       # 系统设置
│   └── users.php          # 用户管理
│
├── assets/                # 静态资源
│   ├── css/style.css      # 样式表
│   └── js/main.js         # 前端脚本
│
└── uploads/               # 文件存储目录（需可写）
    ├── .htaccess          # 禁止直接访问
    └── index.html         # 占位页
```

## 核心功能说明

### 用户系统
- **注册/登录/注销**：标准实现，密码使用 `password_hash()` 哈希存储
- **角色**：`user`（普通用户）和 `admin`（管理员）
- **权限**：
  - 普通用户可上传文件、管理自己的资源、下载公开资源
  - 管理员可管理所有资源、用户、分类、系统设置
  - 管理员可将任何资源设为 VIP 并设置独立密码

### 资源管理
- **本地文件**：通过目录扫描自动发现，或用户手动上传
- **外链资源**：管理员在后台手动添加第三方下载链接（如百度网盘）
- **VIP 资源**：可设置独立下载密码，每个资源密码独立

### 目录扫描
- **固定目录模式**：扫描预设的单一目录
- **指定目录模式**：扫描管理员指定的多个目录
- **批次扫描**：大目录分批处理（200 文件/批），避免 PHP 超时
- **缓存 TTL**：扫描结果缓存指定时间（默认 120 秒），减少磁盘 I/O
- **定时扫描**：推荐配置 cron 定期执行 `cron_scan.php`

### 断点续传
- 支持 HTTP `Range` 头（206 Partial Content）
- 兼容 IDM、迅雷等多线程下载工具
- 仅完整下载（非 Range 请求）增加下载计数

### VIP 密码机制
- 每个 VIP 资源独立密码，使用 `password_hash()` 存储
- 验证通过后存入 session，后续无需重复输入
- **密码修改后旧 session 自动失效**（session 存储哈希值比对）
- **外链 VIP 资源不直接 302 跳转**，防止链接泄露
- 管理员下载无需密码

### 防盗链保护
- 本地文件下载时校验 `HTTP_HOST` 和 `HTTP_REFERER`
- 仅允许本站域名发起的下载请求
- 外站直接链接下载文件会被拒绝（HTTP 403）

### 安全性
- **SQL 注入防护**：所有查询使用 PDO 预处理
- **CSRF 保护**：所有 POST 表单包含 CSRF 令牌
- **路径遍历防护**：下载时验证文件路径在允许的扫描目录内
- **文件上传校验**：扩展名白名单 + `finfo` MIME 类型检测
- **文件名安全处理**：清除危险字符，防覆盖（前缀 `{user_id}_{timestamp}_`）
- **上传目录保护**：`.htaccess` 禁止 PHP 执行和目录索引

## 站点 URL 配置

`SITE_URL` 用于生成所有绝对链接（CSS/JS 资源、下载链接、详情页链接等）。

- **安装时**：通过 `install.php` 填写，写入 `config.php` 的 `BASE_URL` 常量
- **运行时**：默认使用配置文件中的值；管理员可在后台 **系统设置 → 站点 URL** 中修改，修改后立即生效（无需改配置文件）
- **使用场景**：
  - 多域名部署（如 `https://dl.example.com`）
  - HTTP → HTTPS 迁移
  - 反向代理（CDN / Nginx 前端）后域名变更
  - 开发环境与生产环境切换

## 定时任务（推荐）

配置 cron 定期执行扫描，避免用户访问时触发大目录扫描：

```
*/5 * * * * /usr/bin/php /www/wwwroot/你的站点/cron_scan.php
```

或在宝塔面板的 **计划任务** 中添加。

## 常见问题

### Q: 上传文件提示"不支持的文件类型"
A: 在后台 **系统设置 → 允许上传的文件扩展名** 中添加对应的扩展名（逗号分隔）。

### Q: 下载大文件超时
A: 在宝塔 PHP 设置中调大 `max_execution_time`（建议 300+），或使用支持断点续传的下载工具。

### Q: 迁移服务器后资源路径失效
A: 系统使用相对路径存储（相对于 `SITE_ROOT`），只需确保新的 `SITE_ROOT` 正确即可。可在 `config.php` 中调整。

### Q: VIP 资源密码忘记了
A: 管理员可在后台 **资源管理 → 编辑** 中重新设置 VIP 密码。

### Q: 新建分类后资源不显示
A: 请检查资源是否选择了该分类。系统支持递归查询子分类，父分类筛选会显示所有子分类下的资源。

## 技术栈

- **后端**：PHP 8.1（PDO + 原生函数，零第三方依赖）
- **数据库**：MySQL 5.7+ / MariaDB 10.2+（utf8mb4）
- **前端**：HTML5 + CSS3（原生，响应式，无框架）
- **服务器**：Apache（支持 .htaccess）或 Nginx

## 许可

内部项目，自由使用。
