import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:video_player/video_player.dart';

import 'parts.dart';
import 'producer.dart';
import 'send.dart';
import 'tally.dart';
import 'vault.dart';
import 'vs_intro.dart';

/// What this phone filmed, on the phone.
///
/// The one screen in the app that is not about the next bout. It exists for the
/// moment after the mat finishes: somebody picks the phone off the tripod and
/// needs to answer, right there, "did we get the semi-final?" — before the
/// clips are anywhere else, possibly before the hall's wifi has carried a single
/// byte of them.
///
/// So it reads off the phone's own ledger, never off the server, and it says
/// everything the ledger knows about each bout: the bout number and stage, the
/// two corners, the mat and camera angle, when it started, how long it ran, how
/// big the file is, what it was shot at, and whether the event has it yet. A
/// list that only showed file names would send the same person back to a laptop.
///
/// Reached from the pairing screen, because that is where a camera is when
/// nobody is filming with it.
class ClipLibrary extends StatefulWidget {
  const ClipLibrary({super.key, required this.vault});

  final ClipVault vault;

  @override
  State<ClipLibrary> createState() => _ClipLibraryState();
}

class _ClipLibraryState extends State<ClipLibrary> {
  @override
  void initState() {
    super.initState();
    // The queue keeps working while this list is open, and the list keeps up.
    widget.vault.watch(_refresh);
    MakeQueue.instance.addListener(_refresh);

    // Whatever is in the gallery and not on the list yet joins it.
    unawaited(widget.vault.recover());
  }

  @override
  void dispose() {
    widget.vault.unwatch(_refresh);
    MakeQueue.instance.removeListener(_refresh);
    _search.dispose();
    super.dispose();
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  /// all · draw · ex · todo (not uploaded yet) · done (uploaded)
  String _filter = 'all';

  /// One club's bouts only — either corner fought for it. Null is every club.
  String? _team;

  /// Every club on the list, the busiest first, with how many bouts each.
  List<MapEntry<String, int>> _teams() {
    final count = <String, int>{};
    for (final c in widget.vault.clips) {
      for (final club in {c.redClub, c.blueClub}.whereType<String>().where((t) => t.isNotEmpty)) {
        count[club] = (count[club] ?? 0) + 1;
      }
    }

    return count.entries.toList()..sort((a, b) => b.value != a.value ? b.value.compareTo(a.value) : a.key.compareTo(b.key));
  }

  /// Picking bouts for a queue — to upload, or to make their videos.
  bool _picking = false;
  final Set<String> _picked = {};

  List<Clip> get _pickedClips => widget.vault.clips.where((c) => _picked.contains(c.ref)).toList();

  void _togglePick(Clip c) => setState(() => _picked.contains(c.ref) ? _picked.remove(c.ref) : _picked.add(c.ref));

  void _endPicking() => setState(() {
        _picking = false;
        _picked.clear();
      });

  bool _isExhibition(Clip c) => c.number?.toLowerCase().startsWith('ex') ?? false;

  /// What the search box holds; every word must match somewhere in a bout.
  String _query = '';
  final TextEditingController _search = TextEditingController();

  /// Everything a person might type to find a bout: its number (also as
  /// "ex 4"), both names, the round, the weight class, the mat, the event and
  /// when it was filmed — lower-cased, Arabic stripped of its short vowels so
  /// a name matches however it was typed.
  static String _haystack(Clip c) {
    String two(int n) => n.toString().padLeft(2, '0');
    final n = c.number ?? '';
    final exNo = n.toLowerCase().startsWith('ex') ? n.substring(2) : '';

    return _fold([
      n,
      if (exNo.isNotEmpty) 'ex $exNo exhibition $exNo',
      if (n.isNotEmpty && exNo.isEmpty) 'bout $n match $n',
      c.red ?? '',
      c.blue ?? '',
      c.redClub ?? '',
      c.blueClub ?? '',
      c.stage ?? '',
      c.division ?? '',
      c.court ?? '',
      c.event ?? '',
      '${two(c.startedAt.hour)}:${two(c.startedAt.minute)}',
    ].join(' '));
  }

  static String _fold(String s) => s
      .toLowerCase()
      .replaceAll(RegExp('[\u064B-\u065F\u0670]'), '')
      .replaceAll(RegExp('[أإآ]'), 'ا')
      .replaceAll('ة', 'ه')
      .replaceAll('ى', 'ي')
      .replaceAll('−', '-');

  List<Clip> _shown() {
    final clips = widget.vault.clips;

    final filtered = switch (_filter) {
      'draw' => clips.where((c) => !_isExhibition(c)).toList(),
      'ex' => clips.where(_isExhibition).toList(),
      'todo' => clips.where((c) => !c.settled).toList(),
      'done' => clips.where((c) => c.settled).toList(),
      _ => clips,
    }
        .where((c) => _team == null || c.redClub == _team || c.blueClub == _team)
        .toList();

    final words = _fold(_query).split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
    if (words.isEmpty) return filtered;

    return filtered.where((c) {
      final hay = _haystack(c);
      return words.every(hay.contains);
    }).toList();
  }

  Widget _searchBox() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 2, 14, 4),
      child: Container(
        height: 38,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.06),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
        ),
        child: Row(
          children: [
            const SizedBox(width: 10),
            const Icon(Icons.search, size: 18, color: Tally.textFaint),
            const SizedBox(width: 6),
            Expanded(
              child: TextField(
                controller: _search,
                onChanged: (v) => setState(() => _query = v),
                style: Tally.body(13, color: Tally.text),
                cursorColor: Tally.gold,
                decoration: InputDecoration(
                  isDense: true,
                  border: InputBorder.none,
                  hintText: 'Search name, bout no., round, weight…',
                  hintStyle: Tally.body(12.5, color: Tally.textFaint),
                ),
              ),
            ),
            if (_query.isNotEmpty)
              GestureDetector(
                onTap: () {
                  _search.clear();
                  setState(() => _query = '');
                },
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 10),
                  child: Icon(Icons.close, size: 17, color: Tally.textMuted),
                ),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final all = widget.vault.clips;
    final clips = _shown();
    final toUpload = all.where((c) => !c.settled && !c.held).length;

    return Scaffold(
      backgroundColor: Tally.navyDeep,
      body: SafeArea(
        child: Stack(
          children: [
            Column(
              children: [
                _header(all),
                if (all.isNotEmpty) _searchBox(),
                _filters(all),
                Expanded(
                  child: all.isEmpty
                      ? Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.videocam_off_outlined, size: 34, color: Tally.textFaint),
                              const SizedBox(height: 12),
                              Text('NOTHING FILMED YET', style: Tally.label(12, tracking: 0.2)),
                              const SizedBox(height: 6),
                              SizedBox(
                                width: 280,
                                child: Text(
                                  'Bouts recorded on this phone appear here, whether or not '
                                  'they have reached the event yet.',
                                  textAlign: TextAlign.center,
                                  style: Tally.body(12, color: Tally.textFaint),
                                ),
                              ),
                            ],
                          ),
                        )
                      : clips.isEmpty
                          ? Center(
                              child: Text(
                                _query.isNotEmpty ? 'No bout matches "$_query".' : 'Nothing here.',
                                style: Tally.body(12.5, color: Tally.textFaint),
                              ),
                            )
                          : ListView.builder(
                          padding: EdgeInsets.fromLTRB(12, 0, 12, 20 + 56.0 * _bars(toUpload).length),
                          itemCount: clips.length,
                          itemBuilder: (_, i) => _BoutRow(
                            clip: clips[i],
                            picking: _picking,
                            picked: _picked.contains(clips[i].ref),
                            job: MakeQueue.instance.jobFor(clips[i]),
                            queued: widget.vault.wanted(clips[i]),
                            onPlay: () => _picking ? _togglePick(clips[i]) : _play(clips[i]),
                            onUpload: () => _upload(clips[i]),
                            onSend: () => showSendSheet(context, clip: clips[i], vault: widget.vault),
                          ),
                        ),
                ),
              ],
            ),

            // The bottom of the screen: what is picked, and the two queues.
            if (_bars(toUpload).isNotEmpty)
              Positioned(
                left: 14,
                right: 14,
                bottom: 14,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (final bar in _bars(toUpload)) ...[bar, const SizedBox(height: 10)],
                  ]..removeLast(),
                ),
              ),
          ],
        ),
      ),
    );
  }

  /* ── The queues ──────────────────────────────────────────────────────── */

  static const _uploadInk = Color(0xFFDCE8FF);
  static const _uploadFill = Color(0xFF1E3F7A);

  /// The bars along the bottom, top to bottom.
  List<Widget> _bars(int toUpload) {
    final queue = MakeQueue.instance;
    final vault = widget.vault;

    if (_picking) {
      final n = _picked.length;
      final uploadable = _pickedClips.where((c) => !c.settled && !c.held).length;

      return [
        Row(children: [
          Expanded(
            child: _bar(
              icon: Icons.cloud_upload_outlined,
              text: uploadable == 0 ? 'UPLOAD' : 'UPLOAD $uploadable',
              fill: _uploadFill,
              ink: _uploadInk,
              dim: uploadable == 0,
              onTap: uploadable == 0 ? null : _uploadPicked,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _bar(
              icon: Icons.movie_creation_outlined,
              text: n == 0 ? 'MAKE VIDEOS' : 'MAKE $n',
              fill: Tally.gold,
              ink: Tally.navyDeep,
              dim: n == 0,
              onTap: n == 0 ? null : _makePicked,
            ),
          ),
        ]),
      ];
    }

    final out = <Widget>[];

    if (queue.total > 0) {
      final now = queue.current;
      final skipped = queue.count('unchanged');
      final made = queue.count('made');
      final problems = queue.count('failed') + queue.count('skipped');
      final text = queue.busy
          ? 'MAKING ${queue.finished + 1} OF ${queue.total}${now == null ? '' : ' · ${(now.progress * 100).round()}%'}'
          : 'MADE $made${skipped > 0 ? ' · $skipped UNCHANGED' : ''}${problems > 0 ? ' · $problems NOT MADE' : ''}';

      out.add(_bar(
        icon: queue.busy ? Icons.movie_creation_outlined : Icons.check_circle_outline,
        text: text,
        sub: queue.busy && skipped > 0 ? '$skipped already made, nothing changed — skipped' : (queue.busy ? null : 'In the gallery · Movies/Sent bouts'),
        fill: const Color(0xFF3A2F12),
        ink: Tally.goldLight,
        progress: queue.busy ? (queue.finished + (now?.progress ?? 0)) / queue.total : null,
        action: queue.busy ? 'STOP' : 'OK',
        onAction: queue.busy ? queue.stop : queue.clear,
        onTap: _showQueue,
      ));
    }

    final left = vault.wantedLeft;

    if (left > 0) {
      final flying = vault.inFlight;
      out.add(_bar(
        icon: Icons.cloud_upload_outlined,
        text: 'UPLOADING · $left LEFT${flying == null ? '' : ' · ${(flying.fraction * 100).round()}%'}',
        sub: flying == null ? 'Waiting for the connection — it carries on by itself' : null,
        fill: _uploadFill,
        ink: _uploadInk,
        action: 'CANCEL',
        onAction: () => unawaited(vault.cancelUploads()),
      ));
    } else if (toUpload > 0) {
      // The manual door, kept in reach: auto upload is off by default, so
      // this is how a day's footage reaches the event's bout gallery.
      out.add(_bar(
        icon: Icons.cloud_upload_outlined,
        text: vault.inFlight != null ? 'UPLOADING…' : 'UPLOAD ALL $toUpload',
        fill: _uploadFill,
        ink: _uploadInk,
        onTap: _uploadAll,
      ));
    }

    return out;
  }

  Widget _bar({
    required IconData icon,
    required String text,
    required Color fill,
    required Color ink,
    String? sub,
    double? progress,
    String? action,
    VoidCallback? onAction,
    VoidCallback? onTap,
    bool dim = false,
  }) {
    return Opacity(
      opacity: dim ? 0.45 : 1,
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          height: 46,
          clipBehavior: ui.Clip.antiAlias,
          decoration: BoxDecoration(
            color: fill,
            borderRadius: BorderRadius.circular(14),
            boxShadow: const [BoxShadow(color: Color(0x66000000), blurRadius: 18, offset: Offset(0, 6))],
          ),
          child: Stack(
            children: [
              if (progress != null)
                FractionallySizedBox(
                  widthFactor: progress.clamp(0.0, 1.0),
                  child: Container(color: ink.withValues(alpha: 0.12)),
                ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Row(
                  mainAxisAlignment: action == null ? MainAxisAlignment.center : MainAxisAlignment.start,
                  children: [
                    Icon(icon, size: 19, color: ink),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: action == null ? CrossAxisAlignment.center : CrossAxisAlignment.start,
                        children: [
                          FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(text, style: Tally.label(13, color: ink, tracking: 0.12, weight: FontWeight.w700)),
                          ),
                          if (sub != null)
                            Text(sub, maxLines: 1, overflow: TextOverflow.fade, softWrap: false, style: Tally.body(10, color: ink.withValues(alpha: 0.7))),
                        ],
                      ),
                    ),
                    if (action != null) ...[
                      const Spacer(),
                      GestureDetector(
                        onTap: onAction,
                        behavior: HitTestBehavior.opaque,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
                          child: Text(action, style: Tally.label(11.5, color: ink, tracking: 0.14, weight: FontWeight.w700)),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _uploadPicked() {
    final picked = _pickedClips.where((c) => !c.settled && !c.held).toList();
    if (picked.isEmpty) return;

    unawaited(widget.vault.upload(picked));
    _endPicking();
    _say('${picked.length} bout${picked.length == 1 ? '' : 's'} queued for the event\'s bout gallery — one at a time, '
        'in pieces, picking up where it stopped if the wifi drops.');
  }

  /// Whole bout, highlights or both — then every picked bout joins the queue.
  Future<void> _makePicked() async {
    final picked = _pickedClips;
    if (picked.isEmpty) return;

    final kinds = await showModalBottomSheet<List<String>>(
      context: context,
      backgroundColor: Tally.navyPrimary,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (sheet) {
        Widget choice(IconData icon, String title, String line, List<String> kinds) => ListTile(
              leading: Icon(icon, color: Tally.gold),
              title: Text(title, style: Tally.label(13.5, color: Tally.text, tracking: 0.1, weight: FontWeight.w700)),
              subtitle: Text(line, style: Tally.body(11.5, color: Tally.textMuted)),
              onTap: () => Navigator.of(sheet).pop(kinds),
            );

        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(4, 14, 4, 10),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 4),
                  child: Text('MAKE ${picked.length} VIDEO${picked.length == 1 ? '' : 'S'}',
                      style: Tally.label(12, color: Tally.goldLight, tracking: 0.18, weight: FontWeight.w700)),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 6),
                  child: Text('With the score bar and the parts switched on now. A bout already made with nothing changed is skipped.',
                      style: Tally.body(11.5, color: Tally.textFaint)),
                ),
                choice(Icons.movie_outlined, 'WHOLE BOUT', 'The full recording', ['full']),
                choice(Icons.bolt_outlined, 'HIGHLIGHTS', 'Only the scoring moments', ['highlights']),
                choice(Icons.video_library_outlined, 'BOTH', 'Whole bout and highlights', ['full', 'highlights']),
              ],
            ),
          ),
        );
      },
    );

    if (kinds == null || !mounted) return;

    MakeQueue.instance.add(picked, kinds, widget.vault);
    _endPicking();
  }

  /// The queue, bout by bout.
  void _showQueue() {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Tally.navyPrimary,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => ListenableBuilder(
        listenable: MakeQueue.instance,
        builder: (context, _) {
          final jobs = MakeQueue.instance.jobs;

          return ConstrainedBox(
            constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.7),
            child: SafeArea(
              child: ListView(
                shrinkWrap: true,
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
                children: [
                  Text('VIDEO QUEUE', style: Tally.label(12, color: Tally.goldLight, tracking: 0.18, weight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  for (final j in jobs)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Row(children: [
                        SizedBox(
                          width: 54,
                          child: Text(j.clip.number == null ? '—' : (j.clip.number!.toLowerCase().startsWith('ex') ? 'EX ${j.clip.number!.substring(2)}' : j.clip.number!),
                              style: Tally.label(14, color: Tally.goldLight, tracking: 0, weight: FontWeight.w700)),
                        ),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(j.clip.corners, maxLines: 1, overflow: TextOverflow.fade, softWrap: false, style: Tally.body(12, color: Tally.text)),
                            Text(j.kind == 'highlights' ? 'Highlights' : 'Whole bout', style: Tally.body(10.5, color: Tally.textMuted)),
                          ]),
                        ),
                        const SizedBox(width: 8),
                        Text(_jobLabel(j), style: Tally.label(10.5, color: _jobColour(j), tracking: 0.08, weight: FontWeight.w700)),
                      ]),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  static String _jobLabel(MakeJob j) => switch (j.state) {
        'making' => '${(j.progress * 100).round()}%',
        'made' => 'MADE',
        'unchanged' => 'UNCHANGED',
        'skipped' => (j.note ?? 'SKIPPED').toUpperCase(),
        'failed' => 'FAILED',
        _ => 'WAITING',
      };

  static Color _jobColour(MakeJob j) => switch (j.state) {
        'made' || 'unchanged' => Tally.green,
        'failed' => Tally.red,
        'making' => Tally.goldLight,
        _ => Tally.textMuted,
      };

  Widget _header(List<Clip> all) {
    final first = all.isEmpty ? null : all.first;
    final where = [
      if (first?.event != null && first!.event!.isNotEmpty) first.event!,
      if (first?.court != null && first!.court!.isNotEmpty) first.court!,
    ].join(' · ');

    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 6),
      child: Row(
        children: [
          _RoundButton(icon: Icons.chevron_left, onTap: () => Navigator.of(context).maybePop()),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_picking ? '${_picked.length} SELECTED' : 'RECORDED BOUTS',
                    style: Tally.label(15, color: Tally.text, tracking: 0.14, weight: FontWeight.w700)),
                if (!_picking && where.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(where, maxLines: 1, overflow: TextOverflow.fade, softWrap: false, style: Tally.body(11, color: Tally.textFaint)),
                ],
              ],
            ),
          ),
          if (all.isNotEmpty && _picking) ...[
            GestureDetector(
              onTap: () => setState(() {
                final shown = _shown().map((c) => c.ref).toSet();
                shown.every(_picked.contains) ? _picked.removeAll(shown) : _picked.addAll(shown);
              }),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                child: Text(_shown().every((c) => _picked.contains(c.ref)) ? 'NONE' : 'ALL',
                    style: Tally.label(12, color: Tally.goldLight, tracking: 0.14, weight: FontWeight.w700)),
              ),
            ),
            _RoundButton(icon: Icons.close, onTap: _endPicking),
          ] else if (all.isNotEmpty)
            _RoundButton(icon: Icons.checklist, onTap: () => setState(() => _picking = true)),
        ],
      ),
    );
  }

  /// One line of one-word chips that scrolls sideways — never squeezed —
  /// and the count fixed at its end.
  Widget _filters(List<Clip> all) {
    Widget chip(String label, bool on, VoidCallback tap, {IconData? icon, bool clear = false}) {
      return GestureDetector(
        onTap: tap,
        child: Container(
          margin: const EdgeInsetsDirectional.only(end: 6),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: on ? Tally.gold : Colors.white.withValues(alpha: 0.05),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            if (icon != null) ...[Icon(icon, size: 13, color: on ? Tally.navyDeep : Tally.textSecondary), const SizedBox(width: 4)],
            Text(label, style: Tally.body(11, color: on ? Tally.navyDeep : Tally.textSecondary, weight: FontWeight.w600)),
            if (clear) ...[const SizedBox(width: 4), Icon(Icons.close, size: 13, color: on ? Tally.navyDeep : Tally.textSecondary)],
          ]),
        ),
      );
    }

    Widget show(String key, String label) => chip(label, _filter == key, () => setState(() => _filter = key));

    final shown = _shown().length;

    return Padding(
      padding: const EdgeInsets.fromLTRB(0, 2, 14, 6),
      child: Row(
        children: [
          Expanded(
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsetsDirectional.only(start: 14, end: 8),
              child: Row(children: [
                show('all', 'All'),
                // A team picked is the chip's own label, tap ✕ to drop it.
                if (_teams().isNotEmpty)
                  chip(_team ?? 'Team', _team != null, _team != null ? () => setState(() => _team = null) : _pickTeam,
                      icon: Icons.shield_outlined, clear: _team != null),
                show('draw', 'Draw'),
                show('ex', 'Exhibitions'),
                show('todo', 'Pending'),
                show('done', 'Uploaded'),
              ]),
            ),
          ),
          Text(shown == all.length ? '${all.length}' : '$shown/${all.length}', style: Tally.body(10.5, color: Tally.textFaint)),
        ],
      ),
    );
  }

  /// Pick one club; its bouts — red or blue — are all the list shows.
  Future<void> _pickTeam() async {
    final teams = _teams();

    final picked = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Tally.navyPrimary,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (sheet) => ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.of(sheet).size.height * 0.7),
        child: SafeArea(
          child: ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.fromLTRB(4, 14, 4, 10),
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: Text('TEAM', style: Tally.label(12, color: Tally.goldLight, tracking: 0.18, weight: FontWeight.w700)),
              ),
              for (final t in teams)
                ListTile(
                  dense: true,
                  leading: const Icon(Icons.shield_outlined, color: Tally.gold, size: 20),
                  title: Text(t.key, style: Tally.body(13, color: Tally.text, weight: FontWeight.w600)),
                  trailing: Text('${t.value}', style: Tally.label(12, color: Tally.textMuted, tracking: 0, weight: FontWeight.w700)),
                  onTap: () => Navigator.of(sheet).pop(t.key),
                ),
            ],
          ),
        ),
      ),
    );

    if (picked != null && mounted) setState(() => _team = picked);
  }

  /// Every bout not in the event's gallery yet, now, whatever the auto-upload
  /// switch says. They stay on the phone either way.
  void _uploadAll() {
    if (widget.vault.inFlight != null) return;

    unawaited(widget.vault.sendNow());
    _say('Uploading ${widget.vault.waiting} bout${widget.vault.waiting == 1 ? '' : 's'} to the event\'s bout gallery. '
        'They stay on this phone either way.');
  }

  /// One bout to the event's bout gallery, ahead of the rest.
  Future<void> _upload(Clip clip) async {
    if (clip.settled || clip.state == Clip.uploading) return;

    final problem = await widget.vault.sendFirst(clip);
    if (mounted && problem != null) _say(problem);
  }

  void _say(String text) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(backgroundColor: Tally.navyPrimary, content: Text(text, style: Tally.body(12.5))),
    );
  }

  void _play(Clip clip) {
    if (!File(clip.path).existsSync()) {
      _say('That file is no longer on this phone.');

      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => ClipPlayer(clip: clip, vault: widget.vault)),
    );
  }
}

/// One bout as a row of the list — every row the same height, so the list
/// reads as a table: the bout number and length, the round and weight class,
/// the two corners with their scores (the winner lit), and the two things a
/// bout can be given away as: UPLOAD (to the event's bout gallery, for
/// everyone) and SEND (a video with the bar burned in, to one person).
///
/// A long name is never cut and never wraps (a wrapped row would be taller
/// than its neighbours): it is set a little smaller until it fits its line.
class _BoutRow extends StatelessWidget {
  const _BoutRow({
    required this.clip,
    required this.onPlay,
    required this.onUpload,
    required this.onSend,
    this.picking = false,
    this.picked = false,
    this.job,
    this.queued = false,
  });

  final Clip clip;

  /// Choosing bouts for a queue: a tick instead of the two buttons.
  final bool picking;
  final bool picked;

  /// Its newest place in the video queue, and whether it waits to upload.
  final MakeJob? job;
  final bool queued;
  final VoidCallback onPlay;
  final VoidCallback onUpload;
  final VoidCallback onSend;

  static const _blue = Color(0xFF3D7BE0);
  static const _purple = Color(0xFFA993FF);

  bool get _ex => clip.number?.toLowerCase().startsWith('ex') ?? false;

  @override
  Widget build(BuildContext context) {
    final label = _ex ? 'EX ${clip.number!.substring(2)}' : (clip.number ?? (clip.bout > 0 ? '${clip.bout}' : '—'));
    final stage = _ex ? 'Exhibition' : (clip.stage ?? '');
    final division = clip.division ?? '';

    return InkWell(
      onTap: onPlay,
      child: Container(
        height: 66,
        padding: const EdgeInsets.symmetric(horizontal: 4),
        decoration: BoxDecoration(border: Border(bottom: BorderSide(color: Colors.white.withValues(alpha: 0.055)))),
        child: Row(
          children: [
            // Bout number and length.
            Container(
              width: 44,
              height: 46,
              decoration: BoxDecoration(
                color: _ex ? Colors.white.withValues(alpha: 0.05) : Tally.gold.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: _ex ? Colors.white.withValues(alpha: 0.14) : Tally.gold.withValues(alpha: 0.3)),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(label, style: Tally.label(_ex ? 13 : 17, color: _ex ? Tally.textSecondary : Tally.goldLight, tracking: 0, weight: FontWeight.w700)),
                  ),
                  Text(clip.lengthLabel, style: Tally.label(10, color: Tally.textMuted, tracking: 0, weight: FontWeight.w600)),
                ],
              ),
            ),
            const SizedBox(width: 10),

            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _fit(Text.rich(
                    TextSpan(children: [
                      TextSpan(text: stage),
                      if (division.isNotEmpty) TextSpan(text: '${stage.isEmpty ? '' : ' · '}$division', style: const TextStyle(color: _purple)),
                    ]),
                    style: Tally.body(10, color: Tally.textMuted),
                  )),
                  const SizedBox(height: 3),
                  _corner(clip.red, clip.redScore, Tally.red, clip.winner == 'red'),
                  const SizedBox(height: 2),
                  _corner(clip.blue, clip.blueScore, _blue, clip.winner == 'blue'),
                ],
              ),
            ),
            const SizedBox(width: 8),

            if (job != null) ...[_makeBadge(job!), const SizedBox(width: 6)],
            if (picking)
              Container(
                width: 26,
                height: 26,
                margin: const EdgeInsets.symmetric(horizontal: 8),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: picked ? Tally.gold : Colors.transparent,
                  border: Border.all(color: picked ? Tally.gold : Colors.white.withValues(alpha: 0.35), width: 2),
                ),
                child: picked ? const Icon(Icons.check, size: 16, color: Tally.navyDeep) : null,
              )
            else ...[
              _uploadButton(),
              const SizedBox(width: 6),
              _ActionButton(icon: Icons.send_outlined, colour: Tally.gold, onTap: onSend, tooltip: 'Send as a video'),
            ],
          ],
        ),
      ),
    );
  }

  Widget _fit(Widget child) => SizedBox(
        width: double.infinity,
        child: FittedBox(fit: BoxFit.scaleDown, alignment: AlignmentDirectional.centerStart, child: child),
      );

  Widget _corner(String? name, String? score, Color colour, bool won) {
    final shown = (name == null || name.isEmpty)
        ? (clip.recovered && !clip.looked ? 'Looking this bout up…' : '—')
        : name.toUpperCase();

    return Row(
      children: [
        Container(width: 2.5, height: 14, color: colour),
        const SizedBox(width: 6),
        Expanded(
          child: _fit(Text(
            shown,
            maxLines: 1,
            softWrap: false,
            style: Tally.body(11.5, color: won ? Tally.text : Tally.textSecondary, weight: FontWeight.w600),
          )),
        ),
        const SizedBox(width: 8),
        SizedBox(
          width: 18,
          child: Text(
            score ?? '',
            textAlign: TextAlign.right,
            style: Tally.label(14, color: won ? Tally.goldLight : Tally.textMuted, tracking: 0, weight: FontWeight.w700),
          ),
        ),
      ],
    );
  }

  /// Where this bout's video stands in the queue: a ring while it is made,
  /// a film tick once it is in the gallery (made now, or unchanged).
  Widget _makeBadge(MakeJob j) {
    final (IconData icon, Color colour) = switch (j.state) {
      'made' || 'unchanged' => (Icons.video_file_outlined, Tally.green),
      'failed' || 'skipped' => (Icons.videocam_off_outlined, Tally.textFaint),
      _ => (Icons.movie_creation_outlined, Tally.goldLight),
    };

    if (j.state == 'making') {
      return SizedBox(
        width: 26,
        height: 26,
        child: CircularProgressIndicator(value: j.progress == 0 ? null : j.progress, strokeWidth: 2, color: colour),
      );
    }

    return Tooltip(message: j.note ?? j.state, child: Icon(icon, size: 19, color: colour));
  }

  /// UPLOAD — to the event's bout gallery. A tick once it is there, the
  /// percentage while it goes up, dimmed for a clip the paired event does not
  /// recognise (it is never put into another competition's gallery).
  Widget _uploadButton() {
    const light = Color(0xFF8FB8FF);

    if (clip.settled) {
      return _ActionButton(icon: Icons.cloud_done_outlined, colour: Tally.green, onTap: () {}, tooltip: 'In the event gallery');
    }

    if (clip.state == Clip.uploading) {
      return SizedBox(
        width: 34,
        height: 34,
        child: Stack(
          alignment: Alignment.center,
          children: [
            CircularProgressIndicator(value: clip.fraction, strokeWidth: 2, color: light, backgroundColor: light.withValues(alpha: 0.15)),
            Text((clip.fraction * 100).toStringAsFixed(0), style: Tally.label(9.5, color: light, tracking: 0, weight: FontWeight.w700)),
          ],
        ),
      );
    }

    if (queued) {
      return _ActionButton(icon: Icons.schedule, colour: light, onTap: () {}, tooltip: 'Waiting its turn to upload');
    }

    return Opacity(
      opacity: clip.held ? 0.35 : 1,
      child: _ActionButton(icon: Icons.cloud_upload_outlined, colour: light, onTap: clip.held ? () {} : onUpload, tooltip: 'Upload to the event gallery'),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.colour, required this.onTap, required this.tooltip});

  final IconData icon;
  final Color colour;
  final VoidCallback onTap;
  final String tooltip;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: GestureDetector(
        onTap: onTap,
        behavior: HitTestBehavior.opaque,
        child: Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: colour.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: colour.withValues(alpha: 0.38)),
          ),
          child: Icon(icon, size: 18, color: colour),
        ),
      ),
    );
  }
}

/// Watching one back, without leaving the camera.
///
/// Deliberately plain: play, scrub, the clock, and the bout's identity across
/// the top so somebody checking three clips in a row knows which one they are
/// looking at. No editing, no sharing, no deleting — this app films and files;
/// everything else a bout needs happens on the platform, where it is
/// authorised.
class ClipPlayer extends StatefulWidget {
  const ClipPlayer({super.key, required this.clip, required this.vault});

  final Clip clip;
  final ClipVault vault;

  @override
  State<ClipPlayer> createState() => _ClipPlayerState();
}

class _ClipPlayerState extends State<ClipPlayer> {
  VideoPlayerController? _controller;
  String? _fault;

  static const _native = MethodChannel('bh.takeone.lab/share');

  /// The score bar laid over the playing video — painted on the phone by the
  /// same code that burns it into a sent video (ScoreBar.kt), from the event's
  /// plan. Null until it arrives, and for a clip the event has no bout for.
  Map<String, dynamic>? _plan;
  String _planJson = '';
  Uint8List? _bar;
  Timer? _barTimer;
  bool _painting = false;
  bool _showSwitches = false;
  int _paintedAt = -1;

  /// The VS screen, shown before the bout plays (and burned in as the sent
  /// video's opening) — the bout page's intro, drawn by VsScreen.kt.
  /// The VS screen in its parts, animated by VsIntro; null when not showing.
  List<VsLayer>? _vs;
  int _vsLeft = 0;
  Timer? _vsTimer;

  /// The VS music (assets/audio/vs_intro.m4a): under the intro, its impact at
  /// 4 s where the bout begins, then fading away in the file itself — the same
  /// track, at the same moment, as a sent video carries.
  VideoPlayerController? _music;
  Timer? _musicTimer;

  Future<void> _startMusic() async {
    if (!widget.vault.parts.contains('music')) return;

    try {
      final own = widget.vault.musicPath;
      final m = own != null ? VideoPlayerController.file(File(own)) : VideoPlayerController.asset('assets/audio/vs_intro.m4a');
      await m.initialize();
      if (!mounted) {
        await m.dispose();
        return;
      }
      _music = m;
      await m.play();
    } catch (_) {
      // Silent intro; nothing else changes.
    }
  }

  /// The bout is starting: the music jumps to its impact (if the intro was
  /// skipped early) and is let go once it has faded.
  void _releaseMusic() {
    final m = _music;
    if (m == null) return;

    if (m.value.position < const Duration(seconds: 4)) unawaited(m.seekTo(const Duration(seconds: 4)));
    _musicTimer?.cancel();
    _musicTimer = Timer(const Duration(milliseconds: 3400), () {
      _music = null;
      unawaited(m.dispose());
    });
  }

  Future<void> _intro() async {
    if (!widget.vault.parts.contains('intro') || !widget.vault.showOverlay || _plan == null) return;

    try {
      // Upright video, upright VS screen (VsScreen.portrait).
      final upright = (_controller?.value.aspectRatio ?? 16 / 9) < 1;
      final raw = await _native.invokeMethod<List<dynamic>>('vsLayers', {
        'plan': _planJson,
        'width': upright ? 720 : 1280,
        'height': upright ? 1280 : 720,
        'vsLift': widget.vault.vsLift,
      });
      if (raw == null || !mounted) return;
      final png = VsLayer.fromNative(raw);
      if (png.isEmpty) return;

      await _controller?.pause();
      setState(() {
        _vs = png;
        _vsLeft = 4; // the music's impact lands as the bout starts
      });
      unawaited(_startMusic());

      _vsTimer?.cancel();
      _vsTimer = Timer.periodic(const Duration(seconds: 1), (t) {
        if (!mounted) return t.cancel();
        if (_vsLeft <= 1) {
          t.cancel();
          _endIntro();
        } else {
          setState(() => _vsLeft -= 1);
        }
      });
    } catch (_) {
      // No intro; the bout plays as it is.
    }
  }

  /// The statistics card (StatsCard.kt, from the plan's `stats`): shown when
  /// the footage ends — after the winner is declared — exactly as a sent
  /// video closes on it. Null while the bout plays.
  Uint8List? _stats;
  bool _statsAsked = false;

  void _checkEnd() {
    final c = _controller;
    if (c == null || !mounted) return;
    final v = c.value;
    final atEnd = v.duration > Duration.zero && !v.isPlaying && v.position >= v.duration - const Duration(milliseconds: 250);

    if (!atEnd) {
      // Played again or scrubbed back: the card steps aside.
      if (_stats != null) setState(() => _stats = null);
      _statsAsked = false;
      return;
    }

    if (_statsAsked || _plan == null || !widget.vault.showOverlay || !widget.vault.parts.contains('stats')) return;
    _statsAsked = true;
    unawaited(_showStats());
  }

  Future<void> _showStats() async {
    try {
      final upright = (_controller?.value.aspectRatio ?? 16 / 9) < 1;
      final png = await _native.invokeMethod<Uint8List>('statsCard', {
        'plan': _planJson,
        'width': upright ? 720 : 1280,
        'height': upright ? 1280 : 720,
      });
      if (png != null && mounted && _statsAsked) setState(() => _stats = png);
    } catch (_) {
      // No card; the last frame stays.
    }
  }

  void _endIntro() {
    _vsTimer?.cancel();
    if (!mounted) return;
    setState(() => _vs = null);
    _releaseMusic();
    unawaited(_controller?.play());
  }

  /// Full screen: the phone turned on its side, the video and its bar filling
  /// it, the controls hidden until a tap brings them back for a moment.
  bool _full = false;
  bool _chrome = true;
  Timer? _chromeTimer;

  Future<void> _setFull(bool full) async {
    setState(() {
      _full = full;
      _chrome = true;
    });

    await SystemChrome.setPreferredOrientations(full
        ? const [DeviceOrientation.landscapeLeft, DeviceOrientation.landscapeRight]
        : const []);
    _hideChromeSoon();
  }

  void _hideChromeSoon() {
    _chromeTimer?.cancel();
    if (!_full) return;
    _chromeTimer = Timer(const Duration(seconds: 3), () {
      if (mounted && _full && (_controller?.value.isPlaying ?? false)) setState(() => _chrome = false);
    });
  }

  /// The size the preview is painted at: plenty for a phone screen, a fraction
  /// of the cost of painting at full video resolution.
  static const _previewWidth = 1280;

  @override
  void initState() {
    super.initState();
    _open();
  }

  Future<void> _open() async {
    final controller = VideoPlayerController.file(File(widget.clip.path));

    try {
      await controller.initialize();
    } catch (e) {
      // A clip whose muxer never finished has no moov atom and will not open.
      // Say so plainly — it is the one failure this screen exists to reveal.
      if (mounted) setState(() => _fault = 'THIS FILE WILL NOT OPEN');

      return;
    }

    controller.addListener(() {
      if (mounted) setState(() {});
      _checkEnd();
    });

    if (!mounted) {
      await controller.dispose();

      return;
    }

    setState(() => _controller = controller);

    // The plan first (briefly), so a bout with a VS screen opens on it rather
    // than on a second of fight that then freezes behind it.
    await _loadPlan(controller.value.duration.inSeconds).timeout(const Duration(seconds: 4), onTimeout: () {});
    if (!mounted) return;

    if (_plan != null && widget.vault.parts.contains('intro') && widget.vault.showOverlay) {
      await _intro();
      if (_vs != null) return;
    }

    await controller.play();
  }

  Future<void> _loadPlan(int seconds) async {
    final plan = await widget.vault.planFor(widget.clip, seconds: seconds);

    if (!mounted || plan == null) return;

    setState(() {
      _plan = plan;
      _planJson = jsonEncode(plan);
    });

    _barTimer?.cancel();
    _barTimer = Timer.periodic(const Duration(milliseconds: 200), (_) => _paint());
    await _paint(force: true);
  }

  /// Ask for the bar at the video's current moment. Only a change comes back.
  Future<void> _paint({bool force = false}) async {
    final c = _controller;

    if (_painting || c == null || _plan == null || !widget.vault.showOverlay) return;
    // Paused and not moved: the picture on screen is still right.
    final at = c.value.position.inMilliseconds;
    if (!force && !c.value.isPlaying && _bar != null && at == _paintedAt) return;
    _paintedAt = at;

    _painting = true;

    try {
      final aspect = c.value.aspectRatio <= 0 ? 16 / 9 : c.value.aspectRatio;
      final png = await _native.invokeMethod<Uint8List>('preview', {
        'plan': _planJson,
        'key': widget.clip.ref,
        'width': _previewWidth,
        'height': (_previewWidth / aspect).round(),
        'at': c.value.position.inMilliseconds / 1000.0,
        'parts': widget.vault.parts.toList(),
        'lift': widget.vault.lift,
      });

      if (png != null && mounted) setState(() => _bar = png);
    } catch (_) {
      // A preview that cannot be painted leaves the plain video, nothing worse.
    } finally {
      _painting = false;
    }
  }

  @override
  void dispose() {
    _barTimer?.cancel();
    _chromeTimer?.cancel();
    _vsTimer?.cancel();
    _musicTimer?.cancel();
    _music?.dispose();
    if (_full) unawaited(SystemChrome.setPreferredOrientations(const []));
    _controller?.dispose();
    super.dispose();
  }

  /// The video with the bar over it, at the video's own shape.
  Widget _picture(VideoPlayerController c) => AspectRatio(
        aspectRatio: c.value.aspectRatio,
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Leveled as it was filmed (Clip.tilt): turned, and grown just
            // enough to hide the corners. The bar is not turned.
            ClipRect(
              child: Transform.rotate(
                angle: widget.clip.tilt * math.pi / 180,
                child: Transform.scale(
                  scale: Clip.fillFor(widget.clip.tilt, c.value.aspectRatio),
                  child: VideoPlayer(c),
                ),
              ),
            ),
            if (_bar != null && widget.vault.showOverlay && _vs == null)
              IgnorePointer(
                child: Image.memory(_bar!, fit: BoxFit.fill, gaplessPlayback: true),
              ),
            if (_stats != null && _vs == null) ...[
              // The bout's statistics, as a sent video closes on them.
              const ColoredBox(color: Color(0xFF0F1730)),
              Image.memory(_stats!, fit: BoxFit.contain, gaplessPlayback: true),
              Positioned(
                top: 10,
                right: 10,
                child: GestureDetector(
                  onTap: () => setState(() => _stats = null),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: const Color(0xB80A0A0E),
                      border: Border.all(color: Colors.white.withValues(alpha: 0.28)),
                    ),
                    child: Text('CLOSE', style: Tally.label(11, color: Colors.white, tracking: 0.22, weight: FontWeight.w700)),
                  ),
                ),
              ),
            ],
            if (_vs != null) ...[
              // Animated as the bout page's intro is, and as a sent video's.
              VsIntro(layers: _vs!),
              Positioned(
                top: 10,
                right: 10,
                child: GestureDetector(
                  onTap: _endIntro,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: const Color(0xB80A0A0E),
                      border: Border.all(color: Colors.white.withValues(alpha: 0.28)),
                    ),
                    child: Text('SKIP  $_vsLeft', style: Tally.label(11, color: Colors.white, tracking: 0.22, weight: FontWeight.w700)),
                  ),
                ),
              ),
            ],
          ],
        ),
      );

  Widget _fullScreen(VideoPlayerController c) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) unawaited(_setFull(false));
      },
      child: Scaffold(
        backgroundColor: Colors.black,
        body: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () {
            setState(() => _chrome = !_chrome);
            _hideChromeSoon();
          },
          child: Stack(
            children: [
              Center(child: _picture(c)),
              if (_chrome) ...[
                Positioned(
                  top: 12,
                  left: 16,
                  right: 16,
                  child: Row(
                    children: [
                      _RoundButton(icon: Icons.fullscreen_exit, onTap: () => _setFull(false)),
                      const Spacer(),
                      if (_plan != null)
                        _RoundButton(
                          icon: widget.vault.showOverlay ? Icons.scoreboard : Icons.scoreboard_outlined,
                          filled: widget.vault.showOverlay,
                          onTap: () {
                            setState(() => widget.vault.showOverlay = !widget.vault.showOverlay);
                            unawaited(_paint(force: true));
                            _hideChromeSoon();
                          },
                        ),
                    ],
                  ),
                ),
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: Container(
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.bottomCenter,
                        end: Alignment.topCenter,
                        colors: [Color(0xCC000000), Color(0x00000000)],
                      ),
                    ),
                    child: _controls(c),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = _controller;
    final ready = c != null && c.value.isInitialized;

    if (ready && _full) return _fullScreen(c);

    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 10),
              child: Row(
                children: [
                  _RoundButton(icon: Icons.arrow_back, onTap: () => Navigator.of(context).maybePop()),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(widget.clip.title, style: Tally.number(15, weight: FontWeight.w700)),
                        const SizedBox(height: 2),
                        Text(
                          widget.clip.corners,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Tally.body(11.5, color: Tally.textFaint),
                        ),
                      ],
                    ),
                  ),
                  // The score bar: on/off, and which of its parts.
                  if (_plan != null) ...[
                    _RoundButton(
                      icon: widget.vault.showOverlay ? Icons.scoreboard : Icons.scoreboard_outlined,
                      filled: widget.vault.showOverlay,
                      onTap: () {
                        setState(() => widget.vault.showOverlay = !widget.vault.showOverlay);
                        unawaited(_paint(force: true));
                      },
                    ),
                    const SizedBox(width: 8),
                    _RoundButton(
                      icon: Icons.tune,
                      onTap: () => setState(() => _showSwitches = !_showSwitches),
                    ),
                  ],
                ],
              ),
            ),
            if (_plan != null && _showSwitches) _switches(),
            Expanded(
              child: Center(
                child: _fault != null
                    ? Text(_fault!, style: Tally.label(12, color: Tally.amberText, tracking: 0.2))
                    : ready
                        ? GestureDetector(
                            onTap: () => c.value.isPlaying ? c.pause() : c.play(),
                            child: _picture(c),
                          )
                        : const SizedBox(
                            width: 26,
                            height: 26,
                            child: CircularProgressIndicator(strokeWidth: 2.5, color: Tally.gold),
                          ),
              ),
            ),
            if (ready) _controls(c),
          ],
        ),
      ),
    );
  }

  Widget _controls(VideoPlayerController c) {
    final position = c.value.position;
    final total = c.value.duration;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      child: Column(
        children: [
          SliderTheme(
            data: SliderThemeData(
              trackHeight: 3,
              activeTrackColor: Tally.gold,
              inactiveTrackColor: Colors.white.withValues(alpha: 0.16),
              thumbColor: Tally.goldLight,
              thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6),
              overlayShape: const RoundSliderOverlayShape(overlayRadius: 14),
            ),
            child: Slider(
              value: position.inMilliseconds
                  .clamp(0, total.inMilliseconds == 0 ? 1 : total.inMilliseconds)
                  .toDouble(),
              max: (total.inMilliseconds == 0 ? 1 : total.inMilliseconds).toDouble(),
              onChanged: (v) => c.seekTo(Duration(milliseconds: v.round())),
            ),
          ),
          Row(
            children: [
              Text(_clock(position), style: Tally.number(12, color: Tally.textSecondary)),
              const Spacer(),
              _RoundButton(
                icon: Icons.replay_10,
                onTap: () => c.seekTo(position - const Duration(seconds: 10)),
              ),
              const SizedBox(width: 10),
              _RoundButton(
                icon: c.value.isPlaying ? Icons.pause : Icons.play_arrow_rounded,
                filled: true,
                onTap: () => c.value.isPlaying ? c.pause() : c.play(),
              ),
              const SizedBox(width: 10),
              _RoundButton(
                icon: Icons.forward_10,
                onTap: () => c.seekTo(position + const Duration(seconds: 10)),
              ),
              const Spacer(),
              Text(_clock(total), style: Tally.number(12, color: Tally.textFaint)),
              const SizedBox(width: 10),
              _RoundButton(
                icon: _full ? Icons.fullscreen_exit : Icons.fullscreen,
                onTap: () => _setFull(!_full),
              ),
            ],
          ),
        ],
      ),
    );
  }

  static String _clock(Duration d) {
    String two(int n) => n.toString().padLeft(2, '0');

    return '${two(d.inMinutes)}:${two(d.inSeconds % 60)}';
  }

  /// One switch per part of the bar (shared with the send sheet).
  Widget _switches() => Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
        child: BarParts(vault: widget.vault, onChanged: () {
          unawaited(_paint(force: true));
          if (mounted) setState(() {});
        }),
      );
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({required this.icon, required this.onTap, this.filled = false});

  final IconData icon;
  final VoidCallback onTap;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: filled ? 48 : 40,
        height: filled ? 48 : 40,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: filled ? Tally.gold.withValues(alpha: 0.18) : Colors.white.withValues(alpha: 0.08),
          border: Border.all(
            color: filled ? Tally.gold.withValues(alpha: 0.5) : Colors.white.withValues(alpha: 0.14),
          ),
        ),
        child: Icon(icon, color: filled ? Tally.gold : Tally.textSecondary, size: filled ? 26 : 20),
      ),
    );
  }
}
