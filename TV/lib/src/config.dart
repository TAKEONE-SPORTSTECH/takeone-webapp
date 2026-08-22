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

    return trusts(absolute) ? absolute : null;
  }

  /// A wall screen that can be walked to an arbitrary origin is a billboard, so
  /// this app renders exactly one host: the one it was built for.
  static bool trusts(Uri url) =>
      url.scheme == base.scheme && url.host == base.host && url.port == base.port;

  /// True for the pairing flow itself — the waiting room, its status poll, the
  /// claim form. Anything else on this host is a board, and a board is worth
  /// remembering so the screen comes back as itself after a power cut.
  static bool isPairing(Uri url) => url.path == '/screen' || url.path.startsWith('/screen/');
}
