import 'package:flutter_test/flutter_test.dart';
import 'package:takeone_lab/vault.dart';

/// The file name is the only record of a bout that survives an uninstall.
void main() {
  test('reads a boutcam name back into its bout', () {
    final name = 'mat-mat-1_angle-2_bout-A12_m6974_60fps_20261023-101500_'
        'red-${ClipName.piece('Ali Hassan')}_blue-${ClipName.piece('محمد علي')}.mp4';
    final clip = ClipName.read(name: name, path: '/x/$name', bytes: 9, seconds: 90)!;

    expect(clip.court, 'mat 1');
    expect(clip.angle, 2);
    expect(clip.number, 'A12');
    expect(clip.matchId, 6974);
    expect(clip.fps, 60);
    expect(clip.startedAt, DateTime(2026, 10, 23, 10, 15));
    expect(clip.red, 'Ali Hassan');
    expect(clip.blue, 'محمد علي');
    expect(clip.ref, name);
  });

  test('reads the old mat camera name, which never carried the bout id', () {
    const name = 'mat-mat-1_angle-1_bout-70_20261017-164240.mp4';
    final clip = ClipName.read(name: name, path: '/x/$name')!;

    expect(clip.number, '70');
    expect(clip.matchId, isNull);
    expect(clip.startedAt, DateTime(2026, 10, 17, 16, 42, 40));

    // Found, not filmed here: sent nowhere until the event recognises it.
    expect(clip.held, isTrue);
    clip.confirmed = true;
    expect(clip.held, isFalse);
  });

  test('a piece never carries a separator', () {
    expect(ClipName.piece('a_b c/d.e'), 'a-b-c-d-e');
    expect(ClipName.read(name: 'notes.txt', path: '/x'), isNull);
  });

  test('leveling grows the picture just enough to hide the corners', () {
    expect(Clip.fillFor(0, 16 / 9), closeTo(1, 1e-9));
    // 5° on 16:9: cos 5° + (16/9)·sin 5°.
    expect(Clip.fillFor(5, 16 / 9), closeTo(1.1511, 1e-3));
    expect(Clip.fillFor(-5, 16 / 9), Clip.fillFor(5, 16 / 9));
  });
}
