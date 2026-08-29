// boutcam — the matside camera, with live.
//
// ── What this is ───────────────────────────────────────────────────────────
//
// The camera app's instrument, rebuilt on flutter_webrtc so that ONE camera
// session can do two jobs at once:
//
//   · RECORD the bout to a file on this phone, started and stopped per bout,
//     at 60 fps because that is what sport needs — switchable to 30 by the
//     operator, exactly as the shipping app allows.
//   · PUBLISH a live feed continuously, capped at 30 fps, which is all a
//     broadcast can carry over a hall's uplink anyway.
//
// The recording is the product; the feed is the courtesy. If the phone can only
// do one well, it must be the file.
//
// ── Why it looks like the real thing ──────────────────────────────────────
//
// Because the question is not "does WebRTC work" — it is "can the instrument we
// already trust also go live". So it is the same kit (Cam tokens, CamChip,
// CamCell, CamScrim, copied verbatim from lib/src/camera/kit.dart), the same
// faces, the same layout: full-bleed viewfinder, identity top-left, health
// top-right, REC pill in the middle, a 48dp control rail down the right, and
// one big button. Red means recording and nothing else. Warnings are amber.
//
// ── What it does NOT share ────────────────────────────────────────────────
//
// It is a separate app (bh.takeone.lab), a separate project, and it never
// touches the shipping camera's code.
//
// It DOES pair like one: the same /camera/enroll and /camera/{token}/config
// endpoints, the same QR-and-code screen while unclaimed, the same MQTT link
// once paired — so the mat's own hajime starts the recording here, while the
// feed stays up. Measuring anything less would be measuring a simulation. The
// manual bout button stays for bench runs with no mat.
//
// Every sample is posted to the server so the run can be read off a log.

import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;
import 'dart:math' show Point;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:path_provider/path_provider.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'kit.dart';
import 'library.dart';
import 'link.dart';
import 'tally.dart';
import 'vault.dart';

/// What sport is filmed at, and what a broadcast can actually carry.
const int kRecordFpsDefault = 60;
const int kLiveFps = 30;

/// What the live sender is aiming at, in kbps — the denominator of the health
/// ring. It is the encoding cap set on the sender, so the gauge reads "of what
/// was asked for" rather than of a number invented for the dial.
const double kTargetKbps = 2500;

/// The audio meters are read far faster than the telemetry: a level that
/// updates every five seconds is not a level, it is a history.
const Duration kAudioEvery = Duration(milliseconds: 80);

const Duration kSampleEvery = Duration(seconds: 5);
const Duration kShipEvery = Duration(seconds: 30);

/// How often the phone asks the server what it is. Fast while it is waiting to
/// be claimed — somebody is standing there holding it — and slow once paired,
/// because by then the socket carries the urgent messages.
const Duration kPairPoll = Duration(seconds: 3);
const Duration kBeat = Duration(seconds: 8);

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
  runApp(const LabApp());
}

class LabApp extends StatelessWidget {
  const LabApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'boutcam',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        brightness: Brightness.dark,
        scaffoldBackgroundColor: Cam.ink,
        fontFamily: Cam.label,
      ),
      home: const Station(),
    );
  }
}

class Station extends StatefulWidget {
  const Station({super.key});

  @override
  State<Station> createState() => _StationState();
}

class _StationState extends State<Station> with WidgetsBindingObserver {
  static const _device = MethodChannel('bh.takeone.lab/device');

  /// The keep-out margin the real station uses, so a chip never sits under a
  /// rounded corner or a punch-hole camera.
  static const double _keepOut = 16;

  final _renderer = RTCVideoRenderer();

  // Identity. Until the server has been asked, this phone is nobody.
  String? _token;
  bool _claimed = false;
  String? _code;
  String? _claimUrl;
  String? _court;
  int? _angle;
  String? _eventTitle;

  CameraLink? _link;
  bool _linkUp = false;
  Timer? _poll;

  MediaStream? _stream;
  MediaStreamTrack? _video;
  RTCPeerConnection? _pc;
  MediaRecorder? _recorder;

  bool _ready = false;
  bool _connecting = false;
  bool _live = false;
  /// What the console has said about this camera's feed. True until told
  /// otherwise, so a phone that pairs and then loses touch with the server
  /// behaves exactly as it always did: paired means filming, and filming means
  /// live.
  bool _wantLive = true;
  bool _rolling = false;
  String? _fault;

  int _recordFps = kRecordFpsDefault;
  double _zoom = 1;

  // Camera settings, as the design's drawer names them.
  //
  // `auto` / `lock` / `manual` rather than a bare boolean, because they are
  // three different intentions: keep hunting, hold what you have, and let me
  // point it. Manual is the only one where a tap on the viewfinder means
  // anything, which is why the tap handler asks.
  String _focusMode = 'auto';
  String _whiteBalance = 'auto';
  double _ev = 0;
  bool _stabilize = true;
  bool _grid = false;

  /// A frame rate chosen while a clip is being written.
  ///
  /// Changing it means reopening the lens, which would cut the file in half, so
  /// it waits for the bout to end. The drawer says so rather than silently
  /// ignoring the tap — a control that appears to do nothing is worse than one
  /// that explains itself.
  int? _pendingFps;

  // UI.
  bool _drawerOpen = false;
  bool _telemetryOpen = false;

  /// Left and right audio, 0..1, for the meters on the left edge.
  var _audio = const <double>[0, 0];

  String? _streamId;
  bool _h264 = false;

  int _bout = 0;
  String? _clipPath;
  DateTime? _clipSince;
  DateTime? _liveSince;
  final List<_Clip> _clips = [];

  /// The bout the clip being written belongs to, held from `record` until the
  /// file is closed — the mat has moved on by the time it is filed.
  int? _clipMatchId;
  String? _clipNumber;
  String? _clipStage;
  String? _clipRed;
  String? _clipBlue;

  /// Getting the finished bouts off this phone and into the event's vault. It
  /// holds while a clip is being written and uses the gap between bouts.
  late final ClipVault _vault = ClipVault(
    onChange: () {
      if (mounted) setState(() {});
    },
  );

  // Readings.
  double _fps = 0;
  double _kbps = 0;
  int _width = 0;
  int _height = 0;
  int _lost = 0;
  double _rtt = 0;
  String _limitedBy = '—';
  int? _battery;
  String _thermal = '—';
  int? _freeMb;

  int _lastBytes = 0;
  int _lastFrames = 0;
  DateTime? _lastAt;

  final List<Map<String, dynamic>> _pending = [];
  Timer? _sampler;
  Timer? _audioTimer;
  Timer? _shipper;
  Timer? _ticker;
  String _model = 'unknown';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _boot();
  }

  Future<void> _boot() async {
    await _renderer.initialize();
    await _readDevice();

    // The identity survives a restart: a camera that re-enrolled on every launch
    // would mint a new pairing code every time somebody bumped it, and the
    // organiser would be scanning a code that had already been replaced.
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('lab.token');

    if (_token == null) {
      final enrolled = await CameraApi.enrol(_model);
      _token = enrolled?['token'] as String?;

      if (_token != null) {
        await prefs.setString('lab.token', _token!);
      } else if (mounted) {
        setState(() => _fault = 'COULD NOT ENROL — CHECK THE NETWORK');
      }
    }

    // The ledger of what this phone filmed, and the worker that empties it.
    // Loaded before the first bout can start, so a clip left over from a
    // competition that ended on a dead battery is picked up on launch.
    await _vault.load();
    _vault.useToken(_token);
    _vault.start();

    _link = CameraLink(onCommand: _onCommand);

    if (mounted) setState(() {});

    await _sync();
    _schedule();

    _sampler = Timer.periodic(kSampleEvery, (_) => _sample());
    _audioTimer = Timer.periodic(kAudioEvery, (_) => _readAudio());
    _shipper = Timer.periodic(kShipEvery, (_) => _ship());
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && (_rolling || _live)) setState(() {});
    });
  }

  /* ══════════════════════ Identity · pairing ═══════════════════════════════

     Exactly the shipping camera's contract: enrol once, show the code, poll
     until an organiser claims it, then attach the socket and do what the mat
     says. Fast poll while waiting (somebody is standing there holding it), slow
     once paired (the socket carries anything urgent). */

  void _schedule() {
    _poll?.cancel();
    _poll = Timer.periodic(_claimed ? kBeat : kPairPoll, (_) => _sync());
  }

  Future<void> _sync() async {
    if (_token == null) return;

    final result = await CameraApi(_token).configResult();

    // 404 is the server saying this identity is gone — the row was removed, the
    // database restored. Anything else (a timeout, a captive portal) leaves the
    // identity alone, because re-enrolling on a dropped packet would abandon a
    // code an organiser is in the middle of scanning.
    if (result.status == 404) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('lab.token');

      if (mounted) {
        setState(() {
          _token = null;
          _claimed = false;
          _code = null;
          _claimUrl = null;
        });
      }

      await _boot();

      return;
    }

    final config = result.body;
    if (config == null || !mounted) return;

    final claimed = config['claimed'] == true;
    final was = _claimed;

    setState(() {
      _claimed = claimed;
      _code = config['code'] as String?;
      _claimUrl = config['claim_url'] as String?;
      _court = config['court'] as String?;
      _angle = config['angle'] as int?;
      _eventTitle = (config['event'] as Map<String, dynamic>?)?['title'] as String?;
    });

    if (claimed != was) _schedule();

    if (!claimed) {
      // An unclaimed phone holds no lens open and no socket. It is entitled to
      // nothing, and a preview running on a shelf is a flat battery.
      await _stopLive();
      await _link?.close();
      await _closeCamera();

      return;
    }

    if (!_ready) await _open();

    // THE FEED IS NOT A BUTTON ON THIS PHONE.
    //
    // The design takes REC and LIVE away from whoever is holding the camera and
    // gives them to the mat, and it is right to: a camera operator who can
    // start a recording is one who can start the WRONG recording, and a bout
    // filed against the wrong athlete is found out days later.
    //
    // What it got wrong was giving LIVE to nobody. Pairing used to mean "on air
    // until unpaired", with no way to stop a feed short of taking the camera off
    // the mat. So the order now comes from the console, per camera, and this is
    // where a phone reconciles with it — on launch, on resume, and as the
    // backstop for a message the broker never delivered.
    _wantLive = config['broadcasting'] != false;

    if (_wantLive) {
      if (_ready && !_live && !_connecting) unawaited(_goLive());
    } else if (_live || _connecting) {
      await _stopLive();
    }

    await _link?.attach(config['realtime'] as Map<String, dynamic>?);

    if (mounted) setState(() => _linkUp = _link?.connected ?? false);

    // Reconcile with what the mat believes, both ways: a bout that started while
    // this app was dead, and a stop that was missed.
    final shouldRoll = config['recording'] == true;

    if (shouldRoll && !_rolling) {
      await _startBout(match: config['match'] as Map<String, dynamic>?);
    } else if (!shouldRoll && _rolling && _matDriven) {
      await _stopBout();
    }
  }

  /// What the mat just said. The socket is the fast path; the poll above is the
  /// backstop that makes a broker outage late rather than broken.
  void _onCommand(Map<String, dynamic> command) {
    switch (command['action']) {
      case 'record':
        _startBout(match: command['match'] as Map<String, dynamic>?);
        break;
      case 'stop':
        if (_matDriven) _stopBout();
        break;
      case 'standby':
        break;
      // The console switched this camera's feed on or off. The socket is the
      // fast path; the config beat above is the backstop that makes a broker
      // outage late rather than broken.
      case 'live':
        _wantLive = true;
        if (_ready && !_live && !_connecting) unawaited(_goLive());
        break;
      case 'offair':
        _wantLive = false;
        unawaited(_stopLive());
        break;
    }
  }

  /* ══════════════════════ The camera: one session ═══════════════════════════

     Opened once, at the recording frame rate, and never reopened while a clip
     is being written — reopening would cut the file. The live sender is capped
     separately, so 60 fps to disk and 30 fps on the wire come from the same
     capture. */

  /// What to ask the lens for, hardest first.
  ///
  /// A phone that refuses the whole request gives back nothing — no picture at
  /// all — and the commonest reason is not the camera but the MICROPHONE: an
  /// operator who allowed the camera and declined the mic gets a black screen,
  /// which reads as a broken app rather than as a permission they set. Sound is
  /// worth having and not worth the picture, so the second attempt drops it, and
  /// the third drops the resolution and frame rate a device may not be able to
  /// meet. Whatever succeeds first is what the mat gets filmed on.
  List<Map<String, dynamic>> _attempts() => [
        {
          'audio': true,
          'video': {
            'facingMode': 'environment',
            'width': {'ideal': 1920},
            'height': {'ideal': 1080},
            'frameRate': {'ideal': _recordFps},
          },
        },
        {
          'audio': false,
          'video': {
            'facingMode': 'environment',
            'width': {'ideal': 1920},
            'height': {'ideal': 1080},
            'frameRate': {'ideal': _recordFps},
          },
        },
        {'audio': false, 'video': {'facingMode': 'environment'}},
      ];

  Future<void> _open() async {
    MediaStream? stream;
    Object? last;
    var silent = false;

    for (final (index, constraints) in _attempts().indexed) {
      try {
        // A DEADLINE, because the failure seen on a real phone was not an
        // exception — it was silence. getUserMedia sat pending forever and the
        // screen read OPENING THE CAMERA with no error to show, which is the
        // worst of both: nothing works and nothing says why. A request that has
        // not produced a lens in twelve seconds is not going to.
        stream = await navigator.mediaDevices
            .getUserMedia(constraints)
            .timeout(const Duration(seconds: 12));
        silent = index > 0;
        break;
      } catch (e) {
        last = e is TimeoutException ? 'the camera did not answer' : e;
      }
    }

    if (stream == null) {
      final text = last.toString();

      // Permission is the one failure worth naming in words rather than in the
      // plugin's own message: it is the only one the person holding the phone
      // can actually fix, and it is fixed somewhere else entirely.
      setState(() => _fault = text.toLowerCase().contains('permission')
          ? 'CAMERA PERMISSION REFUSED — ALLOW IT IN SETTINGS, THEN REOPEN'
          : 'CAMERA: $text');

      return;
    }

    try {
      _stream = stream;
      _video = stream.getVideoTracks().first;
      _renderer.srcObject = stream;

      // SHOW THE PICTURE FIRST. Focus and exposure used to be tuned before
      // `_ready` was set, which meant a phone whose driver never returned from
      // setFocusMode held a working camera open behind a screen that said it was
      // still opening one. The lens is the product; metering is an improvement
      // to it, and an improvement must never be able to withhold the thing it
      // improves.
      setState(() {
        _ready = true;
        // A silent picture is a warning, not a failure: the bout is being filmed
        // and the operator should know why it will have no sound.
        _fault = silent ? 'NO MICROPHONE — FILMING WITHOUT SOUND' : null;
      });

      // A mat is lit by whatever the venue has, and a white dobok on a bright
      // floor is the hardest thing a phone camera is asked to meter. Both are
      // set explicitly rather than left to the default, because a locked
      // exposure from a previous session would follow the app across a restart.
      // Unawaited, and on its own deadline: see above.
      unawaited(_autoEverything());
    } catch (e) {
      setState(() => _fault = 'CAMERA: $e');
    }
  }

  Future<void> _autoEverything() async {
    final track = _video;
    if (track == null) return;

    // Both on a deadline. These are vendor camera calls: on some devices they
    // do not fail, they simply never come back, and an await with no ceiling
    // turns that into a hang rather than into a missing nicety.
    try {
      await Helper.setFocusMode(track, CameraFocusMode.auto).timeout(_tune);
    } catch (_) {}

    try {
      await Helper.setExposureMode(track, CameraExposureMode.auto).timeout(_tune);
    } catch (_) {}

    if (mounted) setState(() {});
  }

  /// How long a vendor focus/exposure call is given before it is written off.
  static const Duration _tune = Duration(seconds: 3);

  /// Tap the viewfinder to point focus and metering at that spot — the mat,
  /// rather than the bright window behind it. Falls back to full auto.
  Future<void> _pointAt(Offset unit) async {
    final track = _video;
    if (track == null) return;

    try {
      await Helper.setFocusMode(track, CameraFocusMode.locked).timeout(_tune);
      await Helper.setFocusPoint(track, Point<double>(unit.dx, unit.dy)).timeout(_tune);
      await Helper.setExposurePoint(track, Point<double>(unit.dx, unit.dy)).timeout(_tune);
      // A point was taken, so the mode the drawer shows is no longer AUTO.
      if (mounted) setState(() => _focusMode = 'manual');
    } catch (_) {
      await _autoEverything();
      if (mounted) setState(() => _focusMode = 'auto');
    }
  }

  Future<void> _setRecordFps(int rate) async {
    if (rate == _recordFps) {
      setState(() => _pendingFps = null);

      return;
    }

    // QUEUED while rolling, rather than refused. Changing the capture rate
    // means reopening the lens, which cuts the file in half — but a tap that
    // does nothing at all reads as a broken control, so the choice is
    // remembered and applied when the mat ends the bout. The drawer says so.
    if (_rolling) {
      setState(() => _pendingFps = rate);

      return;
    }

    setState(() {
      _recordFps = rate;
      _pendingFps = null;
    });

    // Reopen at the new rate. A live feed survives it: the sender is re-attached
    // to the new track rather than the session being torn down.
    final wasLive = _live;
    await _closeCamera();
    await _open();

    if (wasLive && _video != null) {
      await _replaceSenderTrack();
    }

    await _sample();
  }

  Future<void> _setZoom(double z) async {
    final track = _video;
    if (track == null) return;

    setState(() => _zoom = z);
    try {
      await Helper.setZoom(track, z).timeout(_tune);
    } catch (_) {}
  }

  /// Auto · Lock · Manual, as the drawer offers them.
  ///
  /// Auto keeps hunting. Lock holds what it has, which is what a fixed tripod on
  /// a mat wants once it is pointed — continuous autofocus on a moving fight
  /// breathes in and out all bout. Manual means the next tap on the viewfinder
  /// sets the point.
  Future<void> _setFocusMode(String mode) async {
    setState(() => _focusMode = mode);

    final track = _video;
    if (track == null) return;

    try {
      if (mode == 'auto') {
        await Helper.setFocusMode(track, CameraFocusMode.auto).timeout(_tune);
        await Helper.setExposureMode(track, CameraExposureMode.auto).timeout(_tune);
      } else {
        await Helper.setFocusMode(track, CameraFocusMode.locked).timeout(_tune);
      }
    } catch (_) {
      // A device that will not take the instruction keeps the setting shown —
      // it is what the operator asked for, and the picture is unchanged.
    }
  }

  /// What this camera stack can and cannot actually do.
  ///
  /// flutter_webrtc drives the lens through five calls and no more — zoom,
  /// focus mode, focus point, exposure mode, exposure point — in the Dart
  /// helper AND in its Android handler. Exposure compensation, white balance
  /// and stabilization are not among them: the capture session belongs to the
  /// plugin, and its CaptureRequest never leaves it.
  ///
  /// So those three are drawn, dimmed, and inert. Deleting them would lose a
  /// part of the design that is right and that another camera stack could
  /// serve; leaving them live would be worse than either, because a control
  /// that moves and changes nothing is not discovered until the footage is
  /// watched.
  static const bool _supportsEv = false;
  static const bool _supportsWhiteBalance = false;
  static const bool _supportsStabilization = false;

  /// Exposure compensation, if this camera stack ever carries it.
  ///
  /// A mat under hall lighting with a white dobok on it is the hardest thing a
  /// phone meters, and it errs dark — so this is the dial an operator would
  /// actually reach for. Kept as the one place to wire it the day the lens is
  /// ours to talk to.
  Future<void> _setEv(double ev) async {
    if (!_supportsEv) return;

    setState(() => _ev = ev);
  }

  Future<void> _closeCamera() async {
    _renderer.srcObject = null;
    await _stream?.dispose();
    _stream = null;
    _video = null;
    setState(() => _ready = false);
  }

  /* ══════════════════════ Consumer 1 · the live feed ════════════════════════ */

  Future<void> _goLive() async {
    if (_live || _connecting || _stream == null) return;

    // Switched off from the console. Guarded here as well as at every call site
    // because this is the one door onto the wire — and the server refuses the
    // publish credential in this state anyway, so asking would only produce a
    // fault on the operator's screen for a feed nobody wants up.
    if (!_wantLive) return;

    setState(() {
      _connecting = true;
      _fault = null;
    });

    try {
      final d = await CameraApi(_token).publishToken();

      if (d == null) throw 'no publish token';
      _streamId = d['stream'] as String?;

      final pc = await createPeerConnection({
        'iceServers': (d['ice_servers'] as List?)?.cast<Map<String, dynamic>>() ?? const [],
        'sdpSemantics': 'unified-plan',
      });
      _pc = pc;

      await pc.addTrack(_video!, _stream!);
      final audio = _stream!.getAudioTracks();
      if (audio.isNotEmpty) await pc.addTrack(audio.first, _stream!);

      // The broadcast is capped at 30 fps and a hall-friendly bitrate. The file
      // keeps every frame of the 60 — this only limits what goes on the wire.
      await _capSender();

      final offer = await pc.createOffer({});
      await pc.setLocalDescription(offer);
      await _gathered(pc);

      final local = await pc.getLocalDescription();

      final answer = await _whip(
        Uri.parse(d['whip_url'] as String),
        d['token'] as String,
        local!.sdp!,
      );

      await pc.setRemoteDescription(RTCSessionDescription(answer, 'answer'));

      _h264 = answer.toUpperCase().contains('H264');

      setState(() {
        _live = true;
        _connecting = false;
        _liveSince = DateTime.now();
        // Not H.264 is a real fault, not a detail: the recording made from a
        // VP8 track cannot be muxed into mp4 by anything downstream.
        _fault = _h264 ? null : 'FEED IS NOT H.264';
      });

      _note(_h264 ? 'live_h264' : 'live_non_h264');
    } catch (e) {
      setState(() {
        _connecting = false;
        _fault = 'LIVE: $e';
      });
      _note('live_failed');
    }
  }

  Future<void> _capSender() async {
    final pc = _pc;
    if (pc == null) return;

    for (final sender in await pc.getSenders()) {
      if (sender.track?.kind != 'video') continue;

      final params = sender.parameters;
      final encodings = params.encodings;

      if (encodings == null || encodings.isEmpty) {
        params.encodings = [RTCRtpEncoding(maxFramerate: kLiveFps, maxBitrate: 2500000)];
      } else {
        encodings.first.maxFramerate = kLiveFps;
        encodings.first.maxBitrate = 2500000;
      }

      params.degradationPreference = RTCDegradationPreference.MAINTAIN_FRAMERATE;

      try {
        await sender.setParameters(params);
      } catch (_) {}
    }
  }

  /// Hand the sender the new track after the camera was reopened at a different
  /// frame rate, so the feed does not have to be restarted.
  Future<void> _replaceSenderTrack() async {
    final pc = _pc;
    if (pc == null || _video == null) return;

    for (final sender in await pc.getSenders()) {
      if (sender.track?.kind == 'video') {
        try {
          await sender.replaceTrack(_video);
        } catch (_) {}
      }
    }

    await _capSender();
  }

  /// The WHIP handshake: one POST carrying an SDP offer, one SDP answer back.
  /// Spoken here rather than through the JSON api layer because the body is SDP.
  Future<String> _whip(Uri url, String token, String offer) async {
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 10);

    try {
      final request = await client.postUrl(url);
      request.headers.contentType = ContentType('application', 'sdp');
      request.headers.set('authorization', 'Bearer $token');
      request.write(offer);

      final response = await request.close().timeout(const Duration(seconds: 20));
      final body = await response.transform(utf8.decoder).join();

      if (response.statusCode < 200 || response.statusCode > 299) {
        throw 'WHIP ${response.statusCode}';
      }

      return body;
    } finally {
      client.close(force: true);
    }
  }

  Future<void> _gathered(RTCPeerConnection pc) async {
    final done = Completer<void>();

    pc.onIceGatheringState = (s) {
      if (s == RTCIceGatheringState.RTCIceGatheringStateComplete && !done.isCompleted) {
        done.complete();
      }
    };

    Timer(const Duration(milliseconds: 2500), () {
      if (!done.isCompleted) done.complete();
    });

    return done.future;
  }

  Future<void> _stopLive() async {
    try {
      await _pc?.close();
    } catch (_) {}
    _pc = null;

    setState(() {
      _live = false;
      _liveSince = null;
      _fps = 0;
      _kbps = 0;
      _limitedBy = '—';
    });
    _note('live_stopped');
  }

  /* ══════════════════════ Consumer 2 · the bout's file ══════════════════════ */

  /// Whether the mat started this clip. A hand-started clip must not be stopped
  /// by a stale `stop` arriving from a mat that never started it.
  bool _matDriven = false;

  Future<void> _startBout({Map<String, dynamic>? match}) async {
    if (_rolling || _stream == null) return;

    try {
      final dir = await getExternalStorageDirectory() ?? await getApplicationDocumentsDirectory();
      final clips = Directory('${dir.path}/clips');
      if (!clips.existsSync()) clips.createSync(recursive: true);

      _bout += 1;

      final now = DateTime.now();
      final stamp = '${now.year}${_two(now.month)}${_two(now.day)}-'
          '${_two(now.hour)}${_two(now.minute)}${_two(now.second)}';

      // Named for the bout, as the shipping app names it: identifiable on the
      // phone with no app and no server.
      final label = (match?['number'] ?? match?['bout'] ?? match?['id'])?.toString();
      final parts = [
        if (_court != null) 'mat-${_slug(_court!)}',
        if (_angle != null) 'angle-$_angle',
        'bout-${label ?? _bout}',
        '${_recordFps}fps',
        stamp,
      ];
      final path = '${clips.path}/${parts.join('_')}.mp4';

      final rec = MediaRecorder();
      await rec.start(path, videoTrack: _video!);

      _recorder = rec;
      _clipPath = path;
      _clipMatchId = (match?['id'] as num?)?.toInt();
      _clipNumber = label;
      _clipStage = match?['stage'] as String?;
      _clipRed = match?['red'] as String?;
      _clipBlue = match?['blue'] as String?;

      // Nothing goes up the uplink while a bout is being written: the encoder
      // and the live sender need it more than last bout's 400MB does.
      _vault.hold(true);

      setState(() {
        _rolling = true;
        _matDriven = match != null;
        _clipSince = DateTime.now();
        _fault = null;
      });

      _note(match != null ? 'bout_start_from_mat' : 'bout_start_by_hand');
    } catch (e) {
      setState(() => _fault = 'RECORD: $e');
      _note('record_failed');
    }
  }

  Future<void> _stopBout() async {
    if (!_rolling) return;

    final path = _clipPath;
    final since = _clipSince;

    setState(() {
      _rolling = false;
      _matDriven = false;
      _clipSince = null;
    });

    try {
      await _recorder?.stop();
    } catch (e) {
      setState(() => _fault = 'STOP: $e');
    }
    _recorder = null;

    // Let the muxer finish the moov atom before the file is measured.
    await Future.delayed(const Duration(milliseconds: 900));

    var bytes = 0;
    if (path != null && File(path).existsSync()) bytes = File(path).lengthSync();

    final seconds = since == null ? 0 : DateTime.now().difference(since).inSeconds;

    setState(() {
      _clips.insert(0, _Clip(bout: _bout, bytes: bytes, seconds: seconds, fps: _recordFps));
      if (bytes == 0) _fault = 'BOUT $_bout WROTE AN EMPTY FILE';
    });

    // Into the queue, and the queue is free to run again. An empty file is not
    // queued — there is nothing to send, and filing it would put a row in the
    // event's index promising a video that does not exist.
    if (path != null && bytes > 0) {
      await _vault.add(Clip(
        path: path,
        startedAt: since ?? DateTime.now(),
        endedAt: DateTime.now(),
        bout: _bout,
        matchId: _clipMatchId,
        number: _clipNumber,
        stage: _clipStage,
        red: _clipRed,
        blue: _clipBlue,
        court: _court,
        angle: _angle,
        event: _eventTitle,
        bytes: bytes,
        seconds: seconds,
        fps: _recordFps,
      ));
    }

    _clipMatchId = null;
    _clipNumber = null;
    _clipStage = null;
    _clipRed = null;
    _clipBlue = null;
    _vault.hold(false);

    _note('bout_stop');
    await _readDevice();

    // The frame rate somebody chose mid-bout. Deferred to here precisely
    // because applying it then would have cut the file in half; the bout is
    // over now, so it costs nothing.
    final queued = _pendingFps;
    if (queued != null) {
      setState(() => _pendingFps = null);
      await _setRecordFps(queued);
    }
  }

  /* ══════════════════════ Measurement ══════════════════════════════════════ */

  Future<void> _readDevice() async {
    try {
      final d = await _device.invokeMapMethod<String, dynamic>('read');
      if (d == null) return;
      _battery = (d['battery'] as num?)?.toInt();
      _thermal = (d['thermal'] as String?) ?? '—';
      _freeMb = (d['freeMb'] as num?)?.toInt();
      _model = (d['model'] as String?) ?? 'unknown';
    } catch (_) {}
  }

  /// What the microphone is hearing, for the meters on the left edge.
  ///
  /// Read off the live sender's own statistics, which is the only place a
  /// level is available — so the meters move when there is a feed and rest when
  /// there is not. That is honest: with no broadcast running there is nothing
  /// measuring the sound, and a bar bouncing on invented numbers would say the
  /// mic was fine when nobody had checked.
  Future<void> _readAudio() async {
    final pc = _pc;

    if (pc == null || !_live) {
      if (_audio[0] != 0 && mounted) setState(() => _audio = const [0, 0]);

      return;
    }

    try {
      for (final report in await pc.getStats()) {
        final level = (report.values['audioLevel'] as num?)?.toDouble();

        if (level != null) {
          // audioLevel is 0..1 but sits very low for speech; the meter is read
          // at a glance from a metre away, so it is scaled to fill.
          final scaled = math.min(1.0, math.sqrt(level) * 1.6);

          if (mounted) setState(() => _audio = [scaled, scaled * 0.86]);

          return;
        }
      }
    } catch (_) {}
  }

  Future<void> _sample() async {
    await _readDevice();

    if (_pc != null) {
      try {
        for (final r in await _pc!.getStats()) {
          final v = r.values;
          final isVideo = v['kind'] == 'video' || v['mediaType'] == 'video';

          if (r.type == 'outbound-rtp' && isVideo) {
            final bytes = (v['bytesSent'] as num?)?.toInt() ?? 0;
            final frames = (v['framesEncoded'] as num?)?.toInt() ?? 0;
            final now = DateTime.now();

            if (_lastAt != null) {
              final dt = now.difference(_lastAt!).inMilliseconds / 1000.0;
              if (dt > 0.5) {
                _kbps = ((bytes - _lastBytes) * 8 / 1000) / dt;
                _fps = (frames - _lastFrames) / dt;
              }
            }

            _lastBytes = bytes;
            _lastFrames = frames;
            _lastAt = now;

            _width = (v['frameWidth'] as num?)?.toInt() ?? _width;
            _height = (v['frameHeight'] as num?)?.toInt() ?? _height;

            // The encoder saying what it is short of. Under a rising thermal
            // status, `cpu` here is the phone failing to do both jobs.
            _limitedBy = (v['qualityLimitationReason'] as String?) ?? _limitedBy;
          }

          if (r.type == 'remote-inbound-rtp' && isVideo) {
            _lost = (v['packetsLost'] as num?)?.toInt() ?? _lost;
            _rtt = ((v['roundTripTime'] as num?)?.toDouble() ?? 0) * 1000;
          }
        }
      } catch (_) {}
    }

    var clipBytes = 0;
    if (_rolling && _clipPath != null && File(_clipPath!).existsSync()) {
      clipBytes = File(_clipPath!).lengthSync();
    }

    _pending.add({
      'at': DateTime.now().toIso8601String(),
      'elapsed': _liveSince == null ? 0 : DateTime.now().difference(_liveSince!).inSeconds,
      'phase': _live ? (_rolling ? 'live+rec' : 'live') : (_rolling ? 'rec' : 'idle'),
      'bout': _bout,
      'publishing': _live,
      'recording': _rolling,
      'fps': double.parse(_fps.toStringAsFixed(1)),
      'kbps': double.parse(_kbps.toStringAsFixed(0)),
      'width': _width,
      'height': _height,
      'packets_lost': _lost,
      'quality_limit': _limitedBy,
      'rtt_ms': double.parse(_rtt.toStringAsFixed(0)),
      'battery': _battery ?? -1,
      'thermal': _thermal,
      'file_bytes': clipBytes,
      'file_seconds': _clipSince == null ? 0 : DateTime.now().difference(_clipSince!).inSeconds,
      'free_mb': _freeMb ?? -1,
      'note': 'rec${_recordFps}_live$kLiveFps',
    });

    if (mounted) setState(() {});
  }

  void _note(String note) {
    _pending.add({
      'at': DateTime.now().toIso8601String(),
      'phase': 'event',
      'bout': _bout,
      'note': note,
      'battery': _battery ?? -1,
      'thermal': _thermal,
    });
  }

  /// Samples are kept in memory for the on-screen readout and nothing else.
  ///
  /// They used to be posted to the lab's telemetry door, which needed the shared
  /// key that is no longer in this build. Rather than leave a dead credential in
  /// an app people install, the queue is simply trimmed: what a run needs to
  /// show is on the phone's own screen, and the camera's real heartbeat still
  /// goes to `/camera/{token}/telemetry` like every other camera's.
  Future<void> _ship() async {
    if (_pending.length > 200) {
      _pending.removeRange(0, _pending.length - 200);
    }
  }

  @override
  void dispose() {
    _sampler?.cancel();
    _audioTimer?.cancel();
    _shipper?.cancel();
    _ticker?.cancel();
    _poll?.cancel();
    _vault.stop();
    _link?.close();
    WidgetsBinding.instance.removeObserver(this);
    _renderer.dispose();
    _pc?.close();
    _stream?.getTracks().forEach((t) => t.stop());
    super.dispose();
  }

  /* ══════════════════════ The screen ═══════════════════════════════════════ */

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Cam.ink,
      body: _token == null
          ? _bootScreen('Setting this camera up')
          : _claimed
              ? _station()
              : _unpaired(),
    );
  }

  /// Enrolling, or re-enrolling after the server said this identity is gone.
  Widget _bootScreen(String message) {
    return SafeArea(
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(
              width: 26,
              height: 26,
              child: CircularProgressIndicator(strokeWidth: 2.5, color: Cam.gold),
            ),
            const SizedBox(height: 18),
            Text(message.toUpperCase(), style: Cam.cap(15)),
            if (_fault != null) ...[
              const SizedBox(height: 14),
              CamChip(text: _fault!.toUpperCase(), warning: true, pulse: true, dotColor: Cam.gold),
            ],
          ],
        ),
      ),
    );
  }

  /* ─────────────────────── Unpaired ───────────────────────────────────────
     No preview at all, exactly as the shipping camera does it: an unclaimed
     camera is entitled to nothing, and a lens held open on a shelf is a flat
     battery. The QR, the code, and what to do with them. */

  Widget _unpaired() {
    return SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: _keepOut, vertical: 16),
          child: Wrap(
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 36,
            runSpacing: 24,
            children: [
              if (_claimUrl != null)
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(color: Cam.paper, borderRadius: BorderRadius.circular(12)),
                  child: QrImageView(data: _claimUrl!, size: 216, gapless: true),
                )
              else
                SizedBox(width: 256, child: _bootScreen('Asking for a code')),
              Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('NOT PAIRED TO A MAT', style: Cam.cap(15, color: Cam.gold, tracking: 0.2)),
                  const SizedBox(height: 14),
                  // Chunked 3+3: this gets read aloud across a hall.
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
                  const SizedBox(height: 18),

                  // What this phone already filmed.
                  //
                  // Here, on the pairing screen, because this is where a camera
                  // sits when nobody is filming with it: before the mat opens,
                  // and — the moment that matters — after it closes, when
                  // somebody lifts the phone off the tripod and wants to know
                  // whether the final is on it. Waiting for a wall console to
                  // tell them means waiting for the hall's wifi to have carried
                  // 400MB, which on the day it matters it has not.
                  _ClipsButton(
                    count: _vault.clips.length,
                    waiting: _vault.waiting,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(builder: (_) => ClipLibrary(vault: _vault)),
                    ),
                  ),
                  const SizedBox(height: 18),
                  CamChip(text: 'LAB · REC ${kRecordFpsDefault}FPS · LIVE ${kLiveFps}FPS', dotColor: Cam.gold),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _codeChunked() {
    final code = _code;
    if (code == null || code.length < 6) return '— — —';

    return '${code.substring(0, 3)} ${code.substring(3)}';
  }

  /* ─────────────────────── Paired · the instrument ───────────────────────── */

  Widget _station() {
    // Full-bleed video with everything else floating over it in glass. Offsets
    // come from the SAFE area, never from the glass: a phone has a camera cut
    // into the top of it and a gesture bar drawn over the bottom, and a control
    // placed against the raw edge lands somewhere it cannot be tapped.
    final pad = MediaQuery.paddingOf(context);
    final edge = 22.0;
    final top = pad.top + 18;
    final bottom = pad.bottom + 20;
    final left = pad.left + edge;
    final right = pad.right + edge;

    final health = _health();

    return Stack(
      fit: StackFit.expand,
      children: [
        // ── The viewfinder ────────────────────────────────────────────────
        GestureDetector(
          onTapDown: (d) {
            // No picture yet: the tap means "try again", not "meter here".
            if (!_ready) {
              setState(() => _fault = null);
              _open();

              return;
            }

            // A tap anywhere also dismisses whatever is open over the frame,
            // so the operator is never one careful press away from the mat.
            if (_drawerOpen || _telemetryOpen) {
              setState(() {
                _drawerOpen = false;
                _telemetryOpen = false;
              });

              return;
            }

            if (_focusMode != 'manual') return;

            final size = context.size;
            if (size == null) return;
            _pointAt(Offset(
              (d.localPosition.dx / size.width).clamp(0.0, 1.0),
              (d.localPosition.dy / size.height).clamp(0.0, 1.0),
            ));
          },
          child: Container(
            color: Tally.navyDeep,
            child: _ready
                ? RTCVideoView(_renderer, objectFit: RTCVideoViewObjectFit.RTCVideoViewObjectFitCover)
                : Center(
                    child: Text(
                      _fault == null ? 'OPENING THE CAMERA' : 'TAP TO TRY THE CAMERA AGAIN',
                      style: Tally.label(12, color: Tally.textFaint, tracking: 0.3),
                    ),
                  ),
          ),
        ),

        if (_ready && _grid) const ThirdsGrid(),

        // ── 1 · The tally frame ───────────────────────────────────────────
        TallyFrame(live: _live, recording: _rolling, alarm: !_linkUp),

        // ── 3 · Server link, top-left ─────────────────────────────────────
        Positioned(top: top, left: left, child: ServerLinkChip(connected: _linkUp)),

        // ── 2 · The capsule, top-centre ───────────────────────────────────
        Positioned(
          top: top,
          left: 0,
          right: 0,
          child: Center(
            child: AnimatedSize(
              duration: const Duration(milliseconds: 220),
              curve: Curves.easeOut,
              child: StatusCapsule(
                recording: _rolling,
                recElapsed: _clipSince == null ? Duration.zero : DateTime.now().difference(_clipSince!),
                live: _live,
                liveElapsed: _liveSince == null ? Duration.zero : DateTime.now().difference(_liveSince!),
                context_: _capsuleContext(),
              ),
            ),
          ),
        ),

        // The reason a degraded stream is degraded, and anything that broke.
        // Never only in small text.
        if (_banner() != null)
          Positioned(
            top: top + 52,
            left: left,
            right: right,
            child: Center(child: ReasonBanner(text: _banner()!, health: _fault != null ? Health.offline : health)),
          ),

        // ── 4 · Audio, left edge, centred ─────────────────────────────────
        Positioned(
          left: left,
          top: 0,
          bottom: 0,
          child: Center(child: AudioMeters(levels: _audio)),
        ),

        // ── 5 · Stream health, bottom-left ────────────────────────────────
        Positioned(
          left: left,
          bottom: bottom,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (_telemetryOpen) ...[
                _telemetry(),
                const SizedBox(height: 8),
              ],
              HealthChip(
                health: health,
                fraction: _kbps <= 0 ? 0 : (_kbps / kTargetKbps).clamp(0.0, 1.0),
                bitrate: _bitrateLabel(),
                detail: '${_fps == 0 ? '—' : _fps.toStringAsFixed(1)} fps · '
                    '${_rtt == 0 ? '—' : _rtt.toStringAsFixed(0)} ms · $_lost lost',
                onTap: () => setState(() => _telemetryOpen = !_telemetryOpen),
              ),
            ],
          ),
        ),

        // ── 6 · Resources, bottom-right ───────────────────────────────────
        Positioned(
          right: right,
          bottom: bottom,
          child: ResourceChips(minutesLeft: _minutesLeft(), battery: _battery),
        ),

        // ── 8 · The settings drawer, left of the rail ─────────────────────
        AnimatedPositioned(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          right: _drawerOpen ? right + 66 : right + 20,
          top: 0,
          bottom: 0,
          child: AnimatedOpacity(
            duration: const Duration(milliseconds: 200),
            opacity: _drawerOpen ? 1 : 0,
            child: IgnorePointer(
              ignoring: !_drawerOpen,
              child: Center(child: SingleChildScrollView(child: _drawer())),
            ),
          ),
        ),

        // ── 7 · The rail, right edge, centred ─────────────────────────────
        Positioned(
          right: right,
          top: 0,
          bottom: 0,
          child: Center(
            child: SingleChildScrollView(
              child: CameraRail(
                zoom: _zoom,
                onZoom: _setZoom,
                settingsOpen: _drawerOpen,
                onSettings: () => setState(() => _drawerOpen = !_drawerOpen),
              ),
            ),
          ),
        ),
      ],
    );
  }

  /// "MAT 1 · BOUT 14", or the mat and STANDBY when nothing is running.
  String _capsuleContext() {
    final mat = (_court ?? 'MAT —').toUpperCase();

    return _rolling && _bout > 0 ? '$mat · BOUT $_bout' : '$mat · STANDBY';
  }

  /// The one sentence worth putting across the screen, or none.
  ///
  /// A camera fault outranks a network one: a stream that is struggling is a
  /// degraded broadcast, and a lens that will not open is no recording at all.
  String? _banner() {
    if (_fault != null) return _fault!.toUpperCase();

    final health = _health();
    if (health.calm) return null;

    if (health == Health.offline) return 'STREAM OFFLINE · RECORDING UNAFFECTED';
    if (_limitedBy != 'none' && _limitedBy != '—') {
      return '${_limitedBy.toUpperCase()} LIMITED · RECORDING UNAFFECTED';
    }

    return 'STREAM DEGRADED · RECORDING UNAFFECTED';
  }

  /// Health as a word, from what the sender is actually managing.
  ///
  /// Not live at all is not "offline" — it is nothing, and a red word for a
  /// camera that was never asked to broadcast would be a lie.
  Health _health() {
    if (!_live) return Health.healthy;
    if (_connecting) return Health.reconnecting;
    if (_kbps <= 0 || _fps <= 0) return Health.offline;

    final slow = _fps < kLiveFps * 0.7 || _kbps < kTargetKbps * 0.5;
    final lossy = _lost > 50 || _rtt > 300;

    return slow || lossy ? Health.degraded : Health.healthy;
  }

  String _bitrateLabel() {
    if (_kbps <= 0) return '—';
    if (_kbps >= 1000) return '${(_kbps / 1000).toStringAsFixed(1)}M';

    return '${_kbps.toStringAsFixed(0)}K';
  }

  /// Storage as the minutes of recording it will actually hold.
  ///
  /// Measured against the rate this phone is really writing at when a clip has
  /// been filed, and against a conservative estimate before that.
  int? _minutesLeft() {
    final free = _freeMb;
    if (free == null) return null;

    // MB per minute: from the last clip if there is one, otherwise from the
    // frame rate — 1080p60 is around 400 MB a minute on this encoder.
    var perMinute = _recordFps >= 60 ? 400.0 : 220.0;

    final measured = _clips.firstWhere((c) => c.bytes > 0 && c.seconds > 2, orElse: () => _Clip(bout: 0, bytes: 0, seconds: 0, fps: 0));
    if (measured.bytes > 0) {
      perMinute = (measured.bytes / 1048576) / (measured.seconds / 60);
    }

    if (perMinute <= 0) return null;

    return (free / perMinute).floor();
  }

  /// Everything the health chip summarises, for whoever taps it.
  Widget _telemetry() {
    Widget row(String k, String v) => Padding(
          padding: const EdgeInsets.only(bottom: 5),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(width: 96, child: Text(k, style: Tally.label(9.5))),
              Text(v, style: Tally.number(11, color: Tally.textSecondary, weight: FontWeight.w500)),
            ],
          ),
        );

    return Glass(
      radius: 14,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          row('SENT FPS', _fps == 0 ? '—' : _fps.toStringAsFixed(1)),
          row('BITRATE', _kbps == 0 ? '—' : '${_kbps.toStringAsFixed(0)} kbps'),
          row('SIZE', _width == 0 ? '—' : '$_width×$_height'),
          row('LIMITED BY', _limitedBy),
          row('RTT', _rtt == 0 ? '—' : '${_rtt.toStringAsFixed(0)} ms'),
          row('LOST', '$_lost'),
          if (_thermal != 'none' && _thermal != '—') row('THERMAL', _thermal.toUpperCase()),
          row('ON THIS PHONE', '${_clips.length} clip${_clips.length == 1 ? '' : 's'}'),
          row('IN THE VAULT', '${_vault.uploaded} of ${_vault.uploaded + _vault.waiting}'),
          if (_vault.inFlight != null)
            row('UPLOADING', 'BOUT ${_vault.inFlight!.number ?? _vault.inFlight!.bout} · '
                '${(_vault.inFlight!.fraction * 100).toStringAsFixed(0)}%')
          else if (_vault.waiting > 0)
            row('TO UPLOAD', _rolling
                ? '${_vault.waiting} · HELD WHILE RECORDING'
                : '${_vault.waiting} waiting'),
          if (_streamId != null) row('STREAM', _streamId!),
          if (_eventTitle != null && _eventTitle!.isNotEmpty) row('EVENT', _eventTitle!),
        ],
      ),
    );
  }

  /* ─────────────────────── The settings drawer ───────────────────────────── */

  Widget _drawer() {
    return SizedBox(
      width: 250,
      child: Glass(
        radius: 16,
        blur: Tally.drawerBlur,
        fill: Tally.drawerFill,
        edge: Tally.drawerEdge,
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('CAMERA SETTINGS', style: Tally.label(11, color: Tally.textSecondary, tracking: 0.14, weight: FontWeight.w700)),
                GestureDetector(
                  onTap: () => setState(() => _drawerOpen = false),
                  behavior: HitTestBehavior.opaque,
                  child: const Icon(Icons.close, size: 15, color: Tally.textMuted),
                ),
              ],
            ),
            const SizedBox(height: 14),

            FieldLabel(name: 'AUTOFOCUS', value: _focusMode.toUpperCase()),
            const SizedBox(height: 6),
            Segmented(
              value: _focusMode,
              options: const [
                (label: 'Auto', value: 'auto'),
                (label: 'Lock', value: 'lock'),
                (label: 'Manual', value: 'manual'),
              ],
              onPick: _setFocusMode,
            ),
            const SizedBox(height: 14),

            FieldLabel(name: 'BRIGHTNESS · EV', value: '${_ev >= 0 ? '+' : ''}${_ev.toStringAsFixed(1)}'),
            const SizedBox(height: 6),
            Opacity(
              opacity: _supportsEv ? 1 : 0.4,
              child: SliderTheme(
              data: SliderThemeData(
                trackHeight: 4,
                activeTrackColor: Tally.gold,
                inactiveTrackColor: Colors.white.withValues(alpha: 0.16),
                thumbColor: Tally.goldLight,
                overlayColor: Tally.gold.withValues(alpha: 0.15),
                thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 8),
                trackShape: const RectangularSliderTrackShape(),
              ),
              child: SizedBox(
                height: 22,
                child: Slider(
                  min: -2,
                  max: 2,
                  divisions: 20,
                  value: _ev,
                  onChanged: _supportsEv ? _setEv : null,
                ),
              ),
            ),
            ),
            const SizedBox(height: 14),

            FieldLabel(name: 'FRAME RATE', value: '$_recordFps'),
            const SizedBox(height: 6),
            Segmented(
              value: '$_recordFps',
              options: const [
                (label: '24', value: '24'),
                (label: '30', value: '30'),
                (label: '60', value: '60'),
              ],
              onPick: (v) => _setRecordFps(int.parse(v)),
            ),
            const SizedBox(height: 14),

            FieldLabel(name: 'WHITE BALANCE', value: _whiteBalance.toUpperCase()),
            const SizedBox(height: 6),
            Segmented(
              value: _whiteBalance,
              options: const [
                (label: 'Auto', value: 'auto'),
                (label: 'Indoor', value: 'indoor'),
                (label: 'Lock', value: 'lock'),
              ],
              enabled: _supportsWhiteBalance,
              onPick: (v) => setState(() => _whiteBalance = v),
            ),
            const SizedBox(height: 14),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Stabilization', style: Tally.body(11, color: Tally.textSecondary, weight: FontWeight.w600)),
                TallySwitch(
                  on: _stabilize,
                  enabled: _supportsStabilization,
                  onChanged: (v) => setState(() => _stabilize = v),
                ),
              ],
            ),
            const SizedBox(height: 12),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Rule-of-thirds grid', style: Tally.body(11, color: Tally.textSecondary, weight: FontWeight.w600)),
                TallySwitch(on: _grid, onChanged: (v) => setState(() => _grid = v)),
              ],
            ),
            const SizedBox(height: 12),

            Text(
              _pendingFps != null
                  ? 'Frame rate changes to $_pendingFps at the next bout — the mat owns REC and LIVE, so a mid-stream switch is queued.'
                  : 'Frame-rate changes apply at the next bout — the mat owns REC and LIVE, so mid-stream switches are queued.',
              style: Tally.body(9.5, color: Tally.textFaint),
            ),
            if (!_supportsEv || !_supportsWhiteBalance || !_supportsStabilization) ...[
              const SizedBox(height: 6),
              Text(
                'Brightness, white balance and stabilization are dimmed: this '
                'camera stack exposes zoom, focus and exposure only.',
                style: Tally.body(9.5, color: Tally.textFaint),
              ),
            ],
          ],
        ),
      ),
    );
  }


  static String _two(int n) => n.toString().padLeft(2, '0');

  static String _slug(String raw) =>
      raw.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), '-').replaceAll(RegExp(r'^-|-$'), '');
}

/// "Show me what this phone has." The pairing screen's one other door.
class _ClipsButton extends StatelessWidget {
  const _ClipsButton({required this.count, required this.waiting, required this.onTap});

  final int count;
  final int waiting;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 320,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: Tally.gold.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Tally.gold.withValues(alpha: 0.38)),
        ),
        child: Row(
          children: [
            const Icon(Icons.video_library_outlined, color: Tally.gold, size: 20),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('RECORDED BOUTS', style: Tally.label(11.5, color: Tally.goldLight, tracking: 0.16, weight: FontWeight.w700)),
                  const SizedBox(height: 2),
                  Text(
                    count == 0
                        ? 'Nothing filmed on this phone yet'
                        : '$count on this phone'
                            '${waiting > 0 ? ' · $waiting still to send' : ' · all sent'}',
                    style: Tally.body(11.5, color: Tally.textFaint),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: Tally.textFaint, size: 20),
          ],
        ),
      ),
    );
  }
}

class _Clip {
  _Clip({required this.bout, required this.bytes, required this.seconds, required this.fps});

  final int bout;
  final int bytes;
  final int seconds;
  final int fps;
}
