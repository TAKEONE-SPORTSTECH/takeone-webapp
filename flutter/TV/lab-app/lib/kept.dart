import 'dart:convert';
import 'dart:io';

import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';

/// Videos the phone has already made for sending — kept in the phone's
/// gallery (Movies/Sent bouts) and never made twice.
///
/// Every finished video is filed under its RECIPE: everything that decides
/// what comes out — the footage (path, size, last change), the score plan the
/// event sent, which parts are burned in, the bar's position, the tilt, the VS
/// music file, and the build of this app (a new APK may draw differently).
/// Send the same bout again with nothing changed and the video already in the
/// gallery is handed straight back; change anything — a corrected score, a
/// switch, the music — and the recipe differs, so a fresh one is made (and
/// joins the album beside the earlier one).
///
/// The recipe card lives in the app's own storage and is compared IN FULL, so
/// a collision can never hand out the wrong video. The videos themselves are
/// the user's: nothing here ever deletes one from the gallery. Delete it there
/// and the next send simply makes it again.
class KeptVideos {
  KeptVideos._();

  /// The APK's build number, stamped in by build.sh.
  static const String build = String.fromEnvironment('APP_BUILD', defaultValue: 'dev');

  /// A card nobody has sent from in this long is forgotten (the video stays).
  static const Duration keepFor = Duration(days: 60);

  static const _gallery = MethodChannel('bh.takeone.lab/gallery');

  static Future<Directory> _cards() async {
    final dir = Directory('${(await getApplicationSupportDirectory()).path}/made');
    if (!dir.existsSync()) dir.createSync(recursive: true);

    return dir;
  }

  /// A file as a recipe ingredient: same path, same size, same last change.
  static Map<String, dynamic>? fileStamp(String? path) {
    if (path == null || path.isEmpty) return null;

    final f = File(path);
    if (!f.existsSync()) return {'path': path, 'missing': true};

    final s = f.statSync();

    return {'path': path, 'size': s.size, 'mtime': s.modified.millisecondsSinceEpoch};
  }

  static String _text(Map<String, dynamic> recipe) => jsonEncode({'build': build, ...recipe});

  /// FNV-1a, 64-bit — only names the card; the full recipe decides.
  static String name(String text) {
    var h = 0xcbf29ce484222325;
    for (final b in utf8.encode(text)) {
      h ^= b;
      h *= 0x100000001b3;
    }

    return h.toUnsigned(64).toRadixString(16).padLeft(16, '0');
  }

  static Future<File> _card(String text) async => File('${(await _cards()).path}/${name(text)}.json');

  /// The video made from exactly this recipe, if the phone still has it.
  static Future<({String path, Map<String, dynamic> note})?> find(Map<String, dynamic> recipe) async {
    try {
      final text = _text(recipe);
      final card = await _card(text);

      if (!card.existsSync()) return null;

      final stored = jsonDecode(card.readAsStringSync()) as Map<String, dynamic>;
      final video = File((stored['path'] as String?) ?? '');

      if (stored['recipe'] != text || !video.existsSync() || video.lengthSync() == 0) return null;

      // Sent again: the newest use is what keeps the card.
      card.setLastModifiedSync(DateTime.now());

      return (path: video.path, note: (stored['note'] as Map?)?.cast<String, dynamic>() ?? const <String, dynamic>{});
    } catch (_) {
      return null;
    }
  }

  /// Move a freshly made video into the gallery album and file it under its
  /// recipe. Answers with where it now lives — the gallery, or (when the phone
  /// would not take it) the file as made, which is still sent and still
  /// remembered for as long as it lasts.
  static Future<String> keep(Map<String, dynamic> recipe, String made, {Map<String, dynamic> note = const {}}) async {
    var path = made;

    try {
      final moved = await _gallery.invokeMethod<String>('publish', {
        'path': made,
        'name': made.split('/').last,
        'album': 'sent',
      });
      if (moved != null && File(moved).existsSync()) path = moved;
    } catch (_) {}

    try {
      final text = _text(recipe);
      (await _card(text)).writeAsStringSync(jsonEncode({'recipe': text, 'path': path, 'note': note}));
    } catch (_) {}

    return path;
  }

  /// Cards whose video is gone, or that nobody has used in a long while.
  static Future<void> tidy() async {
    try {
      final now = DateTime.now();

      for (final card in (await _cards()).listSync().whereType<File>()) {
        try {
          final stored = jsonDecode(card.readAsStringSync()) as Map<String, dynamic>;
          final gone = !File((stored['path'] as String?) ?? '').existsSync();

          if (gone || now.difference(card.lastModifiedSync()) > keepFor) card.deleteSync();
        } catch (_) {
          card.deleteSync();
        }
      }
    } catch (_) {}
  }
}
