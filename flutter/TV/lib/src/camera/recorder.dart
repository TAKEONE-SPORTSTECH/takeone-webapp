import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';

/// The lens, and the disk it fills.
///
/// Deliberately dumb: it opens the camera, starts a file, stops a file, and
/// says how much room is left. Every decision about WHEN belongs to the mat
/// (CameraFleet on the server), and everything about what a clip MEANS belongs
/// to ClipLog. This class only has to be reliable.
///
/// Two rules it exists to enforce:
///
///  · A start while already rolling is ignored, and a stop while not rolling is
///    ignored. The mat may repeat itself — a re-sent command, an app resumed
///    mid-bout, an operator pressing hajime twice — and neither may produce a
///    truncated file or a lost one.
///  · The file is on disk before anybody is told about it. Reporting is a
///    courtesy to the console; the recording is the product.
class Recorder {
  CameraController? _controller;
  bool _rolling = false;
  String? _pendingPath;

  /// What the operator set, kept so a re-open (an app restart, a frame-rate
  /// change) comes back framed exactly as the mat was left. A camera that
  /// forgets its zoom between bouts has to be re-aimed by hand, which is the
  /// one thing nobody has time for between bouts.
  double zoom = 1;
  double exposure = 0;
  int fps = 30;

  double minZoom = 1;
  double maxZoom = 1;
  double minExposure = 0;
  double maxExposure = 0;

  bool get rolling => _rolling;
  CameraController? get controller => _controller;
  bool get ready => _controller?.value.isInitialized ?? false;
  bool get canZoom => maxZoom > minZoom + 0.01;
  bool get canExpose => maxExposure > minExposure + 0.01;

  /// Storage and battery, straight from the platform.
  ///
  /// A MethodChannel rather than two more packages: `StatFs` and
  /// `BatteryManager` are a dozen lines of Kotlin each (see MainActivity), and
  /// the storage number is too important to this feature to route through a
  /// dependency we would have to keep in step with the Android SDK.
  static const _platform = MethodChannel('bh.takeone.camera/device');

  /// The rear camera at the highest resolution the phone will hold steadily.
  ///
  /// `veryHigh` (1080p) rather than `max`: 4K fills a phone in under an hour of
  /// bouts, and nobody reviewing a match needs it. Audio ON — a coach reviewing
  /// a bout wants the referee's calls.
  Future<String?> open() async {
    if (_controller != null) return null;

    try {
      final cameras = await availableCameras();

      if (cameras.isEmpty) return 'This device has no camera.';

      final back = cameras.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.back,
        orElse: () => cameras.first,
      );

      final controller = CameraController(
        back,
        ResolutionPreset.veryHigh,
        enableAudio: true,
        // Asked for, not assumed: 60 is smoother for reviewing a kick frame by
        // frame and costs twice the disk, so the operator chooses per mat.
        fps: fps,
      );

      await controller.initialize();
      _controller = controller;

      // What this particular phone will allow. Read AFTER initialize, because
      // the range belongs to the sensor, not to the plugin.
      minZoom = await controller.getMinZoomLevel();
      maxZoom = await controller.getMaxZoomLevel();
      minExposure = await controller.getMinExposureOffset();
      maxExposure = await controller.getMaxExposureOffset();

      // Re-apply what the operator had set, clamped to what this phone can do.
      await applyZoom(zoom);
      await applyExposure(exposure);

      return null;
    } on CameraException catch (e) {
      // The one failure worth naming on screen: permission. Everything else is
      // reported as itself, because a camera that will not open is the end of
      // this device's usefulness and the operator has to know why.
      return e.code == 'CameraAccessDenied'
          ? 'Camera permission was refused. Allow it in Settings and reopen the app.'
          : 'The camera could not be opened (${e.code}).';
    } catch (e) {
      return 'The camera could not be opened.';
    }
  }

  /// Frame the shot. Clamped rather than refused: a slider dragged to the end
  /// on a phone with less range should sit at that phone's end, not throw.
  Future<void> applyZoom(double value) async {
    zoom = value.clamp(minZoom, maxZoom);

    if (!ready || !canZoom) return;

    try {
      await _controller!.setZoomLevel(zoom);
    } catch (_) {
      // A sensor that refuses a level is not a reason to take the camera off
      // the mat. The preview simply stays where it was.
    }
  }

  /// Brighter or darker, in the sensor's own EV steps.
  ///
  /// A hall is lit for spectators, not for cameras: a mat under a skylight
  /// blows out and a corner mat goes muddy, and this is the one correction
  /// worth having at the tripod.
  Future<void> applyExposure(double value) async {
    exposure = value.clamp(minExposure, maxExposure);

    if (!ready || !canExpose) return;

    try {
      await _controller!.setExposureOffset(exposure);
    } catch (_) {}
  }

  /// Change the frame rate, which means opening the camera again.
  ///
  /// Refused while rolling: a bout being recorded is not the moment, and the
  /// file would be cut in half by the re-open.
  Future<String?> setFps(int value) async {
    if (_rolling) return null;

    fps = value;

    await _controller?.dispose();
    _controller = null;

    return open();
  }

  /// Begin a file. Returns its path, or null if it could not start.
  ///
  /// The name carries the bout, so the file is identifiable on the phone with
  /// no app and no server: `mat-1_angle-2_bout-1-04_20260823-121500.mp4`.
  Future<String?> start({String? court, int? angle, String? boutNumber}) async {
    if (_rolling || !ready) return null;

    try {
      await _controller!.startVideoRecording();
      _rolling = true;

      final now = DateTime.now();
      final stamp = '${now.year}${_two(now.month)}${_two(now.day)}-'
          '${_two(now.hour)}${_two(now.minute)}${_two(now.second)}';
      final parts = [
        if (court != null) 'mat-${_slug(court)}',
        if (angle != null) 'angle-$angle',
        if (boutNumber != null && boutNumber.isNotEmpty) 'bout-${_slug(boutNumber)}',
        stamp,
      ];

      final dir = await _clipDir();
      _pendingPath = '${dir.path}/${parts.join('_')}.mp4';

      return _pendingPath;
    } catch (_) {
      _rolling = false;
      return null;
    }
  }

  /// Close the file, name it for the bout, and publish it to the gallery.
  ///
  /// Returns where it ended up: the media-library URI if the phone accepted it
  /// (the normal case, and the only one where a human can find the video), the
  /// private path if it did not.
  Future<({String path, int bytes, String? uri})?> stop() async {
    if (!_rolling || _controller == null) return null;

    _rolling = false;

    try {
      final captured = await _controller!.stopVideoRecording();
      final target = _pendingPath ?? captured.path;
      _pendingPath = null;

      // The plugin writes to its own cache path; move it where the operator
      // can find it under a name that says which bout it is. A failed move is
      // survivable — the original file still exists and is still reported.
      var file = File(captured.path);

      try {
        file = await file.rename(target);
      } catch (_) {
        // Keep the plugin's own file; it is still a complete recording.
      }

      final bytes = await file.length();

      // Then out of the app's private storage and into the phone's media
      // library, where the gallery lists it and a computer sees it over USB.
      // Private storage is where the first build left them, which made every
      // clip real and unreachable at the same time.
      final uri = await _publish(file);

      return (path: uri == null ? file.path : 'Movies/TAKEONE/${file.uri.pathSegments.last}', bytes: bytes, uri: uri);
    } catch (_) {
      return null;
    }
  }

  /// Publish a clip that is still sitting in private storage.
  ///
  /// For recordings made before a clip was published automatically, and for any
  /// whose publish failed at the time — a phone briefly out of storage, an
  /// insert refused mid-competition. The operator presses it once and the video
  /// appears in the gallery like the rest.
  static Future<String?> publishExisting(String path) => _publish(File(path));

  /// Copy a published clip out of the media library into the app's cache, so it
  /// can be read as a plain file.
  ///
  /// A `content://` handle cannot be opened as a File, and the upload needs
  /// byte ranges for resuming. The copy is disposable — the OS clears the cache
  /// — and it is deleted as soon as the upload finishes.
  static Future<String?> cacheCopy(String uri) async {
    try {
      return await _platform.invokeMethod<String>('cacheCopy', {'uri': uri});
    } catch (_) {
      return null;
    }
  }

  /// Remove a clip's video from the phone, wherever it lives.
  ///
  /// True when the video is gone — including when it was already gone, because
  /// the caller's intent is satisfied either way.
  static Future<bool> deleteVideo({String? uri, required String path}) async {
    try {
      return await _platform.invokeMethod<bool>('deleteVideo', {'uri': uri, 'path': path}) ?? false;
    } catch (_) {
      return false;
    }
  }

  /// Hand the file to the platform's media library. Null if it would not take
  /// it — in which case the clip stays private, and still exists.
  static Future<String?> _publish(File file) async {
    try {
      return await _platform.invokeMethod<String>('publishVideo', {
        'path': file.path,
        'name': file.uri.pathSegments.last,
      });
    } catch (_) {
      return null;
    }
  }

  Future<void> close() async {
    try {
      if (_rolling) await _controller?.stopVideoRecording();
    } catch (_) {}

    _rolling = false;
    await _controller?.dispose();
    _controller = null;
  }

  /// {total, free} bytes on the volume the clips are written to, or null.
  static Future<({int total, int free})?> storage() async {
    try {
      final result = await _platform.invokeMapMethod<String, dynamic>('storage');

      if (result == null) return null;

      return (total: (result['total'] as num).toInt(), free: (result['free'] as num).toInt());
    } catch (_) {
      return null;
    }
  }

  /// Battery percentage, or null on a platform that will not say.
  static Future<int?> battery() async {
    try {
      final level = await _platform.invokeMethod<int>('battery');

      return level;
    } catch (_) {
      return null;
    }
  }

  /// Where clips live: the app's own documents directory, so they survive an
  /// app restart and are reachable over USB without root.
  static Future<Directory> _clipDir() async {
    final base = await getApplicationDocumentsDirectory();
    final dir = Directory('${base.path}/clips');

    if (!await dir.exists()) await dir.create(recursive: true);

    return dir;
  }

  static String _two(int n) => n.toString().padLeft(2, '0');

  static String _slug(String raw) =>
      raw.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), '-').replaceAll(RegExp(r'(^-|-$)'), '');
}
