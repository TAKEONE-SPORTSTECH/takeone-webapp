import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'tally.dart';

/// Where the score bar will sit on this camera's picture — drawn over the
/// live preview while the tripod is being set up, so the operator can keep
/// the fight clear of everything a sent video lays over it.
///
/// The boxes are the bar's real geometry (ScoreBar.kt: a 1150-unit-wide
/// design scaled to the frame): the bottom group at the operator's chosen
/// height — match strip, bar and score line — the event pill top left and
/// the live-scoring list top right, with the area left clear for the fight
/// between them. It is laid over the FRAME, not the screen: the preview fills
/// the screen by cropping (cover), so the guide is placed on the frame as
/// the video actually records it, and an edge the screen crops is cropped
/// here too. Nothing of it is ever recorded.
class FramingGuide extends CustomPainter {
  FramingGuide({required this.videoWidth, required this.videoHeight, required this.lift, this.boxes = true, this.contain = false});

  /// The preview shows the WHOLE frame (contain) rather than filling the
  /// screen (cover). With the guide on it always does: a phone screen is wider
  /// than 16:9, so cover crops the top and bottom of the picture — exactly
  /// where the score bar sits — and the operator would frame against a bar
  /// they cannot fully see.
  final bool contain;

  /// Draw the covered areas as boxes. Off when the real bar is shown over the
  /// preview instead — then only the gold clear area is outlined.
  final bool boxes;

  /// The frame as a cover-fit preview lays it out in `size` — where an image
  /// painted at the frame's own shape must go to sit exactly on the picture.
  static Rect frameIn(Size size, int videoWidth, int videoHeight, {bool contain = false}) {
    final (vw, vh) = frameShape(size, videoWidth, videoHeight);
    final fit = contain ? math.min(size.width / vw, size.height / vh) : math.max(size.width / vw, size.height / vh);

    return Rect.fromLTWH((size.width - vw * fit) / 2, (size.height - vh * fit) / 2, vw * fit, vh * fit);
  }

  final int videoWidth;
  final int videoHeight;

  /// The recorded frame's shape as the screen shows it. The camera reports
  /// its sensor's size (landscape) whichever way the phone is held; held
  /// upright, the picture — and the recording — is upright, so the sides swap.
  static (double, double) frameShape(Size screen, int videoWidth, int videoHeight) {
    var vw = videoWidth > 0 ? videoWidth.toDouble() : 16.0;
    var vh = videoHeight > 0 ? videoHeight.toDouble() : 9.0;
    final upright = screen.height > screen.width;
    if (upright != (vh > vw)) {
      final t = vw;
      vw = vh;
      vh = t;
    }

    return (vw, vh);
  }

  /// Design units from the frame's bottom edge to the score line (ClipVault.lift).
  final double lift;

  @override
  void paint(Canvas canvas, Size size) {
    // The frame as the cover-fit preview lays it out on this screen.
    final frame = frameIn(size, videoWidth, videoHeight, contain: contain);
    final fw = frame.width;
    final fh = frame.height;

    // Design units → screen pixels, as the bar scales them (ScoreBar.dw:
    // 1150 wide for a landscape frame, 900 for an upright one).
    final dw = fh > fw ? 900.0 : 1150.0;
    final k = fw / dw;
    final dh = fh / k;
    Rect d(double l, double t, double r, double b) =>
        Rect.fromLTRB(frame.left + l * k, frame.top + t * k, frame.left + r * k, frame.top + b * k);

    canvas.save();
    canvas.clipRect(Offset.zero & size);

    final groupTop = dh - (lift + 13 + 84 + 28);
    final covered = [
      (d(26, groupTop, dw - 26, dh - lift), 'SCORE BAR'),
      (d(22, 16, math.min(600, dw - 220), 74), 'EVENT'),
      (d(dw - 26 - 190, 30, dw - 26, 130), 'LIVE SCORING'),
    ];

    final shade = Paint()..color = const Color(0x40000000);
    final edge = Paint()
      ..color = Colors.white.withValues(alpha: 0.55)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;

    for (final (rect, label) in boxes ? covered : const <(Rect, String)>[]) {
      canvas.drawRect(rect, shade);
      _dashed(canvas, rect, edge);
      _label(canvas, label, rect, Colors.white.withValues(alpha: 0.85));
    }

    // The fight's space: everything between the top boxes and the bar.
    final clear = d(26, 84, dw - 26, groupTop - 8);
    if (clear.height > 20) {
      _dashed(canvas, clear, Paint()
        ..color = Tally.gold.withValues(alpha: 0.9)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.6);
      _label(canvas, 'KEEP THE FIGHT IN HERE', clear, Tally.goldLight, top: true);
    }

    canvas.restore();
  }

  void _dashed(Canvas canvas, Rect r, Paint p) {
    const dash = 7.0;
    const gap = 5.0;
    void line(Offset a, Offset b) {
      final len = (b - a).distance;
      final dir = (b - a) / len;
      for (var s = 0.0; s < len; s += dash + gap) {
        canvas.drawLine(a + dir * s, a + dir * math.min(s + dash, len), p);
      }
    }

    line(r.topLeft, r.topRight);
    line(r.topRight, r.bottomRight);
    line(r.bottomRight, r.bottomLeft);
    line(r.bottomLeft, r.topLeft);
  }

  void _label(Canvas canvas, String text, Rect r, Color colour, {bool top = false}) {
    final tp = TextPainter(
      text: TextSpan(text: text, style: Tally.label(10, color: colour, tracking: 0.2, weight: FontWeight.w700)),
      textDirection: TextDirection.ltr,
    )..layout(maxWidth: r.width);

    final at = top ? Offset(r.center.dx - tp.width / 2, r.top + 6) : r.center - Offset(tp.width / 2, tp.height / 2);
    tp.paint(canvas, at);
  }

  @override
  bool shouldRepaint(FramingGuide old) =>
      old.videoWidth != videoWidth || old.videoHeight != videoHeight || old.lift != lift || old.boxes != boxes || old.contain != contain;
}
