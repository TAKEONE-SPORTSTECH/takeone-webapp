#!/bin/bash
#
# Turn a fresh Raspberry Pi OS Lite install into a court display.
#
#   curl -fsSL https://takeone.bh/pi/install.sh | sudo bash
#     — or, from a copy of this folder —
#   sudo ./install.sh [https://your-server]
#
# Idempotent: safe to re-run to upgrade a screen in place. It never touches an
# existing token, so re-running does not un-pair a screen that is already
# showing a mat.

set -euo pipefail

SERVER="${1:-https://takeone.bh}"
SERVER="${SERVER%/}"
CONF_DIR="/etc/takeone-court"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ "$(id -u)" -ne 0 ]; then
    echo "Run with sudo." >&2
    exit 1
fi

say() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

say "Installing packages"
apt-get update -qq
# cog is the WPE WebKit launcher; the drm platform plugin is what lets it draw
# without a desktop. Both are in Debian/Raspberry Pi OS Bookworm.
apt-get install -y --no-install-recommends \
    cog \
    curl \
    ca-certificates \
    python3-paho-mqtt

# The DRM platform ships separately on some images. Not fatal if absent — cog
# will say so on first run and the journal will show it.
apt-get install -y --no-install-recommends cog-platform-drm 2>/dev/null || \
    echo "note: cog-platform-drm not packaged here; cog's built-in drm platform will be used"

say "Installing the display program"
install -m 755 "$HERE/takeone-court" /usr/local/bin/takeone-court
# The ear: subscribes to this screen's topic so pairing changes land in seconds
# instead of waiting for the board to notice. Optional at runtime — the display
# works without it, just slower — so a missing paho only degrades, never breaks.
install -m 755 "$HERE/takeone-court-link" /usr/local/bin/takeone-court-link
install -m 644 "$HERE/takeone-court.service" /etc/systemd/system/takeone-court.service

install -d -m 700 "$CONF_DIR"
printf '%s' "$SERVER" > "$CONF_DIR/server"
chmod 600 "$CONF_DIR/server"
echo "server: $SERVER"

if [ -s "$CONF_DIR/token" ]; then
    echo "existing screen token kept — this Pi stays paired to its mat"
fi

say "Configuring the console"
# Boot quietly and without a login prompt on the display's tty: nothing should
# appear on the wall except the board.
systemctl disable --now getty@tty1.service 2>/dev/null || true

# A desktop image holds DRM master through its display manager, and cog cannot
# take a screen that X or Wayland already owns — it dies with "could not open
# DRM device". Lite is what the README asks for, but a Pi flashed with Desktop
# by mistake should still become a board rather than a black screen.
if systemctl is-enabled display-manager.service >/dev/null 2>&1 ||
   systemctl is-active display-manager.service >/dev/null 2>&1; then
    systemctl disable --now display-manager.service 2>/dev/null || true
    systemctl set-default multi-user.target >/dev/null
    echo "disabled the desktop: cog needs the screen to itself"
fi

BOOT_CFG=/boot/firmware/cmdline.txt
[ -f "$BOOT_CFG" ] || BOOT_CFG=/boot/cmdline.txt
if [ -f "$BOOT_CFG" ] && ! grep -q 'logo.nologo' "$BOOT_CFG"; then
    sed -i '1 s/$/ consoleblank=0 logo.nologo vt.global_cursor_default=0 loglevel=1/' "$BOOT_CFG"
    echo "quietened boot messages"
fi

# 720p. The board is authored at 1920x1080 and only ever SCALES, so the design
# is identical — but a Pi 3B is filling 56% of the pixels, which is the single
# biggest thing standing between this hardware and a smooth board.
#
# gpu_mem matters just as much and is easier to miss. A Pi 3B ships with 64 MB
# split to the VideoCore, which is not enough to hold the compositor's buffers
# for a full-screen page: it spills, and the board turns into a slideshow that
# looks exactly like a slow network. 128 MB is the smallest split that holds a
# 720p page comfortably and still leaves the 1 GB board room for cog.
CONF=/boot/firmware/config.txt
[ -f "$CONF" ] || CONF=/boot/config.txt
if [ -f "$CONF" ] && ! grep -q '^# takeone-court' "$CONF"; then
    cat >> "$CONF" <<'EOF'

# takeone-court — the board scales, so 720p costs no design and buys frame rate
hdmi_group=1
hdmi_mode=4
disable_overscan=1
gpu_mem=128
EOF
    echo "set output to 720p and gpu_mem=128 (edit $CONF and reboot to change)"
fi

# An earlier install wrote the block without gpu_mem. Re-running must fix that
# screen too, or the fleet silently splits into fast Pis and slow ones.
if [ -f "$CONF" ] && grep -q '^# takeone-court' "$CONF" && ! grep -q '^gpu_mem=' "$CONF"; then
    printf 'gpu_mem=128\n' >> "$CONF"
    echo "added gpu_mem=128 (reboot to apply)"
fi

say "Starting"
systemctl daemon-reload
systemctl enable takeone-court.service
systemctl restart takeone-court.service

cat <<EOF

Done.

  The screen will show a QR code and a pairing code.
  Scan it, choose the event and the mat, and the board appears by itself.

  Logs:     journalctl -u takeone-court -f
  Restart:  sudo systemctl restart takeone-court
  Re-pair:  sudo rm /etc/takeone-court/token && sudo systemctl restart takeone-court

A reboot is recommended so the quiet-boot and 720p settings take effect.
EOF
