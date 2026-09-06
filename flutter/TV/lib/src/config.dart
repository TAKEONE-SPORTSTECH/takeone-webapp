/// Where this television is pointed, and what it is allowed to render.
///
/// The host it is FLASHED with is compile-time: a screen bolted to a wall has no
/// keyboard, so there is no settings screen to get wrong.
///
///   flutter build apk --release \
///     --dart-define=TAKEONE_BASE_URL=https://takeone.bh
///
/// The host it is WORKING against can move, once, to a sibling — see [adopt].
library;

import 'package:shared_preferences/shared_preferences.dart';

class Config {
  const Config._();

  /// Defaults to stage so a freshly built APK is never accidentally pointed at
  /// production by omission.
  static const String baseUrlRaw = String.fromEnvironment(
    'TAKEONE_BASE_URL',
    defaultValue: 'https://stage.takeone.bh',
  );

  /// The host this build was flashed with. Never changes, and is the anchor
  /// every adoption is checked against.
  static final Uri built = Uri.parse(baseUrlRaw);

  static Uri? _adopted;

  /// The host this device is actually working against right now.
  static Uri get base => _adopted ?? built;

  static const String _hostKey = 'takeone.host';

  /// Work against the host a scanned code came from.
  ///
  /// A pairing code is six characters in ONE host's database. Re-pointing it at
  /// the host this box was flashed with — which is what this used to do — meant
  /// the code was accepted and then never found, because the row lives on the
  /// other server. So the code decides the environment: scan production's QR on
  /// a stage-flashed camera and the camera works against production.
  ///
  /// The move is only ever between OUR OWN hosts. It is checked against [built],
  /// not against the current base, so no chain of adoptions can walk this device
  /// off the registrable domain it was flashed with.
  ///
  /// Remembered, because the token a camera enrols with belongs to the host that
  /// issued it: after a power cut the device has to come back to the same server
  /// or it authenticates against the wrong one.
  static void adopt(Uri url) {
    if (!adoptable(url)) return;

    final origin = _origin(url);
    if (origin == base) return;

    _adopted = origin;

    // Fire-and-forget: the switch has to be visible to the very next call, and
    // a failed write costs a re-scan rather than a wrong host.
    SharedPreferences.getInstance()
        .then((prefs) => prefs.setString(_hostKey, origin.toString()))
        .catchError((_) => false);
  }

  /// Restores the adopted host. Call once at startup, before anything reads
  /// [base] — a camera that comes back on the wrong host has a token the server
  /// will refuse.
  static Future<void> restore() async {
    try {
      final saved = (await SharedPreferences.getInstance()).getString(_hostKey);
      if (saved == null) return;

      final url = Uri.tryParse(saved);
      if (url != null && adoptable(url)) _adopted = _origin(url);
    } catch (_) {
      // No stored preference is the normal first-run case, and an unreadable one
      // is not worth failing to start over: the build's own host still works.
    }
  }

  /// The servers this device may be pointed at, built host first.
  ///
  /// takeone.bh and stage.takeone.bh are separate installations with separate
  /// databases, and a pairing code is a row in ONE of them — so a camera can
  /// only film an event on the server it enrolled with. That is not a bug to be
  /// papered over; it is what "two environments" means. What WAS a bug is that
  /// the camera had no way to move: the mechanism to adopt a sibling host has
  /// existed here since the shells were written, and the camera never called it,
  /// so a phone flashed for one server could only reach the other by being
  /// reinstalled — on a competition morning, over hall wifi, from an APK
  /// somebody had to find.
  ///
  /// Bounded by [adoptable], so this can only ever offer our own hosts.
  static List<Uri> get servers {
    final parts = built.host.toLowerCase().split('.');
    final root = parts.length <= 2 ? built.host.toLowerCase() : parts.sublist(parts.length - 2).join('.');

    final hosts = <String>{built.host.toLowerCase(), root, 'stage.$root'};

    return hosts
        .map((h) => Uri(scheme: built.scheme, host: h))
        .where(adoptable)
        .toList()
      ..sort((a, b) => a.host == built.host ? -1 : (b.host == built.host ? 1 : a.host.compareTo(b.host)));
  }

  /// Point this device at one of [servers]. Persisted, like every adoption.
  static Future<void> use(Uri origin) async {
    if (!adoptable(origin)) return;

    _adopted = _origin(origin);

    try {
      await (await SharedPreferences.getInstance()).setString(_hostKey, _origin(origin).toString());
    } catch (_) {}
  }

  /// Back to the host this box was flashed with.
  static Future<void> forget() async {
    _adopted = null;

    try {
      await (await SharedPreferences.getInstance()).remove(_hostKey);
    } catch (_) {}
  }

  /// Scheme, host and port — never a path. What is remembered is an ORIGIN, so
  /// a scanned path can never become part of where this device lives.
  static Uri _origin(Uri url) =>
      Uri(scheme: url.scheme, host: url.host, port: url.hasPort ? url.port : null);

  /// The one address a screen is pointed at. Everything else — enrolling, the
  /// pairing code, the QR, noticing it has been claimed, and going to the board
  /// — is the server's job, exactly as it is for a Pi running a browser.
  static Uri get screen => base.resolve('/screen');

  /// Where a screen is sent to start over as a different screen.
  static Uri get anotherScreen => base.resolve('/screen?new');

  /// Where the MEMBER APP opens. Not `/screen` — that is hall furniture. The
  /// app is a person's own way in, so it lands where the mobile web lands and
  /// lets the server decide whether that means the feed or the sign-in page.
  static Uri get appHome => base.resolve('/');

  /// Resolves what the app is handed — which is frequently ROOT-RELATIVE.
  ///
  /// `pending_screens.destination` holds a path (`/karate/court/<token>`), not
  /// an absolute URL, and so do most links inside the boards. Treating those as
  /// untrusted because they have no host is how the first build of this app sat
  /// on its pairing code forever after being claimed.
  static Uri? resolve(String raw) {
    if (raw.isEmpty) return null;

    final parsed = Uri.tryParse(raw);
    if (parsed == null) return null;

    final absolute = parsed.hasScheme ? parsed : base.resolveUri(parsed);

    // A code printed on one TAKEONE host has to work when scanned on the other.
    //
    // QR codes bake in whichever host generated them, and the pairing code they
    // carry exists in that host's database and nowhere else. So the code is
    // followed to the host that issued it, and this device moves with it.
    if (!trusts(absolute) && adoptable(absolute)) {
      adopt(absolute);
    }

    return trusts(absolute) ? absolute : null;
  }

  /// Another address for the same product — takeone.bh and stage.takeone.bh.
  ///
  /// Compared on the registrable domain, on a dot boundary, so `nottakeone.bh`
  /// and `takeone.bh.evil.com` are both refused. Anchored on [built]: what this
  /// device may be walked to is fixed when it is flashed, not by where it has
  /// already been walked.
  static bool adoptable(Uri url) {
    if (!(url.scheme == 'http' || url.scheme == 'https')) return false;

    String root(String host) {
      final parts = host.toLowerCase().split('.');

      return parts.length <= 2 ? host.toLowerCase() : parts.sublist(parts.length - 2).join('.');
    }

    return root(url.host) == root(built.host);
  }

  /// A wall screen that can be walked to an arbitrary origin is a billboard, so
  /// this app renders exactly one host: the one it was built for.
  static bool trusts(Uri url) =>
      url.scheme == base.scheme && url.host == base.host && url.port == base.port;

  /// Where the app is willing to NAVIGATE, which is wider than what it trusts
  /// as its own origin.
  ///
  /// A member taps a club's Instagram, a payment receipt, a `tel:` number. Those
  /// are legitimate and must leave for the system browser rather than opening
  /// inside a shell that looks like TAKEONE — a login form rendered in our
  /// chrome is a phishing surface even when the link is honest.
  ///
  /// So: our own host stays inside; everything else is handed to Android.
  static bool staysInside(Uri url) {
    if (!(url.scheme == 'http' || url.scheme == 'https')) return false;

    // Subdomains of the build's host count as ours — stage and production are
    // the same product, and a QR printed on one is scanned on the other.
    final host = url.host.toLowerCase();
    final own = base.host.toLowerCase();

    return host == own || host.endsWith('.$own') || _sameSite(host, own);
  }

  /// takeone.bh and stage.takeone.bh are the same site seen from two addresses.
  static bool _sameSite(String a, String b) {
    String root(String h) {
      final parts = h.split('.');
      return parts.length <= 2 ? h : parts.sublist(parts.length - 2).join('.');
    }

    return root(a) == root(b);
  }

  /// The camera's four endpoints. It has no page: it enrols, asks what it is,
  /// beats, and files what it recorded. See CameraController on the server.
  static Uri get cameraEnroll => base.resolve('/camera/enroll');
  static Uri cameraConfig(String token) => base.resolve('/camera/$token/config');
  static Uri cameraTelemetry(String token) => base.resolve('/camera/$token/telemetry');
  static Uri cameraClip(String token) => base.resolve('/camera/$token/clip');
  static Uri cameraClipDelete(String token, int clip) => base.resolve('/camera/$token/clip/$clip');
  static Uri cameraClipUpload(String token, int clip) => base.resolve('/camera/$token/clip/$clip/upload');

  /// True for the pairing flow itself — the waiting room, its status poll, the
  /// claim form. Anything else on this host is a board, and a board is worth
  /// remembering so the screen comes back as itself after a power cut.
  static bool isPairing(Uri url) => url.path == '/screen' || url.path.startsWith('/screen/');
}
