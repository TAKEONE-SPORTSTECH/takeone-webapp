import 'package:flutter_test/flutter_test.dart';
import 'package:takeone_lab/kept.dart';

void main() {
  test('the same recipe always names the same card', () {
    expect(KeptVideos.name('{"kind":"full"}'), KeptVideos.name('{"kind":"full"}'));
    expect(KeptVideos.name('{"kind":"full"}').length, 16);
  });

  test('any change to the recipe names a different card', () {
    final a = KeptVideos.name('{"kind":"full","lift":18}');
    expect(KeptVideos.name('{"kind":"full","lift":19}'), isNot(a));
    expect(KeptVideos.name('{"kind":"highlights","lift":18}'), isNot(a));
  });
}
