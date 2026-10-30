import 'dart:convert';
import 'dart:io';

/// The one server this app talks to, and the four doors it uses.
///
/// It pairs exactly as the shipping camera does — the SAME enrolment and config
/// endpoints — and it now asks for its publish credential the same way too:
/// `/camera/{token}/live`, authorised by the camera's OWN token.
///
/// There is deliberately no shared key here any more. The earlier lab door
/// (`/api/lab/live` + `X-Lab-Key`) needed one compiled into the build, which
/// meant every copy of the APK carried the same secret and anyone who
/// downloaded it had it. A camera token is minted per device, scoped to the one
/// mat that device was claimed onto, and revocable from the console.
class Lab {
  const Lab._();

  static const String base = String.fromEnvironment(
    'BASE_URL',
    defaultValue: 'https://stage.takeone.bh',
  );

  // The camera fleet's own endpoints, unchanged.
  static Uri get enroll => Uri.parse('$base/camera/enroll');
  static Uri config(String token) => Uri.parse('$base/camera/$token/config');
  static Uri live(String token) => Uri.parse('$base/camera/$token/live');

  /// Filing a finished bout, and then sending its bytes. Two doors rather than
  /// one because they are two different failures: a clip can be known to the
  /// event long before the phone finds enough network to carry 400MB, and the
  /// index is what tells an organiser the bout was filmed at all.
  static Uri clip(String token) => Uri.parse('$base/camera/$token/clip');
  static Uri clipUpload(String token, int clip) =>
      Uri.parse('$base/camera/$token/clip/$clip/upload');

  /// "Which bouts are these?" — files found in the gallery after a reinstall,
  /// named back to their bouts by this camera's event.
  static Uri clipsKnown(String token) => Uri.parse('$base/camera/$token/clips/known');

  /// The score bar for one clip as data, so the phone burns it in itself.
  static Uri clipsPlan(String token) => Uri.parse('$base/camera/$token/clips/plan');

  /// A filed clip as a video to SEND — the event's score bar burned in, the
  /// points as chapters. POST asks for it, GET checks on it; both answer 404
  /// when the event cannot make one (not uploaded, no bout, nothing scored).
  static Uri clipShare(String token, int clip, String kind) =>
      Uri.parse('$base/camera/$token/clip/$clip/share/$kind');
}

/// A thin HTTP client with a deadline on everything.
///
/// A phone on hall wifi can have a request hang indefinitely — no error, nothing
/// to catch — and a camera stuck on "setting this up" is the one failure nobody
/// in a hall can diagnose. So every call has a ceiling and every failure is
/// reported as a status rather than an exception.
class CameraApi {
  CameraApi(this.token);

  final String? token;

  static final HttpClient _client = HttpClient()
    ..connectionTimeout = const Duration(seconds: 10)
    ..idleTimeout = const Duration(seconds: 30);

  /// "I am a new camera." Returns {token, code, claim_url}.
  static Future<Map<String, dynamic>?> enrol(String deviceName) async =>
      (await _send(Lab.enroll, 'POST', {
        'device_name': deviceName,
        'app_version': 'lab',
      }))
          .body;

  /// "What am I?" — the unclaimed code, or the mat, angle, MQTT credentials and
  /// whether this camera is supposed to be recording right now.
  ///
  /// The status is kept, because 404 (this identity is gone — re-enrol) and a
  /// timeout (the wifi dropped — wait) demand opposite responses.
  Future<({int status, Map<String, dynamic>? body})> configResult() async =>
      token == null ? (status: 0, body: null) : _send(Lab.config(token!), 'GET', null);

  static Future<({int status, Map<String, dynamic>? body})> _send(
    Uri url,
    String method,
    Map<String, dynamic>? payload, {
    Map<String, String> headers = const {},
  }) async {
    try {
      final request = await _client.openUrl(method, url).timeout(const Duration(seconds: 12));

      request.headers.set('accept', 'application/json');
      headers.forEach(request.headers.set);

      if (payload != null) {
        request.headers.contentType = ContentType.json;
        request.write(jsonEncode(payload));
      }

      final response = await request.close().timeout(const Duration(seconds: 20));
      final text = await response.transform(utf8.decoder).join();

      if (text.isEmpty) return (status: response.statusCode, body: null);

      final decoded = jsonDecode(text);

      return (
        status: response.statusCode,
        body: decoded is Map<String, dynamic> ? decoded : null,
      );
    } catch (_) {
      return (status: 0, body: null);
    }
  }

  /// "I finished a clip, and it is this bout." Returns {id}.
  ///
  /// Idempotent on the file name, so a phone that retried through a flaky hall
  /// network gets the same row back rather than filing the bout twice.
  Future<Map<String, dynamic>?> reportClip({
    required String localRef,
    int? matchId,
    DateTime? startedAt,
    DateTime? endedAt,
    int? durationSeconds,
    int? bytes,
  }) async =>
      token == null
          ? null
          : (await _send(Lab.clip(token!), 'POST', {
              'local_ref': localRef,
              'match_id': ?matchId,
              if (startedAt != null) 'started_at': startedAt.toUtc().toIso8601String(),
              if (endedAt != null) 'ended_at': endedAt.toUtc().toIso8601String(),
              'duration_seconds': ?durationSeconds,
              'bytes': ?bytes,
            }))
              .body;

  /// The bouts behind clips this phone found in its gallery: a list of
  /// {ref, match_id, number, stage, red, blue}, only for those this camera's
  /// event recognises. Null on no network or an unpaired camera.
  Future<List<Map<String, dynamic>>?> known(List<Map<String, dynamic>> clips) async {
    if (token == null) return null;

    final answer = await _send(Lab.clipsKnown(token!), 'POST', {'clips': clips});

    if (answer.status != 200) return null;

    return (answer.body?['clips'] as List<dynamic>?)?.whereType<Map<String, dynamic>>().toList();
  }

  /// The score bar for one clip — every state, the rounds, the highlight
  /// windows, the message — for the phone to draw itself. Null when the event
  /// has no bout for it, or no network.
  Future<Map<String, dynamic>?> plan({
    required String ref,
    int? matchId,
    DateTime? startedAt,
    required int seconds,
  }) async {
    if (token == null) return null;

    final answer = await _send(Lab.clipsPlan(token!), 'POST', {
      'ref': ref,
      'match_id': ?matchId,
      if (startedAt != null) 'started_at': startedAt.toUtc().toIso8601String(),
      'seconds': seconds,
    });

    return answer.status == 200 ? answer.body : null;
  }

  /// A publish credential for THIS camera's stream, minted the moment before
  /// use — the server decides which stream that is, from the mat this camera
  /// was claimed onto. An unclaimed camera gets a 409 and simply does not go
  /// live; there is nothing for it to broadcast yet.
  ///
  /// The status is kept: 410 means this server has no live feed at all (live
  /// broadcasting was removed), which is an answer, not a fault.
  Future<({int status, Map<String, dynamic>? body})> publishToken() async =>
      token == null ? (status: 0, body: null) : _send(Lab.live(token!), 'POST', null);

  /// Ask the event for this clip with its score bar (`ask: true`), or check on
  /// one already asked for. {state: working|ready|failed, file, filename,
  /// message}; status 404 = the event cannot make one, 0 = no network.
  Future<({int status, Map<String, dynamic>? body})> share(int clip, String kind, {required bool ask}) async =>
      token == null
          ? (status: 0, body: null)
          : _send(Lab.clipShare(token!, clip, kind), ask ? 'POST' : 'GET', null);

  /// Fetch a finished file to [to], reporting how far it has got. False on any
  /// failure — the half-written file is removed, never handed on.
  static Future<bool> download(String url, File to, {void Function(double)? progress}) async {
    try {
      final request = await _client.getUrl(Uri.parse(url)).timeout(const Duration(seconds: 15));
      final response = await request.close().timeout(const Duration(seconds: 30));

      if (response.statusCode != 200) {
        await response.drain<void>();

        return false;
      }

      final total = response.contentLength;
      var got = 0;
      final sink = to.openWrite();

      await for (final chunk in response.timeout(const Duration(seconds: 60))) {
        sink.add(chunk);
        got += chunk.length;
        if (total > 0) progress?.call(got / total);
      }

      await sink.close();

      return total <= 0 || got == total;
    } catch (_) {
      try {
        if (to.existsSync()) to.deleteSync();
      } catch (_) {}

      return false;
    }
  }
}
