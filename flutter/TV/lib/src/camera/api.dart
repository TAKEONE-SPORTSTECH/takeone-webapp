import 'dart:convert';
import 'dart:io';

import '../config.dart';

/// Everything this phone says to the server, and nothing more.
///
/// Four calls, mirroring the four the server exposes. Written against
/// `dart:io` rather than a package because that is the entire dependency:
/// four JSON round trips do not earn an HTTP library, and every package added
/// to a build that films competitions is another thing that can break on a
/// device we cannot reach.
///
/// Nothing here throws at the caller. A camera stands on a tripod in a hall
/// with nobody watching it; an exception surfacing as a red screen would take
/// the camera off the mat for the rest of the day, so every call returns null
/// on failure and lets the caller decide whether that matters. The bouts keep
/// being recorded either way — the file is written locally first and reported
/// second.
class CameraApi {
  CameraApi(this.token);

  /// Null until this phone has enrolled. Enrolment is the one call that does
  /// not need it.
  final String? token;

  static final HttpClient _client = HttpClient()
    ..connectionTimeout = const Duration(seconds: 10)
    ..idleTimeout = const Duration(seconds: 30);

  /// "I am a new camera." Returns {token, code, claim_url}.
  static Future<Map<String, dynamic>?> enroll({String? deviceName, String? appVersion}) async =>
      (await _send(Config.cameraEnroll, 'POST', {
        if (deviceName != null) 'device_name': deviceName,
        if (appVersion != null) 'app_version': appVersion,
      })).body;

  /// "What am I?" — the unclaimed code, or the mat, angle and MQTT credentials.
  ///
  /// Also the backstop for the whole feature: if the broker is unreachable or a
  /// message was missed, this is what tells the phone it is supposed to be
  /// recording right now.
  Future<Map<String, dynamic>?> config() async => (await configResult()).body;

  /// The same call, with the STATUS kept.
  ///
  /// The difference between "the hall's wifi dropped" and "this camera no
  /// longer exists" is the difference between waiting and re-enrolling, and a
  /// phone that cannot tell them apart sits on a dead pairing code forever —
  /// which is exactly what happened when a camera's row was removed from the
  /// server: the app kept showing a code that resolved to nothing, and the
  /// organiser scanning it was told it belonged to a screen.
  ///
  /// 404 is the server saying this identity is gone. Anything else — a
  /// timeout, a 500, a captive portal — leaves the identity alone.
  Future<({int status, Map<String, dynamic>? body})> configResult() async =>
      token == null
          ? (status: 0, body: null)
          : _send(Config.cameraConfig(token!), 'GET', null);

  /// "I am alive, and this is how much room is left."
  Future<Map<String, dynamic>?> telemetry({
    int? storageTotalBytes,
    int? storageFreeBytes,
    int? batteryPercent,
    bool? recording,
    String? deviceName,
    String? appVersion,
  }) =>
      token == null
          ? Future.value(null)
          : _body(Config.cameraTelemetry(token!), 'POST', {
              if (storageTotalBytes != null) 'storage_total_bytes': storageTotalBytes,
              if (storageFreeBytes != null) 'storage_free_bytes': storageFreeBytes,
              if (batteryPercent != null) 'battery_percent': batteryPercent,
              if (recording != null) 'recording': recording,
              if (deviceName != null) 'device_name': deviceName,
              if (appVersion != null) 'app_version': appVersion,
            });

  /// "I finished a clip, and it is this bout."
  ///
  /// The file stays here. What is sent is the index — which bout, how long, how
  /// big, and the name this phone knows the file by — so that afterwards
  /// somebody can be told which phone to plug in and what to look for.
  Future<Map<String, dynamic>?> reportClip({
    required String localRef,
    int? matchId,
    DateTime? startedAt,
    DateTime? endedAt,
    int? durationSeconds,
    int? bytes,
  }) =>
      token == null
          ? Future.value(null)
          : _body(Config.cameraClip(token!), 'POST', {
              'local_ref': localRef,
              if (matchId != null) 'match_id': matchId,
              if (startedAt != null) 'started_at': startedAt.toUtc().toIso8601String(),
              if (endedAt != null) 'ended_at': endedAt.toUtc().toIso8601String(),
              if (durationSeconds != null) 'duration_seconds': durationSeconds,
              if (bytes != null) 'bytes': bytes,
            });

  /// The common case: the body, or null for any failure at all.
  /// Take a deleted clip out of the organiser's index.
  ///
  /// Best-effort by design: the video is already gone from the phone, and a
  /// failed call here leaves one stale row rather than undoing a deletion the
  /// volunteer asked for.
  Future<bool> deleteClip(int serverId) async {
    if (token == null) return false;

    final result = await _send(Config.cameraClipDelete(token!, serverId), 'DELETE', null);

    // A row that is already gone is the outcome we wanted.
    return result.status == 200 || result.status == 404;
  }

  static Future<Map<String, dynamic>?> _body(Uri url, String method, Map<String, dynamic>? body) async =>
      (await _send(url, method, body)).body;

  static Future<({int status, Map<String, dynamic>? body})> _send(
    Uri url,
    String method,
    Map<String, dynamic>? body,
  ) async {
    try {
      final request = await _client.openUrl(method, url).timeout(const Duration(seconds: 12));
      request.headers.set(HttpHeaders.acceptHeader, 'application/json');

      if (body != null) {
        request.headers.contentType = ContentType.json;
        request.add(utf8.encode(jsonEncode(body)));
      }

      final response = await request.close().timeout(const Duration(seconds: 20));
      final text = await response.transform(utf8.decoder).join();

      if (response.statusCode >= 400) return (status: response.statusCode, body: null);

      final decoded = jsonDecode(text);

      return (status: response.statusCode, body: decoded is Map<String, dynamic> ? decoded : null);
    } catch (_) {
      // Deliberately silent, and status 0 — "we never reached the server",
      // which is NOT the same as the server saying no. A hall's wifi drops
      // constantly and the camera's job does not depend on this call
      // succeeding: the clip is already on the phone, and the next beat
      // re-reports what this one could not.
      return (status: 0, body: null);
    }
  }
}
