import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';

/// Getting the bouts off the phone.
///
/// boutcam records each bout to the phone's own storage and, until now, that is
/// where every clip stayed: a competition ended with four phones nobody could
/// do anything with until somebody found a cable. This is the piece that closes
/// it — the same two-step contract the shipping camera uses.
///
///   1. FILE IT.   `POST /camera/{token}/clip` tells the event which bout this
///                 was, how long, how big, and what the phone calls the file.
///                 Idempotent on (camera, local_ref), so a retry over hall wifi
///                 does not create a second row.
///   2. SEND IT.   `POST /camera/{token}/clip/{id}/upload`, in 8MB pieces, and
///                 the SERVER's offset is authoritative — a phone that lost the
///                 network asks where it got to and continues from there. The
///                 last piece carries `?final=1` and the server hands the file
///                 to `IngestClipMedia`, which files it in the vault and
///                 attaches it to the bout.
///
/// Three rules this queue keeps, all of them learned the hard way in a hall:
///
/// · **The file is never deleted.** The phone holds the only original until the
///   server says the whole thing arrived, and it keeps holding it afterwards.
///   Storage is recovered by a person, deliberately, not by a background job
///   that guessed wrong about what "done" meant.
/// · **It uploads nothing unless the operator turned auto upload ON.** A camera
///   stands unattended on whatever network the hall has — a metered hotel
///   uplink, a volunteer's tether, a venue that bills by the gigabyte — and
///   spending that on 400MB a bout is not this app's decision to make. The
///   switch lives in the camera settings drawer and defaults to OFF; with it
///   off the clips queue up on the phone and go when somebody says so.
/// · **It never uploads while the camera is rolling.** A bout is being written
///   at 60fps and, on this app, very possibly published live at the same time;
///   spending the uplink on last bout's 400MB during this one is how the feed
///   stutters and the encoder starts dropping frames. The queue holds, and the
///   gap between bouts is what it uses.
/// · **Nothing here throws at the caller.** A camera stands unattended on a
///   tripod. Every failure is a state on a clip and a retry later, never a red
///   screen that takes the mat's camera off the mat.
class Clip {
  Clip({
    required this.path,
    required this.startedAt,
    required this.bout,
    this.endedAt,
    this.matchId,
    this.number,
    this.stage,
    this.red,
    this.blue,
    this.court,
    this.angle,
    this.event,
    this.bytes = 0,
    this.seconds = 0,
    this.fps = 0,
    this.serverId,
    this.sent = 0,
    this.state = pending,
    this.attempts = 0,
  });

  /// Waiting for its turn, on its way, safely in the vault, or given up on for
  /// now (which still means "try again on the next sweep").
  static const pending = 'pending';
  static const uploading = 'uploading';
  static const done = 'done';
  static const failed = 'failed';

  /// Absolute path on this phone. Never sent — the server is told the file NAME,
  /// which is what a person looks for when they finally plug the phone in.
  final String path;

  final DateTime startedAt;
  DateTime? endedAt;

  /// This phone's own count, for the screen. The bout NUMBER below is the mat's.
  final int bout;

  /// The bout this clip is, as the event knows it. Null when the mat was rolling
  /// with nothing loaded — the clip is still real and still worth keeping.
  final int? matchId;
  final String? number;

  /// Who was in it and where, captured at hajime and kept with the file.
  ///
  /// Copied rather than looked up, deliberately: the mat has loaded the next
  /// bout by the time anybody opens this list, and a phone that spent the day
  /// out of network range must still be able to say "that one is the −68kg
  /// semi-final, red was Ali" while standing beside the tripod.
  final String? stage;
  final String? red;
  final String? blue;
  final String? court;
  final int? angle;
  final String? event;

  int bytes;
  int seconds;
  int fps;

  /// The row this clip has in the event's index, once filed.
  int? serverId;

  /// How many bytes the SERVER has confirmed. Persisted, so a restart resumes
  /// roughly where it was rather than re-asking from zero (the server corrects
  /// it either way).
  int sent;

  String state;

  /// Consecutive failures, for the backoff. Reset by any progress.
  int attempts;

  String get ref => path.split('/').last;

  /// What this clip is called on the list: the mat's bout number when there was
  /// one, otherwise this phone's own count — never nothing.
  String get title => (number != null && number!.isNotEmpty) ? 'BOUT $number' : 'CLIP $bout';

  /// The two corners, or the file name when the mat was rolling unloaded.
  String get corners {
    final names = [red, blue].whereType<String>().where((n) => n.isNotEmpty).toList();

    return names.length == 2 ? '${names[0]}  vs  ${names[1]}' : (names.isEmpty ? ref : names.first);
  }

  String get sizeLabel {
    if (bytes <= 0) return '—';
    if (bytes >= 1073741824) return '${(bytes / 1073741824).toStringAsFixed(2)} GB';

    return '${(bytes / 1048576).toStringAsFixed(0)} MB';
  }

  String get lengthLabel {
    final total = seconds > 0 ? seconds : (endedAt ?? startedAt).difference(startedAt).inSeconds;
    final m = (total ~/ 60).toString().padLeft(2, '0');

    return '$m:${(total % 60).toString().padLeft(2, '0')}';
  }

  double get fraction => bytes <= 0 ? 0 : (sent / bytes).clamp(0.0, 1.0);

  bool get settled => state == done;

  Map<String, dynamic> toJson() => {
        'path': path,
        'started_at': startedAt.toIso8601String(),
        'ended_at': endedAt?.toIso8601String(),
        'bout': bout,
        'match_id': matchId,
        'number': number,
        'stage': stage,
        'red': red,
        'blue': blue,
        'court': court,
        'angle': angle,
        'event': event,
        'bytes': bytes,
        'seconds': seconds,
        'fps': fps,
        'server_id': serverId,
        'sent': sent,
        'state': state,
      };

  static Clip fromJson(Map<String, dynamic> json) => Clip(
        path: json['path'] as String,
        startedAt: DateTime.parse(json['started_at'] as String),
        endedAt: json['ended_at'] == null ? null : DateTime.parse(json['ended_at'] as String),
        bout: (json['bout'] as num?)?.toInt() ?? 0,
        matchId: (json['match_id'] as num?)?.toInt(),
        number: json['number'] as String?,
        stage: json['stage'] as String?,
        red: json['red'] as String?,
        blue: json['blue'] as String?,
        court: json['court'] as String?,
        angle: (json['angle'] as num?)?.toInt(),
        event: json['event'] as String?,
        bytes: (json['bytes'] as num?)?.toInt() ?? 0,
        seconds: (json['seconds'] as num?)?.toInt() ?? 0,
        fps: (json['fps'] as num?)?.toInt() ?? 0,
        serverId: (json['server_id'] as num?)?.toInt(),
        sent: (json['sent'] as num?)?.toInt() ?? 0,
        // An upload that was interrupted mid-flight is pending again, not
        // "uploading" — nothing is moving until this queue starts it.
        state: json['state'] == Clip.done ? Clip.done : Clip.pending,
      );
}

/// The phone's ledger of what it filmed, and the worker that empties it.
class ClipVault {
  ClipVault({required this.onChange});

  /// Called whenever anything a screen would draw has changed.
  final void Function() onChange;

  /// Anyone else drawing this ledger — the recorded-bouts list, while it is
  /// open. Held weakly in spirit rather than in fact: a screen adds itself on
  /// the way in and removes itself on the way out, and the queue keeps running
  /// either way.
  final List<void Function()> _watchers = [];

  void watch(void Function() listener) => _watchers.add(listener);

  void unwatch(void Function() listener) => _watchers.remove(listener);

  void _notify() {
    onChange();

    for (final w in List<void Function()>.from(_watchers)) {
      w();
    }
  }

  static const _key = 'boutcam.clips';

  /// The auto-upload switch, remembered across restarts.
  static const _autoKey = 'boutcam.autoUpload';

  /// A competition day is a few hundred bouts. The cap stops a phone that is
  /// never cleared carrying an unbounded list in its preferences file.
  static const _max = 500;

  /// 8MB — exactly what the server accepts in one piece (CameraController).
  static const _chunk = 8 * 1024 * 1024;

  static final HttpClient _client = HttpClient()
    ..connectionTimeout = const Duration(seconds: 15)
    ..idleTimeout = const Duration(seconds: 60);

  final List<Clip> clips = [];

  String? _token;

  /// True while the camera is recording. The queue does nothing at all then.
  bool _held = false;

  /// Does a finished bout go up by itself? OFF until the operator says so.
  ///
  /// Persisted next to the ledger, so a phone that restarts in the middle of a
  /// competition comes back doing what it was told rather than what the app
  /// assumed. `sendNow()` bypasses it — that is somebody asking.
  bool _auto = false;

  bool get auto => _auto;

  bool _running = false;
  Timer? _sweep;

  /// Set after a failure, so a hall with no uplink is not hammered.
  DateTime? _notBefore;

  /// What the screen shows: how many are still to go, and the one in flight.
  int get waiting => clips.where((c) => !c.settled).length;
  int get uploaded => clips.where((c) => c.settled).length;
  Clip? get inFlight {
    for (final c in clips) {
      if (c.state == Clip.uploading) return c;
    }
    return null;
  }

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();

    _auto = prefs.getBool(_autoKey) ?? false;

    final raw = prefs.getString(_key);

    if (raw == null) return;

    try {
      final list = jsonDecode(raw) as List<dynamic>;
      clips
        ..clear()
        ..addAll(list.map((e) => Clip.fromJson(e as Map<String, dynamic>)));
    } catch (_) {
      // A ledger we cannot read is not worth taking the camera down for. The
      // videos are still on the phone under their own names.
    }

    _notify();
  }

  Future<void> _save() async {
    final prefs = await SharedPreferences.getInstance();
    final trimmed = clips.take(_max).toList();

    await prefs.setString(_key, jsonEncode(trimmed.map((c) => c.toJson()).toList()));
  }

  /// The camera's identity, as it changes. A phone that re-enrolled has a new
  /// token and the old clips' rows belong to a camera that no longer exists —
  /// they keep their files and simply get filed again under the new identity.
  void useToken(String? token) {
    if (_token == token) return;

    // Only a REPLACED identity invalidates the rows. Learning the token for the
    // first time after a restart does not: those clips were filed by this same
    // camera and their rows are still theirs, so throwing the ids away would
    // re-send bytes the server already holds.
    final replaced = _token != null && token != null;

    _token = token;

    if (replaced) {
      for (final c in clips) {
        if (!c.settled) {
          c.serverId = null;
          c.sent = 0;
        }
      }

      unawaited(_save());
    }
  }

  /// The operator turning automatic uploading on or off.
  ///
  /// Turning it ON also releases whatever is already queued: those clips are
  /// what they were looking at when they reached for the switch. Turning it off
  /// lets an upload already in flight finish — abandoning it halfway leaves the
  /// server holding a partial file and gains the uplink nothing.
  Future<void> setAuto(bool on) async {
    if (_auto == on) return;

    _auto = on;
    (await SharedPreferences.getInstance()).setBool(_autoKey, on);
    _notify();

    if (on) unawaited(pump());
  }

  /// Send everything waiting, once, whatever the switch says.
  ///
  /// The manual door: an operator who keeps auto upload off still needs a way
  /// to say "now" — on the hall's own wifi, at the end of the day, when the
  /// bytes are free.
  Future<void> sendNow() => pump(force: true);

  /// Hold everything while a bout is being written (see the class comment).
  void hold(bool holding) {
    if (_held == holding) return;

    _held = holding;

    if (!holding) unawaited(pump());
  }

  void start() {
    _sweep?.cancel();
    _sweep = Timer.periodic(const Duration(seconds: 20), (_) => unawaited(pump()));
    unawaited(pump());
  }

  void stop() {
    _sweep?.cancel();
    _sweep = null;
  }

  /// A finished bout joins the queue.
  Future<void> add(Clip clip) async {
    clips.insert(0, clip);
    _notify();
    await _save();
    unawaited(pump());
  }

  /// Do whatever the queue can do right now. Single-flight.
  ///
  /// `force` is an explicit "send it" from a person and ignores the auto-upload
  /// switch and the backoff — not the recording hold, which exists to protect
  /// the bout being filmed and is never somebody's to override.
  Future<void> pump({bool force = false}) async {
    if (_running || _held || _token == null) return;
    if (!_auto && !force) return;
    if (!force && _notBefore != null && DateTime.now().isBefore(_notBefore!)) return;

    _running = true;

    try {
      // Oldest first: the bout somebody is waiting to watch is the one that
      // finished first, not the one that just ended.
      final queue = clips.where((c) => !c.settled).toList().reversed.toList();

      for (final clip in queue) {
        if (_held || _token == null) break;

        final failure = await _sendOne(clip);

        if (failure != null) {
          clip.state = Clip.failed;
          clip.attempts += 1;

          // 30s, a minute, two, four… capped at five. A hall's wifi comes back
          // on its own and this must not be what stops it being used.
          final wait = 30 * (1 << (clip.attempts.clamp(1, 4) - 1));
          _notBefore = DateTime.now().add(Duration(seconds: wait.clamp(30, 300)));

          _notify();
          await _save();

          break;
        }

        clip.state = Clip.done;
        clip.attempts = 0;
        _notBefore = null;

        _notify();
        await _save();
      }
    } finally {
      _running = false;
    }
  }

  /// File it, then send it. Returns null on success or a short reason.
  Future<String?> _sendOne(Clip clip) async {
    final token = _token;

    if (token == null) return 'no token';

    final file = File(clip.path);

    if (!file.existsSync()) return 'file gone';

    final total = file.lengthSync();

    if (total <= 0) return 'empty file';

    // The declared size is a ceiling the server enforces, so it must be the
    // real one — a clip measured before the muxer finished would be refused
    // partway up.
    if (clip.bytes != total) {
      clip.bytes = total;
      await _save();
    }

    if (clip.serverId == null) {
      final filed = await CameraApi(token).reportClip(
        localRef: clip.ref,
        matchId: clip.matchId,
        startedAt: clip.startedAt,
        endedAt: clip.endedAt,
        durationSeconds: clip.seconds,
        bytes: total,
      );

      final id = (filed?['id'] as num?)?.toInt();

      if (id == null) return 'not filed';

      clip.serverId = id;
      await _save();
    }

    clip.state = Clip.uploading;
    _notify();

    var offset = clip.sent.clamp(0, total);

    while (offset < total) {
      if (_held) return 'paused';

      final end = (offset + _chunk) > total ? total : offset + _chunk;
      final isLast = end >= total;

      List<int> body;

      try {
        body = await file.openRead(offset, end).expand((c) => c).toList();
      } catch (_) {
        return 'unreadable';
      }

      final answer = await _postChunk(token, clip.serverId!, offset, body, isLast);

      if (answer == null) return 'network';

      // The server knows where it got to. A mismatch is a resume instruction,
      // not a failure — this is the ordinary case after a dropped connection.
      if (answer.status == 409) {
        final at = (answer.body?['offset'] as num?)?.toInt();

        if (at == null) return 'offset';

        offset = at;
        clip.sent = offset;
        _notify();

        continue;
      }

      if (answer.status >= 400) return 'refused ${answer.status}';

      offset = (answer.body?['offset'] as num?)?.toInt() ?? end;
      clip.sent = offset;
      _notify();

      if (answer.body?['done'] == true) break;
    }

    clip.sent = clip.bytes;

    return null;
  }

  Future<({int status, Map<String, dynamic>? body})?> _postChunk(
    String token,
    int clipId,
    int offset,
    List<int> body,
    bool isLast,
  ) async {
    try {
      final url = Uri.parse('${Lab.clipUpload(token, clipId)}${isLast ? '?final=1' : ''}');
      final request = await _client.postUrl(url).timeout(const Duration(seconds: 20));

      request.headers.set('accept', 'application/json');
      request.headers.set(HttpHeaders.contentTypeHeader, 'application/octet-stream');
      request.headers.set('Upload-Offset', '$offset');
      request.add(body);

      final response = await request.close().timeout(const Duration(minutes: 3));
      final text = await response.transform(utf8.decoder).join();
      final decoded = text.isEmpty ? null : jsonDecode(text);

      return (
        status: response.statusCode,
        body: decoded is Map<String, dynamic> ? decoded : null,
      );
    } catch (_) {
      // The connection dropped, or the hall's wifi went away with it. The
      // server's offset survives; the next sweep carries on from there.
      return null;
    }
  }
}
