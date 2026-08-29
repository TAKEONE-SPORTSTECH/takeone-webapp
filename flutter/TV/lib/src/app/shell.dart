import 'dart:async';
import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

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
class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  WebViewController? _controller;
  bool _offline = false;

  /// The document currently loading. Android reports an HTTP error for every
  /// resource on a page; only the one that IS the page should show a failure.
  String? _document;

  @override
  void initState() {
    super.initState();

    // An ordinary app, not a kiosk: give the system bars back.
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    _boot();
  }

  void _boot() {
    final controller = WebViewController.fromPlatformCreationParams(
      const PlatformWebViewControllerCreationParams(),
    )
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Hall.ink)
      ..addJavaScriptChannel('TakeOneNative', onMessageReceived: _onBridgeCall)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            final url = Uri.tryParse(request.url);
            if (url == null) return NavigationDecision.prevent;

            // Ours stays in the app. Anything else — a club's social link, a
            // tel: or mailto:, a receipt on somebody else's domain — is handed
            // to Android so it opens in the browser or dialler it belongs to.
            if (Config.staysInside(url)) return NavigationDecision.navigate;

            unawaited(_openExternally(url));

            return NavigationDecision.prevent;
          },
          onPageStarted: (url) {
            _document = url;
            if (_offline) setState(() => _offline = false);

            // The bridge the mobile web already expects.
            //
            // Three partials — app-update, push-register and the mobile login —
            // call `window.Capacitor.Plugins`, because until now this app WAS a
            // Capacitor shell. Rather than edit the web and strand every phone
            // still running the old build, this shell answers to the SAME
            // shape: nothing on the server changes, and both apps keep working
            // through the changeover.
            //
            // Injected as the document starts, which is before the page's own
            // scripts evaluate, because app-update.blade.php reads
            // window.Capacitor as soon as it is parsed.
            unawaited(_controller?.runJavaScript(_bridge) ?? Future.value());
          },
          onWebResourceError: (error) {
            // Only a failure of the PAGE itself is worth a screen. A dead image
            // or a slow analytics beacon must not blank the app.
            if (error.url != null && error.url != _document) return;
            if (!mounted) return;
            setState(() => _offline = true);
          },
        ),
      );

    // Everything below is Android-only surface that the plain webview_flutter
    // controller does not expose. It is what a Capacitor bridge was providing.
    final platform = controller.platform;
    if (platform is AndroidWebViewController) {
      // Notification chimes ring the moment they arrive. A browser makes the
      // user tap first; inside our own shell we opt out of that policy.
      platform.setMediaPlaybackRequiresUserGesture(false);

      // THE upload path. Without this a WebView silently ignores every
      // <input type="file">, and the member profile cropper, the payment proof
      // and the club gallery all appear to do nothing when tapped.
      unawaited(platform.setOnShowFileSelector(_pickFiles));

      // Camera for the QR scanner, microphone, and anything else the page asks
      // for. Granted per request, at the moment the page asks — never a blanket
      // grant at launch.
      unawaited(
        platform.setOnPlatformPermissionRequest((request) => request.grant()),
      );

      unawaited(platform.setGeolocationPermissionsPromptCallbacks(
        onShowPrompt: (request) async =>
            const GeolocationPermissionsResponse(allow: true, retain: false),
      ));

    }

    controller.loadRequest(Config.appHome);
    setState(() => _controller = controller);
  }

  /// Hand a file picker to the page. A WebView cannot open one itself, and
  /// without this every `<input type="file">` on the site does nothing at all:
  /// no profile picture, no payment proof, no club gallery upload.
  ///
  /// The page tells us what it will accept and whether it wants more than one
  /// file; both are honoured rather than always opening a generic picker.
  Future<List<String>> _pickFiles(FileSelectorParams params) async {
    try {
      final wantsImage = params.acceptTypes
          .any((t) => t.startsWith('image/') || t == '.jpg' || t == '.png');

      final type = wantsImage ? FileType.image : FileType.any;

      final picked = params.mode == FileSelectorMode.openMultiple
          ? await FilePicker.pickFiles(type: type)
          : [
              if (await FilePicker.pickFile(type: type) case final one?) one,
            ];

      // The WebView wants file:// URIs back. Anything the picker could not give
      // a real path for is dropped rather than handed over broken.
      return picked
          .map((f) => f.path)
          .whereType<String>()
          .map((path) => Uri.file(path).toString())
          .toList(growable: false);
    } catch (_) {
      // A cancelled or failed pick must return empty, never throw: an exception
      // here leaves the page's file input wedged until the app restarts.
      return const <String>[];
    }
  }

  /// Calls arriving from the page's `window.Capacitor` shim.
  void _onBridgeCall(JavaScriptMessage message) {
    Map<String, dynamic> call;
    try {
      call = jsonDecode(message.message) as Map<String, dynamic>;
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
    await _controller?.runJavaScript('window.__takeoneResolve($payload);');
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
    final controller = _controller;

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
          child: controller == null
              ? const Center(child: CircularProgressIndicator())
              : _offline
                  ? _Offline(onRetry: () {
                      setState(() => _offline = false);
                      controller.loadRequest(Config.appHome);
                    })
                  : WebViewWidget(controller: controller),
        ),
      ),
    );
  }
}

/// Shown only when the PAGE failed — never for a stray asset.
/// The `window.Capacitor` shape the mobile web already calls, backed by the
/// Flutter channel instead of a Capacitor bridge. Injected before the page's own
/// scripts run, so `app-update.blade.php` sees it on first evaluation.
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
