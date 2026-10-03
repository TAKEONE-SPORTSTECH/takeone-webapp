#!/usr/bin/env bash
# Run the test suite ONE FILE AT A TIME, serially.
#
# Why: the suite cannot finish in one PHP process (it exhausts 2 GB around
# test 950), and running files in PARALLEL makes them clobber each other's
# Storage::fake() folders. Serial, per-file is the only honest full run.
#
# Usage (from the project root, as root or www-data):
#   scripts/test-per-file.sh [baseline-file]
# Exits non-zero if any file fails. With a baseline file (one failing test
# name per line), exits non-zero only for failures NOT in the baseline.
#
# Clears the config cache first (CLAUDE.md: never test with a cached config)
# and rebuilds it afterwards, so the site is uncached only for the run.
set -u
cd "$(dirname "$0")/.."
OUT="${TEST_OUT_DIR:-storage/framework/testing/per-file}"
BASELINE="${1:-}"
AS=""; [ "$(id -u)" = "0" ] && AS="sudo -u www-data"

mkdir -p "$OUT" && rm -f "$OUT"/*.txt
$AS php artisan config:clear >/dev/null
trap '$AS php artisan config:cache >/dev/null' EXIT

files=$(find tests -name '*Test.php' | sort)
total=0; failed_files=0
for f in $files; do
  total=$((total+1))
  n=$(echo "$f" | tr / _)
  $AS php -d memory_limit=1G vendor/bin/phpunit "$f" > "$OUT/$n.txt" 2>&1 || failed_files=$((failed_files+1))
done

grep -hE '^[0-9]+\) ' "$OUT"/*.txt | sed -E 's/^[0-9]+\) //' | sort -u > "$OUT/_failing.txt"
echo "files: $total, files with failures: $failed_files, failing tests: $(wc -l < "$OUT/_failing.txt")"

if [ -n "$BASELINE" ]; then
  new=$(comm -23 "$OUT/_failing.txt" <(sort -u "$BASELINE"))
  if [ -n "$new" ]; then echo "NEW failures (not in baseline):"; echo "$new"; exit 1; fi
  echo "no new failures against $BASELINE"; exit 0
fi
[ "$failed_files" = "0" ]
