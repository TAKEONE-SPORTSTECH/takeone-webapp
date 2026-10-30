import 'dart:async';

import 'package:flutter/material.dart';

import 'music.dart';
import 'tally.dart';
import 'vault.dart';

/// The score bar's parts, each a switch — what is drawn over the player AND
/// what a sent video carries burned in. One row, used in both places, so the
/// choice is made once and means the same thing everywhere. Remembered by
/// the vault across restarts.
class BarParts extends StatefulWidget {
  const BarParts({super.key, required this.vault, this.onChanged, this.onVsChanged, this.note = 'What is on here is what a sent video carries.'});

  final ClipVault vault;
  final VoidCallback? onChanged;

  /// A change to the VS screen (its switches, music or names position) — so a
  /// preview can show the VS screen rather than the bar. Falls back to onChanged.
  final VoidCallback? onVsChanged;
  final String? note;

  static const labels = {
    'intro': 'VS screen',
    'music': 'VS music',
    'scorebar': 'Score bar',
    'feed': 'Live scoring',
    'info': 'Event',
    'logos': 'Logos',
    'flags': 'Flags',
    'stats': 'Stats card',
  };

  @override
  State<BarParts> createState() => _BarPartsState();
}

class _BarPartsState extends State<BarParts> {
  Future<void> _toggle(String part) async {
    final parts = Set<String>.from(widget.vault.parts);
    parts.contains(part) ? parts.remove(part) : parts.add(part);
    await widget.vault.setParts(parts);
    if (!mounted) return;
    setState(() {});
    (part == 'intro' || part == 'music' ? (widget.onVsChanged ?? widget.onChanged) : widget.onChanged)?.call();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final part in ClipVault.allParts) _chip(part, widget.vault.parts.contains(part)),
          ],
        ),
        const SizedBox(height: 10),
        // Which music the VS screen plays — the built-in intro, or a song the
        // operator picked and cut.
        GestureDetector(
          onTap: () async {
            await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => MusicEditor(vault: widget.vault)));
            if (!mounted) return;
            setState(() {});
            (widget.onVsChanged ?? widget.onChanged)?.call();
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.04),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
            ),
            child: Row(
              children: [
                const Icon(Icons.music_note_outlined, size: 17, color: Tally.gold),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'VS music: ${widget.vault.musicName ?? 'built-in TakeOne intro'}',
                    style: Tally.body(11.5, color: Tally.textSecondary, weight: FontWeight.w600),
                  ),
                ),
                Text('CHANGE', style: Tally.label(10, color: Tally.gold, tracking: 0.16, weight: FontWeight.w700)),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        BarLift(vault: widget.vault, onChanged: widget.onChanged),
        const SizedBox(height: 6),
        BarLift(vault: widget.vault, onChanged: widget.onVsChanged ?? widget.onChanged, vs: true),
        if (widget.note != null) ...[
          const SizedBox(height: 6),
          Text(widget.note!, style: Tally.body(11, color: Tally.textFaint)),
        ],
      ],
    );
  }

  Widget _chip(String part, bool on) {
    return GestureDetector(
      onTap: () => _toggle(part),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: on ? Tally.gold.withValues(alpha: 0.18) : Colors.white.withValues(alpha: 0.06),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: on ? Tally.gold.withValues(alpha: 0.5) : Colors.white.withValues(alpha: 0.14)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(on ? Icons.check_circle : Icons.circle_outlined, size: 14, color: on ? Tally.gold : Tally.textFaint),
            const SizedBox(width: 6),
            Text(
              BarParts.labels[part] ?? part,
              style: Tally.label(10.5, color: on ? Tally.goldLight : Tally.textSecondary, tracking: 0.1, weight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}

/// How high the score bar sits — the match strip, the bar and the score line
/// move as one group; low is nearest the frame's bottom edge. Shared by the
/// player, the send sheet and the camera's framing guide, so the height set
/// while framing the mat is the height every video carries.
class BarLift extends StatefulWidget {
  const BarLift({super.key, required this.vault, this.onChanged, this.vs = false});

  final ClipVault vault;
  final VoidCallback? onChanged;

  /// The VS screen's names instead of the score bar: same slider, its own
  /// setting (ClipVault.vsLift).
  final bool vs;

  @override
  State<BarLift> createState() => _BarLiftState();
}

class _BarLiftState extends State<BarLift> {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // How high the score bar sits — the match strip, the bar and the
        // score line move as one group. Low is nearest the frame's edge.
        Row(
          children: [
            Text(widget.vs ? 'VS SCREEN NAMES POSITION' : 'SCORE BAR POSITION', style: Tally.label(10, color: Tally.textMuted, tracking: 0.16, weight: FontWeight.w700)),
            const Spacer(),
            GestureDetector(
              onTap: () async {
                widget.vs ? await widget.vault.setVsLift(ClipVault.vsLiftDefault) : await widget.vault.setLift(ClipVault.liftDefault);
                if (!mounted) return;
                setState(() {});
                widget.onChanged?.call();
              },
              child: Text('RESET', style: Tally.label(10, color: Tally.gold, tracking: 0.16, weight: FontWeight.w700)),
            ),
          ],
        ),
        Row(
          children: [
            const Icon(Icons.vertical_align_bottom, size: 18, color: Tally.textFaint),
            Expanded(
              child: SliderTheme(
                data: SliderThemeData(
                  trackHeight: 3,
                  activeTrackColor: Tally.gold,
                  inactiveTrackColor: Colors.white.withValues(alpha: 0.16),
                  thumbColor: Tally.goldLight,
                  thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 7),
                  overlayShape: const RoundSliderOverlayShape(overlayRadius: 14),
                ),
                child: Slider(
                  min: widget.vs ? ClipVault.vsLiftMin : ClipVault.liftMin,
                  max: widget.vs ? ClipVault.vsLiftMax : ClipVault.liftMax,
                  value: widget.vs ? widget.vault.vsLift : widget.vault.lift,
                  onChanged: (v) {
                    unawaited(widget.vs ? widget.vault.setVsLift(v, save: false) : widget.vault.setLift(v, save: false));
                    setState(() {});
                    widget.onChanged?.call();
                  },
                  onChangeEnd: (v) => unawaited(widget.vs ? widget.vault.setVsLift(v) : widget.vault.setLift(v)),
                ),
              ),
            ),
            const Icon(Icons.vertical_align_top, size: 18, color: Tally.textFaint),
          ],
        ),
      ],
    );
  }
}
