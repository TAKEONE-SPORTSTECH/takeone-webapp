import 'package:flutter/material.dart';

/// The hall's palette, taken from the web screens so a television running this
/// app and a Pi running the browser look like the same product on one wall.
class Hall {
  const Hall._();

  static const Color ink = Color(0xFF050507);
  static const Color panel = Color(0xFF16161F);
  static const Color paper = Color(0xFFE8E6E0);
  static const Color gold = Color(0xFFFDC436);
  static const Color live = Color(0xFF4ED37B);

  /// 'Barlow Condensed' / 'Anton' if the optional font assets are bundled;
  /// otherwise Flutter falls back to the platform face and the layout holds.
  static const String display = 'Anton';
  static const String body = 'Barlow Condensed';

  static TextStyle eyebrow() => const TextStyle(
        fontFamily: body,
        fontWeight: FontWeight.w600,
        fontSize: 34,
        letterSpacing: 14,
        color: gold,
      );

  static TextStyle codeLabel() => TextStyle(
        fontFamily: body,
        fontWeight: FontWeight.w600,
        fontSize: 26,
        letterSpacing: 8,
        color: paper.withValues(alpha: 0.55),
      );

  /// The fallback somebody reads aloud across a hall: the brightest text on the
  /// screen after the QR itself.
  static TextStyle code() => const TextStyle(
        fontFamily: display,
        fontWeight: FontWeight.w400,
        fontSize: 112,
        height: 1,
        letterSpacing: 18,
        color: Color(0xFFFFFDF5),
        shadows: [Shadow(color: Color(0x73FDC436), blurRadius: 60)],
      );

  static TextStyle hint() => TextStyle(
        fontFamily: body,
        fontWeight: FontWeight.w600,
        fontSize: 32,
        letterSpacing: 2,
        color: paper.withValues(alpha: 0.6),
      );

  static TextStyle foot() => TextStyle(
        fontFamily: body,
        fontWeight: FontWeight.w600,
        fontSize: 22,
        letterSpacing: 4.4,
        color: paper.withValues(alpha: 0.45),
      );

  static TextStyle aside() => TextStyle(
        fontFamily: body,
        fontWeight: FontWeight.w600,
        fontSize: 20,
        letterSpacing: 2.4,
        color: paper.withValues(alpha: 0.35),
      );

  /// The same radial the web stage uses, so the band behind the QR matches.
  static const BoxDecoration stage = BoxDecoration(
    gradient: RadialGradient(
      center: Alignment(0, -0.4),
      radius: 1.1,
      colors: [panel, ink],
      stops: [0, 0.7],
    ),
  );
}
