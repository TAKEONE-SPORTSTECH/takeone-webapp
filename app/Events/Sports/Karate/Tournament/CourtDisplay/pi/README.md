# Court display — the Raspberry Pi

A screen bolted to a wall showing one mat's running order. This folder is the
whole device side; everything it displays is decided on the server.

**Nothing here is preloaded with an event, a mat, or a token.** A Pi is flashed,
plugged in, and shows a QR code until an organiser tells it what it is.

---

## What you need

| | |
|---|---|
| Raspberry Pi 3B | 1GB RAM, and **no 5GHz radio** — see Networking |
| SD card | 8GB+, Raspberry Pi OS **Lite** (no desktop) |
| Power | 2.5A official supply. Under-voltage shows as a random freeze hours later |
| Screen | Any HDMI panel or TV |
| Network | A 4G dongle + data SIM is the recommended setup |

---

## Install

Flash **Raspberry Pi OS Lite (64-bit)** with Raspberry Pi Imager. In Imager's
settings, set a hostname (it becomes the screen's name in the admin list) and
enable SSH — you will want it once the Pi is on a wall.

Boot it, then:

```bash
git clone https://github.com/TAKEONE-SPORTSTECH/takeone-webapp   # or copy this folder over
cd takeone-webapp/app/Events/Sports/Karate/Tournament/CourtDisplay/pi
sudo ./install.sh https://takeone.bh
sudo reboot
```

That is the entire device setup. It is idempotent — re-run it to upgrade a
screen, and it will not un-pair one that is already showing a mat.

---

## What happens on boot

```
power on
   ↓
wait until the SERVER answers          not "is there an interface" — a 4G dongle
   ↓                                   reports a link long before it has a session
first boot only: POST /court/enroll    asks who it is; gets a token that can show
   ↓                                   a pairing code and nothing else
cog renders /court/<token>
   ↓
┌──────────────────────┐               white screen, QR, 6-character code
│  unpaired            │──────────────► organiser scans it, picks event + mat
└──────────────────────┘               takeone-karate-court-link hears it and restarts
   ↓ ▲                                 the display — seconds, not minutes
   │ │  unpair from the console
   │ └───────────────────────────────── back to a FRESH code, ready for another mat
   ↓
┌──────────────────────┐
│  the board           │               closest bout first; shortens as results land
└──────────────────────┘
```

### The ear — `takeone-karate-court-link`

A second process, started by `takeone-karate-court` before it hands the console to cog.
It subscribes to this screen's own MQTT topic and, when the assignment changes,
kills cog — which ends the unit's main process, so systemd brings the display
straight back and the server decides what it now shows.

**Why it is not in the page.** The board carries the same subscription and it
works anywhere with a GPU. On a Pi 3B it does not: the board animates
continuously (a 110px-blur `box-shadow`, gradient `background-position`, clip-path
rows — none of it accelerated), which saturates the renderer. Measured, the same
page reacted to a message in **7 seconds run headless** and **not at all within
six minutes on the real display**, and a 60s timer fired every ~2m50s. Out here
nothing the browser does can starve it: the reaction is now in the same second
the organiser taps.

It degrades rather than breaks. No `python3-paho-mqtt`, no realtime configured,
broker unreachable — the display still works, falling back to the board's own
slow polling.

> Its HTTP request identifies itself as `takeone-karate-court-link/1.0` deliberately.
> Cloudflare answers **403** to urllib's default `Python-urllib/3.11`, which cost
> an afternoon once.

The token is written to `/etc/takeone-karate-court/token`, `0600`, root-owned. It is
that screen's whole identity, and it survives reboots — **a power cut at 11am
brings the board back, not a QR code.**

---

## Why cog and not a browser

`cog` is the WPE WebKit launcher, drawing straight onto DRM/KMS. There is no
desktop, no compositor, no window manager and no browser chrome — no address
bar, no tabs, no menus, nothing to click. It is a systemd service that happens
to render HTML, which is what lets the board be **byte-identical to the approved
design** without a hand-written renderer that would drift from it.

The board is authored at 1920×1080 and only ever *scales*, so `install.sh` sets
the output to **720p**: 56% of the pixels for a design that looks the same on a
TV at viewing distance. This is the single biggest thing standing between a Pi
3B and a smooth board — measure before raising it.

---

## Networking

**A 4G dongle and a data SIM is the recommended setup**, for one blunt reason:
the **Pi 3B has no 5GHz radio** (that arrived with the 3B+). Plenty of venue
networks are 5GHz-only, and at those venues this hardware cannot join the wifi
at all, however good the pairing flow is. Sports halls also tend to have captive
portals and networks that collapse when 400 spectators arrive.

Most ModemManager-supported dongles work with no configuration. To use wifi
instead, set it up in Raspberry Pi Imager before first boot, or:

```bash
sudo nmcli device wifi connect "<SSID>" password "<password>"
```

---

## Running it

```bash
journalctl -u takeone-karate-court -f          # what the screen is doing
sudo systemctl restart takeone-karate-court    # restart the display
sudo systemctl status takeone-karate-court
```

**Re-pair a screen** (move it to another mat or another event):

```bash
sudo rm /etc/takeone-karate-court/token
sudo systemctl restart takeone-karate-court    # back to a QR code
```

Or revoke it from the server, which takes effect immediately and does not need
the device:

```bash
php artisan court:pair <event-uuid> --list
php artisan court:pair <event-uuid> --revoke=<id>
```

---

## When something is wrong

| What you see | What it is |
|---|---|
| Black screen, no output | `journalctl -u takeone-karate-court -e`. Usually cog failing to open DRM — check `/dev/dri/card0` exists |
| Stuck on the QR | Nobody has claimed it, or the code was already used. Codes are spent on first use |
| "still waiting for …" in the log | No route to the server. Check the dongle: `nmcli device status` |
| Board shows, fonts look wrong | The fonts are served by the app; check `curl -I https://takeone.bh/karate/court-display/font/anton-400-latin.woff2` |
| Freezes after hours | Almost always power. Use a 2.5A supply; check `vcgencmd get_throttled` (`0x0` is healthy) |
| Screen blanks | `install.sh` disables blanking — confirm `consoleblank=0` made it into `cmdline.txt` |

---

## What is NOT here yet

- **MQTT.** The board currently re-renders on load. Pushing each mat's queue as
  a *retained* message is the next step: retained matters because a plain
  subscribe hears nothing until the next publish, so a screen rebooted mid-event
  would sit blank.
- **Offline cache.** The board is fetched, not cached to disk, so an uplink that
  dies mid-event leaves the last render on screen but a reboot shows nothing.
- **Local flag mirror.** Flags come from flagcdn; fine on 4G, absent without it.
- **BLE wifi provisioning** — the fallback for venues with good 2.4GHz wifi and
  no reason to burn SIM data.
