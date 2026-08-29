#!/usr/bin/env bash
#
# Builds one variant and drops a clearly-named APK in dist/.
#
#   ./build.sh tv                        # the wall screen, pointed at stage
#   ./build.sh tab                       # the scoring table, pointed at stage
#   ./build.sh cam                       # a camera beside the mat
#   ./build.sh app                       # the member's phone app
#   ./build.sh tv  https://takeone.bh    # production
#
# Three APKs, one codebase. They differ in three ways and no more: the
# application id (so all three can exist side by side on a network and in a
# device's app list), the manifest (a TV is leanback; a tablet is touch; a
# camera also asks for the lens and the microphone), and one --dart-define that
# decides which of them this build is. The first two are WebView shells around
# the real web screens; the camera renders nothing and films.
#
set -euo pipefail

cd "$(dirname "$0")"

VARIANT="${1:-}"
BASE_URL="${2:-https://stage.takeone.bh}"

# Only the member app carries a version series, because only it is published to
# a store that enforces one. The hall APKs are side-loaded onto machines we own.
VERSION_CODE=""
VERSION_NAME=""

case "$VARIANT" in
  tv)  APP_ID="bh.takeone.tv";  TEMPLATE="android_tv"  ;;
  tab) APP_ID="bh.takeone.tab"; TEMPLATE="android_tab" ;;
  cam) APP_ID="bh.takeone.cam"; TEMPLATE="android_cam" ;;
  # The member's app. Its id is NOT ours to choose: bh.takeone.app is already
  # published, and Play will only accept an update that keeps it — and that is
  # signed with the same release key. See the note under "signing" below.
  app) APP_ID="bh.takeone.app"; TEMPLATE="android_app"
       # The published Capacitor build this replaces is versionCode 10 /
       # versionName 1.9. Play accepts an update only if the code goes UP, so
       # this build continues that series rather than restarting at Flutter's
       # default of 1 — which would be rejected as a downgrade.
       VERSION_CODE="11"; VERSION_NAME="1.10" ;;
  *)   echo "usage: $0 {tv|tab|cam|app} [base-url]" >&2; exit 1 ;;
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

# The member app's notification service runs with no Flutter engine, so it
# cannot read a --dart-define. The host is stamped into its manifest instead,
# from the same BASE_URL the Dart side is built with, so the two cannot drift.
if [[ "$VARIANT" == "app" ]]; then
  sed -i "s|__TAKEONE_BASE_URL__|${BASE_URL}|g" "$main/AndroidManifest.xml"
fi

for gradle in android/app/build.gradle android/app/build.gradle.kts; do
  [[ -f "$gradle" ]] || continue
  sed -i -E "s/applicationId = \"[^\"]+\"/applicationId = \"${APP_ID}\"/" "$gradle"
  sed -i -E "s/applicationId \"[^\"]+\"/applicationId \"${APP_ID}\"/" "$gradle"
done

echo "▸ building ${VARIANT} (${APP_ID}) against ${BASE_URL}"

flutter build apk --release \
  ${VERSION_CODE:+--build-number="${VERSION_CODE}"} \
  ${VERSION_NAME:+--build-name="${VERSION_NAME}"} \
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
