/// Where this television is pointed, and what it is allowed to render.
///
/// Both are compile-time, not runtime: a screen bolted to a wall has no
/// keyboard, so there is no settings screen to get wrong — you flash the box
/// with the URL it should live at.
///
///   flutter build apk --release \
///     --dart-define=TAKEONE_BASE_URL=https://takeone.bh
library;

class Config {
  const Config._();

  /// Defaults to stage so a freshly built APK is never accidentally pointed at
  /// production by omission.
  static const String baseUrlRaw = String.fromEnvironment(
    'TAKEONE_BASE_URL',
    defaultValue: 'https://stage.takeone.bh',
  );

  static Uri get base => Uri.parse(baseUrlRaw);

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

    var absolute = parsed.hasScheme ? parsed : base.resolveUri(parsed);

    // A code printed on one TAKEONE host has to work when scanned on the other.
    //
    // QR codes bake in whichever host generated them: a claim code produced on
    // takeone.bh is scanned by a screen flashed for stage during a rehearsal,
    // and the reverse while testing. Honouring the host literally would send a
    // stage screen to production mid-rehearsal — so for OUR hosts the PATH is
    // kept and the host is replaced with the one this build serves.
    if (!trusts(absolute) && _sibling(absolute)) {
      absolute = base.replace(
        path: absolute.path,
        query: absolute.hasQuery ? absolute.query : null,
        fragment: absolute.hasFragment ? absolute.fragment : null,
      );
    }

    return trusts(absolute) ? absolute : null;
  }

  /// Another address for the same product — takeone.bh and stage.takeone.bh.
  ///
  /// Compared on the registrable domain, on a dot boundary, so `nottakeone.bh`
  /// and `takeone.bh.evil.com` are both refused.
  static bool _sibling(Uri url) {
    if (!(url.scheme == 'http' || url.scheme == 'https')) return false;

    String root(String host) {
      final parts = host.toLowerCase().split('.');

      return parts.length <= 2 ? host.toLowerCase() : parts.sublist(parts.length - 2).join('.');
    }

    return root(url.host) == root(base.host);
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
