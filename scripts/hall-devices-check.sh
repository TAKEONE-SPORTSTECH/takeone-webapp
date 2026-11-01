#!/usr/bin/env bash
# Hall devices must keep working — the business core (CLAUDE.md, RULE #3).
#
#   scripts/hall-devices-check.sh tests   the device test files, in-memory DB.
#                                          Run by ship.sh BEFORE anything is
#                                          committed; a failure ships nothing.
#   scripts/hall-devices-check.sh live    both real sites, end to end: a screen
#                                          opened on either host must be known,
#                                          drawn and pairable from either host.
#                                          Run by ship.sh AFTER every deploy.
#
# What "working" means: open /screen on a TV on stage.takeone.bh OR takeone.bh,
# scan it from the event console on EITHER host, and the TV becomes the score
# control, the scoreboard or the upcoming matches; a camera pairs the same way.
# The two hosts share one database, so a code from one is valid on the other.
#
# `live` writes nothing but the waiting screens /screen itself issues — exactly
# what a television does when it is switched on. It never pairs a real event.
set -u
cd "$(dirname "$0")/.."
APP=$(pwd)
AS=""; [ "$(id -u)" = "0" ] && AS="sudo -u www-data"
PROD_SSH=${PROD_SSH:-takeone-live}

TESTS=(
    tests/Feature/Devices/CrossHostScreenPairingTest.php
    tests/Feature/Events/CourtScreenPairingTest.php
    tests/Feature/Devices/DeviceRecoverySafetyTest.php
    tests/Feature/Devices/CameraPairingAndUploadTest.php
    tests/Feature/Events/CourtDisplayTest.php
    tests/Feature/Devices/ConsolePlayersTabTest.php
    tests/Feature/Events/KaratePlayersTabTest.php
)

pass=0; failed=0
ok()  { echo "  ✓ $*"; pass=$((pass+1)); }
bad() { echo "  ✗ $*"; failed=$((failed+1)); }

run_tests() {
    # Never test with a cached config (CLAUDE.md) — and always put it back.
    $AS php artisan config:clear >/dev/null
    trap '$AS php artisan config:cache >/dev/null' EXIT

    for t in "${TESTS[@]}"; do
        out=$($AS php -d memory_limit=1G vendor/bin/phpunit "$t" 2>&1)
        if [ $? -eq 0 ]; then
            ok "$t ($(echo "$out" | grep -Eo 'Tests: [0-9]+|OK \([0-9]+ tests' | head -1))"
        else
            bad "$t"
            echo "$out" | grep -E '^[0-9]+\) |Expected|Failed asserting' | head -12 | sed 's/^/      /'
        fi
    done
}

# Run a command inside the app on a host: here (stage) or over SSH (production).
on() {
    local where=$1; shift
    if [ "$where" = takeone.bh ]; then ssh -o ConnectTimeout=10 -o BatchMode=yes "$PROD_SSH" "$@"
    else bash -c "$@"; fi
}

run_live() {
    tmp=$(mktemp -d)
    trap 'rm -rf "${tmp:-}"' EXIT

    for issuer in stage.takeone.bh takeone.bh; do
        echo "== A screen opened on https://$issuer/screen"
        loc=$(curl -sk -c "$tmp/jar" -o /dev/null -w '%{redirect_url}' "https://$issuer/screen")
        token=$(basename "${loc%%\?*}")
        if [ ${#token} -lt 30 ]; then bad "no screen token issued (redirect: ${loc:-none})"; continue; fi
        ok "issued a screen"

        html=$(curl -sk -b "$tmp/jar" "https://$issuer/screen/$token")
        echo "$html" | grep -q 'class="qr-card"' && ok "waiting page draws its QR" || bad "waiting page has no QR"

        # The code, and what the QR image really encodes, compared byte for byte.
        res=$(echo "$html" | php -r '$h=stream_get_contents(STDIN); preg_match("#<div class=\"qr-card\">(.*?)</div>#s",$h,$m); echo $m[1]??"";' \
            | $AS php -r '
                require "'"$APP"'/vendor/autoload.php"; $app=require "'"$APP"'/bootstrap/app.php";
                $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                $qr=trim(stream_get_contents(STDIN));
                $s=\App\Events\Support\PendingScreen::where("token_hint", substr($argv[1],0,6))->latest("id")->first();
                if(!$s){ echo "NOCODE -"; exit; }
                $enc="unknown";
                foreach(["stage.takeone.bh","takeone.bh"] as $h){ if(trim(\App\Support\Qr::svg("https://$h/screen/claim/{$s->pairing_code}",400,4,"#141010","#ffffff"))===$qr) $enc=$h; }
                echo $s->pairing_code," ",$enc;' "$token" 2>/dev/null)
        code=${res%% *}; enc=${res#* }
        if [ -z "$res" ] || [ "$code" = NOCODE ]; then bad "its code is not in the database"; continue; fi
        [ "$enc" = "$issuer" ] && ok "QR points at https://$issuer/screen/claim/…" || bad "QR points at '$enc', expected $issuer"

        for viewer in stage.takeone.bh takeone.bh; do
            st=$(curl -sk -o /dev/null -w '%{http_code}' "https://$viewer/screen/$token/status")
            [ "$st" = 200 ] && ok "$viewer knows this screen" || bad "$viewer status door answered $st"
            pg=$(curl -sk -o /dev/null -w '%{http_code}' "https://$viewer/screen/$token")
            [ "$pg" = 200 ] && ok "$viewer draws this screen" || bad "$viewer screen page answered $pg"

            known=$(on "$viewer" "cd $APP && $AS php -r '
                require \"vendor/autoload.php\"; \$app=require \"bootstrap/app.php\";
                \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                echo \App\Events\Support\PendingScreen::pairable(\"$code\") ? \"yes\" : \"no\";'" 2>&1 | tail -1)
            [ "$known" = yes ] && ok "$viewer console can pair code $code" || bad "$viewer console cannot pair $code ($known)"
        done
    done

    echo "== The event console clears the other-host flag on every scan"
    for h in stage.takeone.bh takeone.bh; do
        n=$(on "$h" "grep -c 'this.from = there !== here' $APP/resources/views/components/court-screens.blade.php" 2>/dev/null)
        [ "${n:-0}" -ge 1 ] && ok "$h" || bad "$h is serving the old console script"
    done
}

case "${1:-}" in
    tests) echo "Hall devices — tests"; run_tests ;;
    live)  echo "Hall devices — both live sites"; run_live ;;
    *)     echo "usage: $0 tests|live" >&2; exit 2 ;;
esac

echo "  passed $pass, failed $failed"
[ "$failed" -eq 0 ]
