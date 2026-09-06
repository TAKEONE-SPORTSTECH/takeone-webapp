import 'dart:async';
import 'dart:collection';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

import '../config.dart';
import '../theme.dart';

/// TAKEONE — the member's app.
///
/// It renders the real mobile web rather than reimplementing it. That is not a
/// shortcut, it is the project's rule: CLAUDE.md says "the mobile web experience
/// IS the Android app", so a change to a mobile Blade view reaches every
/// installed phone the moment it deploys, with no store release.
///
/// This replaces a Capacitor shell that did the same job. What that shell
/// actually contributed over a browser tab was four things, and they are all
/// reproduced here:
///
///   * uploads — a WebView grants no file picker unless the host app hands it
///     one, and without it a member cannot set a profile picture or send a
///     payment proof;
///   * device permissions — camera for the QR scanner, microphone, location,
///     asked at the moment the page asks rather than up front;
///   * links that leave — a club's Instagram opens in Android, not inside our
///     chrome, because a foreign login form wearing our frame is a phishing
///     surface;
///   * the back button behaving like a phone's, not like a kiosk's.
///
/// Deliberately NOT a kiosk: status bar, rotation and scrolling all stay. The
/// hall variants take those away because a wall screen has nobody holding it;
/// a phone in a pocket is the opposite case.
///
/// ## Why this one does not use `webview_flutter`
///
/// The hall variants still do, and should: they display a board that does not
/// scroll. This one is scrolled constantly, and `webview_flutter` renders the
/// page through Flutter's own compositor, which costs a frame on every fling —
/// the same page was visibly smoother in the phone's browser. It also keeps the
/// Android settings that govern scrolling and zoom private, so there is no way
/// to reach them.
///
/// `flutter_inappwebview` exposes them, and is the same plugin on iOS, which is
/// the whole reason this app is written in Flutter at all.
class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  InAppWebViewController? _controller;
  bool _offline = false;

  @override
  void initState() {
    super.initState();

    // An ordinary app, not a kiosk: give the system bars back.
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  }

  /// How the page is rendered, and the settings that decide whether it feels
  /// like an app or like a page trapped in one.
  InAppWebViewSettings get _settings => InAppWebViewSettings(
        // Notification chimes ring the moment they arrive. A browser makes the
        // user tap first; inside our own shell we opt out of that policy.
        mediaPlaybackRequiresUserGesture: false,

        // Ours stays inside, everything else is handed to the platform — which
        // requires Android to ask us first.
        useShouldOverrideUrlLoading: true,

        // Pinch belongs to a browser, not to a shell with no address bar to
        // escape a stuck zoom with. Done natively here rather than by forcing
        // `user-scalable=no` into the page, which would fail WCAG 1.4.4 for
        // every browser that loads the same HTML.
        supportZoom: false,
        builtInZoomControls: false,
        displayZoomControls: false,

        // The scroll itself. Hybrid composition puts the real WebView in the
        // view hierarchy so it flings on the Android UI thread with its own
        // physics, instead of being copied through Flutter every frame.
        useHybridComposition: true,
        hardwareAcceleration: true,

        // Our own offline screen says something useful; Android's says
        // "net::ERR_" at the member.
        disableDefaultErrorPage: true,

        // iOS, for when this app ships there: inline video rather than the
        // fullscreen player, and the same back-swipe a browser gives.
        allowsInlineMediaPlayback: true,
        allowsBackForwardNavigationGestures: true,
      );

  /// Injected before the page's own scripts evaluate.
  ///
  /// Order matters: the transport shim has to exist before the Capacitor shim
  /// that calls through it, and both have to exist before
  /// `app-update.blade.php`, which reads `window.Capacitor` the moment it is
  /// parsed.
  UnmodifiableListView<UserScript> get _userScripts => UnmodifiableListView([
        for (final source in [_transport, _bridge, _zoomLock])
          UserScript(source: source, injectionTime: UserScriptInjectionTime.AT_DOCUMENT_START),
      ]);

  /// Calls arriving from the page's `window.Capacitor` shim.
  void _onBridgeCall(String message) {
    Map<String, dynamic> call;
    try {
      call = jsonDecode(message) as Map<String, dynamic>;
    } catch (_) {
      return;
    }

    final id = call['id'];
    final method = call['method'];
    if (id is! String || method is! String) return;

    unawaited(_dispatch(id, method, (call['args'] as Map?) ?? const {}));
  }

  Future<void> _dispatch(String id, String method, Map<dynamic, dynamic> args) async {
    Object? value;
    String? error;

    try {
      value = await const MethodChannel('bh.takeone/app').invokeMethod<Object?>(
        method,
        args.map((k, v) => MapEntry(k.toString(), v)),
      );
    } on PlatformException catch (e) {
      error = e.message ?? e.code;
    } on MissingPluginException {
      // An older host without this method. Reported as a failure so the page
      // takes its own fallback — app-update falls back to the system browser.
      error = 'unsupported';
    }

    // Hand the answer back to the promise the shim is holding.
    final payload = jsonEncode({'id': id, 'value': value, 'error': error});
    await _controller?.evaluateJavascript(source: 'window.__takeoneResolve($payload);');
  }

  Future<void> _openExternally(Uri url) async {
    // Launched through the platform's own intent handling rather than a
    // url_launcher dependency: one fewer package for a single call.
    try {
      await const MethodChannel('bh.takeone/app')
          .invokeMethod<void>('openExternally', {'url': url.toString()});
    } on PlatformException {
      // Nothing installed can open it. Silently staying put is the right
      // failure: a member tapping a dead link should not get a crash dialog.
    } on MissingPluginException {
      // Older shell without the channel — leave the tap unhandled rather than
      // navigating somewhere the app should not go.
    }
  }

  /// Does the APP itself hold Android's camera permission?
  ///
  /// Declaring CAMERA in the manifest is not the grant. Android asks the member
  /// for it at the moment it is needed, and until they say yes the WebView can
  /// open a camera device that never delivers a frame — which is what a black
  /// QR scanner is. A shell too old to know the call answers true, so this can
  /// only ever add a check, never take a working scanner away.
  Future<bool> _ensureCameraPermission() async {
    try {
      return await const MethodChannel('bh.takeone/app')
              .invokeMethod<bool>('ensureCameraPermission') ??
          false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return true;
    }
  }

  /// The phone's back button walks the page history first, and only leaves the
  /// app once there is nothing left to go back to.
  Future<bool> _onWillPop() async {
    final controller = _controller;
    if (controller == null) return true;

    if (await controller.canGoBack()) {
      await controller.goBack();

      return false;
    }

    return true;
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;

        // Nothing left in the page's history: this is the root route, so
        // leaving means leaving the app, not popping a route that isn't there.
        if (await _onWillPop()) await SystemNavigator.pop();
      },
      child: Scaffold(
        backgroundColor: Hall.ink,
        body: SafeArea(
          child: _offline
              ? _Offline(onRetry: () {
                  setState(() => _offline = false);
                  unawaited(_controller?.loadUrl(
                        urlRequest: URLRequest(url: WebUri(Config.appHome.toString())),
                      ) ??
                      Future.value());
                })
              : InAppWebView(
                  initialUrlRequest: URLRequest(url: WebUri(Config.appHome.toString())),
                  initialSettings: _settings,
                  initialUserScripts: _userScripts,
                  onWebViewCreated: (controller) {
                    _controller = controller;

                    controller.addJavaScriptHandler(
                      handlerName: 'TakeOneNative',
                      callback: (args) {
                        if (args.isNotEmpty && args.first is String) {
                          _onBridgeCall(args.first as String);
                        }

                        return null;
                      },
                    );
                  },
                  shouldOverrideUrlLoading: (controller, action) async {
                    final url = action.request.url;
                    if (url == null) return NavigationActionPolicy.CANCEL;

                    // Ours stays in the app. Anything else — a club's social
                    // link, a tel: or mailto:, a receipt on somebody else's
                    // domain — is handed to Android so it opens in the browser
                    // or dialler it belongs to.
                    if (Config.staysInside(Uri.parse(url.toString()))) {
                      return NavigationActionPolicy.ALLOW;
                    }

                    unawaited(_openExternally(Uri.parse(url.toString())));

                    return NavigationActionPolicy.CANCEL;
                  },
                  onLoadStart: (controller, url) {
                    if (_offline) setState(() => _offline = false);
                  },
                  onReceivedError: (controller, request, error) {
                    // Only a failure of the PAGE itself is worth a screen. A
                    // dead image or a slow analytics beacon must not blank the
                    // app.
                    if (!request.isForMainFrame!) return;
                    if (!mounted) return;
                    setState(() => _offline = true);
                  },
                  // Camera for the QR scanner, microphone, and anything else the
                  // page asks for. Granted per request, at the moment the page
                  // asks — never a blanket grant at launch.
                  onPermissionRequest: (controller, request) async {
                    // Two grants have to line up, and only one of them is the
                    // page's. Saying GRANT here while the APP holds no runtime
                    // camera permission opens a camera that yields no frames:
                    // the QR scanner paints a black viewfinder, decodes
                    // nothing, and raises no error the page could report. So
                    // ask Android first, and DENY honestly when the member says
                    // no — a refusal the page can see becomes "type the code
                    // instead", which is a way through.
                    final wantsCamera =
                        request.resources.contains(PermissionResourceType.CAMERA) ||
                            request.resources
                                .contains(PermissionResourceType.CAMERA_AND_MICROPHONE);

                    if (wantsCamera && !await _ensureCameraPermission()) {
                      return PermissionResponse(
                        resources: request.resources,
                        action: PermissionResponseAction.DENY,
                      );
                    }

                    return PermissionResponse(
                      resources: request.resources,
                      action: PermissionResponseAction.GRANT,
                    );
                  },
                  onGeolocationPermissionsShowPrompt: (controller, origin) async =>
                      GeolocationPermissionShowPromptResponse(
                    origin: origin,
                    allow: true,
                    retain: false,
                  ),
                ),
        ),
      ),
    );
  }
}

/// The shim that carries a bridge call to Dart.
///
/// `_bridge` was written against `webview_flutter`, whose JavaScript channel
/// appears as a global object with a `postMessage`. This plugin delivers
/// messages through `callHandler` instead, so rather than rewrite the shim the
/// old shape is recreated on top of the new transport — the web calls the same
/// thing it always did, and nothing on the server changes.
const String _transport = r'''
(function () {
  if (window.TakeOneNative) return;

  window.TakeOneNative = {
    postMessage: function (message) {
      window.flutter_inappwebview.callHandler('TakeOneNative', message);
    }
  };
})();
''';

/// The `window.Capacitor` shape the mobile web already calls, backed by the
/// Flutter channel instead of a Capacitor bridge. Injected before the page's own
/// scripts run, so `app-update.blade.php` sees it on first evaluation.
/// Pinch-to-zoom belongs to a browser, not to this shell.
///
/// The page itself must stay zoomable — a viewport carrying `user-scalable=no`
/// fails WCAG 1.4.4 and the project forbids serving one, because on the web that
/// is somebody's only way to read small text. Inside the app the reasoning
/// inverts: there is no address bar, no tab strip and no reload button, so a
/// stray two-finger drag zooms the interface and leaves the member with no
/// obvious way back. The restriction therefore lives HERE, applied by the shell
/// to the rendered document, and what the server sends stays zoomable everywhere
/// else.
///
/// `viewport-fit=cover` is carried over deliberately: without it every
/// `env(safe-area-inset-*)` resolves to zero and every sticky footer in the
/// product loses its safe-area padding.
///
/// Elements that implement their OWN pinch — the bracket, the family tree, the
/// media lightbox — are untouched. They set `touch-action` on themselves and
/// drive pointer events directly; this removes only the browser's
/// document-level gesture.
const String _zoomLock = r"""
(function () {
  if (window.__takeoneZoomLock) return;
  window.__takeoneZoomLock = true;

  var WANT = 'width=device-width, initial-scale=1, maximum-scale=1, ' +
             'user-scalable=no, viewport-fit=cover';

  function lock() {
    var head = document.head || document.documentElement;
    if (!head) return;

    var meta = document.querySelector('meta[name="viewport"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'viewport');
      head.appendChild(meta);
    }
    if (meta.getAttribute('content') !== WANT) {
      meta.setAttribute('content', WANT);
    }
  }

  lock();
  document.addEventListener('DOMContentLoaded', lock);

  // The mobile shell swaps content over AJAX and can rewrite the head with it,
  // so the tag is kept honest rather than set once. Scoped to childList on the
  // head: this must not fire on every mutation in the body.
  try {
    new MutationObserver(lock).observe(document.documentElement, {childList: true});
    if (document.head) {
      new MutationObserver(lock).observe(document.head, {childList: true});
    }
  } catch (e) {}

  // Double-tap is the other document-level zoom and the viewport tag does not
  // cover it. A descendant setting its own touch-action still wins.
  var style = document.createElement('style');
  style.textContent = 'html{touch-action:manipulation}';
  (document.head || document.documentElement).appendChild(style);
})();
""";

const String _bridge = r'''
(function () {
  if (window.Capacitor) return;

  var pending = {};
  var next = 0;

  function call(method, args) {
    return new Promise(function (resolve, reject) {
      var id = 'c' + (++next);
      pending[id] = { resolve: resolve, reject: reject };
      try {
        TakeOneNative.postMessage(JSON.stringify({ id: id, method: method, args: args || {} }));
      } catch (e) {
        delete pending[id];
        reject(e);
      }
    });
  }

  window.__takeoneResolve = function (payload) {
    var p = pending[payload.id];
    if (!p) return;
    delete pending[payload.id];
    if (payload.error) p.reject(new Error(payload.error));
    else p.resolve(payload.value);
  };

  window.Capacitor = {
    isNativePlatform: function () { return true; },
    getPlatform: function () { return 'android'; },
    Plugins: {
      App: { getInfo: function () { return call('appInfo'); } },
      MqttPush: {
        start: function (o) { return call('mqttStart', o); },
        stop: function () { return call('mqttStop'); },
        requestBatteryExemption: function () { return call('batteryExemption'); },
        downloadAndInstall: function (o) { return call('downloadAndInstall', o); },
      },
    },
  };
})();
''';

/// Shown only when the PAGE failed — never for a stray asset.
class _Offline extends StatelessWidget {
  const _Offline({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off_rounded, size: 48, color: Colors.white54),
            const SizedBox(height: 16),
            const Text(
              'No connection',
              style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            const Text(
              'TAKEONE needs the internet to show your clubs, events and messages.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white60, fontSize: 14),
            ),
            const SizedBox(height: 24),
            FilledButton(onPressed: onRetry, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }
}
