<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Node.js-20.x-339933?style=for-the-badge&logo=node.js&logoColor=white" alt="Node.js">
  <img src="https://img.shields.io/badge/Python-3.11+-3776AB?style=for-the-badge&logo=python&logoColor=white" alt="Python">
  <img src="https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="TailwindCSS">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License">
</p>

# 📱 Social CRM — Multi-Channel Messaging Platform

A production-ready **Multi-Channel CRM** (Customer Relationship Management) system built with **Laravel 13**, **Node.js**, and **Python** that enables businesses to manage **WhatsApp** and **Telegram** messaging from a single unified dashboard. Connect multiple accounts, run bulk outreach campaigns, handle real-time inbound/outbound conversations, and manage your team — all from one place.

---

## 🌟 Key Features

### 💬 Multi-Platform Messaging
- **WhatsApp Integration** — Connect WhatsApp accounts via QR code or phone pairing using the [Baileys](https://github.com/WhiskeySockets/Baileys) library
- **Telegram Integration** — Connect Telegram accounts via OTP verification using the [Telethon](https://github.com/LonamiWebs/Telethon) library
- **Real-time Chat** — Send and receive text, image, video, audio, document, and sticker messages in real-time via WebSocket (Laravel Reverb)
- **Media Support** — Full media upload/download support with automatic type detection (images, videos, audio, documents, stickers)
- **Unified Inbox** — All conversations from all connected accounts in one dashboard

### 📊 Campaign Management
- **Bulk Outreach** — Create campaigns to send messages to hundreds of contacts with smart rate limiting
- **CSV/Text Import** — Import client phone numbers or usernames from text lists
- **Smart Delay** — Configurable minimum/maximum delay between messages to avoid platform bans
- **Daily Limits** — Per-account daily message limits to stay within platform guidelines
- **Lead Tracking** — Track each lead's status: pending, contacted, failed with detailed reasons
- **Campaign Analytics** — Real-time progress tracking with contacted/failed/pending counts

### 👥 Team & Account Management
- **Role-Based Access** — Admin and Staff roles with separate dashboards and permissions
- **Multi-Account** — Connect and manage unlimited WhatsApp and Telegram accounts simultaneously
- **Account ↔ Staff Assignment** — Assign specific messaging accounts to specific staff members
- **Auto-Assignment** — One-click auto-distribute accounts evenly across active staff
- **Staff Management** — Create, activate, deactivate, and delete staff accounts
- **Channel Selection** — Staff selects WhatsApp or Telegram channel to work in their session

### 🔧 Session Management
- **QR Code Login** — WhatsApp QR code scanning with real-time QR display via WebSocket
- **Phone Pairing** — WhatsApp phone number pairing as an alternative to QR codes
- **Telegram OTP** — Telegram login with phone OTP and optional 2FA password support
- **Auto-Reconnect** — Engines automatically reconnect active sessions on restart
- **Health Monitoring** — Periodic health checks detect and recover stale connections
- **Session Sync** — One-click sync to align database status with engine status

### ⚙️ System Settings
- **Adjustable Delays** — Configure min/max delay between campaign messages
- **Daily Limits** — Set per-account daily message sending limits
- **Auto-Reset** — Daily limits reset automatically at midnight

### 🛡️ Security
- **Internal API Secret** — HMAC-based authentication between Laravel ↔ Engine microservices
- **Rate Limiting** — Login throttling (10 attempts per minute)
- **Active User Check** — Middleware ensures deactivated staff cannot access the system
- **CSRF Protection** — Full CSRF token verification on all form submissions

---

## 🏗️ Architecture

```text
┌─────────────────────────────────────────────────────────────┐
│                        NGINX (Port 80)                       │
│              Reverse proxy + WebSocket upgrade               │
├──────────────────────┬──────────────────────────────────────┤
│                      │                                       │
│   ┌──────────────────▼──────────────────┐                   │
│   │     Laravel App (Port 8001)         │                   │
│   │  • Admin Dashboard                  │                   │
│   │  • Staff Dashboard                  │                   │
│   │  • Campaign Engine                  │                   │
│   │  • API Controllers                  │                   │
│   └──────┬───────────────────┬──────────┘                   │
│          │                   │                               │
│   ┌──────▼──────┐    ┌──────▼──────┐                        │
│   │   Redis     │    │  MariaDB /  │                        │
│   │  (Queue +   │    │   MySQL     │                        │
│   │   Cache)    │    │  (Storage)  │                        │
│   └─────────────┘    └─────────────┘                        │
│                                                              │
│   ┌─────────────────┐    ┌─────────────────┐                │
│   │  WhatsApp Engine│    │ Telegram Engine │                │
│   │  Node.js :3001  │    │ Python :3002    │                │
│   │  (Baileys)      │    │ (Telethon)      │                │
│   └─────────────────┘    └─────────────────┘                │
│                                                              │
│   ┌─────────────────┐    ┌─────────────────┐                │
│   │ Laravel Horizon │    │ Laravel Reverb  │                │
│   │ (Queue Worker)  │    │ (WebSocket :8080│                │
│   └─────────────────┘    └─────────────────┘                │
│                                                              │
│                    PM2 Process Manager                       │
└─────────────────────────────────────────────────────────────┘
```

---

## 🛠️ Tech Stack

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Backend** | Laravel 13 (PHP 8.4+) | Web app, API, queue jobs, broadcasting |
| **Frontend** | Blade + Tailwind CSS 4 + Vanilla JS | Server-rendered UI with real-time updates |
| **Database** | MySQL / MariaDB | Persistent data storage |
| **Cache/Queue** | Redis + Laravel Horizon | Background job processing & caching |
| **WebSocket** | Laravel Reverb | Real-time message broadcasting |
| **WhatsApp** | Node.js + Baileys | WhatsApp Web multi-device protocol |
| **Telegram** | Python + Telethon + FastAPI | Telegram MTProto client |
| **Build** | Vite 8 | Frontend asset bundling |
| **Process Manager** | PM2 | Production process management |
| **Web Server** | Nginx | Reverse proxy & static assets |

---

## 📋 Prerequisites

Before installation, ensure your server has:

| Requirement | Version |
|------------|---------|
| PHP | ≥ 8.4.1 |
| Composer | ≥ 2.x |
| Node.js | ≥ 20.x |
| npm | ≥ 10.x |
| Python | ≥ 3.11 |
| MySQL / MariaDB | ≥ 8.0 / 10.6 |
| Redis | ≥ 7.x |
| PM2 | Latest |
| Nginx | Latest |
| Git | Latest |

### Required PHP Extensions
```text
php-cli php-fpm php-mysql php-mbstring php-xml php-bcmath 
php-curl php-zip php-redis php-intl php-gd
```

---

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/jahidbinsabit/social-crm.git
cd social-crm
```

### 2. Install System Dependencies (Ubuntu/Debian)

```bash
# Add PHP 8.4 repository (Debian/Ubuntu)
sudo add-apt-repository ppa:ondrej/php -y  # Ubuntu
# OR for Debian:
# curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
# sudo dpkg -i /tmp/debsuryorg-archive-keyring.deb
# sudo sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'

sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring \
    php8.4-xml php8.4-bcmath php8.4-curl php8.4-zip php8.4-redis php8.4-intl php8.4-gd

# Set PHP 8.4 as default
sudo update-alternatives --set php /usr/bin/php8.4

# Install other dependencies
sudo apt install -y nginx redis-server mariadb-server python3-venv python3-full

# Install Node.js 20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install PM2 globally
sudo npm install -g pm2

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3. Create the Database

```bash
sudo mysql -e "CREATE DATABASE social_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'crm_user'@'localhost' IDENTIFIED BY 'your_secure_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON social_crm.* TO 'crm_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### 4. Configure Environment

```bash
cp .env.example .env
nano .env
```

Update these values in `.env`:
```dotenv
APP_URL=http://your-domain-or-ip
APP_ENV=production
APP_DEBUG=false

DB_DATABASE=social_crm
DB_USERNAME=crm_user
DB_PASSWORD=your_secure_password

INTERNAL_API_SECRET=   # Generate: php -r "echo bin2hex(random_bytes(32));"
```

### 5. Install Laravel Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Run database migrations and seed default users
php artisan migrate --seed --force

# Create storage symlink
php artisan storage:link

# Optimize for production
php artisan optimize
```

### 6. Install Frontend Dependencies & Build

```bash
# Install npm packages
npm install

# Build frontend assets (Vite + Tailwind CSS)
npm run build
```

### 7. Install WhatsApp Engine Dependencies

```bash
cd services/whatsapp-engine
npm install
cd ../..
```

### 8. Install Telegram Engine Dependencies

```bash
cd services/telegram-engine
python3 -m venv venv
./venv/bin/pip install --upgrade pip
./venv/bin/pip install -r requirements.txt
cd ../..
```

> **Note:** The Telegram engine requires a Telegram API ID and Hash from [https://my.telegram.org](https://my.telegram.org). Set `API_ID` and `API_HASH` in `.env` or as environment variables in `ecosystem.config.cjs`.

### 9. Set Permissions

```bash
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 775 services/whatsapp-engine/sessions
sudo chmod -R 775 services/telegram-engine/sessions
```

### 10. Configure PM2 (Process Manager)

Edit `ecosystem.config.cjs` and update the `cwd` paths to match your project directory:

```bash
nano ecosystem.config.cjs
```

Update all `cwd` values to your project path (e.g., `/var/www/html` or wherever you cloned the repo).

Then start all services:

```bash
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup   # Follow the printed command to enable auto-start on boot
```

### 11. Configure Nginx

Create the Nginx configuration:

```bash
sudo nano /etc/nginx/sites-available/social-crm
```

Paste this configuration:

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    client_max_body_size 50M;

    # Laravel Web App (reverse proxy to artisan serve)
    location / {
        proxy_pass http://127.0.0.1:8001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Reverb WebSocket
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

Enable the site and restart Nginx:

```bash
sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -sf /etc/nginx/sites-available/social-crm /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 12. Open Firewall Ports

```bash
# UFW (if enabled)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp

# For Google Cloud Platform — also create a firewall rule in GCP Console
# allowing TCP ports 80, 443, and 8080 from 0.0.0.0/0
```

---

## ✅ Verify Installation

```bash
# Check all PM2 processes are online
pm2 status

# Check PM2 logs for errors
pm2 logs --lines 20

# Verify Laravel responds
curl -I http://127.0.0.1:8001

# Verify WhatsApp engine
curl http://127.0.0.1:3001

# Verify Telegram engine
curl http://127.0.0.1:3002

# Check database migrations
php artisan migrate:status
```

---

## 🔑 Default Login Credentials

After running `php artisan migrate --seed`, the following accounts are created:

| Role | Email | Password |
|------|-------|----------|
| **Admin** | `admin@test.com` | `admin123` |
| Staff | `staff1@test.com` | `staff123` |
| Staff | `staff2@test.com` | `staff123` |

> ⚠️ **Change these passwords immediately in production!**

---

## 📖 How to Use

### For Admins

1. **Login** → Go to `http://your-server-ip/login` and sign in with admin credentials.
2. **Dashboard** → View stats: total accounts, active sessions, messages today, unread count, campaign progress.
3. **Sessions** → Connect WhatsApp/Telegram accounts:
   - **WhatsApp**: Click "Start QR Session" → scan QR from your phone, OR use "Phone Pairing" method.
   - **Telegram**: Enter phone number → receive OTP on Telegram → verify code (+ 2FA password if enabled).
4. **Staff** → Create staff accounts, activate/deactivate them.
5. **Assign** → Assign connected messaging accounts to staff members. Use "Auto Assign" to distribute evenly.
6. **Campaigns** → Create bulk outreach campaigns:
   - Select platform (WhatsApp or Telegram)
   - Write your message
   - Paste phone numbers or usernames (one per line or comma-separated)
   - Select staff members to send through their assigned accounts
   - Campaign runs in the background with smart delays
7. **Settings** → Configure message delay (min/max seconds) and daily per-account sending limits.
8. **Chats** → View and reply to all conversations from the Accounts page.

### For Staff

1. **Login** → Sign in with staff credentials.
2. **Channel** → Select your working channel (WhatsApp or Telegram).
3. **Dashboard** → View all assigned accounts and their conversations.
4. **Chat** → Click on any conversation to see full message history. Send text or media replies.
5. **Real-time** → New incoming messages appear instantly via WebSocket — no page refresh needed.
6. **Password** → Change your password from Settings.

---

## 🔄 PM2 Service Management

```bash
# View all process status
pm2 status

# View real-time logs
pm2 logs

# Restart all services
pm2 restart all

# Restart specific service
pm2 restart laravel-web
pm2 restart whatsapp-engine
pm2 restart telegram-engine

# Stop all
pm2 stop all

# Delete all and re-start
pm2 delete all
pm2 start ecosystem.config.cjs
```

### PM2 Managed Processes

| Process | Port | Description |
|---------|------|-------------|
| `laravel-web` | 8001 | Laravel HTTP server (`php artisan serve`) |
| `laravel-horizon` | — | Redis queue worker for background jobs |
| `laravel-reverb` | 8080 | WebSocket server for real-time events |
| `whatsapp-engine` | 3001 | Node.js WhatsApp client (Baileys) |
| `telegram-engine` | 3002 | Python Telegram client (Telethon) |

---

## 📂 Project Structure

```text
social-crm/
├── app/
│   ├── Console/Commands/       # Artisan commands (sync status, reset limits, etc.)
│   ├── Events/                 # WebSocket events (QR ready, new message, status change)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Admin dashboard, accounts, campaigns, sessions, staff, settings
│   │   │   ├── Api/            # Internal API endpoints for engine ↔ Laravel communication
│   │   │   └── Staff/          # Staff dashboard, chat, channel selection, settings
│   │   └── Middleware/         # Admin, Staff, Active user, Internal API auth
│   ├── Jobs/                   # Background jobs (campaign processing, single client send)
│   ├── Models/                 # Eloquent models (User, Account, Campaign, Client, Message, etc.)
│   └── Services/               # Business logic (WhatsApp, Telegram, Media, Outbound messaging)
├── database/
│   ├── migrations/             # Database schema migrations
│   └── seeders/                # Default admin/staff users & system settings
├── resources/views/
│   ├── admin/                  # Admin panel Blade templates
│   ├── auth/                   # Login page
│   ├── layouts/                # Admin & Staff layout templates
│   └── staff/                  # Staff panel Blade templates
├── routes/
│   ├── web.php                 # Web routes (admin & staff panels)
│   └── api.php                 # Internal API routes (engine communication)
├── services/
│   ├── whatsapp-engine/        # Node.js WhatsApp engine (Express + Baileys)
│   │   ├── index.js            # Main WhatsApp engine server
│   │   └── sessions/           # WhatsApp session files (gitignored)
│   └── telegram-engine/        # Python Telegram engine (FastAPI + Telethon)
│       ├── main.py             # Main Telegram engine server
│       ├── requirements.txt    # Python dependencies
│       └── sessions/           # Telegram session files (gitignored)
├── ecosystem.config.cjs        # PM2 process configuration
├── vite.config.js              # Vite build configuration
├── composer.json               # PHP dependencies
├── package.json                # Node.js dependencies
└── .env.example                # Environment template
```

---

## 🔧 Useful Commands

```bash
# Clear all Laravel caches
php artisan optimize:clear

# Rebuild caches for production
php artisan optimize

# Re-run all migrations (WARNING: destroys all data)
php artisan migrate:fresh --seed

# Check migration status
php artisan migrate:status

# Run pending migrations only (safe for production)
php artisan migrate

# Generate a new internal API secret
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Monitor Horizon queue dashboard
# Visit: http://your-server/horizon/dashboard

# Rebuild frontend assets
npm run build
```

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| `ERR_CONNECTION_TIMED_OUT` in browser | Open port 80/443 in cloud firewall (GCP: VM → Edit → Allow HTTP traffic) |
| `composer install` fails with PHP version error | Install PHP 8.4: `sudo apt install php8.4-cli` and set as default |
| `npm run build` fails with missing module | Delete `node_modules` and reinstall: `rm -rf node_modules && npm install && npm run build` |
| WhatsApp QR not appearing | Check engine: `pm2 logs whatsapp-engine` — ensure port 3001 is running |
| Telegram OTP not received | Verify `API_ID` and `API_HASH` are set; check `pm2 logs telegram-engine` |
| Nginx 502 Bad Gateway | Verify Laravel is running: `curl http://127.0.0.1:8001` |
| WebSocket not connecting | Ensure Reverb is running on port 8080 and Nginx proxies `/app` path |
| Campaign stuck on "pending" | Check Horizon: `pm2 logs laravel-horizon` — ensure Redis is running |
| Permission denied errors | Run: `sudo chmod -R 775 storage bootstrap/cache` |

---

## 👤 Author

**JB Sabit** — Full-Stack Developer & WordPress Security Expert

- 🌐 **Website:** [jbsabit.com](https://jbsabit.com)
- 💼 **LinkedIn:** [linkedin.com/in/jbsabit](https://www.linkedin.com/in/jbsabit)
- 🐙 **GitHub:** [github.com/jahidbinsabit](https://github.com/jahidbinsabit)
- 📧 **Email:** [mail@jbsabit.com](mailto:mail@jbsabit.com)
- 💬 **WhatsApp:** [+8801939925649](https://wa.me/8801939925649)
- ✈️ **Telegram:** [@jahidbinsabit](https://t.me/jahidbinsabit)

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).

---

<p align="center">
  Built with ❤️ by <a href="https://jbsabit.com">JB Sabit</a>
</p>
