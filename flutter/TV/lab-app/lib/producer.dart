import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';

import 'kept.dart';
import 'vault.dart';

/// Making a bout's video ON THE PHONE — the score bar burned in, whole or cut
/// to the highlights — once, for the send sheet and the queue alike.
///
/// Before anything is made the RECIPE is checked (lib/kept.dart): the same
/// footage, the same score plan, the same switches, the same music and tilt
/// as a video already in the gallery means that video IS the answer, and
/// nothing is made. Only one video is ever being made at a time — the phone
/// has one video chip and one progress counter — so a send that arrives while
/// the queue works simply waits its turn.
class Producer {
  Producer._();

  static const _native = MethodChannel('bh.takeone.lab/share');

  static Future<void> _tail = Future.value();

  /// One job on the video chip at a time, in the order asked.
  static Future<T> _oneAtATime<T>(Future<T> Function() job) {
    final done = Completer<T>();
    _tail = _tail.then((_) async {
      try {
        done.complete(await job());
      } catch (e, s) {
        done.completeError(e, s);
      }
    });

    return done.future;
  }

  /// Everything that decides what a made video looks like.
  static Map<String, dynamic> recipe(Clip clip, ClipVault vault, Map<String, dynamic> plan, String kind) {
    final parts = vault.parts.toList()..sort();

    return {
      'how': 'render',
      'kind': kind,
      'source': KeptVideos.fileStamp(clip.path),
      'plan': plan,
      'parts': parts,
      'lift': vault.lift,
      'tilt': clip.tilt,
      'vsLift': vault.vsLift,
      'music': KeptVideos.fileStamp(vault.musicPath),
    };
  }

  /// The video for this clip and kind: the one already made when nothing has
  /// changed (`reused`), else a fresh one, moved into the gallery. `error` is
  /// the phone's own words when it could not be made.
  static Future<Made> make(
    Clip clip,
    ClipVault vault,
    Map<String, dynamic> plan,
    String kind, {
    void Function(double fraction)? progress,
  }) async {
    final source = File(clip.path);
    if (!source.existsSync()) return const Made(error: 'That file is no longer on this phone.');

    final wanted = recipe(clip, vault, plan, kind);

    final had = await KeptVideos.find(wanted);
    if (had != null) return Made(path: had.path, reused: true, dropped: (had.note['dropped'] as List?) ?? const []);

    return _oneAtATime(() async {
      // Made by the queue while this one waited its turn?
      final meanwhile = await KeptVideos.find(wanted);
      if (meanwhile != null) {
        return Made(path: meanwhile.path, reused: true, dropped: (meanwhile.note['dropped'] as List?) ?? const []);
      }

      final timer = Timer.periodic(const Duration(milliseconds: 600), (_) async {
        try {
          final p = await _native.invokeMethod<int>('progress');
          if (p != null && p >= 0) progress?.call(p / 100);
        } catch (_) {}
      });

      final dir = Directory('${(await getTemporaryDirectory()).path}/share');
      if (!dir.existsSync()) dir.createSync(recursive: true);

      final names = plan['filename'] as Map<String, dynamic>?;
      final out = '${dir.path}/${safe((names?[kind] as String?) ?? '${clip.ref.replaceAll('.mp4', '')}-$kind.mp4')}';

      String? made;
      String? why;
      List<dynamic> dropped = const [];

      try {
        final answer = await _native.invokeMethod<dynamic>('render', {
          'input': source.path,
          'output': out,
          'plan': jsonEncode(plan),
          'kind': kind,
          'parts': wanted['parts'],
          'lift': vault.lift,
          'tilt': clip.tilt,
          'music': vault.musicPath ?? '',
          'vsLift': vault.vsLift,
        });
        if (answer is Map) {
          made = answer['path'] as String?;
          dropped = (answer['dropped'] as List?) ?? const [];
        } else if (answer is String) {
          made = answer;
        }
      } on PlatformException catch (e) {
        why = e.message;
      } finally {
        timer.cancel();
      }

      if (made == null) return Made(error: why ?? 'The phone gave no reason.');

      return Made(path: await KeptVideos.keep(wanted, made, note: {'dropped': dropped}), dropped: dropped);
    });
  }

  static String safe(String name) => name.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '-');
}

/// What `Producer.make` answers.
class Made {
  const Made({this.path, this.reused = false, this.dropped = const [], this.error});

  final String? path;
  final bool reused;
  final List<dynamic> dropped;
  final String? error;
}

/// One bout, one kind, waiting its turn in the queue.
class MakeJob {
  MakeJob(this.clip, this.kind);

  final Clip clip;

  /// full · highlights
  final String kind;

  /// waiting · making · made · unchanged · skipped · failed
  String state = 'waiting';
  double progress = 0;
  String? note;

  bool get open => state == 'waiting' || state == 'making';
}

/// Several bouts made one after another — a selection, or every bout on the
/// list — while the operator does something else. A bout whose video is
/// already in the gallery with nothing changed is skipped, never made again.
class MakeQueue extends ChangeNotifier {
  MakeQueue._();

  static final MakeQueue instance = MakeQueue._();

  final List<MakeJob> jobs = [];
  bool _running = false;
  bool _stopping = false;

  bool get busy => jobs.any((j) => j.open);
  int get total => jobs.length;
  int get finished => jobs.where((j) => !j.open).length;
  int count(String state) => jobs.where((j) => j.state == state).length;
  MakeJob? get current => jobs.where((j) => j.state == 'making').firstOrNull;

  /// The newest job for a clip, for its row on the list.
  MakeJob? jobFor(Clip clip) => jobs.lastWhereOrNull((j) => j.clip.ref == clip.ref);

  /// Add bouts; a bout already waiting for the same kind is not added twice.
  void add(Iterable<Clip> clips, List<String> kinds, ClipVault vault) {
    // A finished round is cleared when a new one starts.
    if (!busy) jobs.clear();

    for (final clip in clips) {
      for (final kind in kinds) {
        if (jobs.any((j) => j.open && j.clip.ref == clip.ref && j.kind == kind)) continue;
        jobs.add(MakeJob(clip, kind));
      }
    }

    notifyListeners();
    unawaited(_run(vault));
  }

  /// Stop after the video being made now; the rest are dropped.
  void stop() {
    _stopping = true;
    for (final j in jobs.where((j) => j.state == 'waiting')) {
      j.state = 'skipped';
      j.note = 'Stopped';
    }
    notifyListeners();
  }

  void clear() {
    if (busy) return;
    jobs.clear();
    notifyListeners();
  }

  Future<void> _run(ClipVault vault) async {
    if (_running) return;
    _running = true;
    _stopping = false;

    try {
      while (!_stopping) {
        final job = jobs.where((j) => j.state == 'waiting').firstOrNull;
        if (job == null) break;

        job.state = 'making';
        job.progress = 0;
        notifyListeners();

        final plan = await vault.planFor(job.clip);

        if (plan == null) {
          job
            ..state = 'skipped'
            ..note = vault.token == null ? 'Camera not paired' : 'No score from the event';
        } else if (job.kind == 'highlights' && ((plan['windows'] as List?)?.isEmpty ?? true)) {
          job
            ..state = 'skipped'
            ..note = 'Nothing was scored';
        } else {
          final made = await Producer.make(job.clip, vault, plan, job.kind, progress: (p) {
            job.progress = p;
            notifyListeners();
          });

          if (made.path == null) {
            job
              ..state = 'failed'
              ..note = made.error;
          } else {
            job.state = made.reused ? 'unchanged' : 'made';
            job.progress = 1;
          }
        }

        notifyListeners();
      }
    } finally {
      _running = false;
      _stopping = false;
      notifyListeners();
    }
  }
}

extension<T> on Iterable<T> {
  T? lastWhereOrNull(bool Function(T) test) {
    T? found;
    for (final e in this) {
      if (test(e)) found = e;
    }

    return found;
  }
}
