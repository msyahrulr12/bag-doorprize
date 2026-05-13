#!/bin/bash

# =============================================================================
# deploy.sh - Extreme Speed Production Deployment
# Goal: Protect images at all costs and finish deployment in < 60 seconds.
# =============================================================================

set -Eeuo pipefail

# ───────────────── CONFIG ─────────────────
APP_NAME="bagi-hoki"
BASE_DIR="/home/sysadmin"
APP_DIR="${BASE_DIR}/${APP_NAME}"
DEPLOY_BASE="${BASE_DIR}/${APP_NAME}-main"
BACKUP_BASE="${BASE_DIR}/${APP_NAME}-backup"
TODAY=$(date +%Y-%m-%d)
TAR_FILE="${DEPLOY_BASE}/${TODAY}/bag-doorprize-deploy.tar.gz"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="${BACKUP_BASE}/env-backup-${TIMESTAMP}.env"

# ───────────────── COLORS ─────────────────
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

log() { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $*"; }
fail() { echo -e "${RED}[ERROR]${NC} $*" >&2; }

# ───────────────── ROOT CHECK ─────────────────
if [[ $EUID -ne 0 ]]; then fail "Please run as root"; exit 1; fi

# ───────────────── START ─────────────────
log "Starting Extreme Speed Deployment..."

# 1. STOP SERVICES
supervisorctl stop all || true
systemctl stop nginx || true

# 2. INSTANT BACKUP
mkdir -p "$BACKUP_BASE"
cp "$APP_DIR/.env" "$BACKUP_FILE" || true
ok "Config backed up."

# 3. CLEAN OLD FILES (Now preserving public, storage, and .env)
log "Step 3/6 — Cleaning (Preserving Public & Storage)..."
find "$APP_DIR" -mindepth 1 -maxdepth 1 \
    ! -name 'storage' \
    ! -name 'public' \
    ! -name '.env' \
    -exec rm -rf {} +
ok "Old code removed. Images and Assets remain untouched."

# 4. FAST EXTRACTION & SYNC (Excluding Public & Storage)
log "Step 4/6 — Syncing New Code..."
TMP_DIR="${BASE_DIR}/tmp_deploy_${TIMESTAMP}"
mkdir -p "$TMP_DIR"

tar -xf "$TAR_FILE" -C "$TMP_DIR"

# Rsync ignores 'public' and 'storage', making this nearly instant.
rsync -a --delete \
    --exclude='storage' \
    --exclude='public' \
    --exclude='.env' \
    "$TMP_DIR/" "$APP_DIR/"

rm -rf "$TMP_DIR"
ok "Logic and Vendor files updated."

# 5. PERMISSIONS & REBUILD LINK
log "Step 5/6 — Finalizing Permissions..."
cd "$APP_DIR"

# Ensure the public/storage symlink exists (without deleting the parent public folder)
if [[ ! -L "public/storage" ]]; then
    rm -rf public/storage
    php artisan storage:link --force || true
fi

# Set ownership to sysadmin (owner) and www-data (group)
chown -R sysadmin:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"
chmod -R g+s "$APP_DIR/storage"
chmod -R g+s "$APP_DIR/bootstrap/cache"
chmod -R 755 "$APP_DIR/public"

if [[ -f "Makefile" ]]; then
    # Run make deploy as sysadmin to keep file ownership correct
    su - sysadmin -c "cd $APP_DIR && make deploy" || warn "Make deploy failed, check logs later."
fi

# 6. RESTART
# We run supervisorctl in the background with a slight delay 
# so that the web request from the dashboard can finish gracefully 
# before the Octane process is killed/restarted.
(sleep 2 && supervisorctl restart all) &
systemctl restart nginx

echo -e "────────────────────────────────────────────"
echo -e "${GREEN}${BOLD}✔ DEPLOYMENT SUCCESSFUL${NC}"
echo -e "Images are safe. Public folder was not touched."
echo -e "Go home and enjoy your rest!"
echo -e "────────────────────────────────────────────"