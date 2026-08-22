#!/usr/bin/env bash
#
# Generates the Android shell for this app and applies the TV-specific parts.
#
# The Dart, the manifest, the theme and the banner are all committed here; what
# this script does NOT commit is the generated Gradle scaffolding, because that
# belongs to whichever Flutter version you build with. Run it once after
# cloning, and again after a Flutter upgrade.
#
#   ./setup.sh              # scaffold android/ then apply the TV overrides
#   ./setup.sh --force      # re-scaffold over an existing android/
#
set -euo pipefail

cd "$(dirname "$0")"

# Flutter unpacks its gradle-wrapper artifact with tar preserving ownership,
# which fails inside a container whose uid map does not include the archive's
# owner ("Cannot change ownership to uid ...: Invalid argument") and leaves
# `flutter create` unable to finish. Harmless everywhere else.
export TAR_OPTIONS="${TAR_OPTIONS:---no-same-owner --no-same-permissions}"

APP_ID="bh.takeone.tv"
PKG_PATH="bh/takeone/tv"
FORCE="${1:-}"

if ! command -v flutter >/dev/null 2>&1; then
  echo "flutter is not on PATH. Install the SDK first:" >&2
  echo "  git clone -b stable --depth 1 https://github.com/flutter/flutter.git /opt/flutter" >&2
  echo "  export PATH=\"/opt/flutter/bin:\$PATH\"" >&2
  exit 1
fi

if [[ -d android && "$FORCE" != "--force" ]]; then
  echo "android/ already exists — applying the TV overrides only (--force to re-scaffold)."
else
  rm -rf android
  scaffold="$(mktemp -d)"
  trap 'rm -rf "$scaffold"' EXIT

  # Scaffolded in a throwaway directory and copied in, so `flutter create` can
  # never rewrite lib/ or pubspec.yaml — the parts that are actually the app.
  flutter create --platforms=android --org bh.takeone --project-name takeone_tv "$scaffold" >/dev/null
  cp -r "$scaffold/android" ./android
fi

# --- identity ---------------------------------------------------------------
# The scaffold names itself bh.takeone.takeone_tv; this app is bh.takeone.tv.
for gradle in android/app/build.gradle android/app/build.gradle.kts; do
  [[ -f "$gradle" ]] || continue
  sed -i "s/bh\.takeone\.takeone_tv/${APP_ID}/g" "$gradle"
done

# --- the parts this repo owns ----------------------------------------------
main="android/app/src/main"
cp android_tv/AndroidManifest.xml "$main/AndroidManifest.xml"
mkdir -p "$main/res/values" "$main/res/values-night" "$main/res/drawable"
cp android_tv/res/values/colors.xml       "$main/res/values/colors.xml"
cp android_tv/res/values/styles.xml       "$main/res/values/styles.xml"
cp android_tv/res/values-night/styles.xml "$main/res/values-night/styles.xml"
cp android_tv/res/drawable/tv_banner.xml  "$main/res/drawable/tv_banner.xml"

rm -rf "$main/kotlin"
mkdir -p "$main/kotlin/${PKG_PATH}"
cp android_tv/MainActivity.kt "$main/kotlin/${PKG_PATH}/MainActivity.kt"

flutter pub get

cat <<'DONE'

Ready. Build it with the host this screen should live at:

  flutter build apk --release --dart-define=TAKEONE_BASE_URL=https://stage.takeone.bh

Then sideload:

  adb connect <tv-ip>:5555
  adb install -r build/app/outputs/flutter-apk/app-release.apk
DONE
