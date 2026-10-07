# 🚀 cPanel 部署指南 (cPanel Deployment Guide)

本项目无需 MySQL 数据库，纯 PHP 8.3+ 文件存储架构，非常适合部署在任何 cPanel 虚拟主机或独立服务器上。

---

## 📁 步骤 1：上传文件到 cPanel

1. 将整个 `talkie` 项目目录压缩为 `talkie.zip`。
2. 登录您的 **cPanel 控制面板**。
3. 打开 **文件管理器 (File Manager)**。
4. 选择部署路径：
   - **方案 A（独立子域名，推荐）**：例如 `talkie.yourdomain.com`，将文件解压到该子域名的根目录（如 `public_html/talkie` 或 `/home/username/talkie`）。
   - **方案 B（主域名子目录）**：例如 `https://yourdomain.com/talkie/`，将文件解压到 `public_html/talkie/`。

---

## ⚙️ 步骤 2：配置 `.env` 文件

cPanel 默认可能隐藏以点 `.` 开头的文件。
> **提示**：在 cPanel 文件管理器右上角点击 **设置 (Settings)** -> 勾选 **显示隐藏文件 (Show Hidden Files / dotfiles)** -> 点击 **Save**。

1. 在项目根目录中，找到预设的 `.env.cpanel` 文件。
2. 将其重命名为 `.env`（或者复制 `.env.cpanel` 的内容新建 `.env`）。
3. 根据您的实际域名修改配置：

### 方案 A：独立子域名（如 `https://talkie.yourdomain.com`）
```env
APP_ENV=production
APP_DEBUG=false
APP_URL="https://talkie.yourdomain.com"
APP_BASE_PATH=""

# 随机生成一段长密钥保证通信安全
SIGNAL_SECRET=your_unique_random_secret_string_here_987654

SIGNAL_MAX_PEERS=8
SIGNAL_FLOOR_TIMEOUT_MS=30000
```

### 方案 B：主域名子目录（如 `https://yourdomain.com/talkie`）
```env
APP_ENV=production
APP_DEBUG=false
APP_URL="https://yourdomain.com/talkie"
APP_BASE_PATH="/talkie"

SIGNAL_SECRET=your_unique_random_secret_string_here_987654

SIGNAL_MAX_PEERS=8
SIGNAL_FLOOR_TIMEOUT_MS=30000
```

---

## 🔒 步骤 3：设置目录写入权限 (Storage Permissions)

对对讲机房间临时数据、日志及限流目录赋予可写权限：

在 cPanel 文件管理器中，右键以下文件夹并选择 **Change Permissions**，设置为 `775` 或 `755`（若主机环境特殊可设为 `777`）：
- `storage/rooms/`
- `storage/logs/`
- `storage/ratelimit/`

---

## 🌐 步骤 4：启用免费 SSL 证书 (HTTPS)

> ⚠️ **重要**：WebRTC 麦克风录音权限（`getUserMedia`）要求必须在 HTTPS 协议下运行（除 `localhost` 外）。

1. 在 cPanel 中打开 **SSL/TLS Status** 或 **Let's Encrypt SSL**。
2. 为您的域名/子域名勾选并点击 **Run AutoSSL**。
3. 确保访问地址为 `https://...`。

---

## 📱 步骤 5：访问与使用

1. 打开浏览器访问您的网址（例如 `https://talkie.yourdomain.com`）。
2. 输入昵称和频道名称，点击 **JOIN CHANNEL**。
3. 浏览器会弹出麦克风授权窗口，点击 **允许 (Allow)**。
4. 手机端浏览器打开同个网址，即可加入同一频道进行对讲！

---

## 🛠️ cPanel 常见排错

| 现象 | 原因 | 解决方法 |
|---|---|---|
| **页面 404 或样式丢失** | `APP_BASE_PATH` 设置不匹配 | 若部署在根目录，将 `APP_BASE_PATH` 设为 `""`；若在子目录，设为 `"/talkie"`。 |
| **无法说话 / 麦克风未授权** | 未开启 HTTPS | 在 cPanel 中通过 AutoSSL 开启免费 SSL 证书。 |
| **提示 "Could not access room storage"** | `storage/` 目录没有写入权限 | 将 `storage/rooms` 权限修改为 `775` 或 `777`。 |
| **看不到 `.env` 文件** | cPanel 隐藏了以点开头的文件 | 在文件管理器右上角「设置」中勾选「显示隐藏文件」。 |
