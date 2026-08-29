import 'package:flutter/services.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

/// A screen on a wall, and the two things that makes it: it never sleeps, and
/// it has no chrome.
///
/// The wakelock is the reason this app exists rather than a browser shortcut —
/// an Android TV launcher will drop its daydream over a web page no matter what
/// the page says, and a screensaver over a live mat is not a cosmetic problem.
/// It is set here AND with a native window flag in MainActivity, so the screen
/// is already held before Dart has started.
class Kiosk {
  const Kiosk._();
  static Future<void> engage() async {
    await SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    // Landscape on BOTH, and the tablet is the one that needs saying.
    //
    // Letting it rotate freely was the wrong call: every screen in this product
    // is 16:9, so a portrait tablet scales the whole console into a strip across
    // the middle of the glass with two dead bands above and below it and controls
    // too small to hit. Locked to landscape it fills the screen. Either way up —
    // `sensorLandscape` in the manifest — so it still does the right thing when
    // somebody turns it around on the table.
    await SystemChrome.setPreferredOrientations(const [
      DeviceOrientation.landscapeLeft,
      DeviceOrientation.landscapeRight,
    ]);
    // Best-effort: a device that refuses the lock still shows the board, and a
    // thrown error here would take the whole screen down with it.
    //
    // The Dart half of a pair — MainActivity sets FLAG_KEEP_SCREEN_ON before
    // the engine starts, and also declares the activity showable over the
    // keyguard so a camera whose power button is knocked comes straight back
    // rather than waiting behind a lock screen nobody is there to swipe.
    try {
      await WakelockPlus.enable();
    } catch (_) {}
  }
}
