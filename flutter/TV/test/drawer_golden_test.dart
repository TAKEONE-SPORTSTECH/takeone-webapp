import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:takeone_tv/src/camera/clips.dart';
import 'package:takeone_tv/src/camera/drawer.dart';

/// Not a behavioural test — a LOOK at the one panel that cannot be checked on a
/// device without a competition running. Run with --update-goldens to redraw.
/// The bundled faces, loaded into the test harness — without this every glyph
/// renders as a placeholder box and the golden proves layout but not legibility.
Future<void> _loadFonts() async {
  for (final entry in {
    'Barlow Condensed': [
      'assets/fonts/BarlowCondensed-Regular.ttf',
      'assets/fonts/BarlowCondensed-SemiBold.ttf',
      'assets/fonts/BarlowCondensed-Bold.ttf',
    ],
    'Archivo Black': ['assets/fonts/ArchivoBlack-Regular.ttf'],
  }.entries) {
    final loader = FontLoader(entry.key);

    for (final path in entry.value) {
      loader.addFont(File(path).readAsBytes().then((b) => ByteData.view(b.buffer)));
    }

    await loader.load();
  }
}

void main() {
  setUpAll(_loadFonts);

  final clips = [
    CameraClip(
      file: 'Movies/TAKEONE/mat-1_angle-2_bout-1-04.mp4',
      startedAt: DateTime(2026, 8, 23, 12, 15),
      endedAt: DateTime(2026, 8, 23, 12, 21, 41),
      matchNumber: '1-04',
      red: 'Ahmed Al Sayed',
      blue: 'John Carter',
      bytes: 3328599654,
      uri: 'content://media/external/video/media/1042',
      serverId: 1,
    ),
    CameraClip(
      file: '/data/user/0/bh.takeone.cam/app_flutter/clips/bout-1-05.mp4',
      startedAt: DateTime(2026, 8, 23, 12, 26),
      endedAt: DateTime(2026, 8, 23, 12, 30, 12),
      matchNumber: '1-05',
      red: 'Kim Da-eun',
      blue: 'Sofia Lopez',
      bytes: 2147483648,
    ),
    CameraClip(
      file: 'Movies/TAKEONE/mat-1_angle-2_bout-1-06.mp4',
      startedAt: DateTime(2026, 8, 23, 12, 40),
      endedAt: DateTime(2026, 8, 23, 12, 48, 3),
      matchNumber: '1-06',
      red: 'Yusuf Khalid',
      blue: 'Marc Dubois',
      bytes: 4187593113,
      uri: 'content://media/external/video/media/1043',
    ),
  ];

  Widget harness({bool select = false}) => MaterialApp(
        debugShowCheckedModeBanner: false,
        home: Scaffold(
          backgroundColor: const Color(0xFF050507),
          body: Align(
            alignment: Alignment.centerRight,
            child: ClipDrawer(
              clips: clips,
              startInSelect: select,
              onClose: () {},
              onPlay: (_) {},
              onSave: (_) {},
              onUpload: (_) {},
              onDelete: (_) async {},
            ),
          ),
        ),
      );

  testWidgets('clip drawer', (tester) async {
    tester.view.physicalSize = const Size(780 * 3, 360 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(harness());
    await tester.pumpAndSettle();

    await expectLater(find.byType(MaterialApp), matchesGoldenFile('goldens/drawer.png'));
  });

  testWidgets('clip drawer, selecting', (tester) async {
    tester.view.physicalSize = const Size(780 * 3, 360 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(harness(select: true));
    await tester.pumpAndSettle();

    // Choose two, so the footer and the header both have something to say.
    await tester.tap(find.text('AHMED AL SAYED  VS  JOHN CARTER'));
    await tester.tap(find.text('KIM DA-EUN  VS  SOFIA LOPEZ'));
    await tester.pumpAndSettle();

    await expectLater(find.byType(MaterialApp), matchesGoldenFile('goldens/drawer-select.png'));
  });

  testWidgets('delete confirmation', (tester) async {
    tester.view.physicalSize = const Size(780 * 3, 360 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(harness(select: true));
    await tester.pumpAndSettle();

    await tester.tap(find.text('AHMED AL SAYED  VS  JOHN CARTER'));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('DELETE 1 CLIP'));
    await tester.pumpAndSettle();

    await expectLater(find.byType(MaterialApp), matchesGoldenFile('goldens/delete-dialog.png'));
  });
}
