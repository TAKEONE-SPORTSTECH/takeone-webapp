import 'dart:convert';
import 'dart:io';

import '../config.dart';
import 'clips.dart';
import 'recorder.dart';

/// Sending one finished bout to TAKEONE, from a phone on hall wifi.
///
/// It uploads to takeone rather than to the video platform directly, and that is
/// deliberate: a camera stands unattended in a public hall all day, and it must
/// never hold a credential for the platform that holds the organisation's whole
/// media library. It sends to the server it is already enrolled with — whose
/// token reaches exactly this camera's own row — and that server forwards with
/// its own service credential and attaches the bout.
///
/// Chunked and resumable, because a hall's wifi drops and a 500MB upload that
/// restarts from zero never finishes. The SERVER's offset is authoritative: this
/// asks where it got to and continues from there, so a phone that lost the
/// network mid-bout resumes rather than starting again.
class ClipUploader {
  ClipUploader({required this.token, required this.clip, required this.onProgress});

  final String token;
  final CameraClip clip;

  /// 0..1, called often enough to move a bar and rarely enough not to thrash.
  final void Function(double fraction) onProgress;

  /// 8MB — the same size the server forwards in.
  static const _chunk = 8 * 1024 * 1024;

  bool _cancelled = false;

  void cancel() => _cancelled = true;

  static final HttpClient _client = HttpClient()
    ..connectionTimeout = const Duration(seconds: 15)
    ..idleTimeout = const Duration(seconds: 60);

  /// Returns null on success, or a short sentence for the operator.
  Future<String?> send() async {
    final serverId = clip.serverId;

    if (serverId == null) {
      // The clip was never filed with the server — usually a bout recorded
      // while the phone was offline. Nothing to attach a video to yet.
      return 'This clip has not reached the event yet. Try again when the camera is back online.';
    }

    final file = File(clip.uri == null ? clip.file : clip.file);

    if (!await file.exists()) {
      // A published clip's `file` is a display path, not a real one; the video
      // lives in the media library and is read through its uri instead.
      if (clip.uri == null) return 'That file is no longer on this phone.';
    }

    final source = await _open();

    if (source == null) return 'That file could not be opened.';

    final total = await source.length();
    var offset = 0;

    try {
      while (offset < total) {
        if (_cancelled) return 'Upload cancelled.';

        final end = (offset + _chunk) > total ? total : offset + _chunk;
        final body = await source.openRead(offset, end).expand((c) => c).toList();
        final isLast = end >= total;

        final request = await _client.postUrl(
          Uri.parse('${Config.cameraClipUpload(token, serverId)}${isLast ? '?final=1' : ''}'),
        );

        request.headers.set(HttpHeaders.acceptHeader, 'application/json');
        request.headers.set(HttpHeaders.contentTypeHeader, 'application/octet-stream');
        request.headers.set('Upload-Offset', '$offset');
        request.add(body);

        final response = await request.close().timeout(const Duration(minutes: 3));
        final text = await response.transform(utf8.decoder).join();
        final answer = jsonDecode(text.isEmpty ? '{}' : text);

        // The server knows where it got to. A mismatch is a resume instruction,
        // not a failure — this is the ordinary case after a dropped connection.
        if (response.statusCode == 409 && answer is Map && answer['offset'] is int) {
          offset = answer['offset'] as int;

          continue;
        }

        if (response.statusCode >= 400) {
          return 'The event server refused the upload (${response.statusCode}).';
        }

        if (answer is Map && answer['offset'] is int) {
          offset = answer['offset'] as int;
        } else {
          offset = end;
        }

        onProgress(total == 0 ? 1 : offset / total);

        if (answer is Map && answer['done'] == true) break;
      }
    } on SocketException {
      return 'The connection dropped. The upload will carry on from here next time.';
    } catch (e) {
      return 'The upload failed. It will carry on from here next time.';
    }

    return null;
  }

  /// The clip's bytes, wherever they live.
  ///
  /// A published clip is in the phone's media library and is read back through
  /// the platform; an unpublished one is a plain file. Both are the same
  /// recording, and the upload does not care which.
  Future<File?> _open() async {
    final direct = File(clip.file);

    if (await direct.exists()) return direct;

    final uri = clip.uri;

    if (uri == null) return null;

    // MediaStore content:// cannot be opened as a File, so the platform copies
    // it to a cache path first. Deleted by the OS with the rest of the cache.
    final copied = await Recorder.cacheCopy(uri);

    return copied == null ? null : File(copied);
  }
}
