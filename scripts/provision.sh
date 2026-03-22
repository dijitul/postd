#!/bin/bash
# =============================================================================
# postd.uk Server Provisioning Script
# Server : 144.126.207.135 | Ubuntu 24.04 LTS | DigitalOcean LON1
# Run as : root (once only on a fresh droplet)
# Usage  : bash /tmp/provision.sh
#
# This script is idempotent where possible — safe to re-run if interrupted.
# =============================================================================

set -e
set -o pipefail

echo ""
echo "============================================================"
echo "  postd.uk Server Provisioning"
echo "  Server : 144.126.207.135"
echo "  OS     : Ubuntu 24.04 LTS"
echo "  Date   : $(date)"
echo "============================================================"
echo ""

# -----------------------------------------------------------------------------
# 1. System update
# -----------------------------------------------------------------------------
echo ">>> [1/18] Updating system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold"
echo "    Done."

# -----------------------------------------------------------------------------
# 2. Install core utilities
# -----------------------------------------------------------------------------
echo ">>> [2/18] Installing core utilities..."
apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    zip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    ufw \
    fail2ban \
    supervisor \
    certbot \
    python3-certbot-nginx \
    cron \
    htop \
    nano \
    logrotate
echo "    Done."

# -----------------------------------------------------------------------------
# 3. Install Nginx
# -----------------------------------------------------------------------------
echo ">>> [3/18] Installing Nginx..."
apt-get install -y nginx
systemctl enable nginx
systemctl start nginx
echo "    Done."

# -----------------------------------------------------------------------------
# 4. Install PHP 8.3 + PHP-FPM + all required extensions
# -----------------------------------------------------------------------------
echo ">>> [4/18] Adding PHP 8.3 repository and installing PHP..."
# Add Ondrej PPA only if not already present
if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
    add-apt-repository ppa:ondrej/php -y
    apt-get update -y
fi

apt-get install -y \
    php8.3-fpm \
    php8.3-cli \
    php8.3-pgsql \
    php8.3-redis \
    php8.3-curl \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-bcmath \
    php8.3-intl \
    php8.3-gd \
    php8.3-tokenizer \
    php8.3-dom \
    php8.3-fileinfo \
    php8.3-ctype \
    php8.3-openssl \
    php8.3-pcntl

# Increase PHP-FPM limits for queue workers and long scraping jobs
PHP_FPM_INI="/etc/php/8.3/fpm/conf.d/99-postd.ini"
cat > "$PHP_FPM_INI" << 'PHPINI'
; postd.uk custom PHP settings
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 64M
post_max_size = 64M
PHPINI

# Apply same to CLI (for artisan commands)
PHP_CLI_INI="/etc/php/8.3/cli/conf.d/99-postd.ini"
cat > "$PHP_CLI_INI" << 'PHPINI'
; postd.uk custom PHP CLI settings
memory_limit = 1G
max_execution_time = 0
PHPINI

systemctl enable php8.3-fpm
systemctl restart php8.3-fpm
echo "    Done."

# -----------------------------------------------------------------------------
# 5. Install Composer (globally)
# -----------------------------------------------------------------------------
echo ">>> [5/18] Installing Composer..."
if ! command -v composer &>/dev/null; then
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        echo "ERROR: Composer installer checksum mismatch. Aborting." >&2
        rm composer-setup.php
        exit 1
    fi

    php composer-setup.php --quiet
    rm composer-setup.php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    echo "    Composer installed."
else
    echo "    Composer already installed, updating..."
    composer self-update --quiet
fi
composer --version
echo "    Done."

# -----------------------------------------------------------------------------
# 6. Install Node.js 20 LTS + npm
# -----------------------------------------------------------------------------
echo ">>> [6/18] Installing Node.js 20 LTS..."
if ! command -v node &>/dev/null || [[ "$(node -v)" != v20* ]]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi
node --version
npm --version
echo "    Done."

# -----------------------------------------------------------------------------
# 7. Install PostgreSQL 16
# -----------------------------------------------------------------------------
echo ">>> [7/18] Installing PostgreSQL 16..."
# Add PostgreSQL official repository if not present
if ! command -v psql &>/dev/null; then
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | \
        gpg --dearmor -o /usr/share/keyrings/postgresql-keyring.gpg
    echo "deb [signed-by=/usr/share/keyrings/postgresql-keyring.gpg] \
        https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list
    apt-get update -y
fi

apt-get install -y postgresql-16 postgresql-client-16

systemctl enable postgresql
systemctl start postgresql

# Tune PostgreSQL for a typical DO droplet (adjust if upgrading droplet size)
PG_CONF="/etc/postgresql/16/main/postgresql.conf"
if [ -f "$PG_CONF" ]; then
    sed -i "s/#shared_buffers = 128MB/shared_buffers = 256MB/" "$PG_CONF"
    sed -i "s/#work_mem = 4MB/work_mem = 16MB/" "$PG_CONF"
    sed -i "s/#maintenance_work_mem = 64MB/maintenance_work_mem = 128MB/" "$PG_CONF"
    sed -i "s/#max_connections = 100/max_connections = 100/" "$PG_CONF"
    systemctl reload postgresql
fi
echo "    Done."

# -----------------------------------------------------------------------------
# 8. Install Redis 7
# -----------------------------------------------------------------------------
echo ">>> [8/18] Installing Redis 7..."
apt-get install -y redis-server

# Set a basic Redis config — bind to localhost only, enable persistence
REDIS_CONF="/etc/redis/redis.conf"
# Bind to loopback only for security
sed -i "s/^bind .*/bind 127.0.0.1 -::1/" "$REDIS_CONF"
# Set max memory policy (LRU is sensible for cache + queue)
if ! grep -q "^maxmemory-policy" "$REDIS_CONF"; then
    echo "maxmemory-policy allkeys-lru" >> "$REDIS_CONF"
fi
# Enable AOF persistence so queue jobs survive Redis restarts
sed -i "s/^appendonly no/appendonly yes/" "$REDIS_CONF"

systemctl enable redis-server
systemctl restart redis-server
echo "    Done."

# -----------------------------------------------------------------------------
# 9. Create deploy user (postduk)
# -----------------------------------------------------------------------------
echo ">>> [9/18] Creating deploy user 'postduk'..."
if id "postduk" &>/dev/null; then
    echo "    User 'postduk' already exists, skipping creation."
else
    useradd -m -s /bin/bash postduk
    echo "    User 'postduk' created."
fi

# Add postduk to www-data so Nginx can serve files owned by this user
usermod -aG www-data postduk

# Allow postduk to restart Horizon via supervisorctl without a password
SUDOERS_FILE="/etc/sudoers.d/postduk"
if [ ! -f "$SUDOERS_FILE" ]; then
    cat > "$SUDOERS_FILE" << 'SUDOERS'
# Allow postduk to restart Horizon supervisor process only
postduk ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart postd-horizon
postduk ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl status postd-horizon
SUDOERS
    chmod 440 "$SUDOERS_FILE"
    echo "    Sudoers entry created for supervisorctl."
fi
echo "    Done."

# -----------------------------------------------------------------------------
# 10. Generate SSH deploy keypair for GitHub Actions
# -----------------------------------------------------------------------------
echo ">>> [10/18] Generating SSH deploy keypair..."
SSH_DIR="/home/postduk/.ssh"
DEPLOY_KEY="$SSH_DIR/deploy_key"

mkdir -p "$SSH_DIR"
chmod 700 "$SSH_DIR"

if [ ! -f "$DEPLOY_KEY" ]; then
    sudo -u postduk ssh-keygen -t ed25519 -C "postduk@postd.uk" \
        -f "$DEPLOY_KEY" -N ""
    echo "    Deploy keypair generated."
else
    echo "    Deploy keypair already exists, skipping."
fi

# Ensure the public key is in authorized_keys so GitHub Actions can SSH in
AUTH_KEYS="$SSH_DIR/authorized_keys"
if ! grep -qF "$(cat "$DEPLOY_KEY.pub")" "$AUTH_KEYS" 2>/dev/null; then
    cat "$DEPLOY_KEY.pub" >> "$AUTH_KEYS"
    chmod 600 "$AUTH_KEYS"
    echo "    Public key added to authorized_keys."
fi

# Add GitHub to known hosts to avoid interactive prompt during git clone/pull
if ! grep -q "github.com" "$SSH_DIR/known_hosts" 2>/dev/null; then
    sudo -u postduk ssh-keyscan -H github.com >> "$SSH_DIR/known_hosts" 2>/dev/null
    chmod 644 "$SSH_DIR/known_hosts"
    echo "    GitHub added to known_hosts."
fi

chown -R postduk:postduk "$SSH_DIR"
echo "    Done."

# -----------------------------------------------------------------------------
# 11. Create PostgreSQL database and user
# -----------------------------------------------------------------------------
echo ">>> [11/18] Creating PostgreSQL database and user..."

# NOTE: Change this password before running in production!
DB_PASSWORD="${POSTD_DB_PASSWORD:-CHANGE_THIS_STRONG_DB_PASSWORD_BEFORE_RUNNING}"

sudo -u postgres psql -tc "SELECT 1 FROM pg_user WHERE usename='postduk'" \
    | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER postduk WITH PASSWORD '$DB_PASSWORD';"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='postduk'" \
    | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE postduk OWNER postduk;"

sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE postduk TO postduk;"

# Allow the postduk OS user to connect without password via local socket (peer auth)
PG_HBA="/etc/postgresql/16/main/pg_hba.conf"
if ! grep -q "postduk" "$PG_HBA"; then
    # Add local peer auth for postduk above the default catch-all
    sed -i "/^local   all             all/i local   all             postduk                                 peer" "$PG_HBA"
    systemctl reload postgresql
fi
echo "    Done."

# -----------------------------------------------------------------------------
# 12. Create web root directory
# -----------------------------------------------------------------------------
echo ">>> [12/18] Creating web root directory..."
mkdir -p /var/www/postd

# Set ownership: postduk owns the directory (for git pull etc.)
# www-data group for Nginx to serve files
chown -R postduk:www-data /var/www/postd
chmod -R 755 /var/www/postd
echo "    Done."

# -----------------------------------------------------------------------------
# 13. Install Nginx virtual host configs
# -----------------------------------------------------------------------------
echo ">>> [13/18] Installing Nginx virtual host configurations..."

NGINX_AVAILABLE="/etc/nginx/sites-available"
NGINX_ENABLED="/etc/nginx/sites-enabled"

# Copy configs from the repo if the repo is already cloned,
# otherwise install stub configs that certbot will later upgrade to HTTPS.
# Frontend (postd.uk / www.postd.uk)
cat > "$NGINX_AVAILABLE/postd.uk" << 'NGINXFE'
server {
    listen 80;
    listen [::]:80;
    server_name postd.uk www.postd.uk;
    root /var/www/postd/web/dist;
    index index.html;

    # --- Logging ---
    access_log /var/log/nginx/postd.uk.access.log;
    error_log  /var/log/nginx/postd.uk.error.log warn;

    # --- SPA fallback ---
    location / {
        try_files $uri $uri/ /index.html;
    }

    # --- Security headers ---
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

    # --- Gzip compression ---
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        application/json
        application/javascript
        text/xml
        application/xml
        application/xml+rss
        text/javascript
        image/svg+xml
        application/wasm;

    # --- Static assets: long cache ---
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|webp|avif|wasm)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # --- Manifest and service worker: short cache for PWA updates ---
    location ~* (manifest\.json|sw\.js|service-worker\.js)$ {
        expires 0;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
    }

    # --- Certbot ACME challenge ---
    location ~ /.well-known/acme-challenge {
        allow all;
        root /var/www/html;
    }
}
NGINXFE

# API (api.postd.uk)
cat > "$NGINX_AVAILABLE/api.postd.uk" << 'NGINXAPI'
server {
    listen 80;
    listen [::]:80;
    server_name api.postd.uk;
    root /var/www/postd/api/public;
    index index.php;

    # --- Logging ---
    access_log /var/log/nginx/api.postd.uk.access.log;
    error_log  /var/log/nginx/api.postd.uk.error.log warn;

    # --- CORS headers (allow only from postd.uk) ---
    # Note: HTTPS origins enforced here; update if adding www variant
    set $cors_origin "";
    if ($http_origin ~* "^https://(www\.)?postd\.uk$") {
        set $cors_origin $http_origin;
    }

    add_header 'Access-Control-Allow-Origin' $cors_origin always;
    add_header 'Access-Control-Allow-Credentials' 'true' always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, PATCH, DELETE, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Authorization, Content-Type, X-Requested-With, X-XSRF-TOKEN, Accept' always;
    add_header 'Access-Control-Max-Age' '86400' always;

    # --- Handle OPTIONS preflight ---
    if ($request_method = 'OPTIONS') {
        return 204;
    }

    # --- Main location ---
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # --- PHP-FPM ---
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # --- Block hidden files (except ACME challenge) ---
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # --- Security headers ---
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Robots-Tag "noindex, nofollow" always;

    # --- Certbot ACME challenge ---
    location ~ /.well-known/acme-challenge {
        allow all;
        root /var/www/html;
    }
}
NGINXAPI

# Enable sites by creating symlinks (idempotent)
ln -sf "$NGINX_AVAILABLE/postd.uk"     "$NGINX_ENABLED/postd.uk"
ln -sf "$NGINX_AVAILABLE/api.postd.uk" "$NGINX_ENABLED/api.postd.uk"

# Remove default Nginx site if present
rm -f "$NGINX_ENABLED/default"

# Test and reload Nginx
nginx -t
systemctl reload nginx
echo "    Done."

# -----------------------------------------------------------------------------
# 14. Install Supervisor config for Laravel Horizon
# -----------------------------------------------------------------------------
echo ">>> [14/18] Installing Supervisor config for Horizon..."
mkdir -p /var/www/postd/api/storage/logs

cat > /etc/supervisor/conf.d/postd-horizon.conf << 'SUPERVISORCONF'
[program:postd-horizon]
process_name=%(program_name)s
command=php /var/www/postd/api/artisan horizon
autostart=true
autorestart=true
user=postduk
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/postd/api/storage/logs/horizon.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
stopsignal=SIGTERM
killasgroup=true
stopasgroup=true
SUPERVISORCONF

# Reload supervisor config (don't start yet — repo not cloned)
supervisorctl reread 2>/dev/null || true
echo "    Done. Horizon will start automatically once the repo is cloned."

# -----------------------------------------------------------------------------
# 15. Configure UFW firewall
# -----------------------------------------------------------------------------
echo ">>> [15/18] Configuring UFW firewall..."
ufw --force reset
ufw default deny incoming
ufw default allow outgoing

# SSH (keep this — do NOT lock yourself out)
ufw allow 22/tcp comment 'SSH'

# Web traffic
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'

# Enable firewall
ufw --force enable
ufw status verbose
echo "    Done."

# -----------------------------------------------------------------------------
# 16. Configure Fail2ban
# -----------------------------------------------------------------------------
echo ">>> [16/18] Configuring Fail2ban..."
cat > /etc/fail2ban/jail.local << 'FAIL2BAN'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = auto

[sshd]
enabled  = true
port     = 22
logpath  = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled  = true

[nginx-limit-req]
enabled  = true
port     = http,https
logpath  = /var/log/nginx/api.postd.uk.error.log
FAIL2BAN

systemctl enable fail2ban
systemctl restart fail2ban
echo "    Done."

# -----------------------------------------------------------------------------
# 17. Set up log rotation for application logs
# -----------------------------------------------------------------------------
echo ">>> [17/18] Configuring log rotation..."
cat > /etc/logrotate.d/postd << 'LOGROTATE'
/var/www/postd/api/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 postduk www-data
    sharedscripts
    postrotate
        supervisorctl signal USR1 postd-horizon > /dev/null 2>&1 || true
    endscript
}

/var/log/nginx/postd.uk.*.log
/var/log/nginx/api.postd.uk.*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 $(cat /var/run/nginx.pid)
    endscript
}
LOGROTATE
echo "    Done."

# -----------------------------------------------------------------------------
# 18. Set up scheduled cron for Laravel scheduler
# -----------------------------------------------------------------------------
echo ">>> [18/18] Setting up Laravel cron job..."
CRON_FILE="/var/spool/cron/crontabs/postduk"

# Only add if not already present
if ! crontab -u postduk -l 2>/dev/null | grep -q "artisan schedule:run"; then
    (crontab -u postduk -l 2>/dev/null; \
     echo "* * * * * php /var/www/postd/api/artisan schedule:run >> /var/www/postd/api/storage/logs/scheduler.log 2>&1") \
     | crontab -u postduk -
    echo "    Laravel scheduler cron added."
else
    echo "    Laravel scheduler cron already present, skipping."
fi
echo "    Done."

# =============================================================================
# PROVISIONING COMPLETE
# =============================================================================
echo ""
echo "============================================================"
echo "  Provisioning complete!"
echo "============================================================"
echo ""
echo "IMPORTANT: Copy the deploy public key below into:"
echo "  GitHub repo -> Settings -> Deploy keys -> Add deploy key"
echo "  Title: postd.uk production server"
echo "  Allow write access: NO (read-only is fine for deploys)"
echo ""
echo "=== DEPLOY PUBLIC KEY ==="
cat /home/postduk/.ssh/deploy_key.pub
echo "=== END OF PUBLIC KEY ==="
echo ""
echo "NEXT STEPS (in order):"
echo ""
echo "  1. Add the public key above to GitHub Deploy keys."
echo ""
echo "  2. Change the database password:"
echo "     sudo -u postgres psql -c \"ALTER USER postduk PASSWORD 'your-new-password';\""
echo ""
echo "  3. Clone the repository:"
echo "     sudo -u postduk git clone git@github.com:dijitul/postd.git /var/www/postd"
echo ""
echo "  4. Copy and populate the .env file:"
echo "     sudo -u postduk cp /var/www/postd/api/.env.example /var/www/postd/api/.env"
echo "     nano /var/www/postd/api/.env"
echo ""
echo "  5. Install dependencies and generate app key:"
echo "     cd /var/www/postd/api"
echo "     sudo -u postduk composer install --no-dev --optimize-autoloader"
echo "     sudo -u postduk php artisan key:generate"
echo ""
echo "  6. Run migrations:"
echo "     sudo -u postduk php artisan migrate --seed"
echo ""
echo "  7. Build the React frontend:"
echo "     cd /var/www/postd/web"
echo "     sudo -u postduk npm ci"
echo "     sudo -u postduk npm run build"
echo ""
echo "  8. Once DNS is pointed at this server (144.126.207.135), run Certbot:"
echo "     certbot --nginx -d postd.uk -d www.postd.uk -d api.postd.uk \\"
echo "       --non-interactive --agree-tos -m hello@postd.uk"
echo ""
echo "  9. Start Horizon:"
echo "     supervisorctl reread && supervisorctl update && supervisorctl start postd-horizon"
echo ""
echo "  10. Add these secrets to GitHub repo -> Settings -> Secrets -> Actions:"
echo "      SERVER_HOST : 144.126.207.135"
echo "      SERVER_USER : postduk"
echo "      SERVER_SSH_KEY : (contents of /home/postduk/.ssh/deploy_key — PRIVATE key)"
echo ""
echo "  Run: cat /home/postduk/.ssh/deploy_key"
echo "  Copy the entire output (including BEGIN/END lines) into SERVER_SSH_KEY."
echo ""
echo "  Server is ready and waiting. Good luck with postd.uk!"
echo ""
