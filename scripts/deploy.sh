#!/bin/bash
# =============================================================================
# postd.uk Manual Deploy Script
#
# Use this when you need to deploy without GitHub Actions — for example
# during initial setup, debugging a failed pipeline, or applying a hotfix.
#
# Usage:
#   bash scripts/deploy.sh
#
# Requirements:
#   - Your local SSH key must be authorised on the server for the postduk user
#   - Run from the root of the postd.uk repository
# =============================================================================

set -e
set -o pipefail

SERVER_IP="144.126.207.135"
SERVER_USER="postduk"
APP_DIR="/var/www/postd"
API_DIR="$APP_DIR/api"

echo ""
echo "============================================================"
echo "  postd.uk Manual Deploy"
echo "  Target: $SERVER_USER@$SERVER_IP"
echo "  Time  : $(date)"
echo "============================================================"
echo ""

# Confirm before proceeding (avoid accidental deploys)
read -rp "Deploy to production? This will run migrations. (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

echo ""
echo ">>> Connecting to server and deploying..."
echo ""

ssh "$SERVER_USER@$SERVER_IP" << 'REMOTE_SCRIPT'
set -e
set -o pipefail

APP_DIR="/var/www/postd"
API_DIR="$APP_DIR/api"

echo "--- [1/8] Enabling maintenance mode..."
php "$API_DIR/artisan" down --render="errors::503" --retry=60 || true

echo "--- [2/8] Pulling latest code from main..."
cd "$APP_DIR"
git fetch --all
git reset --hard origin/main

echo "--- [3/8] Installing PHP dependencies (no dev)..."
cd "$API_DIR"
composer install --prefer-dist --no-dev --no-interaction --optimize-autoloader

echo "--- [4/8] Running database migrations..."
php artisan migrate --force

echo "--- [5/8] Rebuilding application caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "--- [6/8] Fixing storage permissions..."
chmod -R 775 "$API_DIR/storage" "$API_DIR/bootstrap/cache"

echo "--- [7/8] Bringing application back online..."
php artisan up

echo "--- [8/8] Restarting Horizon..."
php artisan horizon:terminate || true
sudo /usr/bin/supervisorctl restart postd-horizon

echo ""
echo "============================================================"
echo "  Deployment complete!"
echo "  Site: https://postd.uk"
echo "  API : https://api.postd.uk"
echo "  Time: $(date)"
echo "============================================================"
REMOTE_SCRIPT

echo ""
echo ">>> Remote deployment finished."
echo ""

# Optional: quick HTTP check from the local machine
echo ">>> Running quick health check from local machine..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 https://postd.uk 2>/dev/null || echo "000")
if [ "$HTTP_STATUS" = "200" ]; then
    echo "    postd.uk responded with HTTP $HTTP_STATUS — looks good!"
else
    echo "    postd.uk responded with HTTP $HTTP_STATUS — check server logs if unexpected."
fi

API_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 https://api.postd.uk/health 2>/dev/null || echo "000")
if [ "$API_STATUS" = "200" ]; then
    echo "    api.postd.uk responded with HTTP $API_STATUS — looks good!"
else
    echo "    api.postd.uk responded with HTTP $API_STATUS"
fi

echo ""
echo "Done. Check https://postd.uk in your browser."
echo ""
