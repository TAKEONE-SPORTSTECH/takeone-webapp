import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// One recorded bout, as this phone knows it.
///
/// The phone's own record is the authoritative one here: the video never leaves
/// the device in this version, so a clip that the server never heard about is
/// still a real clip, and the operator must be able to find it by bout number
/// on the phone itself. `reported` only says whether the server has been told.
class CameraClip {
  CameraClip({
    required this.file,
    required this.startedAt,
    this.endedAt,
    this.matchId,
    this.matchNumber,
    this.red,
    this.blue,
    this.court,
    this.angle,
    this.bytes,
    this.uri,
    this.serverId,
    this.playStatus,
    this.playVideoKey,
    this.uploadProgress = 0,
    this.reported = false,
  });

  /// Absolute path on this device. Shown to the operator so they can find it
  /// over USB, and never sent to the server — the server is told the file NAME
  /// (see `ref`), which is what a person actually looks for.
  /// Where the video is. After a clip is published this is its place in the
  /// phone's media library (`Movies/TAKEONE/…`) — the folder the gallery shows
  /// and a computer sees over USB.
  String file;
  final DateTime startedAt;
  DateTime? endedAt;
  final int? matchId;
  final String? matchNumber;
  final String? red;
  final String? blue;
  final String? court;
  final int? angle;
  int? bytes;

  /// The media-library handle, when the phone accepted the clip. This is what
  /// the in-app player opens; null means the video is still in the app's own
  /// storage and only this app can reach it.
  String? uri;

  /// The row this clip has in the organiser's index, when it was filed. Kept so
  /// deleting the video here can take that row with it — a console listing a
  /// clip that no longer exists sends somebody hunting through a phone for a
  /// bout that was deleted at the mat an hour earlier.
  int? serverId;

  /// Where this clip is on its way to TAKEONE: null (never sent),
  /// `uploading`, `processing` (there, transcoding), `ready`, or `failed`.
  ///
  /// The `play` in this field's name is historical — footage used to be handed
  /// to a separate video platform, which was disconnected. It goes to the host
  /// this camera is paired to now. Kept as-is because the name is written into
  /// every clip already stored on every phone in the field.
  String? playStatus;

  /// The platform's key for the video, once it exists — this is what makes a
  /// row openable on the site the camera uploaded to.
  String? playVideoKey;

  /// How long an already-uploaded clip is kept on the phone before the drawer
  /// offers to clear it.
  ///
  /// A volunteer's phone fills up over a multi-day championship, and the person
  /// holding it is the least equipped to work out which files are safe to lose.
  static const Duration keepFor = Duration(days: 7);

  /// Is this clip's footage definitely on the server?
  ///
  /// `playVideoKey` is set from the server's own answer once the bytes have
  /// landed and been accepted — not when the upload starts, and not by anything
  /// the phone decides on its own. A clip still `uploading`, one that `failed`,
  /// and one nobody ever sent are all false here.
  bool get isSafelyUploaded => playVideoKey != null;

  /// May the drawer offer to delete this to free space?
  ///
  /// Two conditions, and the first is not negotiable: the footage exists
  /// somewhere else. An un-uploaded clip is the ONLY copy of a bout that was
  /// fought once, so it is never offered, whatever its age or the state of the
  /// disk. The age is only there so a clip uploaded this morning is still on the
  /// phone this afternoon, when somebody asks to see it again at the mat.
  bool get isExpendable {
    if (!isSafelyUploaded) return false;

    return DateTime.now().difference(endedAt ?? startedAt) > keepFor;
  }

  /// 0..1 while bytes are moving. Not persisted: an upload that was interrupted
  /// resumes from the server's offset, not from a number remembered here.
  double uploadProgress;

  bool reported;

  String get ref => file.split('/').last;

  Duration get length => (endedAt ?? DateTime.now()).difference(startedAt);

  /// What the operator reads on the phone: the bout, or the time if the mat
  /// was rolling with nothing loaded.
  String get title {
    if (matchNumber != null && matchNumber!.isNotEmpty) return 'Bout $matchNumber';
    return 'Unassigned clip';
  }

  String get subtitle {
    final names = [red, blue].where((n) => n != null && n.isNotEmpty).join('  vs  ');
    return names.isEmpty ? ref : names;
  }

  Map<String, dynamic> toJson() => {
        'file': file,
        'started_at': startedAt.toIso8601String(),
        'ended_at': endedAt?.toIso8601String(),
        'match_id': matchId,
        'match_number': matchNumber,
        'red': red,
        'blue': blue,
        'court': court,
        'angle': angle,
        'bytes': bytes,
        'uri': uri,
        'server_id': serverId,
        'play_status': playStatus,
        'play_video_key': playVideoKey,
        'reported': reported,
      };

  static CameraClip fromJson(Map<String, dynamic> json) => CameraClip(
        file: json['file'] as String,
        startedAt: DateTime.parse(json['started_at'] as String),
        endedAt: json['ended_at'] == null ? null : DateTime.parse(json['ended_at'] as String),
        matchId: json['match_id'] as int?,
        matchNumber: json['match_number'] as String?,
        red: json['red'] as String?,
        blue: json['blue'] as String?,
        court: json['court'] as String?,
        angle: json['angle'] as int?,
        bytes: json['bytes'] as int?,
        uri: json['uri'] as String?,
        serverId: json['server_id'] as int?,
        playStatus: json['play_status'] as String?,
        playVideoKey: json['play_video_key'] as String?,
        reported: json['reported'] as bool? ?? false,
      );
}

/// The phone's ledger of what it filmed.
///
/// Kept on the device because that is where the video is. A camera that spent
/// the day out of network range still knows, on its own screen, that its third
/// file is bout 1-04 — which is the fact somebody needs when they plug it in.
class ClipLog {
  static const _key = 'takeone.camera.clips';

  /// Newest first, and capped. A competition day is a few hundred bouts; the
  /// cap stops a phone that is never cleared from carrying an unbounded list
  /// into its preferences file.
  static const _max = 500;

  static Future<List<CameraClip>> load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null) return [];

    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list.map((e) => CameraClip.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  static Future<void> save(List<CameraClip> clips) async {
    final prefs = await SharedPreferences.getInstance();
    final trimmed = clips.take(_max).toList();
    await prefs.setString(_key, jsonEncode(trimmed.map((c) => c.toJson()).toList()));
  }
}
