import 'package:flutter/material.dart';

/// The matside camera's visual kit: the tokens, and the four things every
/// screen state is built from.
///
/// Kept in one file because the design brief specifies them once and every
/// state reuses them — a chip that drifts 2dp between states is the difference
/// between equipment and an app.
class Cam {
  const Cam._();

  // ── Tokens ────────────────────────────────────────────────────────────────
  static const Color ink = Color(0xFF050507);
  static const Color panel = Color(0xFF16161F);
  static const Color paper = Color(0xFFE8E6E0);

  /// Accents, active controls, and EVERY warning. Red is not a warning colour
  /// here — see [rec].
  static const Color gold = Color(0xFFFDC436);
  static const Color live = Color(0xFF4ED37B);

  /// Recording, and the final delete button. Nothing else, ever: a phone
  /// showing red across a hall means a camera is rolling, and a red battery
  /// warning would make that signal a lie.
  static const Color rec = Color(0xFFFF4438);

  static const String label = 'Barlow Condensed';
  static const String display = 'Archivo Black';

  /// Labels and status: condensed, upper case, tracked out.
  static TextStyle cap(double size, {Color? color, double tracking = 0.12, FontWeight weight = FontWeight.w600}) =>
      TextStyle(
        fontFamily: label,
        fontWeight: weight,
        fontSize: size,
        letterSpacing: size * tracking,
        color: color ?? paper,
        height: 1.1,
      );

  /// Numerals: elapsed time, bout numbers, zoom values, the pairing code.
  static TextStyle num(double size, {Color? color, double tracking = 0.02}) => TextStyle(
        fontFamily: display,
        fontSize: size,
        letterSpacing: size * tracking,
        color: color ?? paper,
        height: 1.05,
      );

  /// Body text, the one place sentence case is allowed.
  static TextStyle body(double size, {Color? color}) => TextStyle(
        fontFamily: label,
        fontWeight: FontWeight.w400,
        fontSize: size,
        color: color ?? paper.withValues(alpha: 0.65),
        height: 1.35,
      );
}

/// A status chip: identity, storage, battery, link.
///
/// Non-interactive on purpose — these are readings, and a reading that can be
/// pressed invites somebody to press it while a bout is running. The warning
/// variant is amber with a pulsing dot, never red.
class CamChip extends StatelessWidget {
  const CamChip({
    super.key,
    required this.text,
    this.dotColor,
    this.icon,
    this.warning = false,
    this.pulse = false,
  });

  final String text;
  final Color? dotColor;
  final IconData? icon;
  final bool warning;
  final bool pulse;

  @override
  Widget build(BuildContext context) {
    final foreground = warning ? Cam.gold : Cam.paper;

    return Container(
      // A minimum, not a fixed height: a chip whose text has to wrap — a refused
      // permission says what to do about it, in a sentence — grew its text
      // straight out through a 36dp box and over whatever was underneath.
      constraints: const BoxConstraints(minHeight: 36),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: warning ? Cam.gold.withValues(alpha: 0.16) : Cam.ink.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: warning ? Cam.gold : Cam.paper.withValues(alpha: 0.14),
          width: warning ? 1.5 : 1,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (dotColor != null) ...[
            _Dot(color: warning ? Cam.gold : dotColor!, pulse: pulse),
            const SizedBox(width: 8),
          ],
          if (icon != null) ...[
            Icon(icon, size: 15, color: foreground.withValues(alpha: 0.85)),
            const SizedBox(width: 6),
          ],
          // Flexible, so a long event title ellipsises inside the chip instead
          // of overflowing the row it sits in.
          Flexible(
            child: Text(
              text,
              style: Cam.cap(14, color: foreground),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

/// A 10dp status dot, optionally breathing.
class _Dot extends StatefulWidget {
  const _Dot({required this.color, this.pulse = false});

  final Color color;
  final bool pulse;

  @override
  State<_Dot> createState() => _DotState();
}

class _DotState extends State<_Dot> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  );

  @override
  void initState() {
    super.initState();
    if (widget.pulse) _controller.repeat(reverse: true);
  }

  @override
  void didUpdateWidget(_Dot old) {
    super.didUpdateWidget(old);
    if (widget.pulse && !_controller.isAnimating) {
      _controller.repeat(reverse: true);
    } else if (!widget.pulse && _controller.isAnimating) {
      _controller.stop();
      _controller.value = 1;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      // Opacity only: this sits over a live preview being encoded, and a
      // scale/blur animation here would cost frames where it matters most.
      opacity: widget.pulse
          ? Tween<double>(begin: 1, end: 0.25).animate(_controller)
          : const AlwaysStoppedAnimation(1),
      child: Container(
        width: 10,
        height: 10,
        decoration: BoxDecoration(color: widget.color, shape: BoxShape.circle),
      ),
    );
  }
}

/// A cell in the control rail: a zoom preset, an FPS choice, the EV stepper.
class CamCell extends StatelessWidget {
  const CamCell({
    super.key,
    required this.child,
    this.active = false,
    this.disabled = false,
    this.height = 48,
    this.onTap,
  });

  final Widget child;
  final bool active;
  final bool disabled;
  final double height;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: disabled ? 0.55 : 1,
      child: GestureDetector(
        onTap: disabled ? null : onTap,
        behavior: HitTestBehavior.opaque,
        child: Container(
          width: 48,
          height: height,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: Cam.panel,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: active ? Cam.gold : Cam.paper.withValues(alpha: 0.14),
              width: active ? 1.5 : 1,
            ),
          ),
          child: child,
        ),
      ),
    );
  }
}

/// The scrim every overlay sits on, so a white dobok on a bright mat cannot
/// swallow the status text.
class CamScrim extends StatelessWidget {
  const CamScrim({super.key, this.height = 96, this.opacity = 0.62});

  final double height;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        height: height,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Cam.ink.withValues(alpha: opacity), Cam.ink.withValues(alpha: 0)],
          ),
        ),
      ),
    );
  }
}
