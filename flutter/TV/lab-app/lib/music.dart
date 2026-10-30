import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:video_player/video_player.dart';

import 'tally.dart';
import 'vault.dart';

/// The VS screen's music, chosen and cut by the operator.
///
/// Pick a song on the phone, drag where it starts and where it ends, listen,
/// save. The VS screen runs for the first four seconds of the cut — the gold
/// marker on the track shows where the bout starts — and the end fades away
/// under the bout's own sound. Saving cuts the song into the app's own VS
/// music file (MainActivity.makeMusic), which the player and every sent video
/// then use instead of the built-in track. Reset goes back to the built-in.
class MusicEditor extends StatefulWidget {
  const MusicEditor({super.key, required this.vault});

  final ClipVault vault;

  @override
  State<MusicEditor> createState() => _MusicEditorState();
}

class _MusicEditorState extends State<MusicEditor> {
  static const _native = MethodChannel('bh.takeone.lab/music');

  /// Where the bout starts, in the music — the VS screen's length.
  static const _intro = 4.0;
  static const _minLength = 5.0;
  static const _maxLength = 20.0;

  String? _path;
  String? _name;
  double _length = 0;
  RangeValues _range = const RangeValues(0, 7);

  VideoPlayerController? _player;
  Timer? _stopper;
  bool _busy = false;
  String? _note;

  @override
  void dispose() {
    _stopper?.cancel();
    _player?.dispose();
    super.dispose();
  }

  Future<void> _pick() async {
    try {
      final picked = await _native.invokeMapMethod<String, dynamic>('pick');
      if (picked == null || !mounted) return;

      await _player?.dispose();
      final player = VideoPlayerController.file(File(picked['path'] as String));
      await player.initialize();

      final seconds = ((picked['ms'] as num?)?.toDouble() ?? player.value.duration.inMilliseconds.toDouble()) / 1000;

      setState(() {
        _player = player;
        _path = picked['path'] as String;
        _name = picked['name'] as String?;
        _length = seconds;
        final end = seconds < 7 ? seconds : 7.0;
        _range = RangeValues(0, end);
        _note = null;
      });
    } on PlatformException catch (e) {
      setState(() => _note = 'That file could not be opened (${e.message ?? e.code}).');
    }
  }

  /// Play the selection, fading out its last seconds the way a video will.
  Future<void> _preview() async {
    final p = _player;
    if (p == null) return;

    _stopper?.cancel();
    if (p.value.isPlaying) {
      await p.pause();
      setState(() {});

      return;
    }

    await p.seekTo(Duration(milliseconds: (_range.start * 1000).round()));
    await p.setVolume(1);
    await p.play();
    setState(() {});

    final len = _range.end - _range.start;
    final fade = len / 2 < 3 ? len / 2 : 3.0;
    _stopper = Timer.periodic(const Duration(milliseconds: 100), (t) async {
      final at = p.value.position.inMilliseconds / 1000 - _range.start;
      if (at >= len || !p.value.isPlaying) {
        t.cancel();
        await p.pause();
        if (mounted) setState(() {});

        return;
      }
      final v = at > len - fade ? ((len - at) / fade).clamp(0.0, 1.0) : 1.0;
      await p.setVolume(v);
      if (mounted) setState(() {});
    });
  }

  Future<void> _save() async {
    final path = _path;
    if (path == null) return;

    await _player?.pause();
    setState(() => _busy = true);

    try {
      final made = await _native.invokeMethod<String>('make', {
        'path': path,
        'start': (_range.start * 1000).round(),
        'end': (_range.end * 1000).round(),
      });

      if (made != null) {
        await widget.vault.setMusic(made, _name ?? 'Your music');
        if (mounted) Navigator.of(context).maybePop();
      }
    } on PlatformException catch (e) {
      setState(() => _note = 'The music could not be cut (${e.message ?? e.code}).');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _drag(RangeValues v) {
    var start = v.start;
    var end = v.end;

    // Long enough for the VS screen and a fade; short enough to stay an intro.
    if (end - start < _minLength) {
      if (start != _range.start) {
        start = (end - _minLength).clamp(0.0, _length);
      } else {
        end = (start + _minLength).clamp(0.0, _length);
      }
    }
    if (end - start > _maxLength) {
      if (start != _range.start) {
        end = start + _maxLength;
      } else {
        start = end - _maxLength;
      }
    }

    setState(() => _range = RangeValues(start.clamp(0.0, _length), end.clamp(0.0, _length)));
  }

  static String _t(double s) {
    final m = s ~/ 60;
    final r = s - m * 60;

    return '$m:${r.toStringAsFixed(1).padLeft(4, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final current = widget.vault.musicName;
    final playing = _player?.value.isPlaying ?? false;
    final position = (_player?.value.position.inMilliseconds ?? 0) / 1000;

    return Scaffold(
      backgroundColor: Tally.navyDeep,
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 28),
          children: [
            Row(
              children: [
                GestureDetector(
                  onTap: () => Navigator.of(context).maybePop(),
                  child: Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: 0.08)),
                    child: const Icon(Icons.chevron_left, color: Tally.textSecondary),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('VS SCREEN MUSIC', style: Tally.label(15, color: Tally.text, tracking: 0.14, weight: FontWeight.w700)),
                      Text('Now: ${current ?? 'Built-in TakeOne intro'}', style: Tally.body(11, color: Tally.textFaint)),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 22),

            _button(Icons.library_music_outlined, _path == null ? 'CHOOSE A SONG' : 'CHOOSE ANOTHER SONG', _pick, Tally.gold),

            if (_path != null) ...[
              const SizedBox(height: 22),
              Text(_name ?? '', style: Tally.body(13, color: Tally.text, weight: FontWeight.w600)),
              Text('${_t(_length)} long', style: Tally.body(11, color: Tally.textFaint)),
              const SizedBox(height: 18),

              // The track: the selection, where the bout starts in it, and
              // where the preview has got to.
              LayoutBuilder(builder: (context, box) {
                const pad = 24.0;
                final w = box.maxWidth - pad * 2;
                double x(double s) => pad + (_length <= 0 ? 0 : s / _length * w);
                final bout = _range.start + _intro;

                return SizedBox(
                  height: 70,
                  child: Stack(
                    children: [
                      Positioned(
                        left: x(_range.start),
                        width: (x(_range.end) - x(_range.start)).clamp(0, double.infinity),
                        top: 6,
                        height: 22,
                        child: Container(
                          decoration: BoxDecoration(
                            color: Tally.gold.withValues(alpha: 0.14),
                            borderRadius: BorderRadius.circular(6),
                          ),
                        ),
                      ),
                      if (bout < _range.end)
                        Positioned(
                          left: x(bout) - 1,
                          top: 0,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              Container(width: 2, height: 34, color: Tally.gold),
                            ],
                          ),
                        ),
                      if (playing)
                        Positioned(left: x(position) - 1, top: 6, child: Container(width: 2, height: 22, color: Colors.white)),
                      Positioned(
                        left: 0,
                        right: 0,
                        top: 18,
                        child: SliderTheme(
                          data: SliderThemeData(
                            trackHeight: 3,
                            activeTrackColor: Tally.gold,
                            inactiveTrackColor: Colors.white.withValues(alpha: 0.14),
                            thumbColor: Tally.goldLight,
                            rangeThumbShape: const RoundRangeSliderThumbShape(enabledThumbRadius: 9),
                            overlayShape: const RoundSliderOverlayShape(overlayRadius: 18),
                          ),
                          child: RangeSlider(
                            min: 0,
                            max: _length <= 0 ? 1 : _length,
                            values: _range,
                            onChanged: _drag,
                            onChangeEnd: (_) {
                              if (playing) unawaited(_player?.pause());
                            },
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              }),

              Row(
                children: [
                  _time('STARTS', _range.start),
                  const Spacer(),
                  _time('BOUT STARTS', _range.start + _intro, gold: true),
                  const Spacer(),
                  _time('ENDS', _range.end),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'The VS screen plays the first 4 seconds; the bout starts at the gold mark — put a beat or a drop there. '
                'The last ${(_range.end - _range.start) / 2 < 3 ? ((_range.end - _range.start) / 2).toStringAsFixed(1) : '3'} '
                'seconds fade away under the bout.',
                style: Tally.body(11, color: Tally.textFaint),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(child: _button(playing ? Icons.pause : Icons.play_arrow_rounded, playing ? 'STOP' : 'LISTEN', _preview, Tally.textSecondary)),
                  const SizedBox(width: 10),
                  Expanded(child: _button(Icons.check, _busy ? 'SAVING…' : 'USE THIS', _busy ? () {} : _save, Tally.gold)),
                ],
              ),
            ],

            if (_note != null) ...[
              const SizedBox(height: 14),
              Text(_note!, style: Tally.body(12, color: Tally.amberText)),
            ],

            if (current != null) ...[
              const SizedBox(height: 28),
              _button(Icons.restart_alt, 'BACK TO THE BUILT-IN MUSIC', () async {
                await widget.vault.clearMusic();
                if (mounted) setState(() {});
              }, Tally.textMuted),
            ],
          ],
        ),
      ),
    );
  }

  Widget _time(String label, double s, {bool gold = false}) => Column(
        children: [
          Text(label, style: Tally.label(9.5, color: gold ? Tally.gold : Tally.textMuted, tracking: 0.14, weight: FontWeight.w700)),
          Text(_t(s), style: Tally.number(14, color: gold ? Tally.goldLight : Tally.text, weight: FontWeight.w700)),
        ],
      );

  Widget _button(IconData icon, String label, VoidCallback onTap, Color colour) => GestureDetector(
        onTap: onTap,
        child: Container(
          height: 48,
          decoration: BoxDecoration(
            color: colour.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: colour.withValues(alpha: 0.45)),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 19, color: colour),
              const SizedBox(width: 8),
              Text(label, style: Tally.label(12.5, color: colour, tracking: 0.12, weight: FontWeight.w700)),
            ],
          ),
        ),
      );
}
