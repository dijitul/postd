# postd.uk Server Setup Guide

**Server:** DigitalOcean Droplet, Ubuntu 24.04 LTS, LON1
**IP:** 144.126.207.135
**Prepared by:** DevOps Automator

This guide walks Olly through completing the server setup after the provision script has run. Follow each step in order.

---

## Prerequisites

Before you begin, you will need:

- SSH access to the server as root: `ssh root@144.126.207.135`
- The postd.uk GitHub repository created at `github.com/dijitul/postd`
- Access to GitHub repository settings (to add deploy keys and secrets)
- All API credentials ready (Stripe, OpenAI, social platform apps, etc.)

---

## Step 1: Run the provision script

Upload and run the provision script on the fresh server. You only ever run this once.

```bash
# From your local machine, upload the script
scp scripts/provision.sh root@144.126.207.135:/tmp/provision.sh

# SSH in as root
ssh root@144.126.207.135

# Run it (takes around 5-10 minutes)
bash /tmp/provision.sh
```

The script will print a deploy public key at the end. Copy it — you need it in the next step.

If you need to see it again at any point:

```bash
cat /home/postduk/.ssh/deploy_key.pub
```

---

## Step 2: Add the deploy key to GitHub

1. Go to `https://github.com/dijitul/postd/settings/keys`
2. Click **Add deploy key**
3. Title: `postd.uk production server`
4. Key: paste the public key from Step 1
5. Allow write access: **leave unticked** (read-only is sufficient for deploys)
6. Click **Add key**

---

## Step 3: Clone the repository

SSH back in as root, then clone the repo as the postduk user:

```bash
sudo -u postduk git clone git@github.com:dijitul/postd.git /var/www/postd
```

If this is the first time connecting to GitHub from this server, you may see a prompt to confirm the GitHub host key fingerprint. Type `yes`.

Verify the clone worked:

```bash
ls /var/www/postd
```

You should see: `api/`, `web/`, `scripts/`, `docs/`, etc.

---

## Step 4: Set the database password

The provision script created the database with a placeholder password. Change it now before going any further:

```bash
sudo -u postgres psql -c "ALTER USER postduk PASSWORD 'your-strong-password-here';"
```

Choose a strong password (20+ characters, mixed). You will need this in the next step.

---

## Step 5: Create and populate the .env file

```bash
sudo -u postduk cp /var/www/postd/api/.env.example /var/www/postd/api/.env
nano /var/www/postd/api/.env
```

Fill in every blank value. At minimum, you need:

| Variable | What to put |
|----------|-------------|
| `APP_KEY` | Leave blank for now -- generated in Step 6 |
| `DB_PASSWORD` | The password you set in Step 4 |
| `REDIS_HOST` | `127.0.0.1` (already correct) |
| `AWS_ACCESS_KEY_ID` | DigitalOcean Spaces key |
| `AWS_SECRET_ACCESS_KEY` | DigitalOcean Spaces secret |
| `AWS_BUCKET` | `postduk-media` |
| `STRIPE_KEY` | Stripe publishable key |
| `STRIPE_SECRET` | Stripe secret key |
| `STRIPE_WEBHOOK_SECRET` | From Stripe dashboard -- webhooks section |
| `OPENAI_API_KEY` | OpenAI API key |
| `FACEBOOK_APP_ID` / `FACEBOOK_APP_SECRET` | Meta developer app |
| `GOOGLE_PLACES_API_KEY` | Google Cloud console |
| `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` | Your SMTP provider |
| `HORIZON_SECRET` | Any long random string (used to secure Horizon dashboard) |

---

## Step 6: Generate the application key

```bash
cd /var/www/postd/api
sudo -u postduk php artisan key:generate
```

This writes `APP_KEY` into the .env file automatically. Do not skip this -- the application will not boot without it.

---

## Step 7: Install PHP dependencies

```bash
cd /var/www/postd/api
sudo -u postduk composer install --no-dev --optimize-autoloader
```

This takes a minute or two on first run.

---

## Step 8: Run database migrations and seeders

```bash
cd /var/www/postd/api
sudo -u postduk php artisan migrate --seed
```

This creates all tables and seeds any required initial data (plan features, admin user, etc.).

---

## Step 9: Build the React frontend

```bash
cd /var/www/postd/web
sudo -u postduk npm ci
sudo -u postduk npm run build
```

The built output lands in `web/dist/`. Nginx is already configured to serve from there.

---

## Step 10: Install Nginx configs

The provision script installs the Nginx configs automatically. Verify they are in place and working:

```bash
# Check symlinks exist
ls -la /etc/nginx/sites-enabled/

# Test config syntax
nginx -t

# Reload if all good
systemctl reload nginx
```

You should see both `postd.uk` and `api.postd.uk` in the enabled sites.

If they are missing, install them manually:

```bash
cp /var/www/postd/scripts/nginx-postd.uk.conf /etc/nginx/sites-available/postd.uk
cp /var/www/postd/scripts/nginx-api.postd.uk.conf /etc/nginx/sites-available/api.postd.uk
ln -s /etc/nginx/sites-available/postd.uk /etc/nginx/sites-enabled/postd.uk
ln -s /etc/nginx/sites-available/api.postd.uk /etc/nginx/sites-enabled/api.postd.uk
nginx -t && systemctl reload nginx
```

---

## Step 11: Point DNS at the server

In your DNS provider, create these A records pointing to `144.126.207.135`:

| Record | Type | Value | TTL |
|--------|------|-------|-----|
| `postd.uk` | A | `144.126.207.135` | 300 |
| `www.postd.uk` | A | `144.126.207.135` | 300 |
| `api.postd.uk` | A | `144.126.207.135` | 300 |

TTL of 300 (5 minutes) lets you change things quickly if needed. You can increase it later.

Wait for DNS to propagate before running Certbot. You can check with:

```bash
dig +short postd.uk
dig +short api.postd.uk
```

Both should return `144.126.207.135`.

---

## Step 12: Install SSL certificates

Once DNS has propagated, run Certbot:

```bash
certbot --nginx \
  -d postd.uk \
  -d www.postd.uk \
  -d api.postd.uk \
  --non-interactive \
  --agree-tos \
  -m hello@postd.uk
```

Certbot will:
- Obtain certificates from Let's Encrypt
- Automatically modify the Nginx configs to add HTTPS server blocks
- Set up automatic renewal via a systemd timer

Verify renewal works:

```bash
certbot renew --dry-run
```

---

## Step 13: Start Laravel Horizon

```bash
supervisorctl reread
supervisorctl update
supervisorctl start postd-horizon
```

Verify Horizon is running:

```bash
supervisorctl status postd-horizon
```

You should see: `postd-horizon   RUNNING   pid XXXX, uptime 0:00:XX`

You can also check the Horizon web dashboard at `https://api.postd.uk/horizon` (protected by the `HORIZON_SECRET` you set in .env).

---

## Step 14: Add GitHub Actions secrets

These secrets allow the GitHub Actions pipeline to deploy automatically on every push to `main`.

1. Go to `https://github.com/dijitul/postd/settings/secrets/actions`
2. Add the following secrets:

| Secret name | Value |
|-------------|-------|
| `SERVER_HOST` | `144.126.207.135` |
| `SERVER_USER` | `postduk` |
| `SERVER_SSH_KEY` | Private key (see below) |
| `DEPLOY_BYPASS_SECRET` | Any long random string (used to bypass maintenance mode) |

To get the private key:

```bash
cat /home/postduk/.ssh/deploy_key
```

Copy the entire output including the `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----` lines. Paste this as the value of `SERVER_SSH_KEY`.

---

## Step 15: Test the pipeline

Push a small change to the `main` branch and watch the Actions tab on GitHub:

`https://github.com/dijitul/postd/actions`

You should see the workflow run through: tests, build, and deploy. The whole pipeline typically takes 5-8 minutes.

---

## Useful commands for ongoing management

```bash
# Check Horizon status
supervisorctl status postd-horizon

# View Horizon logs
tail -f /var/www/postd/api/storage/logs/horizon.log

# View Laravel logs
tail -f /var/www/postd/api/storage/logs/laravel.log

# View Nginx access log (API)
tail -f /var/log/nginx/api.postd.uk.access.log

# View Nginx error log
tail -f /var/log/nginx/api.postd.uk.error.log

# Run a manual deploy (without GitHub Actions)
bash /var/www/postd/scripts/deploy.sh

# Check SSL certificate expiry
certbot certificates

# View firewall status
ufw status verbose

# Check fail2ban banned IPs
fail2ban-client status sshd
```

---

## Troubleshooting

**Site shows 502 Bad Gateway**
- PHP-FPM may not be running: `systemctl status php8.3-fpm`
- Restart it: `systemctl restart php8.3-fpm`

**API returns 500 errors**
- Check: `tail -50 /var/www/postd/api/storage/logs/laravel.log`
- Often caused by missing .env values or failed migration

**Horizon is not processing jobs**
- Check: `supervisorctl status postd-horizon`
- Restart: `supervisorctl restart postd-horizon`
- Check logs: `tail -f /var/www/postd/api/storage/logs/horizon.log`

**GitHub Actions deployment fails at SSH step**
- Verify `SERVER_SSH_KEY` secret contains the full private key including header/footer lines
- Check the key is in authorized_keys: `cat /home/postduk/.ssh/authorized_keys`
- Test SSH manually: `ssh postduk@144.126.207.135`

**Certbot fails**
- DNS may not have propagated yet -- wait and try again
- Check DNS: `dig +short postd.uk` should return `144.126.207.135`

---

*Last updated: March 2026*
