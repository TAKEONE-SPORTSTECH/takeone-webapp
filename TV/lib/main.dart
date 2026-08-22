import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

import 'src/config.dart';
import 'src/device.dart';
import 'src/kiosk.dart';
import 'src/theme.dart';

/// TAKEONE SCREEN — a television that is one of the hall's screens.
///
/// This app renders the REAL web screens rather than reimplementing them: it
/// opens `/screen`, which shows the pairing QR, and when an organiser claims it
/// the server sends it to its board. Same page, same fonts, same layout, same
/// MQTT client as a Raspberry Pi running a browser — because it IS that page.
///
/// The first build of this drew its own QR natively. That was a mistake twice
/// over: it looked nothing like the web screen it was supposed to be, and it
/// re-implemented a pairing flow that already worked (badly — it discarded the
/// server's root-relative destination and never left the QR). What the app is
/// actually FOR is the two things a browser on a TV cannot do:
///
///   * never sleep — no launcher daydream over a live mat
///   * no chrome at all — no status bar, no address bar, no scrollbars
///
/// Plus one thing worth keeping from the native build: it remembers the board
/// it was paired to, so a power cut brings the screen back as itself instead of
/// asking an organiser to pair it again mid-competition.
void main() {
  runApp(const TakeOneScreen());
}

class TakeOneScreen extends StatelessWidget {
  const TakeOneScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'TAKEONE ${Device.label}',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        brightness: Brightness.dark,
        scaffoldBackgroundColor: Hall.ink,
        useMaterial3: true,
      ),
      home: const Wall(),
    );
  }
}

class Wall extends StatefulWidget {
  const Wall({super.key});

  @override
  State<Wall> createState() => _WallState();
}

class _WallState extends State<Wall> {
  /// The board this screen was last paired to. Not the pairing page — that is
  /// resolved by the server from its own cookie.
  static const _boardKey = 'takeone.screen.board';

  WebViewController? _controller;
  Timer? _retryTimer;
  DateTime? _backSince;
  bool _everLoaded = false;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    Kiosk.engage();
    _boot();
  }

  @override
  void dispose() {
    _retryTimer?.cancel();
    super.dispose();
  }

  Future<void> _boot() async {
    final prefs = await SharedPreferences.getInstance();
    final remembered = prefs.getString(_boardKey);
    final start = remembered == null ? Config.screen : Config.resolve(remembered) ?? Config.screen;

    final controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Hall.ink)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            final target = Config.resolve(request.url);

            return target == null
                ? NavigationDecision.prevent
                : NavigationDecision.navigate;
          },
          onPageStarted: (url) {
            _allowAutoplay(_controller);
            _retryTimer?.cancel();
            _retryTimer = null;
            if (_failed) setState(() => _failed = false);

            _remember(url);
          },
          onPageFinished: (_) {
            _everLoaded = true;
            _hideScrollbars();
          },
          onWebResourceError: (error) {
            // Only the main document matters. A missing font or a dropped poll
            // must never blank a board that is otherwise on the glass.
            if (error.isForMainFrame != true) return;

            setState(() => _failed = true);

            // Keep trying by itself, forever: nobody is standing at this screen
            // to press anything, and the hall's link is the likeliest thing to
            // have dropped.
            _retryTimer ??= Timer.periodic(
              const Duration(seconds: 5),
              (_) => _controller?.reload(),
            );
          },
        ),
      );

    // Order matters, and this order was the bug on a television.
    //
    // Autoplay is granted by a PLATFORM call, and loading is a race against it:
    // a board that reaches its introduction music while the setting is still in
    // flight has its play() refused — and a refusal sticks to that element until
    // the page is touched, which on a wall is never. So the permission is
    // granted first, the view is attached second, and only then is anything
    // loaded.
    _allowAutoplay(controller);

    if (!mounted) return;

    setState(() => _controller = controller);

    await controller.loadRequest(start);
  }

  /// Let the board's sound play without anybody touching the screen.
  ///
  /// Android WebView refuses to autoplay audio until the page has had a user
  /// gesture — and nothing ever gestures at a screen on a wall. So an event's
  /// introduction music, its celebration and its point sounds would load and sit
  /// there silent, which is the one failure nobody in the hall can diagnose.
  ///
  /// The permission is granted by the EMBEDDER, not asked for by the page, which
  /// is why it lives here. Not a general loosening: this app renders exactly one
  /// host, refuses navigation off it, and has no pointer to abuse.
  void _allowAutoplay(WebViewController? controller) {
    final platform = controller?.platform;

    if (platform is AndroidWebViewController) {
      platform.setMediaPlaybackRequiresUserGesture(false);
    }
  }

  /// Remember a board, forget the pairing room.
  ///
  /// Written on navigation rather than on claim, because the server owns the
  /// claim: whatever the screen ends up showing IS what it was adopted as.
  Future<void> _remember(String url) async {
    final target = Config.resolve(url);
    if (target == null) return;

    final prefs = await SharedPreferences.getInstance();

    if (Config.isPairing(target)) {
      await prefs.remove(_boardKey);

      return;
    }

    await prefs.setString(_boardKey, target.toString());
  }

  /// The app's own viewport, not the board's design.
  ///
  /// On a television: a hall screen is exactly 1920x1080 of board, nothing is
  /// meant to move, and a scrollbar down the side of a wall display is a defect
  /// whatever caused it — so scrolling is pinned shut.
  ///
  /// On a tablet: the scrollBAR is hidden and scrolling is LEFT ALONE. This is
  /// the scoring table, held in somebody's hands; a console taller than a 10"
  /// screen with overflow pinned would be a console whose bottom half cannot be
  /// reached.
  void _hideScrollbars() {
    final lockScrolling = Device.isTv ? "html,body{overflow:hidden!important}" : '';

    _controller?.runJavaScript(
      "(function(){var s=document.createElement('style');"
      "s.textContent='::-webkit-scrollbar{display:none!important}$lockScrolling';"
      "document.head.appendChild(s);})();",
    );

    if (Device.isTablet) _followVisualViewport();
  }

  /// A tablet's VISIBLE area can change without its layout box changing — the
  /// soft keyboard is the usual way. Every screen re-fits on `resize` and on its
  /// own ResizeObserver, so all this has to do is make sure a visual-viewport
  /// change counts as one. Cheap, idempotent, and a no-op on anything that does
  /// not support it.
  void _followVisualViewport() {
    _controller?.runJavaScript(
      "(function(){"
      "if(!window.visualViewport||window.__tkVV)return;"
      "window.__tkVV=1;"
      "var f=function(){window.dispatchEvent(new Event('resize'));};"
      "window.visualViewport.addEventListener('resize',f);"
      "window.visualViewport.addEventListener('scroll',f);"
      "})();",
    );
  }

  /// The box the web page is given, and it must be the box a person can SEE.
  ///
  /// Every screen in this product is authored at 1920x1080 and scaled down by
  /// `min(width / 1920, height / 1080)` of whatever element it is handed. So the
  /// one thing that ruins it is being handed a viewport taller than the visible
  /// area: the page dutifully scales to fit a box that runs off the bottom of
  /// the glass, and the row of controls along the bottom of the scoring console
  /// goes with it — visible nowhere, reachable by nothing.
  ///
  /// On a TELEVISION that cannot happen: no keyboard, no bars coming back, so it
  /// gets the full edge-to-edge window and the board fills the wall.
  ///
  /// On a TABLET it happens constantly. It is held in somebody's hands, a swipe
  /// brings the navigation bar back, and tapping the competitor search opens a
  /// soft keyboard over the bottom third. `Scaffold` + `SafeArea` means each of
  /// those SHRINKS the viewport instead of covering it, the page's own
  /// ResizeObserver re-fits, and the whole console — bottom row included — stays
  /// on screen at a slightly smaller scale. Smaller and complete beats bigger
  /// and amputated.
  Widget _viewport(Widget child) {
    if (Device.isTv) return child;

    return Scaffold(
      backgroundColor: Hall.ink,
      // Default true, stated because it is the point: the keyboard must resize
      // the page, not sit on top of it.
      resizeToAvoidBottomInset: true,
      body: SafeArea(child: child),
    );
  }

  /// Hold BACK to hand the wall back to whoever is setting it up: forget the
  /// board and start over as a new screen. A single press does nothing on
  /// purpose — BACK is the easiest key to hit by accident on a TV remote, and
  /// this screen may be showing a live mat.
  Future<void> _startOver() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_boardKey);

    await _controller?.loadRequest(Config.anotherScreen);
  }

  @override
  Widget build(BuildContext context) {
    final controller = _controller;

    return Focus(
      autofocus: true,
      onKeyEvent: (_, event) {
        final isBack = event.logicalKey == LogicalKeyboardKey.goBack ||
            event.logicalKey == LogicalKeyboardKey.escape;

        if (!isBack) return KeyEventResult.ignored;

        if (event is KeyDownEvent) {
          _backSince ??= DateTime.now();
        } else if (event is KeyUpEvent) {
          final since = _backSince;
          _backSince = null;

          if (since != null &&
              DateTime.now().difference(since) >= const Duration(seconds: 3)) {
            _startOver();
          }
        }

        // Always handled: BACK must never pop this app off the wall.
        return KeyEventResult.handled;
      },
      child: ColoredBox(
        color: Hall.ink,
        child: Stack(
          children: [
            if (controller != null) _viewport(WebViewWidget(controller: controller)),

            // Only ever before the FIRST load. Once a screen has been on the
            // glass, the app draws nothing over it — a board a few seconds
            // stale beats a live mat covered in app chrome.
            if (!_everLoaded && (controller == null || _failed)) const _Booting(),
          ],
        ),
      ),
    );
  }
}

/// Deliberately almost nothing, and never an error.
///
/// The web screen learned this the hard way: every failure of a "setting this
/// screen up" spinner looks identical from the floor, and the one machine that
/// cannot be debugged is a screen bolted to a wall. So this is only ever a few
/// seconds long — it retries underneath, forever — and names the host it is
/// trying, which is the one fact somebody standing in the hall can act on.
class _Booting extends StatelessWidget {
  const _Booting();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: Hall.stage,
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('TAKEONE ${Device.label}', style: Hall.eyebrow()),
            const SizedBox(height: 28),
            Text(Config.base.host.toUpperCase(), style: Hall.foot()),
          ],
        ),
      ),
    );
  }
}
