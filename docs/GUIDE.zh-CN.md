<p align="center">
  <a href="GUIDE.md">Français</a> · <a href="GUIDE.en.md">English</a> · <a href="GUIDE.es.md">Español</a> · <a href="GUIDE.de.md">Deutsch</a> · <a href="GUIDE.it.md">Italiano</a> · <a href="GUIDE.pt-BR.md">Português</a> · <b>简体中文</b>
</p>

# Éclair CMS 使用指南

安装、使用、保护和维护 Éclair 所需了解的一切。如需项目介绍，请返回 [README](../README.zh-CN.md)。

> [!NOTE]
> Éclair 的后台支持 7 种语言（含简体中文），本指南中的菜单和按钮名称以简体中文界面为准。网站前台支持 6 种语言（法语、西班牙语、英语、德语、意大利语、葡萄牙语），暂不支持中文。

## 目录

1. [安装](#安装)
2. [功能详解](#功能详解)
3. [日常使用](#日常使用)
4. [安全](#安全)
5. [更新、备份、迁移](#更新备份迁移)
6. [故障排除](#故障排除)（包括 [Nginx](#nginx)）
7. [开发者指南](#开发者指南)

---

## 安装

### 环境要求

- **PHP 8.1 及以上**（推荐 8.3），需要 `mbstring`、`json`、`dom` 和 `session` 扩展（几乎所有环境都默认启用）。
- 推荐：`gd`（WebP 图片）、`zip`（一键备份）、`curl`（IndexNow）。
- **Apache 或 LiteSpeed** 服务器（几乎所有虚拟主机都满足：Hostinger、IONOS、OVHcloud……）。使用 Nginx 请见[下文](#nginx)。
- HTTPS 证书（几乎所有主机商都提供免费的 Let's Encrypt 证书）。

安装向导会替你检查以上所有条件，并说明如何修正。

### 安装步骤

1. 下载安装包 [`eclair-cms-1.1.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.1.0.zip)（315 KB）并解压。
2. 使用 FTP 软件（如 FileZilla）或主机的文件管理器，把 **`eclair` 文件夹里的内容**上传到主机的公开目录（`public_html`、`www`……）。
   > ⚠️ `.htaccess` 是隐藏文件：请开启“显示隐藏文件”，确保它们也被上传。
3. 打开你的网站地址：**安装向导随即启动**（支持 7 种语言，包括简体中文）。
4. 回答 4 个问题（你的网站、你的账户、你的可见性、后台地址）。彩纸飘落 🎉：网站上线，并自带几个页面和一篇示例文章。

> 🔒 出于安全考虑，必须在首次访问后的 **2 小时内**完成安装（否则任何人都可能接管一个上传后被遗忘的网站）。超时后，请通过 FTP 删除 `data/install-window.php` 文件并刷新页面。

安装到子目录（`example.com/site/`）：只需把文件上传到该子目录，一切都会自动识别。

### 4 种网站类型

| 类型（安装向导中的名称） | 自动创建的内容 |
|---|---|
| 🏢 企业官网 | 首页（横幅、优势、最新文章）、服务（含 FAQ）、关于、联系、博客、法律声明、隐私政策 |
| ✍️ 博客 | 首页 = 最新文章、关于、联系、“Guides”分类、法律页面 |
| 🧑‍💻 自由职业 / 作品集 | 以你的名字命名的首页、技能、关于、博客、联系、法律页面 |
| 🎯 单页网站 | 一个销售页面（横幅、优势、FAQ、表单）+ 法律页面，没有博客 |

初始内容提供法语、西班牙语和英语版本，其他语言的网站会使用英语内容。法律页面提供了一个待完善的框架，依据的是法国（LCEN）、西班牙（LSSI）和欧盟 GDPR 的要求；你所在国家或地区的其他法律要求需要自行补充。

### 安装完成后

仪表盘会显示一份“新手入门”入门清单：标志和主色、首页、第一篇文章、作者资料、双重认证、法律声明、Google Search Console。

---

## 功能详解

### ⚡ 速度

- **自动静态缓存**：每个页面只生成一次，之后无需加载应用即可直接输出。每次修改后缓存会自动清空（定时发布的文章会在预定时间清空）。
- **CSS 内联在页面中**（压缩后约 12 KB），使用系统字体：没有阻塞渲染的请求，也没有布局偏移。
- **上传时自动优化图片**：缩放尺寸（最大 2000 px），生成**响应式 WebP**（480、800、1200、1600 px），写明宽高，延迟加载，并优先加载主图。
- ETag 与 `304 Not Modified` 响应，静态文件启用 Brotli/Gzip 压缩和一年的浏览器缓存（通过 `.htaccess`）。

### 🔎 SEO

- 简洁的网址，网址变更时**自动 301 重定向**，另有重定向管理器（方便接手旧的 WordPress 或 Wix 网站）。
- 编辑器内可设置标题和元描述，并带计数器和**实时 Google 搜索预览**。
- **实时 SEO 分析**并给出评分：标题和描述长度、关键词（标题、网址、引言、小标题、密度）、字数、结构、替代文本、内部链接、特色图片……
- 完整的 **JSON-LD 结构化数据**：WebSite（+ 站内搜索）、Organization / Person / LocalBusiness（地址、营业时间、社交主页）、WebPage / AboutPage / ContactPage、BlogPosting（作者、日期、栏目）、BreadcrumbList、FAQPage、ProfilePage。
- 包含图片和 `hreflang` 的 XML 站点地图、robots.txt、全文 RSS 订阅、Open Graph 和 Twitter Cards（**自动生成 1200×630 分享图**）、面包屑导航、规范网址。
- 每个内容只有一个地址：自动处理 HTTPS、带或不带 www、末尾斜杠、大写字母和 `index.php` 的重定向。
- **IndexNow**：每次发布都会通知 Bing、Yandex、Seznam 等搜索引擎。
- 支持 Google Search Console 和 Bing Webmaster Tools 验证（粘贴 `<meta>` 标签即可，Éclair 会自动提取验证码）。
- 多语言：每个内容可设置语言，关联各语言版本并自动生成 `hreflang` 标签。
- 法语内容自动应用法语排版规范（在 `: ; ! ?` 前和 « » 引号内加不换行空格）。

### 🤖 GEO（AI 问答引擎）

- 自动生成 **`/llms.txt`**（llmstxt.org 标准）和 **`/llms-full.txt`**。
- **每个页面都有 Markdown 版本**：在任意网址后加上 `.md` 即可（带元数据头、`noindex` 和规范网址，不会产生重复内容）。
- **一键设置 AI 爬虫策略**：开放（推荐，便于被引用）、仅限引用（允许问答引擎，拒绝模型训练）或关闭。覆盖 GPTBot、OAI-SearchBot、ChatGPT-User、ClaudeBot、Claude-SearchBot、Claude-User、PerplexityBot、Google-Extended、Applebot-Extended、CCBot、Meta-ExternalAgent 等，并可选 `Content-Signal` 指令。
- **要点区块**（界面中名为 Key takeaways）：显示在文章开头，是 AI 最爱引用的摘要。
- 编辑器内的 **GEO 评分**：摘要、开门见山的引言、FAQ、问句式小标题、列表和表格、具体数据、外部来源、明确的作者（E-E-A-T）、短段落、内容新鲜度。
- 作者页面包含简介和社交主页（`sameAs`），并显示发布日期和更新日期。

### ✍️ 区块编辑器

- 16 种区块：文本、小标题（H2-H4）、列表、引用、图片、提示框、FAQ、表格、按钮、YouTube/Vimeo 视频（点击前不加载任何 Cookie）、代码、分隔线、横幅、优势、最新文章、联系表单。
- 在空行输入 **`/`** 即可选择区块，支持拖放，并带格式工具栏（粗体、斜体、链接、代码、高亮）。
- **智能内部链接**：输入链接时，编辑器会推荐你的页面和文章（2 秒完成内部链接）。
- **智能粘贴**：从 Word、Google Docs 或网页粘贴时，标题、列表、表格和粗体会自动转换为整洁的区块。
- `Ctrl+S` 保存，本地备份草稿（断网也不会丢失内容），发布前可真实预览，**保留最近 20 个历史版本**，支持定时发布。

### 🧩 其他功能

媒体库（拖放上传、替代文本、识别正在使用的图片）、可拖放的菜单（页眉和页脚）、用于构建主题集群的“支柱”分类、站内搜索、**无需验证码**的防垃圾联系表单（蜜罐字段、令牌、按网络和按天限流）及收件箱、管理员和编辑账户、自动深色模式、6 种系统字体、保证 WCAG 对比度的主色、7 种语言的后台界面（含简体中文）、6 种语言的公开网站（fr、es、en、de、it、pt）。

---

## 日常使用

| 我想…… | 位置 |
|---|---|
| 写一篇文章 | 仪表盘 › **撰写文章** |
| 在网站上直接编辑页面 | **✏️ Edit** 按钮（仅在登录后可见，名称随网站语言变化） |
| 更换标志、主色、字体 | 设置 › 外观 |
| 设置首页 | 设置 › 常规 |
| 允许或屏蔽 AI | 设置 › AI 与 GEO |
| 关联 Google Search Console | 设置 › SEO |
| 在菜单中添加链接 | 菜单 |
| 重定向旧网址 | 重定向 |
| 开启双重认证 | 个人资料 › 双重验证 |
| 下载备份 | 安全 › 备份 |

编辑器快捷键：`/` 插入区块 · `Ctrl+S` 保存 · `Ctrl+K` 链接 · `Ctrl+B` 粗体 · `Ctrl+I` 斜体 · `Enter` 新段落 · `Shift+Enter` 换行。

---

## 安全

Éclair 为恶劣环境而设计（共享主机，以及全天候尝试破解密码的机器人）。它经过了一次攻击性安全审计（竞态条件、暴力破解、重定向、注入、恶意文件）：发现的每个漏洞都已修复，并重放攻击加以验证（`tests/run.php`）。

**数据与服务器**
- **没有数据库**：不可能发生 SQL 注入。
- **数据双重保护**：`data/`、`app/` 和 `cache/` 目录由 `.htaccess` 禁止访问；每个数据文件都以 `<?php exit; ?>` 开头（即使服务器忽略 `.htaccess`，也不会显示任何内容），并且任何 `<?php` 序列都无法写入其中。文件权限私有（0600），以原子方式写入。
- **受保护的安装过程**：只能在首次访问后的 2 小时内安装；网站地址在**由服务器自行验证**之后，才会用于重定向访客（错误或被篡改的地址无法劫持任何流量）。

**登录**
- **密码**使用 Argon2id 哈希（19 MiB，2 轮：安全且适合共享主机；以 bcrypt 作为后备），至少 10 个字符，常见密码会被拒绝。
- **防暴力破解**：每次尝试在校验前先预占名额（即使并发请求也无法突破限制）；按网络（IPv4 地址或 IPv6 的 /64 段）和被攻击的账户分别限流。攻击者故意输错密码，**也绝不会把你常用的设备锁在门外**。同一时间最多进行两次密码校验：大量请求涌入也不会耗尽内存。
- **TOTP 双重认证**（Google Authenticator、1Password、Bitwarden……），附二维码和 8 个只显示一次的恢复码；每个验证码只能使用一次，并发提交也一样；连续输错 10 个验证码后，该账户的双重认证会锁定 15 分钟，并通过电子邮件通知账户所有者。
- **账户修改为原子操作**：正在进行的登录永远无法撤销修改密码、全部下线、启用双重认证、降级或删除账户等操作。
- **敏感操作需再次输入密码**（备份、账户管理、高级设置、修改邮箱），适用于登录超过 15 分钟之后。临时密码必须在首次登录时更换。
- **加固的会话**：登录前不创建会话（机器人无法塞满磁盘），Cookie 设置 `HttpOnly`、`Secure`、`SameSite=Strict`，登录时重新生成，闲置后自动退出，与浏览器绑定，并提供“让其他设备退出登录”。网站启用 HTTPS 后，后台会强制使用 HTTPS。
- 所有操作都带 **CSRF 令牌**，并校验请求来源。

**公开网站**
- **严格的安全响应头**：Content-Security-Policy（不允许内联脚本，样式使用 SHA-256 哈希）、防点击劫持、`nosniff`、`Referrer-Policy`、`Permissions-Policy`，以及可选的 HSTS。它们只取决于设置，与请求无关：缓存无法被投毒。
- **没有开放重定向**：被构造的地址（`/\example.com`、控制字符……）一律返回 404。
- **基于白名单过滤内容**（先解析 HTML，再完整重写）；`javascript:` 和 `data:` 链接无法使用，即使经过伪装（`javascript&colon;`）也不行。Markdown、`llms.txt` 和 RSS 版本始终是惰性内容。
- **联系表单**无需验证码：蜜罐字段、令牌，每个网络每 10 分钟最多 5 条消息，全站每天最多 100 条；收件箱上限 500 条消息（优先清除垃圾信息），并分页显示。
- **上传图片时重新编码**（清除隐藏载荷和 EXIF/GPS 数据）；GIF 动图逐块重写；任何包含代码的图片都会被拒绝；最大 4000 万像素；拒绝 SVG，`/media` 目录不执行脚本，也不列出文件。

**后台**
- **可自定义后台地址**（减少机器人骚扰），区分管理员和编辑角色；网站上的“Edit”按钮不会暴露后台地址。
- **日志有上限**（字段会截断，最大 1 MB）：无法被恶意撑大。
- **完整性检查**：把每个文件与其原始指纹比对，找出可疑文件，包括 `data/` 或 `cache/` 中缺少保护行的 `.php` 文件。
- **实时防护测试**：安全页面会从你的浏览器检测内部文件是否确实无法访问，以及网站地址是否已通过验证。

---

## 更新、备份、迁移

**备份**：安全 › 下载备份（`data/` 和 `media/` 的 ZIP 压缩包）。如果没有 zip 扩展，请通过 FTP 复制这两个目录。

**更新 Éclair**：
1. 先做一次备份。
2. 通过 FTP 用新版本中的 `index.php`、`.htaccess` 以及 `app/` 和 `assets/` 目录替换旧文件。
3. **不要改动** `data/`（内容、设置、账户）和 `media/`（图片）。
4. 在后台执行：设置 › 高级 › 清除缓存。

**迁移到其他主机或域名**：复制整个目录，然后在 设置 › 常规 中修改网站地址。内部链接会自动适配，子目录也不例外。

---

## 故障排除

**忘记密码（或丢失了双重认证手机），又没有其他管理员？**
通过 FTP 创建 `data/reset.php` 文件，内容必须正好是以下三行：

```
<?php exit; ?>
your-address@example.com
YourNewPassword2026
```

在 24 小时内，**用这个邮箱和新密码登录**：密码会被替换，该账户的双重认证会被关闭，你的其他会话会被注销，文件也会被自动删除。其他人无法触发此操作（必须知道文件内容）；缺少第一行的文件会被拒绝并删除。

**“安装已锁定”**：首次访问后的 2 小时安装时限已过。通过 FTP 删除 `data/install-window.php` 并刷新页面。

**网站地址未验证（安全页面）**：你的服务器无法通过网站地址访问自身（主机防火墙、DNS 尚未生效……）。网站可以正常运行，但不会重定向到正式地址（带或不带 www）。域名就绪后，点击“验证网站地址”即可。

**除首页外所有页面都是 404 错误**：`.htaccess` 文件没有上传（它是隐藏文件），或者网址重写功能未启用。如果主机商要求，请取消 `.htaccess` 中 `RewriteBase /` 这一行的注释。

**上传后立即出现 500 错误**：主机商拒绝了 `.htaccess` 中的某条指令（非常少见：只使用了各处都支持的指令）。删除相关代码块，或联系你的主机商。

**图片没有转换为 WebP**：缺少 GD 扩展（或其 WebP 支持）。请在主机的 PHP 设置面板中启用；在此之前，图片会保留为 JPG/PNG 格式。

**收不到联系表单的邮件**：消息也会保存在后台（消息）。请在 设置 › 联系 中检查邮箱地址；有些主机商要求发件人必须属于网站域名。

**调试模式**：在根目录创建 `eclair-config.php` 文件（参考 `eclair-config.exemple.php`），写入 `define('ECLAIR_DEBUG', true);`，问题解决后再将其删除。

### Nginx

在 Nginx 下，Éclair 不需要 `.htaccess`，但必须复现以下规则：

```nginx
location ~ ^/(app|data|cache)(/|$) { return 404; }
location ~ /\.(?!well-known/) { return 404; }
location ^~ /media/ {
    location ~* \.(php\d?|phtml|phar|s?html?|svgz?|xml|js)$ { return 404; }
    add_header X-Content-Type-Options nosniff;
    expires 1y;
}
location ~* \.(webp|avif|jpe?g|png|gif|ico|css|js|woff2)$ { expires 1y; add_header Cache-Control "public, immutable"; }
location / { try_files $uri /index.php$is_args$args; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ \.php$ { return 404; }
```

---

## 开发者指南

```
eclair/                  ← 上传到主机的目录
├── index.php            前端控制器（无需加载应用即可输出缓存）
├── .htaccess            网址重写、防护规则、压缩、浏览器缓存
├── app/lib/             代码（Eclair\… 类）：Content、Blocks、Seo、Analyzer、Media、Auth……
├── app/admin/views/     后台界面
├── app/theme/           公开网站模板 + theme.css（内联到每个页面）
├── app/lang/            后台翻译（en、es），以法语原文作为键
├── assets/admin/        后台的 CSS 和 JavaScript（无任何依赖）
├── assets/site/site.js  唯一的公开脚本（2.4 KB，压缩后 1.2 KB）
├── data/                内容、设置、账户（安装时创建，永不公开）
├── cache/               生成的页面
└── media/               上传的图片
tests/run.php            单元测试 + HTTP 集成测试（包括安全审计中的攻击场景）
tools/router.php         PHP 内置服务器的路由脚本（开发用）
tools/build.php          生成完整性指纹 + 安装包 dist/eclair-cms-<版本>.zip
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # 开发服务器
php tests/run.php                                  # 测试（单元 + HTTP）
php tools/build.php                                # 生成可发布的安装包
```

`PHP_CLI_SERVER_WORKERS` 让网站能够访问自身（用于验证网站地址）：如果只有一个进程，验证会直接失败，重定向到正式地址的功能将保持关闭。

如需定制主题，请修改 `app/theme/*.php` 和 `app/theme/theme.css`（记得先备份：完整性检查会把这些文件标记为已修改，这是正常现象）。

---

Éclair 以 [MIT 许可证](../LICENSE)发布。后台的部分图标源自 [Lucide](https://lucide.dev)（ISC 许可证）：见 [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt)。
