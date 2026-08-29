import 'dart:math' as math;
import 'dart:ui';

import 'package:flutter/material.dart';

/// The camera screen's design system — the "Tally Frame" handoff, as specified.
///
/// ── The idea ───────────────────────────────────────────────────────────────
///
/// Borrowed from broadcast: a tally light tells the room which camera is live,
/// and it does it from the edge of the box rather than from a label somebody
/// has to walk up to and read. So the SCREEN'S OWN EDGE is the indicator here —
/// gold all the way round means on air, blinking red corner ticks mean
/// recording — and not one pixel of it sits over the mat. Everything else is
/// whisper-thin glass pushed into the corners.
///
/// ── Why the tokens live in one place ───────────────────────────────────────
///
/// Every value below is fixed by the handoff: colours, type sizes, tracking,
/// radii, the blur behind the glass. A chip that drifts two points between
/// states is the difference between equipment and an app, and this is equipment
/// — it is read at a distance, in a hall, by somebody who is watching a fight
/// and not a phone.
class Tally {
  const Tally._();

  // ── Colour ────────────────────────────────────────────────────────────────
  static const Color navyDeep = Color(0xFF0B132B);
  static const Color navyPanel = Color(0xFF0E1730);
  static const Color navyPrimary = Color(0xFF1E2C4F);

  /// On air, selection, every accent.
  static const Color gold = Color(0xFFD8B25F);
  static const Color goldLight = Color(0xFFEDCD85);

  /// Recording, and critical. Never a warning colour — see [amber].
  static const Color red = Color(0xFFE6455A);
  static const Color green = Color(0xFF4AC97E);
  static const Color greenText = Color(0xFFA9E6C3);
  static const Color amber = Color(0xFFE0A33C);
  static const Color amberText = Color(0xFFF4CD7E);

  static const Color text = Color(0xFFEEF1F6);
  static const Color textSecondary = Color(0xFFC6CEDE);
  static const Color textMuted = Color(0xFF8B96AD);
  static const Color textFaint = Color(0xFF6F7D9C);

  // ── Glass ─────────────────────────────────────────────────────────────────
  static const double glassBlur = 10;
  static const double drawerBlur = 14;
  static Color glassFill = navyDeep.withValues(alpha: 0.35);
  static Color drawerFill = navyDeep.withValues(alpha: 0.45);
  static Color glassEdge = Colors.white.withValues(alpha: 0.12);
  static Color drawerEdge = Colors.white.withValues(alpha: 0.14);

  static const String face = 'Poppins';

  /// A label: upper case, tracked out, small. The handoff's 10–12px band.
  static TextStyle label(double size, {Color? color, double tracking = 0.1, FontWeight weight = FontWeight.w600}) =>
      TextStyle(
        fontFamily: face,
        fontWeight: weight,
        fontSize: size,
        letterSpacing: size * tracking,
        color: color ?? textMuted,
        height: 1.2,
      );

  /// A number that changes while somebody is reading it — a timer, a bitrate.
  /// Tabular figures, so the digits do not shuffle sideways as they count.
  static TextStyle number(double size, {Color? color, FontWeight weight = FontWeight.w600}) => TextStyle(
        fontFamily: face,
        fontWeight: weight,
        fontSize: size,
        color: color ?? text,
        height: 1.2,
        fontFeatures: const [FontFeature.tabularFigures()],
      );

  static TextStyle body(double size, {Color? color, FontWeight weight = FontWeight.w400}) => TextStyle(
        fontFamily: face,
        fontWeight: weight,
        fontSize: size,
        color: color ?? textSecondary,
        height: 1.5,
      );
}

/// How the stream is doing, in one word.
///
/// A word rather than a number because the person holding this phone cannot act
/// on 1.8 Mbps — they can act on "the network is limiting you". The numbers are
/// still there underneath for whoever wants them.
enum Health {
  healthy('STREAM HEALTHY', Tally.green, Tally.greenText),
  degraded('DEGRADED', Tally.amber, Tally.amberText),
  reconnecting('RECONNECTING', Tally.amber, Tally.amberText),
  offline('OFFLINE', Tally.red, Tally.red);

  const Health(this.word, this.ring, this.ink);

  final String word;
  final Color ring;
  final Color ink;

  bool get calm => this == Health.healthy;
}

/* ══════════════════════════ Glass ════════════════════════════════════════ */

/// The one surface every overlay in this design is made of.
///
/// Blur behind, a translucent navy fill on top, a hairline white edge. Written
/// once so a chip and the drawer cannot drift apart: they differ only in the
/// two numbers the handoff gives them.
class Glass extends StatelessWidget {
  const Glass({
    super.key,
    required this.child,
    this.radius = 999,
    this.padding = const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
    this.blur = Tally.glassBlur,
    this.fill,
    this.edge,
  });

  final Widget child;
  final double radius;
  final EdgeInsets padding;
  final double blur;
  final Color? fill;
  final Color? edge;

  @override
  Widget build(BuildContext context) {
    final shape = BorderRadius.circular(radius);

    return ClipRRect(
      borderRadius: shape,
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            color: fill ?? Tally.glassFill,
            borderRadius: shape,
            border: Border.all(color: edge ?? Tally.glassEdge),
          ),
          child: child,
        ),
      ),
    );
  }
}

/// A dot that breathes, or does not.
///
/// REC blinks at 1.2s and the corner ticks at 1.4s; LIVE is steady. That is not
/// decoration: a blinking light is the universal sign for "writing", and a
/// steady one for "connected", and swapping them would mislead anybody who has
/// ever worked a camera.
class PulseDot extends StatefulWidget {
  const PulseDot({super.key, required this.color, this.size = 9, this.blink = false, this.period = const Duration(milliseconds: 1200)});

  final Color color;
  final double size;
  final bool blink;
  final Duration period;

  @override
  State<PulseDot> createState() => _PulseDotState();
}

class _PulseDotState extends State<PulseDot> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: widget.period)
    ..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final dot = Container(
      width: widget.size,
      height: widget.size,
      decoration: BoxDecoration(color: widget.color, shape: BoxShape.circle),
    );

    if (!widget.blink) return dot;

    return FadeTransition(
      opacity: Tween<double>(begin: 1, end: 0.25).animate(CurvedAnimation(parent: _c, curve: Curves.easeInOut)),
      child: dot,
    );
  }
}

/* ══════════════════════ 1 · The tally frame ══════════════════════════════ */

/// The screen's edge, doing the work a label cannot.
///
/// Gold border plus an inner glow when the feed is up; four blinking red corner
/// ticks while a clip is being written. Both can show at once — they are
/// different jobs — and neither is ever drawn over the subject, which is the
/// whole reason the indicator lives out here instead of in a corner chip.
class TallyFrame extends StatefulWidget {
  const TallyFrame({super.key, required this.live, required this.recording, this.alarm = false});

  final bool live;
  final bool recording;

  /// The command channel has dropped. The frame pulses — this is the one
  /// failure that needs a human to walk over.
  final bool alarm;

  @override
  State<TallyFrame> createState() => _TallyFrameState();
}

class _TallyFrameState extends State<TallyFrame> with TickerProviderStateMixin {
  late final AnimationController _tick =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))..repeat(reverse: true);
  late final AnimationController _alarm =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..repeat(reverse: true);

  @override
  void dispose() {
    _tick.dispose();
    _alarm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Stack(
        fit: StackFit.expand,
        children: [
          // On air: a solid gold edge with the glow pulled inwards.
          if (widget.live)
            Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: Tally.gold.withValues(alpha: 0.9), width: 3),
                boxShadow: [
                  BoxShadow(
                    color: Tally.gold.withValues(alpha: 0.28),
                    blurRadius: 34,
                    spreadRadius: -6,
                    blurStyle: BlurStyle.inner,
                  ),
                ],
              ),
            ),

          // The link is down: the frame itself asks for someone.
          if (widget.alarm)
            FadeTransition(
              opacity: Tween<double>(begin: 0.85, end: 0.15).animate(CurvedAnimation(parent: _alarm, curve: Curves.easeInOut)),
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: Tally.red.withValues(alpha: 0.9), width: 3),
                ),
              ),
            ),

          // Rolling: four ticks, blinking together.
          if (widget.recording)
            FadeTransition(
              opacity: Tween<double>(begin: 1, end: 0.25).animate(CurvedAnimation(parent: _tick, curve: Curves.easeInOut)),
              child: Stack(
                fit: StackFit.expand,
                children: const [
                  Positioned(top: 10, left: 10, child: _Tick(top: true, left: true)),
                  Positioned(top: 10, right: 10, child: _Tick(top: true, left: false)),
                  Positioned(bottom: 10, left: 10, child: _Tick(top: false, left: true)),
                  Positioned(bottom: 10, right: 10, child: _Tick(top: false, left: false)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _Tick extends StatelessWidget {
  const _Tick({required this.top, required this.left});

  final bool top;
  final bool left;

  @override
  Widget build(BuildContext context) {
    const side = BorderSide(color: Tally.red, width: 3);
    const r = Radius.circular(12);

    return SizedBox(
      width: 26,
      height: 26,
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(
            top: top ? side : BorderSide.none,
            bottom: top ? BorderSide.none : side,
            left: left ? side : BorderSide.none,
            right: left ? BorderSide.none : side,
          ),
          borderRadius: BorderRadius.only(
            topLeft: top && left ? r : Radius.zero,
            topRight: top && !left ? r : Radius.zero,
            bottomLeft: !top && left ? r : Radius.zero,
            bottomRight: !top && !left ? r : Radius.zero,
          ),
        ),
      ),
    );
  }
}

/* ══════════════════════ 2 · The status capsule ═══════════════════════════ */

/// REC, LIVE and the bout, in one pill at the top.
///
/// One capsule rather than three chips because these are one thought — what
/// this camera is doing right now — and because two timers that can start
/// independently are much easier to compare when they sit side by side. Idle,
/// it collapses to the mat and the word STANDBY: an empty frame around nothing
/// would read as a fault.
class StatusCapsule extends StatelessWidget {
  const StatusCapsule({
    super.key,
    required this.recording,
    required this.recElapsed,
    required this.live,
    required this.liveElapsed,
    required this.context_,
  });

  final bool recording;
  final Duration recElapsed;
  final bool live;
  final Duration liveElapsed;

  /// "MAT 1 · BOUT 14", or "MAT 1 · STANDBY" when nothing is running.
  final String context_;

  static String clock(Duration d) =>
      '${d.inMinutes.toString().padLeft(2, '0')}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final divider = Container(width: 1, color: Colors.white.withValues(alpha: 0.14));

    return Glass(
      padding: EdgeInsets.zero,
      child: IntrinsicHeight(
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (recording) ...[
              _Segment(
                tint: Tally.red.withValues(alpha: 0.14),
                dot: const PulseDot(color: Tally.red, blink: true),
                word: 'REC',
                wordColor: Tally.text,
                value: clock(recElapsed),
              ),
              divider,
            ],
            if (live) ...[
              _Segment(
                tint: Tally.gold.withValues(alpha: 0.14),
                dot: const PulseDot(color: Tally.gold),
                word: 'LIVE',
                wordColor: Tally.goldLight,
                value: clock(liveElapsed),
              ),
              divider,
            ],
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
              child: Text(context_, style: Tally.label(11, color: Tally.textSecondary, tracking: 0.08)),
            ),
          ],
        ),
      ),
    );
  }
}

class _Segment extends StatelessWidget {
  const _Segment({required this.tint, required this.dot, required this.word, required this.wordColor, required this.value});

  final Color tint;
  final Widget dot;
  final String word;
  final Color wordColor;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: tint,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          dot,
          const SizedBox(width: 7),
          Text(word, style: Tally.label(11, color: wordColor, tracking: 0.14, weight: FontWeight.w700)),
          const SizedBox(width: 7),
          Text(value, style: Tally.number(15)),
        ],
      ),
    );
  }
}

/* ══════════════════════ 3 · The server link ══════════════════════════════ */

/// Whether the mat can still reach this camera.
///
/// The quietest chip on the screen while it is green, and the loudest thing on
/// it when it is not — because every other failure here degrades the picture,
/// and this one means the camera has stopped taking orders.
class ServerLinkChip extends StatelessWidget {
  const ServerLinkChip({super.key, required this.connected});

  final bool connected;

  @override
  Widget build(BuildContext context) {
    return Glass(
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          PulseDot(color: connected ? Tally.green : Tally.red, size: 7, blink: !connected),
          const SizedBox(width: 6),
          Text(
            connected ? 'SERVER LINKED' : 'SERVER LOST',
            style: Tally.label(10, color: connected ? Tally.textMuted : Tally.red),
          ),
        ],
      ),
    );
  }
}

/* ══════════════════════ 4 · Audio ════════════════════════════════════════ */

/// Two bars on the left edge: is this thing hearing anything.
///
/// Sound is the cheapest thing to lose without noticing — a muted mic looks
/// exactly like a quiet hall until the recording is played back. A meter that
/// never moves says so at a glance.
class AudioMeters extends StatelessWidget {
  const AudioMeters({super.key, required this.levels});

  /// 0..1 per channel.
  final List<double> levels;

  @override
  Widget build(BuildContext context) {
    return Glass(
      radius: 10,
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 10),
      child: SizedBox(
        height: 120,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            for (final (i, level) in levels.indexed) ...[
              if (i > 0) const SizedBox(width: 4),
              _Bar(level: level.clamp(0.0, 1.0)),
            ],
          ],
        ),
      ),
    );
  }
}

class _Bar extends StatelessWidget {
  const _Bar({required this.level});

  final double level;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.bottomCenter,
      child: FractionallySizedBox(
        heightFactor: math.max(level, 0.02),
        child: Container(
          width: 5,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(3),
            // Green to the loud end, amber, then red — the levels a sound
            // engineer already reads without being told.
            gradient: const LinearGradient(
              begin: Alignment.bottomCenter,
              end: Alignment.topCenter,
              colors: [Tally.green, Tally.green, Tally.amber, Tally.amber, Tally.red],
              stops: [0, 0.62, 0.62, 0.85, 0.85],
            ),
          ),
        ),
      ),
    );
  }
}

/* ══════════════════════ 5 · Stream health ════════════════════════════════ */

/// A conic gauge of what is actually going out, against what was asked for.
class HealthRing extends StatelessWidget {
  const HealthRing({super.key, required this.fraction, required this.health, required this.bitrate, this.size = 34});

  final double fraction;
  final Health health;
  final String bitrate;
  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        painter: _RingPainter(fraction.clamp(0.0, 1.0), health.ring),
        child: Center(
          child: Container(
            width: size * 0.7,
            height: size * 0.7,
            decoration: const BoxDecoration(color: Tally.navyPanel, shape: BoxShape.circle),
            alignment: Alignment.center,
            child: FittedBox(
              child: Padding(
                padding: const EdgeInsets.all(2),
                child: Text(bitrate, style: Tally.number(8.5, color: health.ring, weight: FontWeight.w700)),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  _RingPainter(this.fraction, this.color);

  final double fraction;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final track = Paint()
      ..color = Colors.white.withValues(alpha: 0.12)
      ..style = PaintingStyle.fill;

    canvas.drawCircle(rect.center, size.width / 2, track);

    if (fraction <= 0) return;

    canvas.drawArc(
      rect,
      -math.pi / 2,
      2 * math.pi * fraction,
      true,
      Paint()
        ..color = color
        ..style = PaintingStyle.fill,
    );
  }

  @override
  bool shouldRepaint(covariant _RingPainter old) => old.fraction != fraction || old.color != color;
}

/// The ring, the word, and the three numbers under it.
///
/// Tapping it opens everything else. The handoff is explicit that amber and red
/// SWELL — a warning that is the same size as the calm state is a warning
/// nobody sees.
class HealthChip extends StatelessWidget {
  const HealthChip({
    super.key,
    required this.health,
    required this.fraction,
    required this.bitrate,
    required this.detail,
    required this.onTap,
  });

  final Health health;
  final double fraction;
  final String bitrate;
  final String detail;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final loud = !health.calm;

    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Glass(
        padding: const EdgeInsets.only(left: 8, right: 16, top: 7, bottom: 7),
        edge: loud ? health.ring.withValues(alpha: 0.55) : null,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            HealthRing(fraction: fraction, health: health, bitrate: bitrate, size: loud ? 42 : 34),
            const SizedBox(width: 10),
            Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(health.word, style: Tally.label(11, color: health.ink, weight: FontWeight.w700)),
                const SizedBox(height: 1),
                Text(detail, style: Tally.number(10, color: Tally.textMuted, weight: FontWeight.w400)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// The full-width sentence a degraded stream gets.
///
/// "Errors never live only in small text" — and the reason matters more than
/// the fault: network-limited while the recording is untouched is a completely
/// different situation from a camera that has stopped.
class ReasonBanner extends StatelessWidget {
  const ReasonBanner({super.key, required this.text, required this.health});

  final String text;
  final Health health;

  @override
  Widget build(BuildContext context) {
    return Glass(
      radius: 10,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      fill: health.ring.withValues(alpha: 0.16),
      edge: health.ring.withValues(alpha: 0.6),
      child: Text(text, textAlign: TextAlign.center, style: Tally.label(10.5, color: health.ink)),
    );
  }
}

/* ══════════════════════ 6 · Resources ════════════════════════════════════ */

/// Storage as TIME, not gigabytes.
///
/// Nobody standing at a mat can convert 14 GB into whether this phone lasts the
/// session. Minutes at the rate it is actually writing is the same fact,
/// answered.
class ResourceChips extends StatelessWidget {
  const ResourceChips({super.key, required this.minutesLeft, required this.battery});

  final int? minutesLeft;
  final int? battery;

  static String duration(int? minutes) {
    if (minutes == null) return '—';
    if (minutes < 60) return '${minutes}m left';

    return '${minutes ~/ 60}h ${(minutes % 60).toString().padLeft(2, '0')}m left';
  }

  @override
  Widget build(BuildContext context) {
    final lowStorage = minutesLeft != null && minutesLeft! < 30;
    final lowBattery = (battery ?? 100) < 20;
    final flatBattery = (battery ?? 100) < 10;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Glass(
          radius: 10,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.album_outlined, size: 13, color: lowStorage ? Tally.amber : Tally.textSecondary),
              const SizedBox(width: 6),
              Text(
                duration(minutesLeft),
                style: Tally.number(11, color: lowStorage ? Tally.amberText : Tally.textSecondary, weight: FontWeight.w500),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Glass(
          radius: 10,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.battery_std_outlined,
                size: 13,
                color: flatBattery ? Tally.red : (lowBattery ? Tally.amber : Tally.textSecondary),
              ),
              const SizedBox(width: 6),
              Text(
                battery == null ? '—' : '$battery%',
                style: Tally.number(11,
                    color: flatBattery ? Tally.red : (lowBattery ? Tally.amberText : Tally.textSecondary),
                    weight: FontWeight.w500),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/* ══════════════════════ 7 · The rail ═════════════════════════════════════ */

/// Camera-only controls. Nothing here starts or stops anything.
///
/// That is the design's central claim and it is worth stating plainly: the mat
/// owns REC and LIVE. A camera operator who can also start a recording is a
/// camera operator who can start the WRONG recording, and the bout that gets
/// filed against the wrong athlete is discovered days later.
class CameraRail extends StatelessWidget {
  const CameraRail({super.key, required this.zoom, required this.onZoom, required this.onSettings, required this.settingsOpen});

  final double zoom;
  final ValueChanged<double> onZoom;
  final VoidCallback onSettings;
  final bool settingsOpen;

  @override
  Widget build(BuildContext context) {
    return Glass(
      radius: 14,
      padding: const EdgeInsets.all(8),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final z in const [1.0, 2.0, 4.0]) ...[
            RailCell(
              selected: zoom == z,
              onTap: () => onZoom(z),
              child: Text(
                '${z.toStringAsFixed(0)}×',
                style: Tally.label(12,
                    color: zoom == z ? Tally.goldLight : Tally.textSecondary,
                    tracking: 0,
                    weight: zoom == z ? FontWeight.w700 : FontWeight.w600),
              ),
            ),
            const SizedBox(height: 8),
          ],
          Container(height: 1, width: 30, color: Colors.white.withValues(alpha: 0.12)),
          const SizedBox(height: 8),
          RailCell(
            selected: settingsOpen,
            onTap: onSettings,
            child: Icon(Icons.settings_outlined,
                size: 18, color: settingsOpen ? Tally.goldLight : Tally.textSecondary),
          ),
        ],
      ),
    );
  }
}

/// A 44×44 target — the smallest thing a thumb finds without looking.
class RailCell extends StatelessWidget {
  const RailCell({super.key, required this.child, required this.onTap, this.selected = false});

  final Widget child;
  final VoidCallback onTap;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 44,
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? Tally.gold.withValues(alpha: 0.2) : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
          border: selected ? Border.all(color: Tally.gold, width: 1.5) : null,
        ),
        child: child,
      ),
    );
  }
}

/* ══════════════════════ 8 · The settings drawer ══════════════════════════ */

/// A row of mutually exclusive options — the drawer's workhorse.
class Segmented extends StatelessWidget {
  const Segmented({super.key, required this.options, required this.value, required this.onPick, this.enabled = true});

  final List<({String label, String value})> options;
  final String value;
  final ValueChanged<String> onPick;

  /// A control the camera cannot actually carry out is shown, dimmed, and does
  /// not respond. Dimmed rather than deleted because the setting is real and a
  /// different phone may well support it; inert rather than pretending, because
  /// a control that appears to work and changes nothing is the worst thing on
  /// any instrument — it is discovered on the footage, afterwards.
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (final (i, option) in options.indexed) ...[
          if (i > 0) const SizedBox(width: 6),
          Expanded(
            child: Opacity(
              opacity: enabled ? 1 : 0.4,
              child: GestureDetector(
                onTap: enabled ? () => onPick(option.value) : null,
                behavior: HitTestBehavior.opaque,
                child: Container(
                  height: 32,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: option.value == value ? Tally.gold.withValues(alpha: 0.2) : Colors.transparent,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: option.value == value ? Tally.gold : Colors.white.withValues(alpha: 0.14),
                      width: option.value == value ? 1.5 : 1,
                    ),
                  ),
                  child: Text(
                    option.label,
                    style: Tally.label(11,
                        color: option.value == value ? Tally.goldLight : Tally.textSecondary,
                        tracking: 0,
                        weight: option.value == value ? FontWeight.w700 : FontWeight.w600),
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// The label line above every control: what it is, and what it is set to.
class FieldLabel extends StatelessWidget {
  const FieldLabel({super.key, required this.name, required this.value});

  final String name;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(name, style: Tally.label(10)),
        Text(value, style: Tally.label(10, color: Tally.goldLight)),
      ],
    );
  }
}

class TallySwitch extends StatelessWidget {
  const TallySwitch({super.key, required this.on, required this.onChanged, this.enabled = true});

  final bool on;
  final ValueChanged<bool> onChanged;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: enabled ? 1 : 0.4,
      child: GestureDetector(
      onTap: enabled ? () => onChanged(!on) : null,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        width: 38,
        height: 22,
        decoration: BoxDecoration(
          color: on ? Tally.gold : Colors.white.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(11),
        ),
        child: Stack(
          children: [
            AnimatedAlign(
              duration: const Duration(milliseconds: 160),
              alignment: on ? Alignment.centerRight : Alignment.centerLeft,
              child: Padding(
                padding: const EdgeInsets.all(2),
                child: Container(
                  width: 18,
                  height: 18,
                  decoration: const BoxDecoration(color: Tally.navyPanel, shape: BoxShape.circle),
                ),
              ),
            ),
          ],
        ),
      ),
      ),
    );
  }
}

/* ══════════════════════ 9 · Rule of thirds ═══════════════════════════════ */

/// Four hairlines. The oldest framing aid there is, and the one thing on this
/// screen drawn deliberately over the subject — at 5% white, which is visible
/// while composing and invisible while watching.
class ThirdsGrid extends StatelessWidget {
  const ThirdsGrid({super.key});

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(child: CustomPaint(painter: _ThirdsPainter(), size: Size.infinite));
  }
}

class _ThirdsPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: 0.05)
      ..strokeWidth = 1;

    for (final f in const [1 / 3, 2 / 3]) {
      canvas.drawLine(Offset(size.width * f, 0), Offset(size.width * f, size.height), paint);
      canvas.drawLine(Offset(0, size.height * f), Offset(size.width, size.height * f), paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}
