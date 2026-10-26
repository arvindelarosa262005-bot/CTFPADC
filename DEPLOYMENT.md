# P4DC CTF — Production Deployment Guide

This guide describes how to deploy the pre-verified P4DC CTF Laravel platform to any VPS, bare-metal server, or cloud host (Ubuntu 22.04 / 24.04, Debian 12, AWS EC2, DigitalOcean Droplet, Linode, Hetzner, etc.).

---

## 1. Prerequisites on the Host Server

- **Operating System:** Ubuntu 22.04/24.04 LTS or Debian 12
- **Installed Tools:** Docker Engine 24+, Docker Compose v2, Git, Nginx (or Caddy), Certbot
- **DNS Records:** A / AAAA record pointing your chosen domain (e.g. `ctf.example.com`) to the host server IP.

```bash
# Install Docker Engine & Compose plugin
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Install Nginx & Certbot
sudo apt-get update && sudo apt-get install -y nginx certbot python3-certbot-nginx
```

---

## 2. Clone & Configure the Project

```bash
cd /opt
sudo git clone <YOUR_GIT_REPO_URL> p4dc-ctf
cd p4dc-ctf/p4dc-scaffold

# Copy production environment configuration
cp .env.example .env
```

### Production `.env` Settings

Edit `.env` using `nano .env`:
```ini
APP_NAME="P4DC Cyber Range"
APP_ENV=production
APP_KEY=base64:Rgs3OsbxlMtOixBBwpLJGLPA9YGJDCy25QwyFr1JmQg=
APP_DEBUG=false
APP_URL=https://ctf.example.com

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=p4dc
DB_USERNAME=p4dc
DB_PASSWORD=your_strong_database_password_here
DB_SSLMODE=prefer

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=file
QUEUE_CONNECTION=sync

APP_FORWARD_PORT=8080
DB_FORWARD_PORT=5433

# Oracle Challenge Settings (for RSA Oracle)
ORACLE_HOST=ctf.example.com
ORACLE_PORT=5000
```

---

## 3. Database Restoration (Preserving Competition Baseline)

If deploying to a fresh server, restore the verified database dump containing Admin ID 44, all 11 challenges, and account records:

```bash
# Start the PostgreSQL service first
docker compose up -d db

# Wait for healthy status
docker compose ps

# Import the baseline database backup
docker compose exec -T db psql -U p4dc -d p4dc < /path/to/p4dc_baseline_backup.sql
```

---

## 4. Build & Launch the Application

```bash
# Build the optimized production image
docker compose build app

# Start the full stack
docker compose up -d

# Verify containers are healthy
docker compose ps
```

---

## 5. Production Optimization & Cache Warmup

Inside the running application container:

```bash
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan storage:link
```

---

## 6. Configure Nginx Reverse Proxy & SSL (HTTPS)

1. Copy the provided Nginx configuration:
```bash
sudo cp docker/nginx-production.conf /etc/nginx/sites-available/p4dc-ctf.conf
```
2. Replace `ctf.example.com` with your actual domain:
```bash
sudo sed -i 's/ctf.example.com/your-actual-domain.com/g' /etc/nginx/sites-available/p4dc-ctf.conf
```
3. Obtain Let's Encrypt SSL certificate:
```bash
sudo certbot certonly --nginx -d your-actual-domain.com
```
4. Enable the site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/p4dc-ctf.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 7. Post-Deployment Verification

Run the comprehensive production test suite inside the container on your live server:

```bash
docker compose exec app php /var/www/html/scripts/comprehensive_production_test.php
```

All 43 checks should report `[PASS]`.

