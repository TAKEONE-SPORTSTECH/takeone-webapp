import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';

import 'api.dart';
import 'kept.dart';
import 'parts.dart';
import 'producer.dart';
import 'tally.dart';
import 'vault.dart';

/// Sending a bout from the phone — WhatsApp, or any app the phone offers.
///
/// Two ways to send, and the sheet picks the better one by itself:
///
///   · WITH THE SCORE BAR. The phone knows who fought, but not the score: the
///     points are logged on the mat's scoring tablet and kept by the event. So
///     when this clip has reached the event, the phone asks the event for its
///     copy — score bar burned into the picture, every point a chapter, the
///     points written out as the message — downloads it, and sends that.
///   · PLAIN. When the event cannot make one (not uploaded yet, no bout loaded
///     when it was filmed, nothing scored, no network), the phone shrinks its
///     own file the way WhatsApp would — 1080p, 30 fps, a capped bitrate, on
///     the phone's own video chip — and sends that instead, and says so.
///
/// Nothing here deletes or changes the recording. Every video it makes goes
/// into the phone's gallery (Movies/Sent bouts) and is remembered by what made
/// it (lib/kept.dart): sending the same bout again with nothing changed hands
/// back the one already made instead of making it twice.
Future<void> showSendSheet(BuildContext context, {required Clip clip, required ClipVault vault}) {
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    isScrollControlled: true,
    builder: (_) => _SendSheet(clip: clip, vault: vault),
  );
}

enum _Phase { choose, uploading, asking, making, downloading, rendering, shrinking, handing, failed }

class _SendSheet extends StatefulWidget {
  const _SendSheet({required this.clip, required this.vault});

  final Clip clip;
  final ClipVault vault;

  @override
  State<_SendSheet> createState() => _SendSheetState();
}

class _SendSheetState extends State<_SendSheet> {
  static const _native = MethodChannel('bh.takeone.lab/share');

  _Phase _phase = _Phase.choose;
  double? _progress;
  String? _note;
  Timer? _poll;
  bool _gone = false;

  /// The event can only make a score-bar copy of a clip it has received.
  bool get _atEvent =>
      widget.clip.settled && widget.clip.serverId != null && widget.vault.token != null;

  /// Not at the event yet, but it can be: it is a known bout and this camera
  /// is connected. One tap sends it first and carries straight on.
  bool get _reachable =>
      !_atEvent && widget.clip.matchId != null && !widget.clip.held && widget.vault.token != null;

  /// Whether the event has been ASKED (POST) for this copy yet. Checking back
  /// is a GET; asking spends a render, so it happens once.
  bool _asked = false;

  /// Give up waiting for the vault to file a clip after this long.
  DateTime? _arrivingSince;

  /// The score bar for this clip as the event describes it, fetched once
  /// when the sheet opens: with it, the phone burns the bar in and cuts the
  /// highlights itself — no upload. Null until it arrives, or when the event
  /// has no bout for this clip.
  Map<String, dynamic>? _plan;
  bool _planning = true;

  bool get _local => _plan != null;
  bool get _localHighlights => ((_plan?['windows'] as List?)?.isNotEmpty ?? false);


  /* ── The live preview: what will be burned, on a real frame of this bout ── */

  Uint8List? _pvFrame;
  double _pvAspect = 16 / 9;
  Uint8List? _pvBar;
  Uint8List? _pvVs;
  bool _pvVsMode = false;

  /// The closing statistics card, previewed as it will end the video.
  Uint8List? _pvStats;
  bool _pvStatsMode = false;
  Timer? _pvSoon;

  /// A moment worth previewing: just after the first scoring moment, else a
  /// third of the way in.
  double get _pvAt {
    final windows = (_plan?['windows'] as List?) ?? const [];
    if (windows.isNotEmpty) {
      final w = windows.first as List;
      return ((w[0] as num).toDouble() + (w[1] as num).toDouble()) / 2;
    }
    final s = widget.clip.seconds > 0 ? widget.clip.seconds : 60;
    return s / 3;
  }

  Future<void> _loadFrame() async {
    try {
      final png = await _native.invokeMethod<Uint8List>('frame', {
        'path': widget.clip.path,
        'atMs': (_pvAt * 1000).round(),
        'width': 960,
      });
      if (png == null || _gone) return;
      // The bout's own shape — upright or landscape — sizes the preview.
      final image = await decodeImageFromList(png);
      setState(() {
        _pvFrame = png;
        _pvAspect = image.height == 0 ? 16 / 9 : image.width / image.height;
      });
      unawaited(_refreshPreview());
    } catch (_) {}
  }

  void _previewSoon({bool? vs, bool? stats}) {
    if (vs != null) _pvVsMode = vs;
    if (stats != null) _pvStatsMode = stats;
    if (vs == true) _pvStatsMode = false;
    if (stats == true) _pvVsMode = false;
    _pvSoon?.cancel();
    _pvSoon = Timer(const Duration(milliseconds: 90), _refreshPreview);
    if (!_gone) setState(() {});
  }

  Future<void> _refreshPreview() async {
    if (_plan == null || _gone) return;
    final plan = jsonEncode(_plan);

    try {
      if (_pvStatsMode) {
        final upright = _pvAspect < 1;
        final png = await _native.invokeMethod<Uint8List>('statsCard', {
          'plan': plan, 'width': upright ? 540 : 960, 'height': upright ? 960 : 540,
        });
        if (!_gone) setState(() => _pvStats = png);
      } else if (_pvVsMode) {
        final upright = _pvAspect < 1;
        final png = await _native.invokeMethod<Uint8List>('vs', {
          'plan': plan, 'width': upright ? 540 : 960, 'height': upright ? 960 : 540, 'vsLift': widget.vault.vsLift,
        });
        if (!_gone && png != null) setState(() => _pvVs = png);
      } else {
        final png = await _native.invokeMethod<Uint8List>('preview', {
          'plan': plan,
          'key': 'sheet|${widget.clip.ref}',
          'width': _pvAspect < 1 ? 540 : 960,
          'height': _pvAspect < 1 ? (540 / _pvAspect).round() : (960 / _pvAspect).round(),
          'at': _pvAt,
          'parts': widget.vault.parts.where((p) => p != 'intro' && p != 'music').toList(),
          'lift': widget.vault.lift,
        });
        if (!_gone && png != null) setState(() => _pvBar = png);
      }
    } catch (_) {}
  }

  Widget _preview() {
    final vsOn = widget.vault.parts.contains('intro');
    final showVs = _pvVsMode && vsOn && !_pvStatsMode;
    final statsOn = widget.vault.parts.contains('stats') && _plan?['stats'] != null;
    final showStats = _pvStatsMode && statsOn;

    Widget tab(String label, bool on, VoidCallback tap) => GestureDetector(
          onTap: tap,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: on ? Tally.gold : Colors.black.withValues(alpha: 0.55),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(label, style: Tally.label(9.5, color: on ? Tally.navyDeep : Tally.text, tracking: 0.12, weight: FontWeight.w700)),
          ),
        );

    // An upright bout previews upright, kept to a height the sheet can hold.
    return Center(
      child: ConstrainedBox(
      constraints: const BoxConstraints(maxHeight: 300),
      child: ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: AspectRatio(
        aspectRatio: _pvAspect,
        child: Stack(
          fit: StackFit.expand,
          children: [
            const ColoredBox(color: Colors.black),
            if (showStats && _pvStats != null)
              Image.memory(_pvStats!, fit: BoxFit.contain, gaplessPlayback: true)
            else if (showVs && _pvVs != null)
              Image.memory(_pvVs!, fit: BoxFit.contain, gaplessPlayback: true)
            else ...[
              if (_pvFrame != null) Image.memory(_pvFrame!, fit: BoxFit.cover, gaplessPlayback: true),
              if (_pvBar != null) Image.memory(_pvBar!, fit: BoxFit.fill, gaplessPlayback: true),
            ],
            Positioned(
              left: 8,
              top: 8,
              child: Row(children: [
                tab('BOUT', !showVs && !showStats, () => _previewSoon(vs: false, stats: false)),
                const SizedBox(width: 6),
                if (vsOn) tab('VS SCREEN', showVs, () => _previewSoon(vs: true)),
                if (vsOn) const SizedBox(width: 6),
                if (statsOn) tab('STATS', showStats, () => _previewSoon(stats: true)),
              ]),
            ),
          ],
        ),
      ),
      ),
      ),
    );
  }

  Future<void> _fetchPlan() async {
    final plan = await widget.vault.planFor(widget.clip);

    if (_gone) return;
    setState(() {
      _plan = plan;
      _planning = false;
    });
    if (plan != null) {
      unawaited(_loadFrame());
      unawaited(_refreshPreview());
    }
  }

  void _tick() {
    if (!_gone && _phase == _Phase.uploading) setState(() => _progress = widget.clip.fraction);
  }

  @override
  void initState() {
    super.initState();
    widget.vault.watch(_tick);
    unawaited(_sweep());
    unawaited(KeptVideos.tidy());

    // A recovered clip may not have its bout yet — ask, so the choices below
    // are the real ones by the time a finger reaches them. Then the score bar.
    unawaited(() async {
      if (widget.clip.held || widget.clip.matchId == null) await widget.vault.identify(force: true);
      if (_gone) return;
      setState(() {});
      await _fetchPlan();
    }());
  }

  @override
  void dispose() {
    _gone = true;
    _poll?.cancel();
    _pvSoon?.cancel();
    widget.vault.unwatch(_tick);
    super.dispose();
  }

  void _set(_Phase phase, {double? progress, String? note}) {
    if (_gone) return;
    setState(() {
      _phase = phase;
      _progress = progress;
      if (note != null) _note = note;
    });
  }

  /* ── The two roads ─────────────────────────────────────────────────── */

  Future<void> _send(String kind) async {
    _note = null;
    _asked = false;
    _arrivingSince = null;

    // Made on this phone from the event's score: the first choice whenever the
    // event described the bout. The server's own copy only when the clip is
    // already there and the phone has no plan.
    if (_local && (kind == 'full' || _localHighlights)) {
      await _render(kind);

      return;
    }

    if (_reachable) {
      _set(_Phase.uploading, progress: widget.clip.fraction);

      final problem = await widget.vault.sendFirst(widget.clip);

      if (_gone) return;

      if (problem != null) {
        if (kind == 'highlights') {
          _set(_Phase.failed, note: problem);

          return;
        }

        await _plain(note: '$problem So it goes without the score bar.');

        return;
      }
    }

    if (!_atEvent) {
      if (kind == 'highlights') {
        _set(_Phase.failed, note: widget.clip.held
            ? 'The event this camera is paired to does not know this clip. Pair the camera to the event it was filmed at.'
            : 'This clip was filmed with no bout loaded on the mat, so there is no score to cut highlights from.');

        return;
      }

      await _plain(note: 'This clip has no bout at the event, so it goes without the score bar.');

      return;
    }

    _set(_Phase.asking);
    final api = CameraApi(widget.vault.token);
    final first = await api.share(widget.clip.serverId!, kind, ask: true);

    await _follow(api, kind, first);
  }

  /// Act on what the event said, checking back while it is being made.
  Future<void> _follow(CameraApi api, String kind, ({int status, Map<String, dynamic>? body}) answer) async {
    if (_gone) return;

    final body = answer.body;
    final state = body?['state'];

    if (answer.status == 200 && state == 'ready' && body?['file'] is String) {
      await _fromEvent(body!);

      return;
    }

    if (answer.status == 200 && state == 'working') {
      _asked = true;
      _set(_Phase.making);
      _poll?.cancel();
      _poll = Timer(const Duration(seconds: 5), () async {
        final next = await api.share(widget.clip.serverId!, kind, ask: false);
        await _follow(api, kind, next);
      });

      return;
    }

    // Every byte arrived and the vault is filing it — seconds, usually. Check
    // back (a read, not a new request), then ask once it is filed.
    if (answer.status == 200 && state == 'arriving') {
      _arrivingSince ??= DateTime.now();

      if (DateTime.now().difference(_arrivingSince!) > const Duration(minutes: 4)) {
        _set(_Phase.failed, note: 'The event has the clip and is still filing it. Try again in a minute.');

        return;
      }

      _set(_Phase.making);
      _poll?.cancel();
      _poll = Timer(const Duration(seconds: 4), () async {
        final next = await api.share(widget.clip.serverId!, kind, ask: false);
        await _follow(api, kind, next);
      });

      return;
    }

    // Filed now, but nobody has asked for this copy yet: ask.
    if (answer.status == 200 && state == 'idle' && !_asked) {
      _asked = true;
      _set(_Phase.asking);
      await _follow(api, kind, await api.share(widget.clip.serverId!, kind, ask: true));

      return;
    }

    // Anything else: no score-bar copy to be had. Whole bouts still go, plain.
    if (kind == 'highlights') {
      _set(_Phase.failed, note: answer.status == 0
          ? 'No connection to the event. Highlights need it — try again on the hall wifi.'
          : 'The event cannot cut highlights for this clip (nothing was scored while it filmed).');

      return;
    }

    await _plain(note: answer.status == 0
        ? 'No connection to the event, so it goes without the score bar.'
        : answer.status == 404
            ? 'The event has no score for this clip, so it goes without the score bar.'
            : 'The event could not make the score-bar version, so it goes without it.');
  }

  Future<void> _fromEvent(Map<String, dynamic> body) async {
    // The event's copy changes when its score does — and so do its size and
    // its message. Same of all three: the one already downloaded.
    final recipe = <String, dynamic>{
      'how': 'event',
      'clip': widget.clip.serverId,
      'kind': body['kind'],
      'bytes': body['bytes'],
      'filename': body['filename'],
      'message': body['message'],
    };
    final message = (body['message'] as String?) ?? _caption();
    final had = await KeptVideos.find(recipe);

    if (had != null) {
      await _hand(had.path, message);

      return;
    }

    final dir = await _shareDir();
    final file = File('${dir.path}/${_safe((body['filename'] as String?) ?? 'bout.mp4')}');

    _set(_Phase.downloading, progress: 0);

    final ok = await CameraApi.download(body['file'] as String, file, progress: (p) {
      if (!_gone && _phase == _Phase.downloading) setState(() => _progress = p);
    });

    if (!ok) {
      await _plain(note: 'The score-bar version would not download, so it goes without it.');

      return;
    }

    await _hand(await KeptVideos.keep(recipe, file.path), message);
  }

  /// The score bar burned in on this phone, whole or cut to the highlights —
  /// or, when nothing that decides the video has changed, the one this phone
  /// already made (lib/producer.dart).
  Future<void> _render(String kind) async {
    _set(_Phase.rendering, progress: 0);

    final made = await Producer.make(widget.clip, widget.vault, _plan!, kind, progress: (p) {
      if (!_gone && _phase == _Phase.rendering) setState(() => _progress = p);
    });

    if (_gone) return;

    if (made.path == null) {
      // The phone's own words, in full: what to send back so it can be fixed.
      _set(_Phase.failed, note: 'This phone could not make the ${kind == 'highlights' ? 'highlights' : 'video'}.'
          '${made.error == null ? '' : '\n\nPhone says: ${made.error}'}');

      return;
    }

    _droppedNotice(made.dropped);

    await _hand(made.path!, (_plan!['message'] as String?) ?? _caption());
  }

  void _droppedNotice(List<dynamic> dropped) {
    if (dropped.isEmpty || !mounted) return;

    final what = dropped.contains('stats')
        ? 'the VS screen, its music and the stats card'
        : dropped.contains('intro') ? 'the VS screen and its music' : 'the VS music';
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      backgroundColor: Tally.navyPrimary,
      content: Text('This phone could not add $what, so the video goes without it.', style: Tally.body(12.5)),
    ));
  }

  /// The phone's own copy, made smaller the way WhatsApp would.
  Future<void> _plain({String? note}) async {
    final source = File(widget.clip.path);

    if (!source.existsSync()) {
      _set(_Phase.failed, note: 'That file is no longer on this phone.');

      return;
    }

    final recipe = <String, dynamic>{'how': 'plain', 'source': KeptVideos.fileStamp(source.path)};
    final had = await KeptVideos.find(recipe);

    if (had != null) {
      if (note != null) _note = note;
      await _hand(had.path, _caption());

      return;
    }

    _set(_Phase.shrinking, note: note);

    final dir = await _shareDir();
    final out = '${dir.path}/${_safe(widget.clip.ref.replaceAll('.mp4', ''))}-send.mp4';

    String? made;
    try {
      made = await _native.invokeMethod<String>('compress', {'input': source.path, 'output': out});
    } on PlatformException {
      made = null;
    }

    // A phone whose video chip refuses the job still has the original.
    await _hand(made == null ? source.path : await KeptVideos.keep(recipe, made), _caption());
  }

  Future<void> _hand(String path, String text) async {
    _set(_Phase.handing);

    try {
      await _native.invokeMethod<void>('send', {'path': path, 'text': text, 'title': widget.clip.corners});
    } on PlatformException catch (e) {
      _set(_Phase.failed, note: 'Android could not open the share sheet (${e.code}).');

      return;
    }

    if (mounted) Navigator.of(context).maybePop();
  }

  /* ── Small things ──────────────────────────────────────────────────── */

  String _caption() {
    final stage = widget.clip.stage;

    return [
      widget.clip.corners,
      [widget.clip.title, if (stage != null && stage.isNotEmpty) stage].join(' · '),
      if (widget.clip.event != null && widget.clip.event!.isNotEmpty) widget.clip.event!,
    ].join('\n');
  }

  static String _safe(String name) => Producer.safe(name);

  Future<Directory> _shareDir() async {
    final dir = Directory('${(await getTemporaryDirectory()).path}/share');
    if (!dir.existsSync()) dir.createSync(recursive: true);

    return dir;
  }

  /// Copies handed out yesterday have done their job.
  Future<void> _sweep() async {
    try {
      final dir = await _shareDir();
      final cutoff = DateTime.now().subtract(const Duration(days: 1));

      for (final f in dir.listSync().whereType<File>()) {
        if (f.lastModifiedSync().isBefore(cutoff)) f.deleteSync();
      }
    } catch (_) {}
  }

  /* ── Drawing ───────────────────────────────────────────────────────── */

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Tally.navyPanel,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.fromLTRB(18, 10, 18, 18 + MediaQuery.of(context).padding.bottom),
      // Scrolls when the phone is on its side and the choices outgrow it.
      child: SingleChildScrollView(child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(99)),
            ),
          ),
          const SizedBox(height: 14),
          Text('SEND THIS BOUT', style: Tally.label(13, color: Tally.gold, tracking: 0.18)),
          const SizedBox(height: 4),
          Text(widget.clip.corners, style: Tally.body(13, color: Tally.textSecondary)),
          const SizedBox(height: 16),
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 220),
            child: _phase == _Phase.choose ? _choices() : _working(),
          ),
        ],
      )),
    );
  }

  Widget _choices() {
    return Column(
      key: const ValueKey('choose'),
      children: [
        // What the bar carries when it is burned in — the same switches as the
        // player's, so the choice made while watching is already set here.
        if (_local) ...[
          Align(
            alignment: Alignment.centerLeft,
            child: Text('BURN INTO THE VIDEO', style: Tally.label(10, color: Tally.textMuted, tracking: 0.16, weight: FontWeight.w700)),
          ),
          const SizedBox(height: 8),
          _preview(),
          const SizedBox(height: 12),
          BarParts(
            vault: widget.vault,
            note: null,
            onChanged: () => _previewSoon(vs: false),
            onVsChanged: () => _previewSoon(vs: true),
          ),
          const SizedBox(height: 14),
        ],
        _Choice(
          icon: Icons.movie_outlined,
          title: 'Whole bout',
          hint: _local
              ? 'With the score bar and every point — made on this phone'
              : _planning
                  ? 'Checking the event for the score…'
                  : _atEvent
                      ? 'With the score bar and every point'
                      : _reachable
                          ? 'Sends it to the event, then adds the score bar and every point'
                          : 'Plain video — the event has no score for this clip',
          onTap: () => _send('full'),
        ),
        const SizedBox(height: 10),
        _Choice(
          icon: Icons.auto_awesome,
          title: 'Highlights',
          hint: _localHighlights
              ? '${(_plan!['windows'] as List).length} scoring moments, score bar on — made on this phone'
              : _planning
                  ? 'Checking the event for the score…'
                  : _local
                      ? 'Nothing was scored while this clip was filming'
                      : _atEvent
                          ? 'Only the scoring moments, score bar on'
                          : _reachable
                              ? 'Sends it to the event, then cuts the scoring moments'
                              : 'The event has no score for this clip',
          enabled: _localHighlights || (!_planning && !_local && (_atEvent || _reachable)),
          onTap: () => _send('highlights'),
        ),
        const SizedBox(height: 10),
        _Choice(
          icon: Icons.bolt,
          title: 'Plain, right now',
          hint: 'No score bar — made on this phone, no internet needed',
          onTap: () => _plain(),
        ),
      ],
    );
  }

  Widget _working() {
    final (label, detail) = switch (_phase) {
      _Phase.uploading => (
          'SENDING TO THE EVENT  ${((_progress ?? 0) * 100).toStringAsFixed(0)}%',
          'The whole recording goes up once, then the event cuts it. Keep this open, on the hall wifi.'
        ),
      _Phase.rendering => (
          'ADDING THE SCORE BAR  ${((_progress ?? 0) * 100).toStringAsFixed(0)}%',
          'On this phone, from the event\'s score. Keep this open.'
        ),
      _Phase.asking => ('ASKING THE EVENT', 'For the version with the score bar.'),
      _Phase.making => ('ADDING THE SCORE BAR', 'The event is making it — about as long as the bout. Keep this open.'),
      _Phase.downloading => ('DOWNLOADING', 'The version with the score bar.'),
      _Phase.shrinking => ('MAKING IT SMALLER', 'On this phone, the way WhatsApp would.'),
      _Phase.handing => ('OPENING SHARE', 'Pick WhatsApp, then the contact.'),
      _Phase.failed => ('COULD NOT SEND', ''),
      _Phase.choose => ('', ''),
    };

    final failed = _phase == _Phase.failed;

    return Column(
      key: ValueKey(_phase),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(label, style: Tally.label(12, color: failed ? Tally.amberText : Tally.text, tracking: 0.16)),
        if (detail.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(detail, style: Tally.body(12.5, color: Tally.textMuted)),
        ],
        if (!failed) ...[
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: _progress,
              minHeight: 6,
              backgroundColor: Colors.white10,
              color: Tally.gold,
            ),
          ),
        ],
        if (_note != null) ...[
          const SizedBox(height: 12),
          // Selectable, so the phone's own error can be copied and sent on.
          SelectableText(_note!, style: Tally.body(12, color: failed ? Tally.amberText : Tally.textFaint)),
        ],
        if (failed) ...[
          const SizedBox(height: 14),
          TextButton(
            onPressed: () => _set(_Phase.choose),
            child: Text('BACK', style: Tally.label(11, color: Tally.gold, tracking: 0.16)),
          ),
        ],
      ],
    );
  }
}

class _Choice extends StatelessWidget {
  const _Choice({
    required this.icon,
    required this.title,
    required this.hint,
    required this.onTap,
    this.enabled = true,
  });

  final IconData icon;
  final String title;
  final String hint;
  final VoidCallback onTap;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: enabled ? 1 : 0.45,
      child: Material(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: enabled ? onTap : null,
          borderRadius: BorderRadius.circular(14),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: Tally.gold.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: Tally.gold, size: 21),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: Tally.number(15, weight: FontWeight.w700)),
                      const SizedBox(height: 2),
                      Text(hint, style: Tally.body(12, color: Tally.textMuted)),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right, color: Tally.textFaint),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
