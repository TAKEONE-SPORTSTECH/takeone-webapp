import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import 'clips.dart';
import 'recorder.dart';
import 'kit.dart';

/// Watch one bout back, at the tripod.
///
/// "Did that record?" is asked about thirty seconds after a final, by somebody
/// standing next to the mat, and the answer has to be watchable there and then.
///
/// Deliberately not an editor: play, pause, scrub, close. No trim, no export,
/// no share — the video is in the phone's own gallery, where every one of those
/// tools already exists and is better than anything built here.
///
/// It also knows when to get out of the way: if the mat starts a bout while
/// somebody is watching yesterday's, the station closes this and the camera is
/// aiming again before the first exchange.
class ClipPlayer extends StatefulWidget {
  const ClipPlayer({super.key, required this.clip});

  final CameraClip clip;

  static Future<void> open(BuildContext context, CameraClip clip) {
    return Navigator.of(context).push(MaterialPageRoute(
      fullscreenDialog: true,
      builder: (_) => ClipPlayer(clip: clip),
    ));
  }

  @override
  State<ClipPlayer> createState() => _ClipPlayerState();
}

class _ClipPlayerState extends State<ClipPlayer> {
  VideoPlayerController? _controller;
  String? _fault;

  /// The chrome fades out of the way, because the frame is the point.
  bool _chrome = true;
  Timer? _fade;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _fade?.cancel();
    _controller?.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      // The gallery handle when the clip was published (the normal case), the
      // private file when it was not. Same recording, different door.
      final uri = widget.clip.uri;

      var controller = uri != null
          ? VideoPlayerController.contentUri(Uri.parse(uri))
          : VideoPlayerController.file(File(widget.clip.file));

      try {
        await controller.initialize();
      } on Object catch (first) {
        // A published clip that will not open is usually a media-library row
        // whose pending flag was never cleared: the whole recording is there and
        // nothing will read it. We own the row, so ask the platform to clear it
        // and try once more before telling an operator their bout is gone.
        await controller.dispose();

        if (uri == null || !await Recorder.repairVideo(uri)) {
          // Not repairable. If the private original is still on the phone — it
          // is, for anything this build published — play that instead.
          final original = File(widget.clip.file);

          if (uri == null || !original.existsSync()) rethrow;

          controller = VideoPlayerController.file(original);
        } else {
          controller = VideoPlayerController.contentUri(Uri.parse(uri));
        }

        debugPrint('takeone: clip needed recovery — ${first.runtimeType}: $first');
        await controller.initialize();
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
      _restartFade();
    } catch (e) {
      // The reason, not just the verdict. A volunteer cannot act on it, but the
      // person they hand the phone to can, and "could not be opened" told
      // nobody anything the last time this happened.
      debugPrint('takeone: clip failed to open — ${e.runtimeType}: $e');

      if (mounted) setState(() => _fault = 'This clip could not be opened.');
    }
  }

  void _restartFade() {
    _fade?.cancel();
    _fade = Timer(const Duration(seconds: 3), () {
      if (mounted && (_controller?.value.isPlaying ?? false)) setState(() => _chrome = false);
    });
  }

  void _toggle() {
    final controller = _controller;
    if (controller == null) return;

    setState(() {
      controller.value.isPlaying ? controller.pause() : controller.play();
      _chrome = true;
    });

    _restartFade();
  }

  static String _clock(Duration d) =>
      '${d.inMinutes.toString().padLeft(2, '0')}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final controller = _controller;
    final value = controller?.value;

    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onTap: () {
          if (!_chrome) {
            setState(() => _chrome = true);
            _restartFade();

            return;
          }

          _toggle();
        },
        child: Stack(
          children: [
            Center(
              child: _fault != null
                  ? Text(_fault!, style: Cam.body(15, color: Cam.gold))
                  : controller == null || value == null || !value.isInitialized
                      ? const CircularProgressIndicator(strokeWidth: 2.4, color: Cam.gold)
                      : AspectRatio(
                          aspectRatio: value.aspectRatio,
                          child: VideoPlayer(controller),
                        ),
            ),

            AnimatedOpacity(
              opacity: _chrome ? 1 : 0,
              duration: const Duration(milliseconds: 220),
              child: IgnorePointer(
                ignoring: !_chrome,
                child: Stack(
                  children: [
                    const Positioned(top: 0, left: 0, right: 0, child: CamScrim(height: 92, opacity: 0.7)),
                    Positioned(
                      top: 12,
                      left: 44,
                      right: 44,
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Text(widget.clip.matchNumber ?? '—', style: Cam.num(16, color: Cam.gold)),
                                    const SizedBox(width: 10),
                                    Flexible(
                                      child: Text(
                                        widget.clip.subtitle.toUpperCase(),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: Cam.cap(14, tracking: 0.06),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  '${_clock(widget.clip.length)}'
                                  '${widget.clip.uri != null ? ' · IN GALLERY' : ' · IN APP STORAGE'}',
                                  style: Cam.cap(11, color: Cam.paper.withValues(alpha: 0.5), tracking: 0.08),
                                ),
                              ],
                            ),
                          ),
                          GestureDetector(
                            onTap: () => Navigator.of(context).pop(),
                            behavior: HitTestBehavior.opaque,
                            child: const SizedBox(
                              width: 48,
                              height: 48,
                              child: Icon(Icons.close, color: Cam.paper),
                            ),
                          ),
                        ],
                      ),
                    ),

                    if (controller != null && value != null && value.isInitialized)
                      Positioned(
                        left: 44,
                        right: 44,
                        bottom: 14,
                        child: Row(
                          children: [
                            GestureDetector(
                              onTap: _toggle,
                              behavior: HitTestBehavior.opaque,
                              child: Container(
                                width: 56,
                                height: 56,
                                decoration: const BoxDecoration(color: Cam.paper, shape: BoxShape.circle),
                                child: Icon(
                                  value.isPlaying ? Icons.pause : Icons.play_arrow,
                                  color: Cam.ink,
                                  size: 28,
                                ),
                              ),
                            ),
                            const SizedBox(width: 14),
                            Text(_clock(value.position), style: Cam.num(14)),
                            Expanded(
                              child: SliderTheme(
                                data: SliderThemeData(
                                  trackHeight: 4,
                                  activeTrackColor: Cam.gold,
                                  inactiveTrackColor: Cam.paper.withValues(alpha: 0.2),
                                  thumbColor: Cam.gold,
                                  thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 9),
                                  overlayShape: const RoundSliderOverlayShape(overlayRadius: 18),
                                ),
                                child: Slider(
                                  value: value.position.inMilliseconds
                                      .clamp(0, value.duration.inMilliseconds)
                                      .toDouble(),
                                  max: value.duration.inMilliseconds.toDouble().clamp(1, double.infinity),
                                  onChanged: (ms) {
                                    controller.seekTo(Duration(milliseconds: ms.round()));
                                    _restartFade();
                                  },
                                ),
                              ),
                            ),
                            Text(_clock(value.duration), style: Cam.num(14, color: Cam.paper.withValues(alpha: 0.6))),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
