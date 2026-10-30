import 'dart:math' as math;
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';

/// One part of the VS screen, drawn by the phone (VsScreen.layers): where it
/// sits on the 1920×1080 canvas and its picture.
class VsLayer {
  VsLayer(this.name, this.rect, this.png);

  final String name;
  final Rect rect;
  final Uint8List png;

  static List<VsLayer> fromNative(List<dynamic> raw) => [
        for (final m in raw.whereType<Map>())
          VsLayer(
            m['name'] as String,
            Rect.fromLTRB(
              (m['rect'] as List)[0] as double,
              (m['rect'] as List)[1] as double,
              (m['rect'] as List)[2] as double,
              (m['rect'] as List)[3] as double,
            ),
            m['png'] as Uint8List,
          ),
      ];
}

/// The VS screen's intro in the player, animated exactly as a sent video's
/// (VsAnim.kt — the bout page's own keyframes): panels slide in, the top
/// lines drop in, the fighters' details rise, the VS slams down, a white
/// flash, then the VS pulses. The timing table is VsAnim's; change both.
class VsIntro extends StatefulWidget {
  const VsIntro({super.key, required this.layers});

  final List<VsLayer> layers;

  @override
  State<VsIntro> createState() => _VsIntroState();
}

class _VsIntroState extends State<VsIntro> with SingleTickerProviderStateMixin {
  late final Ticker _ticker;
  double _t = 0;

  @override
  void initState() {
    super.initState();
    _ticker = createTicker((elapsed) => setState(() => _t = elapsed.inMicroseconds / 1e6))..start();
  }

  @override
  void dispose() {
    _ticker.dispose();
    super.dispose();
  }

  /// cubic-bezier(.22, 1, .36, 1), solved for y at x = p.
  static double ease(double p) {
    final x = p.clamp(0.0, 1.0);
    var lo = 0.0, hi = 1.0, t = x;
    for (var i = 0; i < 24; i++) {
      final bx = 3 * (1 - t) * (1 - t) * t * 0.22 + 3 * (1 - t) * t * t * 0.36 + t * t * t;
      if (bx < x) {
        lo = t;
      } else {
        hi = t;
      }
      t = (lo + hi) / 2;
    }

    return 3 * (1 - t) * (1 - t) * t + 3 * (1 - t) * t * t + t * t * t;
  }

  static double _p(double t, double delay, double length) => ((t - delay) / length).clamp(0.0, 1.0);

  @override
  Widget build(BuildContext context) {
    final t = _t;
    final children = <Widget>[];
    // The canvas: the page's 1920×1080, or 1080×1920 for an upright video —
    // where the panels come in from the top and the bottom instead.
    final canvas = widget.layers.firstWhere((l) => l.name == 'base', orElse: () => widget.layers.first).rect;
    final upright = canvas.height > canvas.width;

    for (final l in widget.layers) {
      var dx = 0.0, dy = 0.0, alpha = 1.0, scale = 1.0, rotate = 0.0;

      switch (l.name) {
        case 'red':
          final off = 1 - ease(_p(t, 0, 0.9));
          if (upright) {
            dy = -1.05 * l.rect.height * off;
          } else {
            dx = -1.05 * l.rect.width * off;
          }
        case 'blue':
          final off = 1 - ease(_p(t, 0, 0.9));
          if (upright) {
            dy = 1.05 * l.rect.height * off;
          } else {
            dx = 1.05 * l.rect.width * off;
          }
        case 'top':
          final e = ease(_p(t, 0.5, 0.8));
          alpha = e;
          dy = -32.4 * (1 - e);
        case 'infoRed' || 'infoBlue' || 'chips':
          final delay = l.name == 'infoRed' ? 0.7 : (l.name == 'infoBlue' ? 0.85 : 1.3);
          final e = ease(_p(t, delay, 0.8));
          alpha = e;
          dy = 43.2 * (1 - e);
        case 'vs':
          final p = ease(_p(t, 1.1, 0.7));
          double s, r, a;
          if (p < 0.6) {
            final q = p / 0.6;
            s = 3.4 + (0.92 - 3.4) * q;
            r = -6 + 7 * q;
            a = q;
          } else if (p < 0.8) {
            final q = (p - 0.6) / 0.2;
            s = 0.92 + (1.06 - 0.92) * q;
            r = 1 - 0.5 * q;
            a = 1;
          } else {
            final q = (p - 0.8) / 0.2;
            s = 1.06 + (1 - 1.06) * q;
            r = 0.5 - 0.5 * q;
            a = 1;
          }
          scale = s * (1 + 0.045 * (0.5 - 0.5 * math.cos(2 * math.pi * t / 2.4)));
          rotate = r;
          alpha = t < 1.1 ? 0 : a;
      }

      if (alpha <= 0.001) continue;

      children.add(Positioned.fromRect(
        rect: l.rect.shift(Offset(dx, dy)),
        child: Opacity(
          opacity: alpha.clamp(0.0, 1.0),
          child: Transform.rotate(
            angle: rotate * math.pi / 180,
            child: Transform.scale(
              scale: scale,
              child: Image.memory(l.png, fit: BoxFit.fill, gaplessPlayback: true, filterQuality: FilterQuality.medium),
            ),
          ),
        ),
      ));
    }

    // The flash as the VS lands.
    final f = _p(t, 1.5, 0.9);
    if (f > 0 && f < 1) {
      final e = ease(f);
      final a = e < 0.12 ? e / 0.12 * 0.85 : 0.85 * (1 - (e - 0.12) / 0.88);
      children.add(Positioned.fill(child: ColoredBox(color: Colors.white.withValues(alpha: a.clamp(0.0, 1.0)))));
    }

    return ColoredBox(
      color: Colors.black,
      child: FittedBox(
        fit: BoxFit.contain,
        child: ClipRect(child: SizedBox(width: canvas.width, height: canvas.height, child: Stack(children: children))),
      ),
    );
  }
}
