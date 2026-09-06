import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import 'tally.dart';
import 'vault.dart';

/// What this phone filmed, on the phone.
///
/// The one screen in the app that is not about the next bout. It exists for the
/// moment after the mat finishes: somebody picks the phone off the tripod and
/// needs to answer, right there, "did we get the semi-final?" — before the
/// clips are anywhere else, possibly before the hall's wifi has carried a single
/// byte of them.
///
/// So it reads off the phone's own ledger, never off the server, and it says
/// everything the ledger knows about each bout: the bout number and stage, the
/// two corners, the mat and camera angle, when it started, how long it ran, how
/// big the file is, what it was shot at, and whether the event has it yet. A
/// list that only showed file names would send the same person back to a laptop.
///
/// Reached from the pairing screen, because that is where a camera is when
/// nobody is filming with it.
class ClipLibrary extends StatefulWidget {
  const ClipLibrary({super.key, required this.vault});

  final ClipVault vault;

  @override
  State<ClipLibrary> createState() => _ClipLibraryState();
}

class _ClipLibraryState extends State<ClipLibrary> {
  @override
  void initState() {
    super.initState();
    // The queue keeps working while this list is open, and the list keeps up.
    widget.vault.watch(_refresh);
  }

  @override
  void dispose() {
    widget.vault.unwatch(_refresh);
    super.dispose();
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final clips = widget.vault.clips;

    return Scaffold(
      backgroundColor: Tally.navyDeep,
      body: SafeArea(
        child: Column(
          children: [
            _header(clips.length),
            Expanded(
              child: clips.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.videocam_off_outlined, size: 34, color: Tally.textFaint),
                          const SizedBox(height: 12),
                          Text('NOTHING FILMED YET', style: Tally.label(12, tracking: 0.2)),
                          const SizedBox(height: 6),
                          SizedBox(
                            width: 280,
                            child: Text(
                              'Bouts recorded on this phone appear here, whether or not '
                              'they have reached the event yet.',
                              textAlign: TextAlign.center,
                              style: Tally.body(12, color: Tally.textFaint),
                            ),
                          ),
                        ],
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                      itemCount: clips.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 10),
                      itemBuilder: (_, i) => _ClipCard(
                        clip: clips[i],
                        onPlay: () => _play(clips[i]),
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header(int count) {
    final waiting = widget.vault.waiting;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Row(
        children: [
          _RoundButton(
            icon: Icons.arrow_back,
            onTap: () => Navigator.of(context).maybePop(),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('RECORDED BOUTS', style: Tally.label(13, color: Tally.gold, tracking: 0.18)),
                const SizedBox(height: 3),
                Text(
                  count == 0
                      ? 'On this phone'
                      : '$count on this phone · ${widget.vault.uploaded} in the vault'
                          '${waiting > 0 ? ' · $waiting waiting' : ''}',
                  style: Tally.body(11.5, color: Tally.textFaint),
                ),
              ],
            ),
          ),

          // The manual door. Auto upload is off by default, so this is how a
          // day's footage leaves the phone: deliberately, on a network the
          // operator picked. Hidden when there is nothing waiting.
          if (waiting > 0)
            GestureDetector(
              onTap: _sendNow,
              behavior: HitTestBehavior.opaque,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                decoration: BoxDecoration(
                  color: Tally.gold.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: Tally.gold.withValues(alpha: 0.4)),
                ),
                child: Text(
                  widget.vault.inFlight != null ? 'SENDING' : 'SEND $waiting',
                  style: Tally.label(10, color: Tally.gold, tracking: 0.14, weight: FontWeight.w700),
                ),
              ),
            ),
        ],
      ),
    );
  }

  /// Push whatever is waiting, now, whatever the auto-upload switch says.
  void _sendNow() {
    if (widget.vault.inFlight != null) return;

    unawaited(widget.vault.sendNow());

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: Tally.navyPrimary,
        content: Text(
          'Sending ${widget.vault.waiting} bout${widget.vault.waiting == 1 ? '' : 's'} to the event. '
          'They stay on this phone either way.',
          style: Tally.body(12.5),
        ),
      ),
    );
  }

  void _play(Clip clip) {
    if (!File(clip.path).existsSync()) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: Tally.navyPrimary,
          content: Text('That file is no longer on this phone.', style: Tally.body(12.5)),
        ),
      );

      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => ClipPlayer(clip: clip)),
    );
  }
}

/// One bout, with everything the phone knows about it.
class _ClipCard extends StatelessWidget {
  const _ClipCard({required this.clip, required this.onPlay});

  final Clip clip;
  final VoidCallback onPlay;

  @override
  Widget build(BuildContext context) {
    final where = [
      if (clip.court != null && clip.court!.isNotEmpty) clip.court!.toUpperCase(),
      if (clip.angle != null) 'ANGLE ${clip.angle}',
      if (clip.stage != null && clip.stage!.isNotEmpty) clip.stage!.toUpperCase(),
    ].join(' · ');

    return Glass(
      radius: 16,
      padding: const EdgeInsets.fromLTRB(14, 13, 14, 13),
      child: InkWell(
        onTap: onPlay,
        borderRadius: BorderRadius.circular(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // The play affordance carries the length, so the thing you press
                // also tells you what pressing it costs you.
                Container(
                  width: 54,
                  height: 54,
                  decoration: BoxDecoration(
                    color: Tally.gold.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Tally.gold.withValues(alpha: 0.35)),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.play_arrow_rounded, color: Tally.gold, size: 24),
                      Text(clip.lengthLabel, style: Tally.number(10, color: Tally.goldLight)),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(clip.title, style: Tally.number(16, weight: FontWeight.w700)),
                      const SizedBox(height: 3),
                      Text(
                        clip.corners,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Tally.body(12.5, color: Tally.textSecondary),
                      ),
                      if (where.isNotEmpty) ...[
                        const SizedBox(height: 5),
                        Text(where, style: Tally.label(9.5, tracking: 0.16)),
                      ],
                    ],
                  ),
                ),
                _StateChip(clip: clip),
              ],
            ),
            const SizedBox(height: 11),
            Divider(height: 1, color: Colors.white.withValues(alpha: 0.07)),
            const SizedBox(height: 9),

            // The facts a person actually asks for, in the order they ask.
            Wrap(
              spacing: 16,
              runSpacing: 7,
              children: [
                _Fact(label: 'STARTED', value: _clock(clip.startedAt)),
                _Fact(label: 'LENGTH', value: clip.lengthLabel),
                _Fact(label: 'SIZE', value: clip.sizeLabel),
                if (clip.fps > 0) _Fact(label: 'SHOT AT', value: '${clip.fps} fps'),
                if (clip.event != null && clip.event!.isNotEmpty) _Fact(label: 'EVENT', value: clip.event!),
              ],
            ),
            const SizedBox(height: 8),
            Text(clip.ref, style: Tally.body(10, color: Tally.textFaint)),
          ],
        ),
      ),
    );
  }

  static String _clock(DateTime at) {
    String two(int n) => n.toString().padLeft(2, '0');

    return '${two(at.day)}/${two(at.month)}  ${two(at.hour)}:${two(at.minute)}';
  }
}

/// Where this clip is on its way to the event: the one fact the list is for
/// after "did we get it" — because a clip that is only on the phone is a clip
/// somebody must not wipe yet.
class _StateChip extends StatelessWidget {
  const _StateChip({required this.clip});

  final Clip clip;

  @override
  Widget build(BuildContext context) {
    late final Color colour;
    late final String text;

    if (clip.state == Clip.done) {
      colour = Tally.green;
      text = 'IN THE VAULT';
    } else if (clip.state == Clip.uploading) {
      colour = Tally.gold;
      text = 'SENDING ${(clip.fraction * 100).toStringAsFixed(0)}%';
    } else if (clip.state == Clip.failed) {
      colour = Tally.amber;
      text = 'WILL RETRY';
    } else {
      colour = Tally.textFaint;
      text = 'ON THIS PHONE';
    }

    return Container(
      margin: const EdgeInsets.only(left: 8),
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: colour.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: colour.withValues(alpha: 0.4)),
      ),
      child: Text(text, style: Tally.label(9, color: colour, tracking: 0.14, weight: FontWeight.w700)),
    );
  }
}

class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label, style: Tally.label(8.5, tracking: 0.18)),
        const SizedBox(height: 2),
        Text(value, style: Tally.number(12, color: Tally.textSecondary, weight: FontWeight.w500)),
      ],
    );
  }
}

/// Watching one back, without leaving the camera.
///
/// Deliberately plain: play, scrub, the clock, and the bout's identity across
/// the top so somebody checking three clips in a row knows which one they are
/// looking at. No editing, no sharing, no deleting — this app films and files;
/// everything else a bout needs happens on the platform, where it is
/// authorised.
class ClipPlayer extends StatefulWidget {
  const ClipPlayer({super.key, required this.clip});

  final Clip clip;

  @override
  State<ClipPlayer> createState() => _ClipPlayerState();
}

class _ClipPlayerState extends State<ClipPlayer> {
  VideoPlayerController? _controller;
  String? _fault;

  @override
  void initState() {
    super.initState();
    _open();
  }

  Future<void> _open() async {
    final controller = VideoPlayerController.file(File(widget.clip.path));

    try {
      await controller.initialize();
    } catch (e) {
      // A clip whose muxer never finished has no moov atom and will not open.
      // Say so plainly — it is the one failure this screen exists to reveal.
      if (mounted) setState(() => _fault = 'THIS FILE WILL NOT OPEN');

      return;
    }

    controller.addListener(() {
      if (mounted) setState(() {});
    });

    if (!mounted) {
      await controller.dispose();

      return;
    }

    setState(() => _controller = controller);
    await controller.play();
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = _controller;
    final ready = c != null && c.value.isInitialized;

    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 10),
              child: Row(
                children: [
                  _RoundButton(icon: Icons.arrow_back, onTap: () => Navigator.of(context).maybePop()),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(widget.clip.title, style: Tally.number(15, weight: FontWeight.w700)),
                        const SizedBox(height: 2),
                        Text(
                          widget.clip.corners,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Tally.body(11.5, color: Tally.textFaint),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Center(
                child: _fault != null
                    ? Text(_fault!, style: Tally.label(12, color: Tally.amberText, tracking: 0.2))
                    : ready
                        ? GestureDetector(
                            onTap: () => c.value.isPlaying ? c.pause() : c.play(),
                            child: AspectRatio(
                              aspectRatio: c.value.aspectRatio,
                              child: VideoPlayer(c),
                            ),
                          )
                        : const SizedBox(
                            width: 26,
                            height: 26,
                            child: CircularProgressIndicator(strokeWidth: 2.5, color: Tally.gold),
                          ),
              ),
            ),
            if (ready) _controls(c),
          ],
        ),
      ),
    );
  }

  Widget _controls(VideoPlayerController c) {
    final position = c.value.position;
    final total = c.value.duration;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      child: Column(
        children: [
          SliderTheme(
            data: SliderThemeData(
              trackHeight: 3,
              activeTrackColor: Tally.gold,
              inactiveTrackColor: Colors.white.withValues(alpha: 0.16),
              thumbColor: Tally.goldLight,
              thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6),
              overlayShape: const RoundSliderOverlayShape(overlayRadius: 14),
            ),
            child: Slider(
              value: position.inMilliseconds
                  .clamp(0, total.inMilliseconds == 0 ? 1 : total.inMilliseconds)
                  .toDouble(),
              max: (total.inMilliseconds == 0 ? 1 : total.inMilliseconds).toDouble(),
              onChanged: (v) => c.seekTo(Duration(milliseconds: v.round())),
            ),
          ),
          Row(
            children: [
              Text(_clock(position), style: Tally.number(12, color: Tally.textSecondary)),
              const Spacer(),
              _RoundButton(
                icon: Icons.replay_10,
                onTap: () => c.seekTo(position - const Duration(seconds: 10)),
              ),
              const SizedBox(width: 10),
              _RoundButton(
                icon: c.value.isPlaying ? Icons.pause : Icons.play_arrow_rounded,
                filled: true,
                onTap: () => c.value.isPlaying ? c.pause() : c.play(),
              ),
              const SizedBox(width: 10),
              _RoundButton(
                icon: Icons.forward_10,
                onTap: () => c.seekTo(position + const Duration(seconds: 10)),
              ),
              const Spacer(),
              Text(_clock(total), style: Tally.number(12, color: Tally.textFaint)),
            ],
          ),
        ],
      ),
    );
  }

  static String _clock(Duration d) {
    String two(int n) => n.toString().padLeft(2, '0');

    return '${two(d.inMinutes)}:${two(d.inSeconds % 60)}';
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({required this.icon, required this.onTap, this.filled = false});

  final IconData icon;
  final VoidCallback onTap;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: filled ? 48 : 40,
        height: filled ? 48 : 40,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: filled ? Tally.gold.withValues(alpha: 0.18) : Colors.white.withValues(alpha: 0.08),
          border: Border.all(
            color: filled ? Tally.gold.withValues(alpha: 0.5) : Colors.white.withValues(alpha: 0.14),
          ),
        ),
        child: Icon(icon, color: filled ? Tally.gold : Tally.textSecondary, size: filled ? 26 : 20),
      ),
    );
  }
}
