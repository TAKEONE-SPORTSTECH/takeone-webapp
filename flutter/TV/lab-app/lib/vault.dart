import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;

import 'package:flutter/services.dart';
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
    this.recovered = false,
    this.confirmed = false,
    this.tilt = 0,
  });

  /// The leveling the camera was set to when this clip was filmed, in degrees
  /// (clockwise). The phone cannot rotate what its lens records, so the file
  /// stays as filmed and this is applied wherever the clip is SHOWN — the
  /// player, and a video made to send. Kept per clip, so straightening the
  /// camera later never tilts the bouts already on the phone.
  final double tilt;

  /// How much a picture turned by `degrees` must grow to fill its own frame
  /// again with no empty corners — the zoom the leveling costs.
  static double fillFor(double degrees, double aspect) {
    final t = degrees.abs() * math.pi / 180;
    final a = aspect <= 0 ? 16 / 9 : aspect;

    return math.cos(t) + math.max(a, 1 / a) * math.sin(t);
  }

  /// Waiting for its turn, on its way, safely in the vault, or given up on for
  /// now (which still means "try again on the next sweep").
  static const pending = 'pending';
  static const uploading = 'uploading';
  static const done = 'done';
  static const failed = 'failed';

  /// Absolute path on this phone. Never sent — the server is told the file NAME,
  /// which is what a person looks for when they finally plug the phone in.
  ///
  /// Normally the phone's gallery (Movies/TAKEONE), where a finished bout is
  /// moved so it outlives the app; a private path only until that move lands.
  String path;

  final DateTime startedAt;
  DateTime? endedAt;

  /// This phone's own count, for the screen. The bout NUMBER below is the mat's.
  final int bout;

  /// The bout this clip is, as the event knows it. Null when the mat was rolling
  /// with nothing loaded — the clip is still real and still worth keeping.
  int? matchId;
  String? number;

  /// Who was in it and where, captured at hajime and kept with the file.
  ///
  /// Copied rather than looked up, deliberately: the mat has loaded the next
  /// bout by the time anybody opens this list, and a phone that spent the day
  /// out of network range must still be able to say "that one is the −68kg
  /// semi-final, red was Ali" while standing beside the tripod.
  String? stage;
  String? red;
  String? blue;
  String? court;
  final int? angle;
  String? event;

  /// What the bracket says about the bout, read back from the event: the
  /// division, both scores (as the scoring table recorded them) and, once the
  /// bout is over, which corner won.
  String? division;
  String? redScore;
  String? blueScore;
  String? winner;
  bool isFinal = false;

  /// The club each corner fought for, as the bout page names it — what the
  /// list's Team filter reads. `clubsLooked` once the event has answered with
  /// clubs at all, so a finished bout is asked once more, not forever.
  String? redClub;
  String? blueClub;
  bool clubsLooked = false;

  /// The event has answered for this clip at least once (see
  /// ClipVault.identify) — so a list with no names says "not on file" rather
  /// than "still looking".
  bool looked = false;

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

  /// Found in the phone's gallery rather than filmed by this install — the app
  /// was reinstalled. Such a clip may belong to a competition long over, so it
  /// is sent nowhere until the event this camera is paired to recognises it
  /// ([confirmed]); until then it stays on the phone and plays and sends plain.
  bool recovered;
  bool confirmed;

  /// Recovered and not (yet) recognised by this camera's event.
  bool get held => recovered && !confirmed;

  /// Why the last attempt to send it failed, for the screen. Not persisted.
  String? problem;

  String get ref => path.split('/').last;

  /// What this clip is called on the list: the mat's bout number when there was
  /// one, otherwise this phone's own count — never nothing.
  String get title => (number != null && number!.isNotEmpty && !number!.startsWith('ex'))
      ? 'BOUT $number'
      : (number != null && number!.startsWith('ex'))
          ? 'EXHIBITION ${number!.substring(2)}'
          : (bout > 0 ? 'CLIP $bout' : 'RECORDED BOUT');

  /// The two corners, or the file name when the mat was rolling unloaded.
  String get corners {
    final names = [red, blue].whereType<String>().where((n) => n.isNotEmpty).toList();

    if (names.length == 2) return '${names[0]}  vs  ${names[1]}';
    if (names.isNotEmpty) return names.first;

    // Never the file name: nobody reads "mat-mat-1_angle-1_…" as a bout.
    return title == 'CLIP $bout' && bout == 0 ? 'Recorded bout' : title;
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
        'recovered': recovered,
        'confirmed': confirmed,
        'tilt': tilt,
        'division': division,
        'red_score': redScore,
        'blue_score': blueScore,
        'winner': winner,
        'final': isFinal,
        'red_club': redClub,
        'blue_club': blueClub,
        'clubs_looked': clubsLooked,
        'looked': looked,
      };

  static Clip fromJson(Map<String, dynamic> json) => _fromJson(json)
    ..division = json['division'] as String?
    ..redScore = json['red_score'] as String?
    ..blueScore = json['blue_score'] as String?
    ..winner = json['winner'] as String?
    ..isFinal = json['final'] == true
    ..redClub = json['red_club'] as String?
    ..blueClub = json['blue_club'] as String?
    ..clubsLooked = json['clubs_looked'] == true
    ..looked = json['looked'] == true;

  static Clip _fromJson(Map<String, dynamic> json) => Clip(
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
        recovered: json['recovered'] == true,
        confirmed: json['confirmed'] == true,
        tilt: (json['tilt'] as num?)?.toDouble() ?? 0,
      );
}

/// Everything a bout's FILE NAME says about it — the only record that survives
/// the app being uninstalled, because the gallery keeps the file and nothing
/// else.
///
///   `mat-<court>_angle-<n>_bout-<no>_m<match id>_<fps>fps_<yyyymmdd-hhmmss>_red-<name>_blue-<name>.mp4`
///
/// Every piece is optional and found by its prefix, so the same reader takes
/// this app's older names and the old mat camera's
/// (`mat-mat-1_angle-1_bout-70_20261017-164240.mp4`) — those recordings sit in
/// the same folder and are worth finding too.
class ClipName {
  /// One piece of a name: no separator, nothing a file system refuses. Any
  /// script survives, so an Arabic name is still the competitor's name.
  static String piece(String raw, {int max = 32}) {
    final cleaned = raw
        .trim()
        .replaceAll(RegExp(r'[\s_/\\:*?"<>|.]+'), '-')
        .replaceAll(RegExp(r'^-+|-+$'), '');
    final runes = cleaned.runes.toList();

    return String.fromCharCodes(runes.length > max ? runes.sublist(0, max) : runes);
  }

  static Clip? read({required String name, required String path, int bytes = 0, int seconds = 0}) {
    if (!name.toLowerCase().endsWith('.mp4')) return null;

    String? court, number, red, blue;
    int? angle, matchId, fps;
    DateTime? at;

    // Android names a second file "x (1).mp4"; the bout is the same one.
    final stem = name.substring(0, name.length - 4).replaceAll(RegExp(r' \(\d+\)$'), '');

    for (final part in stem.split('_')) {
      RegExpMatch? m;

      if (part.startsWith('mat-')) {
        court = part.substring(4).replaceAll('-', ' ');
      } else if ((m = RegExp(r'^angle-(\d+)$').firstMatch(part)) != null) {
        angle = int.parse(m!.group(1)!);
      } else if (part.startsWith('bout-')) {
        number = part.substring(5);
      } else if ((m = RegExp(r'^m(\d+)$').firstMatch(part)) != null) {
        matchId = int.parse(m!.group(1)!);
      } else if ((m = RegExp(r'^(\d+)fps$').firstMatch(part)) != null) {
        fps = int.parse(m!.group(1)!);
      } else if ((m = RegExp(r'^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$').firstMatch(part)) != null) {
        final g = [for (var i = 1; i <= 6; i++) int.parse(m!.group(i)!)];
        at = DateTime(g[0], g[1], g[2], g[3], g[4], g[5]);
      } else if (part.startsWith('red-')) {
        red = part.substring(4).replaceAll('-', ' ');
      } else if (part.startsWith('blue-')) {
        blue = part.substring(5).replaceAll('-', ' ');
      }
    }

    final started = at ?? File(path).statSync().modified;

    return Clip(
      path: path,
      startedAt: started,
      endedAt: seconds > 0 ? started.add(Duration(seconds: seconds)) : null,
      bout: 0,
      matchId: matchId,
      number: number,
      red: red,
      blue: blue,
      court: court,
      angle: angle,
      bytes: bytes,
      seconds: seconds,
      fps: fps ?? 0,
      recovered: true,
    );
  }
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

  /// The bouts the operator picked to upload, remembered so a restart in the
  /// middle of the list carries on with it.
  static const _wantedKey = 'boutcam.upload.wanted';

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

  /// The operator's own upload list (by file name): picked on the Recorded
  /// bouts screen, one or all. These go whatever the auto-upload switch says,
  /// one after another, each in 8MB pieces — and a list that stalls on bad wifi
  /// is picked up again by the sweep until every one of them is there.
  final Set<String> _wanted = {};

  bool wanted(Clip clip) => _wanted.contains(clip.ref) && !clip.settled;

  /// How many of the picked bouts are still to go.
  int get wantedLeft => clips.where(wanted).length;

  /// Put these bouts on the upload list and start on it now.
  Future<void> upload(Iterable<Clip> picked) async {
    for (final c in picked) {
      if (c.settled || c.held) continue;
      _wanted.add(c.ref);
      c.attempts = 0;
      c.problem = null;
    }

    _notBefore = null;
    await _saveWanted();
    _notify();
    unawaited(pump(force: true));
  }

  /// Forget the upload list. A piece already on its way finishes; what reached
  /// the event stays there, and the rest stay on the phone.
  Future<void> cancelUploads() async {
    _wanted.clear();
    await _saveWanted();
    _notify();
  }

  Future<void> _saveWanted() async {
    _wanted.removeWhere((ref) => !clips.any((c) => c.ref == ref && !c.settled));
    (await SharedPreferences.getInstance()).setStringList(_wantedKey, _wanted.toList());
  }

  /// What the screen shows: how many are still to go, and the one in flight.
  int get waiting => clips.where((c) => !c.settled && !c.held).length;
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
    _wanted
      ..clear()
      ..addAll(prefs.getStringList(_wantedKey) ?? const []);
    _parts = (prefs.getStringList(_partsKey) ?? allParts).toSet();
    // The statistics card joined the list later: a choice saved before it
    // existed gets it switched on once, and keeps every other switch as set.
    if (prefs.getBool(_statsAddedKey) != true) {
      _parts.add('stats');
      await prefs.setBool(_statsAddedKey, true);
      await prefs.setStringList(_partsKey, _parts.toList());
    }
    _lift = (prefs.getDouble(_liftKey) ?? liftDefault).clamp(liftMin, liftMax);
    _vsLift = (prefs.getDouble(_vsLiftKey) ?? vsLiftDefault).clamp(vsLiftMin, vsLiftMax);
    final music = prefs.getString(_musicKey);
    if (music != null && File(music).existsSync()) {
      _musicPath = music;
      _musicName = prefs.getString(_musicNameKey);
    }

    final raw = prefs.getString(_key);

    if (raw != null) {
      try {
        final list = jsonDecode(raw) as List<dynamic>;
        clips
          ..clear()
          ..addAll(list.map((e) => Clip.fromJson(e as Map<String, dynamic>)));
      } catch (_) {
        // A ledger we cannot read is not worth taking the camera down for. The
        // videos are still on the phone under their own names — and the
        // gallery pass below finds them again.
      }
    }

    _notify();

    // The one question asked before anything else starts: the camera's own
    // permission prompt comes later, and two prompts at once cancel each other.
    await askGallery();

    // The heavy part (moving files) runs alongside the boot, not in front of it.
    unawaited(recover());
  }

  /// Ask for the gallery read grant if this install does not have it yet.
  static Future<void> askGallery() async {
    try {
      if (await _gallery.invokeMethod<bool>('canRead') != true) {
        await _gallery.invokeMethod<bool>('askRead');
      }
    } catch (_) {}
  }

  /* ─────────────────── the gallery: what survives an uninstall ─────────────────── */

  static const _gallery = MethodChannel('bh.takeone.lab/gallery');

  /// Move a finished bout into the phone's gallery and answer with its new
  /// path, or null when the phone would not take it (the private file is then
  /// untouched and still the clip). The native side proves the copy before it
  /// removes the original.
  static Future<String?> keep(String path) async {
    try {
      return await _gallery.invokeMethod<String>('publish', {
        'path': path,
        'name': path.split('/').last,
      });
    } catch (_) {
      return null;
    }
  }

  static bool _inGallery(String path) => path.contains('/Movies/TAKEONE/');

  bool _recovering = false;

  /// Make the list match what is really on the phone.
  ///
  /// Two jobs, both run on every launch and both harmless when there is
  /// nothing to do:
  ///   · a clip still in private storage (recorded before this build, or whose
  ///     move failed at the time) is moved into the gallery now;
  ///   · every bout in the gallery that the list does not know — the case after
  ///     the app was uninstalled and installed again — is read back from its
  ///     file name and joins the list as waiting to be sent.
  ///
  /// Needs the gallery read grant after a reinstall (the files belong to the
  /// install that wrote them — see [askGallery]); without it the list shows
  /// what this install recorded, and nothing worse.
  Future<void> recover() async {
    if (_recovering || _held) return;

    _recovering = true;

    try {
      var changed = false;

      for (final clip in List<Clip>.from(clips)) {
        if (_held) break;
        if (_inGallery(clip.path) || !File(clip.path).existsSync()) continue;

        final moved = await keep(clip.path);

        if (moved != null) {
          clip.path = moved;
          changed = true;
        }
      }

      List<dynamic> found = const [];

      try {
        found = await _gallery.invokeMethod<List<dynamic>>('scan') ?? const [];
      } catch (_) {}

      final byRef = {for (final c in clips) c.ref: c};

      for (final row in found.whereType<Map>()) {
        final path = row['path'] as String?;
        final name = row['name'] as String?;

        if (path == null || name == null) continue;

        final known = byRef[name];

        if (known != null) {
          // The list knew it under a path that is gone (moved by an older
          // build, or the private copy was cleared) — point it at the file.
          if (known.path != path && !File(known.path).existsSync()) {
            known.path = path;
            changed = true;
          }

          continue;
        }

        final clip = ClipName.read(
          name: name,
          path: path,
          bytes: (row['bytes'] as num?)?.toInt() ?? 0,
          seconds: (row['seconds'] as num?)?.toInt() ?? 0,
        );

        if (clip == null) continue;

        clips.add(clip);
        byRef[name] = clip;
        changed = true;
      }

      if (changed) {
        clips.sort((a, b) => b.startedAt.compareTo(a.startedAt));
        _notify();
        await _save();
      }
    } finally {
      _recovering = false;
    }

    await identify(force: true);
  }

  DateTime? _identified;

  /* ─────────────── the score bar, as the event describes it ─────────────── */

  final Map<String, ({Map<String, dynamic> plan, DateTime at})> _plans = {};

  /// The score bar for this clip — every state, the rounds, the highlight
  /// windows, the names and pictures — as the event describes it. Fetched
  /// once and kept for a while: the player and the send sheet both ask, and
  /// the answer for a finished bout does not change. Null when the event has
  /// no bout for the clip, or there is no network.
  Future<Map<String, dynamic>?> planFor(Clip clip, {int? seconds}) async {
    final kept = _plans[clip.ref];
    if (kept != null && DateTime.now().difference(kept.at) < const Duration(minutes: 10)) return kept.plan;

    final length = (seconds != null && seconds > 0)
        ? seconds
        : clip.seconds > 0
            ? clip.seconds
            : (clip.endedAt ?? clip.startedAt).difference(clip.startedAt).inSeconds;

    if (length <= 0 || _token == null) return null;

    final plan = await CameraApi(_token).plan(
      ref: clip.ref,
      matchId: clip.matchId,
      startedAt: clip.startedAt,
      seconds: length,
    );

    if (plan != null) _plans[clip.ref] = (plan: plan, at: DateTime.now());

    return plan;
  }

  /// Which parts of the score bar are on — the player's switches, remembered,
  /// and what a sent video carries. Everything until somebody turns one off.
  static const allParts = ['intro', 'music', 'scorebar', 'feed', 'info', 'logos', 'flags', 'stats'];

  /// v3 since the VS music joined the list: a choice saved before it existed
  /// must not silently leave it off.
  static const _partsKey = 'boutcam.overlay.parts.v3';
  static const _statsAddedKey = 'boutcam.overlay.parts.statsAdded';

  Set<String> _parts = allParts.toSet();
  Set<String> get parts => _parts;

  /// Whether the bar is laid over the player at all.
  bool showOverlay = true;

  /// How high the bottom group (match strip, bar, score line) sits: design
  /// units from the frame's bottom edge to the score line. Must agree with
  /// ScoreBar.kt's LIFT_DEFAULT / LIFT_MIN / LIFT_MAX.
  static const liftDefault = 18.0;
  static const liftMin = 4.0;
  static const liftMax = 240.0;
  static const _liftKey = 'boutcam.overlay.lift';

  double _lift = liftDefault;
  double get lift => _lift;

  /// How high the fighters' names sit on the VS screen (VsScreen.kt's
  /// INFO_DEFAULT / INFO_MIN / INFO_MAX, on its 1920×1080 canvas).
  static const vsLiftDefault = 40.0;
  static const vsLiftMin = 16.0;
  static const vsLiftMax = 520.0;
  static const _vsLiftKey = 'boutcam.vs.lift';

  double _vsLift = vsLiftDefault;
  double get vsLift => _vsLift;

  Future<void> setVsLift(double value, {bool save = true}) async {
    _vsLift = value.clamp(vsLiftMin, vsLiftMax);
    if (save) (await SharedPreferences.getInstance()).setDouble(_vsLiftKey, _vsLift);
  }

  /// The operator's own VS music (cut by MusicEditor), or null for the
  /// built-in track. Only kept while the file is really there.
  static const _musicKey = 'boutcam.music.path';
  static const _musicNameKey = 'boutcam.music.name';

  String? _musicPath;
  String? _musicName;
  String? get musicPath => _musicPath;
  String? get musicName => _musicPath == null ? null : _musicName;

  Future<void> setMusic(String path, String name) async {
    _musicPath = path;
    _musicName = name;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_musicKey, path);
    await prefs.setString(_musicNameKey, name);
  }

  Future<void> clearMusic() async {
    final old = _musicPath;
    _musicPath = null;
    _musicName = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_musicKey);
    await prefs.remove(_musicNameKey);
    if (old != null) {
      try {
        File(old).deleteSync();
      } catch (_) {}
    }
  }

  /// Moves the group now (the preview follows); `save` writes it down.
  Future<void> setLift(double value, {bool save = true}) async {
    _lift = value.clamp(liftMin, liftMax);
    if (save) (await SharedPreferences.getInstance()).setDouble(_liftKey, _lift);
  }

  Future<void> setParts(Set<String> parts) async {
    _parts = parts;
    (await SharedPreferences.getInstance()).setStringList(_partsKey, parts.toList());
  }

  /// Ask the event which bouts the recovered clips are, and give them back
  /// their bout, their round and their two names.
  ///
  /// A clip from the old camera app was named only "bout-70"; one recorded by
  /// this app carries its bout id. Either way the event that filmed it knows
  /// the rest — and the answer is what makes the score bar, the highlights and
  /// the names on this list possible again. Only this camera's event answers,
  /// so a clip from another competition stays [Clip.held]. Cheap, and run on
  /// launch, on pairing, on opening the list and now and then from the queue.
  Future<void> identify({bool force = false}) async {
    final token = _token;

    if (token == null) return;
    if (!force && _identified != null && DateTime.now().difference(_identified!) < const Duration(minutes: 2)) return;

    _identified = DateTime.now();

    // Anything the list cannot yet say properly: a recovered clip the event
    // has not placed, one with no names, and any bout whose result is not in.
    final asking = clips
        .where((c) =>
            c.held ||
            (c.recovered && !c.looked) ||
            ((c.matchId != null || c.recovered) && !c.isFinal) ||
            ((c.matchId != null || c.looked) && !c.clubsLooked) ||
            // An exhibition can be LINKED to its draw bout afterwards (the
            // organiser's Exhibitions tab), and from then on the event answers
            // with that bout — so an exhibition is never "final" for asking.
            (c.number?.toLowerCase().startsWith('ex') ?? false))
        .take(300)
        .toList();

    if (asking.isEmpty) return;

    final answer = await CameraApi(token).known([
      for (final c in asking) {'ref': c.ref, 'match_id': ?c.matchId},
    ]);

    if (answer == null) return;

    final byRef = {for (final c in asking) c.ref: c};
    var changed = false;

    for (final row in answer) {
      final clip = byRef[row['ref']];

      if (clip == null) continue;

      // Only a camera paired to the clip's own event may send it there.
      if (row['here'] == true) clip.confirmed = true;

      clip.looked = true;
      clip.matchId = (row['match_id'] as num?)?.toInt() ?? clip.matchId;
      clip.number = (row['number'] as String?) ?? clip.number;
      clip.stage = (row['stage'] as String?) ?? clip.stage;
      clip.division = (row['division'] as String?) ?? clip.division;
      clip.red = (row['red'] as String?) ?? clip.red;
      clip.blue = (row['blue'] as String?) ?? clip.blue;
      clip.redScore = (row['red_score'] as String?) ?? clip.redScore;
      clip.blueScore = (row['blue_score'] as String?) ?? clip.blueScore;
      clip.winner = (row['winner'] as String?) ?? clip.winner;
      clip.isFinal = row['final'] == true;
      clip.event = (row['event'] as String?) ?? clip.event;
      if (row.containsKey('red_club')) {
        clip.redClub = row['red_club'] as String?;
        clip.blueClub = row['blue_club'] as String?;
        clip.clubsLooked = true;
      }
      changed = true;
    }

    // Asked and not answered: the event has no record of these files.
    for (final c in asking) {
      if (c.recovered && !c.looked) {
        c.looked = true;
        changed = true;
      }
    }

    if (changed) {
      _notify();
      await _save();
      unawaited(pump());
    }
  }

  /// The clip somebody is waiting on right now (the Send sheet). It jumps the
  /// queue; everything else waits its turn behind it.
  Clip? _priority;

  /// Send THIS clip to the event now, whatever the switch says, and answer
  /// when it is there: null on success, otherwise a sentence for the screen.
  Future<String?> sendFirst(Clip clip) async {
    if (clip.settled) return null;
    if (_held) return 'The camera is recording. It sends between bouts — try again when this one ends.';
    if (_token == null) return 'This camera is not connected to the event yet.';

    if (clip.held) await identify(force: true);
    if (clip.held) {
      return 'The event this camera is paired to does not know this clip. Pair the camera to the event it was filmed at, then try again.';
    }

    _priority = clip;
    clip.attempts = 0;
    clip.problem = null;

    // A sweep already running picks this clip next; wait for it, then make
    // sure it was this one that got sent.
    while (_running) {
      await Future.delayed(const Duration(milliseconds: 400));
    }

    if (!clip.settled) await pump(force: true, only: clip);

    if (_priority == clip) _priority = null;

    return clip.settled ? null : (clip.problem ?? 'It did not reach the event. Check the wifi and try again.');
  }

  Future<void> _save() async {
    final prefs = await SharedPreferences.getInstance();
    final trimmed = clips.take(_max).toList();

    await prefs.setString(_key, jsonEncode(trimmed.map((c) => c.toJson()).toList()));
  }

  /// The camera's identity, or null before it has one — what the Send button
  /// asks the event with.
  String? get token => _token;

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

    if (token != null) unawaited(identify(force: true));

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
  Future<void> sendNow() => pump(force: true, all: true);

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
  ///
  /// With auto upload off, a sweep still works through the operator's upload
  /// list (`upload()`) — and only that list, unless `all` (UPLOAD ALL) says so.
  Future<void> pump({bool force = false, Clip? only, bool all = false}) async {
    if (_running || _held || _token == null) return;
    if (!_auto && !force && _wanted.isEmpty) return;
    if (!force && _notBefore != null && DateTime.now().isBefore(_notBefore!)) return;

    _running = true;

    try {
      unawaited(identify());

      final tried = <Clip>{};

      // Picked one at a time rather than listed up front, so a clip somebody
      // asked for while this sweep was running goes next, not last.
      Clip? next() {
        bool open(Clip c) => !c.settled && !c.held && !tried.contains(c);

        if (only != null) return open(only) ? only : null;

        final first = _priority;
        if (first != null && open(first)) return first;

        // The operator's own list next, in the list's order (oldest first).
        for (final c in clips.reversed) {
          if (open(c) && _wanted.contains(c.ref)) return c;
        }

        // With the switch off, only what somebody asked for goes.
        if (!_auto && !all) return null;

        // Oldest first: the bout somebody is waiting to watch is the one that
        // finished first, not the one that just ended.
        for (final c in clips.reversed) {
          if (open(c)) return c;
        }

        return null;
      }

      for (var clip = next(); clip != null; clip = next()) {
        if (_held || _token == null) break;

        tried.add(clip);

        final failure = await _sendOne(clip);

        if (failure != null) {
          clip.state = Clip.failed;
          clip.attempts += 1;
          clip.problem = _explain(failure);

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
        clip.problem = null;
        _notBefore = null;

        if (_wanted.remove(clip.ref)) await _saveWanted();

        _notify();
        await _save();
      }
    } finally {
      _running = false;
    }
  }

  static String _explain(String failure) => switch (failure) {
        'network' || 'not filed' => 'No connection to the event. Try again on the hall wifi.',
        'file gone' => 'That file is no longer on this phone.',
        'paused' => 'The camera started recording. It sends between bouts.',
        _ => 'The event did not take it ($failure). Try again in a moment.',
      };

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

      // A piece that did not get through is tried again — the same piece, from
      // the same offset — a few times before the sweep gives up and waits. If
      // it DID arrive and only the answer was lost, the server says so (409
      // with its offset) and the loop carries on from there.
      var answer = await _postChunk(token, clip.serverId!, offset, body, isLast);

      for (final wait in const [2, 5, 10, 20]) {
        if (answer != null || _held) break;
        await Future.delayed(Duration(seconds: wait));
        if (_held) return 'paused';
        answer = await _postChunk(token, clip.serverId!, offset, body, isLast);
      }

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
