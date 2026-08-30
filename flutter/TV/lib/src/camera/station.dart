import 'dart:async';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:flutter/services.dart';

import 'api.dart';
import 'clips.dart';
import 'drawer.dart';
import 'kit.dart';
import 'link.dart';
import 'player.dart';
import 'recorder.dart';
import 'uploader.dart';

/// A phone that is one of a mat's cameras.
///
/// The whole app, and it has exactly two screens because it only ever has two
/// jobs:
///
///   Unclaimed — stand there showing a QR until an organiser scans it and says
///               which mat this is. It can do nothing else; it does not even
///               open the lens.
///   Claimed   — hold the camera open, wait for the mat, roll when told, stop
///               when told, and keep a list of what it filmed.
///
/// Nobody presses record. That is the feature: an operator with four phones
/// around a mat cannot start four recordings at hajime and stop four at the
/// decision, and if they try, the one they fumble is the bout somebody wants to
/// see. The scoring table already knows when a bout starts — so the table
/// starts the cameras.
///
/// Written to survive a day rather than a demo: every network call may fail
/// silently, the clip is written to disk before anybody is told about it, a
/// repeated command cannot truncate a file, and the config poll re-syncs a
/// phone that missed a message or was restarted mid-bout.
class CameraStation extends StatefulWidget {
  const CameraStation({super.key});

  @override
  State<CameraStation> createState() => _CameraStationState();
}

class _CameraStationState extends State<CameraStation> with WidgetsBindingObserver, TickerProviderStateMixin {
  static const _tokenKey = 'takeone.camera.token';

  /// The operator's framing. Kept on the phone because it belongs to where the
  /// phone is STANDING, not to the event: a camera restarted between bouts must
  /// come back at the same zoom and the same exposure, or somebody has to
  /// re-aim it while a mat waits.
  static const _setupKey = 'takeone.camera.setup';

  /// Unclaimed: ask often, because somebody is standing there waiting for the
  /// screen to change. Claimed: every 20s, as a beat and a backstop.
  static const _pairPoll = Duration(seconds: 4);
  static const _beat = Duration(seconds: 20);

  String? _token;
  final _recorder = Recorder();
  CameraLink? _link;
  Timer? _timer;

  // What the server last told us we are.
  bool _claimed = false;
  String? _code;
  String? _claimUrl;
  String? _eventTitle;
  String? _court;
  int? _angle;

  // What we are doing about it.
  Map<String, dynamic>? _bout;
  List<CameraClip> _clips = [];
  String? _fault;

  /// H1 — the lens was refused. A takeover rather than a message, because
  /// nothing else on this screen is worth reading until it is fixed.
  bool _permissionRefused = false;

  /// E/F — the clip drawer, and whether it opened straight into multi-select
  /// (which is how "free up space" arrives from the storage banner).
  bool _drawerOpen = false;
  bool _drawerSelecting = false;

  /// H2 — the banner is dismissible; the amber chip behind it is not.
  bool _storageBannerOpen = true;

  /// The rail is a visitor: it appears when asked for and leaves by itself, so
  /// the picture is unobstructed the other 99% of the day.
  bool _railOpen = false;
  Timer? _railTimer;

  /// When the current recording started, for the elapsed clock, and a ticker
  /// to move it. Only runs while rolling — a timer redrawing the screen every
  /// second all day would cost frames for nothing.
  DateTime? _recordingSince;
  Timer? _tick;

  /// Since when the mat has been unreachable, for H3's counter.
  DateTime? _offlineSince;

  /// The REC dot's breath. One controller, started and stopped with recording.
  late final AnimationController _recPulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  );
  int? _batteryPercent;
  int? _storageFree;
  int? _storageTotal;
  bool _linkUp = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _boot();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    _tick?.cancel();
    _railTimer?.cancel();
    _recPulse.dispose();
    _link?.close();
    _recorder.close();
    super.dispose();
  }

  /// A camera must never be left rolling into a paused app, and must come back
  /// knowing what the mat is doing now rather than what it was doing when the
  /// screen went off.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _sync();
  }

  Future<void> _boot() async {
    _clips = await ClipLog.load();

    final prefs = await SharedPreferences.getInstance();

    _recorder.zoom = prefs.getDouble('$_setupKey.zoom') ?? 1;
    _recorder.exposure = prefs.getDouble('$_setupKey.exposure') ?? 0;
    _recorder.fps = prefs.getInt('$_setupKey.fps') ?? 30;
    var token = prefs.getString(_tokenKey);

    token ??= await _enrol();

    if (!mounted) return;

    setState(() => _token = token);

    _link = CameraLink(onCommand: _onCommand);
    await _sync();
    _schedule();
  }

  /// Ask the server for a new identity and keep it.
  Future<String?> _enrol() async {
    final enrolled = await CameraApi.enroll(deviceName: 'Camera', appVersion: '1.0.0');
    final token = enrolled?['token'] as String?;

    if (token != null) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, token);
    }

    return token;
  }

  /// This phone's identity no longer exists on the server. Become a new camera.
  ///
  /// The one failure that cannot be waited out. A camera's row can go — an
  /// event cleaned up, a database restored from a backup taken before this
  /// phone enrolled — and until this existed the app kept polling a dead token
  /// and kept showing a pairing code that resolved to nothing, so an organiser
  /// scanning it was told, wrongly, that it belonged to a screen. There is no
  /// state worth preserving here: an unclaimed identity is worth nothing, and
  /// the clips already recorded are indexed on the phone, not by the token.
  Future<void> _reenrol() async {
    await _link?.close();
    await _recorder.close();

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);

    final token = await _enrol();

    if (!mounted) return;

    setState(() {
      _token = token;
      _claimed = false;
      _code = null;
      _claimUrl = null;
      _court = null;
      _angle = null;
      _bout = null;
    });

    _schedule();
    if (token != null) await _sync();
  }

  Future<void> _rememberSetup() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setDouble('$_setupKey.zoom', _recorder.zoom);
    await prefs.setDouble('$_setupKey.exposure', _recorder.exposure);
    await prefs.setInt('$_setupKey.fps', _recorder.fps);
  }

  void _schedule() {
    _timer?.cancel();
    _timer = Timer.periodic(_claimed ? _beat : _pairPoll, (_) => _sync());
  }

  /// Ask the server what we are, tell it how we are, and reconcile.
  ///
  /// This is the backstop for every missed message. If the mat believes this
  /// camera should be rolling and it is not, it starts here — which is what
  /// makes an app restart mid-bout recoverable instead of a lost recording.
  Future<void> _sync() async {
    if (_token == null) return;

    final api = CameraApi(_token);
    final result = await api.configResult();

    // Gone, not unreachable: re-enrol rather than poll a dead identity.
    if (result.status == 404) {
      await _reenrol();

      return;
    }

    final config = result.body;

    if (config == null || !mounted) return;

    final claimed = config['claimed'] == true;
    final wasClaimed = _claimed;

    setState(() {
      _claimed = claimed;
      _code = config['code'] as String?;
      _claimUrl = config['claim_url'] as String?;
      _eventTitle = (config['event'] as Map<String, dynamic>?)?['title'] as String?;
      _court = config['court'] as String?;
      _angle = config['angle'] as int?;
    });

    if (claimed != wasClaimed) _schedule();

    if (!claimed) {
      // An unclaimed phone holds no lens open and no socket: it is entitled to
      // nothing, and a camera preview running for an hour on a shelf is just a
      // flat battery.
      await _link?.close();
      await _recorder.close();

      return;
    }

    if (!_recorder.ready) {
      final fault = await _recorder.open();

      if (mounted) {
        setState(() {
          _fault = fault;
          // H1 is its own screen, so it is recognised here rather than shown as
          // a message over a preview that will never arrive.
          _permissionRefused = fault != null && fault.contains('permission');
        });
      }
    }

    await _link?.attach(config['realtime'] as Map<String, dynamic>?);

    if (mounted) {
      final up = _link?.connected ?? false;

      setState(() {
        _linkUp = up;
        // H3 counts from the moment it went, not from when somebody looked.
        _offlineSince = up ? null : (_offlineSince ?? DateTime.now());
      });
    }

    // Reconcile with what the mat believes. Both directions matter: a bout that
    // started while the app was dead, and a stop that was missed.
    final shouldRoll = config['recording'] == true;

    if (shouldRoll && !_recorder.rolling) {
      await _startRolling(config['match'] as Map<String, dynamic>?);
    } else if (!shouldRoll && _recorder.rolling) {
      await _stopRolling();
    }

    await _beatTelemetry(api);
  }

  Future<void> _beatTelemetry(CameraApi api) async {
    final storage = await Recorder.storage();
    final battery = await Recorder.battery();

    if (mounted) {
      setState(() {
        _storageFree = storage?.free;
        _storageTotal = storage?.total;
        _batteryPercent = battery;
      });
    }

    final beat = await api.telemetry(
      storageTotalBytes: storage?.total,
      storageFreeBytes: storage?.free,
      batteryPercent: battery,
      recording: _recorder.rolling,
    );

    // What became of the uploads. The phone's part ends when the last chunk is
    // accepted; the transcode happens after that, so the beat is how a row
    // learns the footage is filed and gets its link.
    final states = beat?['clips'];

    if (states is List && mounted) {
      var changed = false;

      for (final raw in states) {
        if (raw is! Map) continue;

        final clip = _clips.where((c) => c.serverId == raw['id']).firstOrNull;

        if (clip == null) continue;

        final status = raw['play_status'] as String?;
        final key = raw['play_video_key'] as String?;

        if (clip.playStatus != status || clip.playVideoKey != key) {
          clip.playStatus = status;
          clip.playVideoKey = key;
          changed = true;
        }
      }

      if (changed) {
        setState(() {});
        await ClipLog.save(_clips);
      }
    }
  }

  /// A command off the mat's channel.
  void _onCommand(Map<String, dynamic> command) {
    switch (command['action']) {
      case 'record':
        _startRolling(command['match'] as Map<String, dynamic>?);
        break;
      case 'stop':
        _stopRolling();
        break;
      case 'standby':
        // No file yet — but the clip that opens on hajime is already named.
        if (mounted) setState(() => _bout = command['match'] as Map<String, dynamic>?);
        break;
      case 'paired':
      case 'unpaired':
        _sync();
        break;

      // ── Commands about footage already on this phone ───────────────────
      //
      // The console can ASK; the phone decides. That order matters most for
      // `purge`: the operator at the scoring table is not the one who knows
      // whether the server has the bytes, and the rule that matters — never
      // destroy the only copy of a bout — can only be enforced where the
      // files actually are.
      case 'upload':
        _commandUpload(command['clip'] as String?);
        break;
      case 'purge':
        unawaited(_commandPurge(command['clip'] as String?));
        break;
      case 'play':
        _commandPlay(command['clip'] as String?);
        break;
    }
  }

  /// The clip a command names, or the most recent one when it names none.
  CameraClip? _clipRef(String? ref) {
    if (_clips.isEmpty) return null;
    if (ref == null || ref.isEmpty) return _clips.first;

    for (final c in _clips) {
      if (c.ref == ref) return c;
    }

    return null;
  }

  /// Send one clip now, or every clip that has never been sent.
  void _commandUpload(String? ref) {
    if (ref == 'all') {
      for (final c in _clips) {
        if (c.playVideoKey == null && c.playStatus != 'uploading') {
          unawaited(_uploadClip(c));
        }
      }

      return;
    }

    final clip = _clipRef(ref);
    if (clip != null) unawaited(_uploadClip(clip));
  }

  /// Free space — but only footage the server has confirmed it holds.
  ///
  /// The guard is `isSafelyUploaded`, NOT `isExpendable`. They are different
  /// rules for different questions: `isExpendable` also requires the clip to be
  /// a week old, because that one governs what the drawer offers to clear
  /// UNPROMPTED, and a bout uploaded this morning should still be on the phone
  /// this afternoon when somebody asks to see it at the mat. An explicit "free
  /// space now" from the console is not that question, and answering it with
  /// the week-old rule would have made this command silently do nothing.
  ///
  /// What does not move is the part that matters: the bytes must be on the
  /// server. A bout nobody has uploaded is the ONLY copy of a fight that
  /// happened once, so this refuses it however the command was phrased and
  /// whoever sent it. That check lives here, on the phone, because the console
  /// is not where the files are.
  Future<void> _commandPurge(String? ref) async {
    final targets = <CameraClip>[];

    if (ref == 'all') {
      targets.addAll(_clips.where((c) => c.isSafelyUploaded));
    } else {
      final one = _clipRef(ref);
      if (one != null && one.isSafelyUploaded) targets.add(one);
    }

    if (targets.isEmpty) return;

    await _deleteClips(targets);
  }

  /// Put a clip on this phone's own screen, from the scoring table.
  ///
  /// Only useful while somebody is looking at the phone — a handset asleep in a
  /// pocket will not wake for this, and it deliberately does not try to.
  void _commandPlay(String? ref) {
    final clip = _clipRef(ref);

    if (clip != null && mounted) _play(clip);
  }

  Future<void> _startRolling(Map<String, dynamic>? bout) async {
    if (_recorder.rolling || !_recorder.ready) return;

    final path = await _recorder.start(
      court: _court,
      angle: _angle,
      boutNumber: bout?['number'] as String?,
    );

    if (path == null) return;

    final clip = CameraClip(
      file: path,
      startedAt: DateTime.now(),
      matchId: bout?['id'] as int?,
      matchNumber: bout?['number'] as String?,
      red: bout?['red'] as String?,
      blue: bout?['blue'] as String?,
      court: _court,
      angle: _angle,
    );

    setState(() {
      _bout = bout;
      _clips = [clip, ..._clips];
      _recordingSince = clip.startedAt;
      // Nobody should be reading a list or watching yesterday's bout when this
      // one is starting: the drawer stands aside by itself.
      _drawerOpen = false;
      _railOpen = false;
    });

    // Watching yesterday's bout while this one starts is the one thing this
    // screen must not allow: the player is the top route, so it pops.
    if (_playerOpen && mounted) Navigator.of(context).maybePop();

    _recPulse.repeat(reverse: true);
    _tick?.cancel();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && _recorder.rolling) setState(() {});
    });

    await ClipLog.save(_clips);
  }

  Future<void> _stopRolling() async {
    if (!_recorder.rolling) return;

    final result = await _recorder.stop();
    if (!mounted) return;

    final clip = _clips.isNotEmpty ? _clips.first : null;

    if (clip != null) {
      clip.endedAt = DateTime.now();
      clip.bytes = result?.bytes;
      // Where it ended up: the gallery, normally. Kept so the list can say it
      // and the player can open it.
      if (result != null) {
        clip.file = result.path;
        clip.uri = result.uri;
      }
      setState(() {});
      await ClipLog.save(_clips);

      // Told to the server AFTER the file is closed and on disk. If this fails
      // the clip is still here, still named for its bout, and still listed on
      // this screen — which is the record that matters.
      final ack = await CameraApi(_token).reportClip(
        localRef: clip.ref,
        matchId: clip.matchId,
        startedAt: clip.startedAt,
        endedAt: clip.endedAt,
        durationSeconds: clip.length.inSeconds,
        bytes: clip.bytes,
      );

      if (ack != null && mounted) {
        clip.reported = true;
        clip.serverId = ack['id'] as int?;
        setState(() {});
        await ClipLog.save(_clips);

        /*
         * On hall wifi, the bout goes up by itself.
         *
         * "On hall wifi" is not a network name — a phone cannot usefully tell
         * one SSID from another, and the thing that actually matters is whether
         * the SERVER can be reached. The clip was just filed with it and came
         * back with an id, so it can. That ack IS the signal.
         *
         * Uploading here rather than on a timer means the bytes move while the
         * mat is between bouts and the camera is idle, instead of during the
         * next one. A failure is not retried at the operator: the clip stays on
         * the phone, says `failed` in the drawer, and the console can ask again.
         */
        unawaited(_uploadClip(clip));
      }
    }

    _tick?.cancel();
    _tick = null;
    _recPulse.stop();
    _recPulse.value = 1;

    if (mounted) {
      setState(() {
        _bout = null;
        _recordingSince = null;
      });
    }
  }

  /// Which clip is being handed to the gallery right now, if any.
  String? _publishing;

  /// Put one already-recorded clip into the phone's media library.
  Future<void> _publishClip(CameraClip clip) async {
    if (_publishing != null || clip.uri != null) return;

    setState(() => _publishing = clip.file);

    final uri = await Recorder.publishExisting(clip.file);

    if (!mounted) return;

    if (uri != null) {
      clip.uri = uri;
      clip.file = 'Movies/TAKEONE/${clip.ref}';
      await ClipLog.save(_clips);
    }

    setState(() => _publishing = null);
  }

  /// Upload one clip to TAKEONE.
  ///
  /// The phone sends bytes and nothing else: the server it uploads to already
  /// knows which bout this camera was filming, and attaches the competitors,
  /// clubs, corners, officials, division, result and officiating timeline once
  /// the file lands. That is why this is one press rather than a form.
  Future<void> _uploadClip(CameraClip clip) async {
    if (clip.playStatus == 'uploading' || clip.playVideoKey != null) return;

    setState(() {
      clip.playStatus = 'uploading';
      clip.uploadProgress = 0;
    });

    final fault = await ClipUploader(
      token: _token!,
      clip: clip,
      onProgress: (fraction) {
        if (!mounted) return;
        // Redrawn on whole percents: a list rebuilding on every 8MB chunk is
        // work the encoder needs more than the drawer does.
        if ((fraction * 100).floor() != (clip.uploadProgress * 100).floor()) {
          setState(() => clip.uploadProgress = fraction);
        }
      },
    ).send();

    if (!mounted) return;

    setState(() {
      clip.playStatus = fault == null ? 'processing' : 'failed';
      clip.uploadProgress = fault == null ? 1 : clip.uploadProgress;
    });

    await ClipLog.save(_clips);

    // The video key arrives on the next config beat, once the server has
    // stored the file and the platform has created the video.
    if (fault == null) await _sync();
  }

  void _closeDrawer() => setState(() {
        _drawerOpen = false;
        _drawerSelecting = false;
      });

  /// Show the rail, and start its six seconds. Every control that is pressed
  /// calls this too, so a volunteer adjusting three things is not fighting a
  /// panel that keeps leaving.
  void _openRail() {
    setState(() => _railOpen = true);
    _railTimer?.cancel();
    _railTimer = Timer(const Duration(seconds: 6), () {
      if (mounted) setState(() => _railOpen = false);
    });
  }

  /// Delete clips: the videos, this phone's records, and the organiser's index.
  ///
  /// In that order and never partially. The video is what a volunteer asked to
  /// be rid of, so it goes first; the phone's own record follows because it now
  /// describes nothing; the console's row is best-effort last, since a stale
  /// row is a nuisance and a half-deleted clip is a lie.
  Future<void> _deleteClips(List<CameraClip> clips) async {
    for (final clip in clips) {
      final gone = await Recorder.deleteVideo(uri: clip.uri, path: clip.file);

      if (!gone) continue;

      _clips.removeWhere((c) => c.file == clip.file);

      final serverId = clip.serverId;
      if (serverId != null) await CameraApi(_token).deleteClip(serverId);
    }

    await ClipLog.save(_clips);

    if (mounted) setState(() {});
  }

  /// The one place the app sends somebody out to Android's own settings: the
  /// camera permission, which cannot be re-asked once it is refused for good.
  Future<void> _openSettings() async {
    try {
      await const MethodChannel('bh.takeone.camera/device').invokeMethod('openSettings');
    } catch (_) {}
  }

  /* ───────────────────────── The operator's dials ─────────────────────── */

  double _zoomAtGestureStart = 1;

  Future<void> _setZoom(double value) async {
    await _recorder.applyZoom(value);
    if (mounted) setState(() {});
    await _rememberSetup();
  }

  Future<void> _setExposure(double value) async {
    await _recorder.applyExposure(value);
    if (mounted) setState(() {});
    await _rememberSetup();
  }

  Future<void> _setFps(int rate) async {
    if (_recorder.rolling) return;

    setState(() => _fault = null);

    final fault = await _recorder.setFps(rate);

    if (mounted) setState(() => _fault = fault);
    await _rememberSetup();
  }


  /* ══════════════════════════ The screen ══════════════════════════════════

     One rule drives this layout: the recorded video is 16:9 and the preview
     must never lie about it. So the preview is a 16:9 frame at full screen
     height, centred, with honest black pillars either side — and those pillars
     are where the controls live, so nothing a volunteer presses ever sits on
     top of the picture they are aiming.

     On a screen wider than 16:9 the pillars appear; on 16:10 they vanish and
     the controls fall back onto the preview's corners over the standard scrim.
     Nothing is stretched in either case.                                    */

  /// Everything in the design is measured against a 44dp keep-out on the short
  /// edges, for the cutout and the gesture bar.
  static const double _keepOut = 44;

  /// Below these, the readings turn amber. A phone that fills up stops being a
  /// camera silently, which is the failure worth interrupting for.
  static const int _storageWarnPercent = 10;
  static const int _batteryWarnPercent = 20;

  bool get _storageLow {
    final free = _storageFree, total = _storageTotal;

    return free != null && total != null && total > 0 && free / total * 100 < _storageWarnPercent;
  }

  bool get _batteryLow => (_batteryPercent ?? 100) < _batteryWarnPercent;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Cam.ink,
      body: Stack(
        children: [
          Positioned.fill(
            child: _token == null
                ? const _Boot(message: 'Setting this camera up')
                : _permissionRefused
                    ? _permissionTakeover()
                    : _claimed
                        ? _station()
                        : _unpaired(),
          ),

          // The clip drawer sits ABOVE every state, not inside the paired one.
          //
          // What is on this phone is the operator's to look at whenever they
          // pick it up. Footage recorded at yesterday's event does not stop
          // being reviewable because the camera has since been unpaired, and a
          // volunteer holding an unclaimed phone should not have to find an
          // organiser and a QR code to answer "is the final still on here?".
          if (_drawerOpen) ...[
            Positioned.fill(
              child: GestureDetector(
                onTap: _closeDrawer,
                child: Container(color: Cam.ink.withValues(alpha: 0.38)),
              ),
            ),
            Positioned(
              top: 0,
              bottom: 0,
              right: 0,
              child: ClipDrawer(
                clips: _clips,
                startInSelect: _drawerSelecting,
                saving: _publishing,
                onClose: _closeDrawer,
                onPlay: _play,
                onSave: _publishClip,
                onUpload: _uploadClip,
                onDelete: _deleteClips,
              ),
            ),
          ],
        ],
      ),
    );
  }

  /* ─────────────────────── A · Unpaired ──────────────────────────────────
     No preview at all: an unclaimed camera is entitled to nothing, and a lens
     held open on a shelf is a flat battery. The QR and the code, nothing else. */

  Widget _unpaired() {
    return SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: _keepOut, vertical: 16),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              if (_claimUrl != null)
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(color: Cam.paper, borderRadius: BorderRadius.circular(12)),
                  child: QrImageView(data: _claimUrl!, size: 216, gapless: true),
                )
              else
                const SizedBox(width: 256, child: _Boot(message: 'Asking for a code')),
              const SizedBox(width: 36),
              Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('NOT PAIRED TO A MAT', style: Cam.cap(15, color: Cam.gold, tracking: 0.2)),
                  const SizedBox(height: 14),
                  // Chunked 3+3, because this gets read aloud across a hall.
                  Text(_codeChunked(), style: Cam.num(52, tracking: 0.12)),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: 320,
                    child: Text(
                      'Scan the QR in the organiser console, or type this code '
                      'under Cameras → Add.',
                      style: Cam.body(14),
                    ),
                  ),
                  // Recordings are reachable without pairing. The footage on
                  // this phone belongs to whoever is holding it, and needing an
                  // organiser and a QR code to check whether the final is still
                  // on here is the wrong answer at the end of a long day.
                  if (_clips.isNotEmpty) ...[
                    const SizedBox(height: 18),
                    GestureDetector(
                      onTap: () => setState(() {
                        _drawerOpen = true;
                        _drawerSelecting = false;
                      }),
                      behavior: HitTestBehavior.opaque,
                      child: Container(
                        height: 40,
                        padding: const EdgeInsets.symmetric(horizontal: 14),
                        decoration: BoxDecoration(
                          color: Cam.paper.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Cam.paper.withValues(alpha: 0.16)),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.video_library_outlined, size: 17, color: Cam.paper.withValues(alpha: 0.75)),
                            const SizedBox(width: 9),
                            Text(
                              'RECORDINGS · ${_clips.length}',
                              style: Cam.cap(13, color: Cam.paper.withValues(alpha: 0.85), tracking: 0.1),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(color: Cam.live, shape: BoxShape.circle),
                      ),
                      const SizedBox(width: 8),
                      Text('ON EVENT WI-FI', style: Cam.cap(12, color: Cam.paper.withValues(alpha: 0.5))),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _codeChunked() {
    final code = _code ?? '------';

    return code.length == 6 ? '${code.substring(0, 3)}-${code.substring(3)}' : code;
  }

  /* ─────────────────────── B–H · The camera ──────────────────────────────── */

  /// True while the full-screen player is on top, so a bout starting can send
  /// it away — the mat always wins.
  bool _playerOpen = false;

  Future<void> _play(CameraClip clip) async {
    setState(() => _playerOpen = true);
    await ClipPlayer.open(context, clip);
    if (mounted) setState(() => _playerOpen = false);
  }

  Widget _station() {
    final rolling = _recorder.rolling;

    return LayoutBuilder(
      builder: (context, box) {
        // The 16:9 frame at full height, and what is left over each side.
        final frameWidth = box.maxHeight * 16 / 9;
        final pillar = ((box.maxWidth - frameWidth) / 2).clamp(0.0, box.maxWidth / 2);

        // Under 24dp there is no pillar worth putting a control in, so the
        // controls come back onto the preview over the scrim.
        final inPillars = pillar >= 24;

        return Stack(
          children: [
            Positioned.fill(child: Container(color: Cam.ink)),

            // The picture, never stretched.
            Center(
              child: SizedBox(
                width: frameWidth.clamp(0.0, box.maxWidth),
                height: box.maxHeight,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    _preview(),
                    // D · the across-the-hall signal, tracing the RECORDED
                    // frame rather than the screen.
                    if (rolling)
                      IgnorePointer(
                        child: Container(
                          decoration: BoxDecoration(border: Border.all(color: Cam.rec, width: 3)),
                        ),
                      ),
                  ],
                ),
              ),
            ),

            // The scrim the status sits on.
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: CamScrim(height: rolling ? 104 : 96, opacity: rolling ? 0.66 : 0.62),
            ),

            ..._statusLayer(rolling),
            ..._controlLayer(pillar: pillar, inPillars: inPillars, rolling: rolling),

            // E/F · the drawer is mounted at the top level — see build().
          ],
        );
      },
    );
  }

  /// The preview itself, at the sensor's NATURAL size.
  ///
  /// The old build passed the sensor's height as the width, which is exactly
  /// what squeezed the picture — a 1280x720 stream drawn into a 720x1280 box.
  ///
  /// `cover` inside a 16:9 frame rather than the brief's `contain`, and the
  /// difference only shows on a sensor whose preview stream is 4:3: contain
  /// would letterbox and show MORE than is being recorded, which is worse than
  /// useless when the volunteer is using this to aim. Cover shows the 16:9
  /// slice that actually lands in the file. On the usual 16:9 stream the two
  /// are identical.
  Widget _preview() {
    final controller = _recorder.controller;

    if (_fault != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 40),
          child: Text(_fault!, textAlign: TextAlign.center, style: Cam.body(15, color: Cam.gold)),
        ),
      );
    }

    if (controller == null || !controller.value.isInitialized) {
      return const _Boot(message: 'Opening the camera');
    }

    return GestureDetector(
      onScaleStart: (_) => _zoomAtGestureStart = _recorder.zoom,
      onScaleUpdate: (details) {
        if (details.scale == 1.0 || !_recorder.canZoom) return;
        _setZoom(_zoomAtGestureStart * details.scale);
      },
      child: ClipRect(
        child: Center(
          child: AspectRatio(
            aspectRatio: 16 / 9,
            child: FittedBox(
              fit: BoxFit.cover,
              child: SizedBox(
                width: controller.value.previewSize!.width,
                height: controller.value.previewSize!.height,
                child: CameraPreview(controller),
              ),
            ),
          ),
        ),
      ),
    );
  }

  /* ── The status overlay: identity, health, what the mat is doing ───────── */

  List<Widget> _statusLayer(bool rolling) {
    return [
      // Identity, top-left. H3 turns THIS chip amber — the thing that broke is
      // the thing that changes colour.
      Positioned(
        top: 12,
        left: _keepOut,
        child: CamChip(
          text: '${_court ?? '—'} · CAM ${_angle ?? '—'}'
              '${_eventTitle != null && _eventTitle!.isNotEmpty ? ' · ${_eventTitle!.toUpperCase()}' : ''}',
          dotColor: _linkUp ? Cam.live : Cam.gold,
          warning: !_linkUp,
          pulse: !_linkUp,
        ),
      ),

      // Health, top-right.
      Positioned(
        top: 12,
        right: _keepOut,
        child: Row(
          children: [
            CamChip(
              text: _storageFree == null
                  ? 'STORAGE —'
                  : '${(_storageFree! / 1073741824).toStringAsFixed(0)} GB · ${_storagePercent()}%',
              icon: Icons.sd_storage_outlined,
              warning: _storageLow,
              pulse: _storageLow,
              dotColor: _storageLow ? Cam.gold : null,
            ),
            const SizedBox(width: 8),
            CamChip(
              text: _batteryPercent == null ? '—' : '$_batteryPercent%',
              icon: Icons.battery_std_outlined,
              warning: _batteryLow,
            ),
          ],
        ),
      ),

      // D · RECORDING, or C · the bout that is coming.
      if (rolling)
        Positioned(top: 12, left: 0, right: 0, child: Center(child: _recPill()))
      else if (_bout != null)
        Positioned(top: 12, left: 0, right: 0, child: Center(child: _nextChip())),

      // H3 · the link is down. Said in amber, never red.
      if (!_linkUp && _claimed)
        Positioned(
          top: rolling ? 96 : 60,
          left: 0,
          right: 0,
          child: Center(
            child: CamChip(
              text: 'LINK TO MAT LOST — RETRYING${_offlineFor()}',
              warning: true,
              pulse: true,
              dotColor: Cam.gold,
            ),
          ),
        ),

      // H2 · storage is nearly gone, with the action that fixes it.
      if (_storageLow && _storageBannerOpen)
        Positioned(
          top: 60,
          left: 0,
          right: 0,
          child: Center(child: _storageBanner()),
        ),

      // B · nothing is happening, and that is worth saying.
      if (!rolling && _bout == null)
        Positioned.fill(
          child: IgnorePointer(
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'WAITING FOR THE MAT',
                    style: Cam.cap(20, color: Cam.paper.withValues(alpha: 0.85), tracking: 0.18).copyWith(
                      shadows: [const Shadow(color: Cam.ink, blurRadius: 12)],
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Recording starts automatically',
                    style: Cam.body(14, color: Cam.paper.withValues(alpha: 0.45)).copyWith(
                      shadows: [const Shadow(color: Cam.ink, blurRadius: 10)],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),

      // C · aim now. The one moment a volunteer is meant to touch this.
      if (!rolling && _bout != null)
        Positioned(
          bottom: 14,
          left: 0,
          right: 0,
          child: Center(
            child: Text('STANDBY — AIM NOW', style: Cam.cap(13, color: Cam.gold, tracking: 0.2)),
          ),
        ),

      // Bottom-left: the clips. In the pillar when there is one.
      Positioned(
        bottom: 12,
        left: _keepOut,
        child: _clipsButton(),
      ),
    ];
  }

  int _storagePercent() {
    final free = _storageFree, total = _storageTotal;

    return (free != null && total != null && total > 0) ? (free / total * 100).round() : 0;
  }

  Widget _recPill() {
    final elapsed = _recordingSince == null ? Duration.zero : DateTime.now().difference(_recordingSince!);
    final minutes = elapsed.inMinutes.toString().padLeft(2, '0');
    final seconds = (elapsed.inSeconds % 60).toString().padLeft(2, '0');

    return Column(
      children: [
        Container(
          height: 52,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          decoration: BoxDecoration(
            color: Cam.rec,
            borderRadius: BorderRadius.circular(10),
            boxShadow: [BoxShadow(color: Cam.rec.withValues(alpha: 0.45), blurRadius: 18, offset: const Offset(0, 4))],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              FadeTransition(
                opacity: Tween<double>(begin: 1, end: 0.25).animate(_recPulse),
                child: Container(
                  width: 14,
                  height: 14,
                  decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                ),
              ),
              const SizedBox(width: 10),
              Text('REC', style: Cam.cap(18, color: Colors.white, tracking: 0.2, weight: FontWeight.w700)),
              const SizedBox(width: 12),
              Text('$minutes:$seconds', style: Cam.num(26, color: Colors.white)),
            ],
          ),
        ),
        if (_bout != null) ...[
          const SizedBox(height: 6),
          _boutChip(_bout!, height: 30),
        ],
      ],
    );
  }

  Widget _nextChip() {
    return Container(
      height: 40,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: Cam.ink.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Cam.gold),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('NEXT', style: Cam.cap(13, color: Cam.gold, tracking: 0.18)),
          const SizedBox(width: 10),
          Text(_bout?['number']?.toString() ?? '—', style: Cam.num(16, color: Cam.paper)),
          const SizedBox(width: 10),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 300),
            child: Text(
              _names(_bout!),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Cam.cap(13, color: Cam.paper.withValues(alpha: 0.8), tracking: 0.06),
            ),
          ),
        ],
      ),
    );
  }

  Widget _boutChip(Map<String, dynamic> bout, {double height = 30}) {
    return Container(
      height: height,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: Cam.ink.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(bout['number']?.toString() ?? '—', style: Cam.num(14, color: Cam.gold)),
          const SizedBox(width: 10),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 320),
            child: Text(
              _names(bout),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Cam.cap(12, color: Cam.paper.withValues(alpha: 0.85), tracking: 0.06),
            ),
          ),
        ],
      ),
    );
  }

  String _names(Map<String, dynamic> bout) {
    final red = (bout['red'] ?? '').toString();
    final blue = (bout['blue'] ?? '').toString();

    return [red, blue].where((n) => n.isNotEmpty).join('  vs  ').toUpperCase();
  }

  String _offlineFor() {
    if (_offlineSince == null) return '';

    return ' · ${DateTime.now().difference(_offlineSince!).inSeconds}s';
  }

  Widget _storageBanner() {
    return Container(
      height: 44,
      padding: const EdgeInsets.only(left: 14, right: 8),
      decoration: BoxDecoration(
        color: Cam.ink.withValues(alpha: 0.85),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Cam.gold, width: 1.5),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('STORAGE LOW${_minutesLeft()}', style: Cam.cap(13, color: Cam.gold, tracking: 0.14)),
          const SizedBox(width: 12),
          GestureDetector(
            onTap: () => setState(() {
              _drawerOpen = true;
              _drawerSelecting = true;
            }),
            behavior: HitTestBehavior.opaque,
            child: Container(
              height: 32,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: Cam.gold,
                borderRadius: BorderRadius.circular(7),
              ),
              child: Text('FREE UP SPACE', style: Cam.cap(12, color: Cam.ink, weight: FontWeight.w700)),
            ),
          ),
          GestureDetector(
            onTap: () => setState(() => _storageBannerOpen = false),
            behavior: HitTestBehavior.opaque,
            child: const SizedBox(width: 40, height: 40, child: Icon(Icons.close, size: 16, color: Cam.gold)),
          ),
        ],
      ),
    );
  }

  /// Roughly how long is left at this frame rate. Deliberately rough: an
  /// estimate a volunteer can act on beats a precise number nobody reads.
  String _minutesLeft() {
    final free = _storageFree;

    if (free == null) return '';

    // ~120 MB a minute at 1080p30, double at 60.
    final perMinute = _recorder.fps >= 60 ? 240e6 : 120e6;

    return ' — ≈ ${(free / perMinute).round()} MIN LEFT';
  }

  Widget _clipsButton() {
    return GestureDetector(
      onTap: () => setState(() {
        _drawerOpen = true;
        _drawerSelecting = false;
      }),
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 48,
        height: 48,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Cam.ink.withValues(alpha: 0.72),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Cam.paper.withValues(alpha: 0.14)),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.video_library_outlined, size: 17, color: Cam.paper.withValues(alpha: 0.85)),
            const SizedBox(height: 2),
            Text('${_clips.length}', style: Cam.num(11, color: Cam.paper.withValues(alpha: 0.85))),
          ],
        ),
      ),
    );
  }

  /* ── The control rail: zoom, exposure, frame rate ──────────────────────── */

  List<Widget> _controlLayer({required double pillar, required bool inPillars, required bool rolling}) {
    final right = inPillars ? (pillar - 48) / 2 : _keepOut;

    return [
      Positioned(
        right: right.clamp(8.0, 200.0),
        bottom: 12,
        child: _railOpen ? _rail(rolling) : _zoomPill(),
      ),
    ];
  }

  /// Collapsed: just what the zoom currently is, and a way in.
  Widget _zoomPill() {
    return GestureDetector(
      onTap: _openRail,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 48,
        height: 48,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Cam.ink.withValues(alpha: 0.72),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Cam.paper.withValues(alpha: 0.14)),
        ),
        child: Text('${_recorder.zoom.toStringAsFixed(1)}×', style: Cam.num(13)),
      ),
    );
  }

  Widget _rail(bool rolling) {
    final presets = <double>[1, 2, 4].where((z) => z <= _recorder.maxZoom + 0.01).toList();

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final preset in presets) ...[
          CamCell(
            active: (_recorder.zoom - preset).abs() < 0.05,
            onTap: () {
              _openRail();
              _setZoom(preset);
            },
            child: Text('${preset.toStringAsFixed(1)}×', style: Cam.num(13, color: (_recorder.zoom - preset).abs() < 0.05 ? Cam.gold : Cam.paper)),
          ),
          const SizedBox(height: 6),
        ],

        // Exposure, in the sensor's own EV steps: a hall is lit for spectators.
        if (_recorder.canExpose) ...[
          CamCell(
            height: 64,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _evStep('+', 0.1),
                Text(
                  '${_recorder.exposure >= 0 ? '+' : ''}${_recorder.exposure.toStringAsFixed(1)}',
                  style: Cam.num(12, color: _recorder.exposure == 0 ? Cam.paper : Cam.gold),
                ),
                _evStep('−', -0.1),
              ],
            ),
          ),
          const SizedBox(height: 6),
        ],

        // Locked while rolling: re-opening the camera would cut the clip.
        CamCell(
          active: _recorder.fps == 30,
          disabled: rolling,
          onTap: () {
            _openRail();
            _setFps(30);
          },
          child: Text('30', style: Cam.num(13, color: _recorder.fps == 30 ? Cam.gold : Cam.paper)),
        ),
        const SizedBox(height: 6),
        CamCell(
          active: _recorder.fps == 60,
          disabled: rolling,
          onTap: () {
            _openRail();
            _setFps(60);
          },
          child: Text('60', style: Cam.num(13, color: _recorder.fps == 60 ? Cam.gold : Cam.paper)),
        ),
      ],
    );
  }

  Widget _evStep(String glyph, double delta) {
    return GestureDetector(
      onTap: () {
        _openRail();
        _setExposure(_recorder.exposure + delta);
      },
      behavior: HitTestBehavior.opaque,
      child: SizedBox(
        height: 20,
        width: 44,
        child: Center(child: Text(glyph, style: Cam.cap(14, color: Cam.paper.withValues(alpha: 0.8)))),
      ),
    );
  }

  /* ── H1 · no camera permission: nothing else on this screen matters ────── */

  Widget _permissionTakeover() {
    return SafeArea(
      child: Stack(
        children: [
          // The network is fine, and saying so stops somebody debugging wifi.
          Positioned(
            top: 12,
            left: _keepOut,
            child: CamChip(
              text: '${_court ?? 'NOT PAIRED'} · CAM ${_angle ?? '—'}',
              dotColor: _linkUp ? Cam.live : Cam.gold,
            ),
          ),
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: _keepOut),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(color: Cam.rec, width: 2),
                    ),
                    child: Text('!', style: Cam.num(26, color: Cam.rec)),
                  ),
                  const SizedBox(height: 16),
                  Text('CAMERA PERMISSION REFUSED', style: Cam.cap(18, tracking: 0.16)),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: 460,
                    child: Text(
                      'This phone cannot film until camera access is allowed. '
                      'The mat sees this camera as offline.',
                      textAlign: TextAlign.center,
                      style: Cam.body(14),
                    ),
                  ),
                  const SizedBox(height: 20),
                  GestureDetector(
                    onTap: _openSettings,
                    behavior: HitTestBehavior.opaque,
                    child: Container(
                      height: 48,
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      alignment: Alignment.center,
                      decoration: BoxDecoration(color: Cam.gold, borderRadius: BorderRadius.circular(10)),
                      child: Text('OPEN SETTINGS', style: Cam.cap(14, color: Cam.ink, weight: FontWeight.w700)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// The only spinner in the app, and it is never on screen for long.
class _Boot extends StatelessWidget {
  const _Boot({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(
            width: 26,
            height: 26,
            child: CircularProgressIndicator(strokeWidth: 2.4, color: Cam.gold),
          ),
          const SizedBox(height: 14),
          Text(message.toUpperCase(), style: Cam.cap(12, color: Cam.paper.withValues(alpha: 0.55))),
        ],
      ),
    );
  }
}
