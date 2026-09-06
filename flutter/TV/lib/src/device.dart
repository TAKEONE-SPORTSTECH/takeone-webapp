/// Which kind of machine this build is for.
///
/// One codebase, two APKs. The differences are small but real, and they pull in
/// opposite directions — which is exactly why they are a build-time decision
/// rather than a runtime guess:
///
///   * A TELEVISION is bolted to a wall in one orientation and shows a board
///     that is authored at 1920x1080. Nothing on it should ever scroll, and a
///     scrollbar down the side of a wall display is a defect whatever caused it.
///   * A TABLET is picked up and handed to an official. It is the scoring table
///     — a touch surface, with a soft keyboard and system bars that come back —
///     so it must be given a viewport equal to what is VISIBLE, and must not have
///     scrolling taken away from it. Both are landscape: the screens are 16:9, and
///     a portrait tablet renders the console as an unusable strip.
///
///   * A CAMERA is a phone clamped beside the mat. It renders no board at all —
///     it films. Nobody presses record on it: the scoring table calls hajime and
///     every camera on that mat starts, the bout is filed and they all stop. It
///     is the one variant that is not a WebView shell, because a WebView cannot
///     be trusted to hold a video file for eight hours.
///
///   * The MEMBER APP is a phone in somebody's pocket. It is the one variant
///     that is NOT a kiosk: it keeps its status bar, its back button, its
///     rotation and its scrolling, because it is an ordinary app rather than a
///     machine bolted to a wall. It renders the same mobile web the browser
///     does — per CLAUDE.md, "the mobile web experience IS the Android app" —
///     and adds the two things a browser tab cannot do: notifications that
///     arrive when the app is closed, and the camera/upload plumbing a WebView
///     needs to be granted explicitly.
///
///   flutter build apk --release --dart-define=TAKEONE_DEVICE=tab
library;

enum DeviceKind { tv, tab, cam, app }

class Device {
  const Device._();

  static const String _raw = String.fromEnvironment(
    'TAKEONE_DEVICE',
    defaultValue: 'tv',
  );

  /// Unknown values fall back to `tv`: it is the stricter of the two, and a
  /// mistyped flag should not quietly hand a wall screen a rotating viewport.
  static DeviceKind get kind => switch (_raw) {
    'tab' => DeviceKind.tab,
    'cam' => DeviceKind.cam,
    'app' => DeviceKind.app,
    _ => DeviceKind.tv,
  };

  static bool get isTv => kind == DeviceKind.tv;
  static bool get isTablet => kind == DeviceKind.tab;
  static bool get isCamera => kind == DeviceKind.cam;

  /// The member's phone app. Deliberately the only variant that is not a kiosk.
  static bool get isApp => kind == DeviceKind.app;

  /// Everything except the app is a machine placed in a hall and left there.
  static bool get isKiosk => !isApp;

  /// What the boot splash calls this machine, so a screen that cannot reach the
  /// server still says which build is on it.
  static String get label => switch (kind) {
    DeviceKind.tab => 'TABLET',
    DeviceKind.cam => 'CAMERA',
    DeviceKind.app => 'TAKEONE',
    DeviceKind.tv => 'SCREEN',
  };
}
