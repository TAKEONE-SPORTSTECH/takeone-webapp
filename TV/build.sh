#!/usr/bin/env bash
#
# Builds one variant and drops a clearly-named APK in dist/.
#
#   ./build.sh tv                        # the wall screen, pointed at stage
#   ./build.sh tab                       # the scoring table, pointed at stage
#   ./build.sh tv  https://takeone.bh    # production
#
# Two APKs, one codebase. They differ in three ways and no more: the application
# id (so both can exist side by side on a network and in a device's app list),
# the manifest (a TV is leanback + fixed landscape; a tablet is touch + rotates),
# and one --dart-define that decides whether scrolling is pinned shut.
#
set -euo pipefail

cd "$(dirname "$0")"

VARIANT="${1:-}"
BASE_URL="${2:-https://stage.takeone.bh}"

case "$VARIANT" in
  tv)  APP_ID="bh.takeone.tv";  TEMPLATE="android_tv"  ;;
  tab) APP_ID="bh.takeone.tab"; TEMPLATE="android_tab" ;;
  *)   echo "usage: $0 {tv|tab} [base-url]" >&2; exit 1 ;;
esac

if [[ ! -d android ]]; then
  echo "android/ is missing — run ./setup.sh first." >&2
  exit 1
fi

export PATH="/opt/flutter/bin:$PATH"
export ANDROID_SDK_ROOT="${ANDROID_SDK_ROOT:-/opt/android-sdk}"
# See the note in setup.sh: tar cannot preserve ownership in a container.
export TAR_OPTIONS="${TAR_OPTIONS:---no-same-owner --no-same-permissions}"

main="android/app/src/main"

# The launcher icon and the TV banner: the TAKEONE mark, generated from
# public/images/logo.png by ./tools-make-icons.php and committed here. Copied for
# BOTH variants — one product, one icon — before the variant-specific manifest
# goes in, since that manifest is what points at them.
cp -r android_shared_res/. "$main/res/"

# The manifest for this variant. The namespace (and so the Kotlin package, and
# so `.MainActivity`) stays bh.takeone.tv in both builds — only the APPLICATION
# ID changes, which is what makes them separate installable apps.
cp "$TEMPLATE/AndroidManifest.xml" "$main/AndroidManifest.xml"

for gradle in android/app/build.gradle android/app/build.gradle.kts; do
  [[ -f "$gradle" ]] || continue
  sed -i -E "s/applicationId = \"[^\"]+\"/applicationId = \"${APP_ID}\"/" "$gradle"
  sed -i -E "s/applicationId \"[^\"]+\"/applicationId \"${APP_ID}\"/" "$gradle"
done

echo "▸ building ${VARIANT} (${APP_ID}) against ${BASE_URL}"

flutter build apk --release \
  --dart-define="TAKEONE_BASE_URL=${BASE_URL}" \
  --dart-define="TAKEONE_DEVICE=${VARIANT}"

mkdir -p dist
out="dist/takeone-screen-${VARIANT}.apk"
mv build/app/outputs/flutter-apk/app-release.apk "$out"

# MOVED, not copied. Flutter writes every variant to the same app-release.apk,
# so leaving it there means the directory always holds one unlabelled file that
# happens to be whichever variant was built last — which is indistinguishable
# from "the build didn't produce two APKs". dist/ is the only place to look.
rm -f build/app/outputs/flutter-apk/app-release.apk.sha1

echo
echo "✓ ${out}  ($(du -h "$out" | cut -f1))"
"${ANDROID_SDK_ROOT}/build-tools/34.0.0/aapt2" dump badging "$out" \
  | grep -E "^package:|^application:|leanback-launchable" || true
