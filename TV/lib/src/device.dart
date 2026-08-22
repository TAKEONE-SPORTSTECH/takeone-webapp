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
///   flutter build apk --release --dart-define=TAKEONE_DEVICE=tab
library;

enum DeviceKind { tv, tab }

class Device {
  const Device._();

  static const String _raw = String.fromEnvironment(
    'TAKEONE_DEVICE',
    defaultValue: 'tv',
  );

  /// Unknown values fall back to `tv`: it is the stricter of the two, and a
  /// mistyped flag should not quietly hand a wall screen a rotating viewport.
  static DeviceKind get kind => _raw == 'tab' ? DeviceKind.tab : DeviceKind.tv;

  static bool get isTv => kind == DeviceKind.tv;
  static bool get isTablet => kind == DeviceKind.tab;

  /// What the boot splash calls this machine, so a screen that cannot reach the
  /// server still says which build is on it.
  static String get label => isTablet ? 'TABLET' : 'SCREEN';
}
