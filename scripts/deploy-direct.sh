#!/usr/bin/env bash
#
# Runs ON the production container (takeone.bh), started over SSH by
# scripts/ship.sh. The same steps as .github/workflows/deploy.yml, without
# GitHub (owner's request, 2026-10-31: the Actions deploy kept failing).
#
#   bash deploy-direct.sh BUILD_DIR
#
# BUILD_DIR holds the release source that ship.sh copied (git archive of the
# commit being shipped). Dependencies and assets are built there, as the deploy
# user, then synced into the served tree with the same protections the
# workflow used. Any failure stops the script; the site is left in maintenance
# mode only if it got that far, and the newest backup is named.

set -euo pipefail

BUILD=${1:?usage: deploy-direct.sh BUILD_DIR}
DEPLOY_PATH=/var/www/takeone
RUN_AS=actionsrunner
KEEP_CODE_ARCHIVES=10

as_deploy() { sudo -u "$RUN_AS" -H bash -lc "$*"; }
artisan()   { (cd "$DEPLOY_PATH" && sudo -u "$RUN_AS" php artisan "$@"); }
step()      { printf '\n\033[1m── %s\033[0m\n' "$*"; }

went_down=0
on_error() {
    echo
    echo "✗ Deploy failed. The backup taken at the start is the newest file in $DEPLOY_PATH/storage/app/backups/:"
    ls -t "$DEPLOY_PATH/storage/app/backups/" 2>/dev/null | head -3 | sed 's/^/    /'
    [ "$went_down" = 1 ] && echo "The site is in maintenance mode. To reopen it as it is now: cd $DEPLOY_PATH && sudo -u $RUN_AS php artisan up"
}
trap on_error ERR

chown -R "$RUN_AS:$RUN_AS" "$BUILD"

step "Install PHP dependencies"
as_deploy "cd '$BUILD' && composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --quiet"

step "Build frontend assets"
as_deploy "cd '$BUILD' && npm ci --no-audit --no-fund --loglevel=error && npm run build --silent" | tail -3

# RULE #2: a verified backup before anything changes.
step "Back up production (database + uploads)"
artisan takeone:backup

step "Archive the current code"
archive="storage/app/backups/code-$(date +%Y%m%d-%H%M%S).tar.gz"
(cd "$DEPLOY_PATH" && tar -czf "$archive" \
    --exclude='./storage' --exclude='./vendor' --exclude='./node_modules' \
    --exclude='./.git' --exclude='./database/database.sqlite*' \
    --exclude='./.env*' --exclude='./docker/whatsapp/data' \
    --exclude='*/.gradle' . && tar -tzf "$archive" > /dev/null)
chown "$RUN_AS:$RUN_AS" "$DEPLOY_PATH/$archive"
echo "  $DEPLOY_PATH/$archive ($(du -h "$DEPLOY_PATH/$archive" | cut -f1))"

step "Maintenance mode on"
artisan down --retry=30
went_down=1

# Same rules as deploy.yml: anchored excludes, protected production-only
# files, --checksum (size+mtime misses same-size changes), -rlD not -a.
# The release is a git archive, so production's own .git is protected.
step "Sync build to $DEPLOY_PATH"
rsync -rlD --checksum --delete \
    --exclude='/.env' \
    --exclude='/database/database.sqlite*' \
    --exclude='/storage/' \
    --exclude='/node_modules/' \
    --filter='P /.git/***' \
    --filter='P /bootstrap/cache/*.php' \
    --filter='P /node_modules/***' \
    --filter='P /.env*' \
    --filter='P /docker/whatsapp/data/***' \
    --filter='P /public/app/*.apk' \
    --filter='P .gradle/***' \
    --filter='P /drafts/***' \
    --filter='P /verify-skills.php' \
    --filter='P /public/emperor-dashboard.html' \
    "$BUILD/" "$DEPLOY_PATH/"

# Whatever is shipped is ON in production: on/off switches from
# deploy/feature-flags.conf, never a credential or server setting.
step "Apply feature switches"
(
    cd "$DEPLOY_PATH"
    f=deploy/feature-flags.conf
    if [ ! -f "$f" ]; then echo "  No $f — switches left as they are."; exit 0; fi
    server='^(APP|DB|MAIL|CACHE|SESSION|QUEUE|LOG|BACKUP|BROADCAST|REDIS|FILESYSTEM|VITE|AWS|PUSHER|REVERB)_|^FILE_XSENDFILE(=|$)|DEBUG|^(TELESCOPE|IGNITION|DEBUGBAR|CLOCKWORK|PULSE|NIGHTWATCH|SENTRY|SANCTUM|CORS|HORIZON)_|CAPTCHA|2FA|TWO_FACTOR|VERIF|THROTTLE|TRUST'
    enums='^(NEW_UI|PARTIES_AUTHORITY)$'
    changed=0
    while IFS= read -r line; do
        case "$line" in ''|'#'*) continue ;; esac
        printf '%s' "$line" | grep -qE '^[A-Z][A-Z0-9_]*=[a-z0-9_-]*$' || { echo "  skipped a malformed line"; continue; }
        key=${line%%=*}; val=${line#*=}
        printf '%s' "$key" | grep -qE "$server" && { echo "  $key describes the server — skipped"; continue; }
        if [ "$val" != true ] && [ "$val" != false ] && ! printf '%s' "$key" | grep -qE "$enums"; then
            echo "  $key is not an on/off switch — skipped"; continue
        fi
        old=$(grep -E "^$key=" .env | tail -1 | cut -d= -f2- || true)
        [ "$old" = "$val" ] && continue
        if grep -qE "^$key=" .env; then sed -i "s|^$key=.*|$key=$val|" .env
        else printf '\n%s=%s\n' "$key" "$val" >> .env; fi
        echo "  $key: ${old:-(unset)} -> $val"
        changed=$((changed+1))
    done < "$f"
    echo "  $changed switch(es) changed."
)

step "Permissions"
chown -R "$RUN_AS:$RUN_AS" "$DEPLOY_PATH/storage" "$DEPLOY_PATH/bootstrap/cache" "$DEPLOY_PATH/database"
(cd "$DEPLOY_PATH" && chmod -R ug+rwX storage bootstrap/cache && chmod g+w database)

# Never cache:clear / optimize:clear here — the cache store holds live state
# (mat scoreboards, sign-in codes, throttles), not just derived data.
step "Migrate & cache"
artisan migrate --force
artisan config:cache
artisan route:cache
artisan event:cache
artisan view:clear
artisan view:cache

step "Restart queue workers, reload Apache"
artisan queue:restart
systemctl reload apache2

step "Maintenance mode off"
artisan up
went_down=0

step "Check the live site answers"
for path in /login /; do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -H 'Host: takeone.bh' "http://127.0.0.1$path")
    echo "  $path -> $code"
    case "$code" in 200|302) ;; *) echo "✗ takeone.bh$path answered $code after the deploy"; exit 1 ;; esac
done

# Clean-up: old code archives beyond the newest few. Database and upload
# backups are pruned by takeone:backup's own retention. The build folder is
# kept so the next build reuses its vendor/ and node_modules/.
step "Clean up"
ls -1t "$DEPLOY_PATH"/storage/app/backups/code-*.tar.gz 2>/dev/null | tail -n +$((KEEP_CODE_ARCHIVES + 1)) | while read -r old; do
    rm -f -- "$old" && echo "  removed old code archive $(basename "$old")"
done
echo "  kept the newest $KEEP_CODE_ARCHIVES code archives"

trap - ERR
echo
echo "✓ Deployed."
