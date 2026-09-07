import 'package:flutter/material.dart';

import '../config.dart';
import 'clips.dart';
import 'kit.dart';

/// What this phone filmed today, and the only place a clip can be removed.
///
/// A drawer rather than a screen: the preview stays live behind it, because the
/// question being answered ("did bout 1-04 record?") is asked while the next
/// bout is walking on. It closes itself the moment the mat starts a bout —
/// nobody should be reading a list when they should be aiming.
///
/// Delete is deliberately awkward. It is the one irreversible action on a
/// device standing next to a live competition: it is never a top-level row
/// button, the confirmation says what will be freed and what else it removes,
/// and the final press is a hold, not a tap.
class ClipDrawer extends StatefulWidget {
  const ClipDrawer({
    super.key,
    required this.clips,
    required this.onClose,
    required this.onPlay,
    required this.onSave,
    required this.onUpload,
    required this.onDelete,
    required this.autoUpload,
    required this.onAutoUpload,
    this.startInSelect = false,
    this.saving,
  });

  final List<CameraClip> clips;
  final VoidCallback onClose;
  final void Function(CameraClip clip) onPlay;
  final void Function(CameraClip clip) onSave;

  /// Upload this clip to TAKEONE. The server attaches the bout.
  final void Function(CameraClip clip) onUpload;

  /// Given every clip the volunteer chose. One call, so the app can delete a
  /// selection as one act and report once.
  final Future<void> Function(List<CameraClip> clips) onDelete;

  /// Does a finished bout go up by itself? Off by default — see
  /// `_CameraStationState._autoUpload`.
  final bool autoUpload;

  /// The operator flipping that switch.
  final void Function(bool on) onAutoUpload;

  /// Opened straight into multi-select — how "free up space" arrives from the
  /// storage-low banner.
  final bool startInSelect;

  /// The clip currently being handed to the gallery, if any.
  final String? saving;

  @override
  State<ClipDrawer> createState() => _ClipDrawerState();
}

class _ClipDrawerState extends State<ClipDrawer> {
  late bool _selecting = widget.startInSelect;
  final Set<String> _selected = {};
  bool _deleting = false;

  List<CameraClip> get _chosen => widget.clips.where((c) => _selected.contains(c.file)).toList();

  int get _chosenBytes => _chosen.fold(0, (sum, c) => sum + (c.bytes ?? 0));

  /// A size in the unit that actually says something.
  ///
  /// This was always GB to one decimal, so every clip a camera realistically
  /// records — a three-minute bout is tens of megabytes — displayed as "0.0 GB".
  /// A screen full of recordings all claiming to be empty is how you end up
  /// hunting a storage bug that was never there.
  static String _size(int bytes) {
    if (bytes >= 1073741824) return '${(bytes / 1073741824).toStringAsFixed(1)} GB';
    if (bytes >= 1048576) return '${(bytes / 1048576).round()} MB';
    if (bytes >= 1024) return '${(bytes / 1024).round()} KB';

    // Worth saying plainly: a clip with no bytes is a recording that failed,
    // not a small one.
    return bytes == 0 ? 'EMPTY' : '$bytes B';
  }

  @override
  Widget build(BuildContext context) {
    final total = widget.clips.fold<int>(0, (sum, c) => sum + (c.bytes ?? 0));

    return Container(
      width: 340,
      decoration: BoxDecoration(
        color: Cam.panel,
        border: Border(left: BorderSide(color: Cam.paper.withValues(alpha: 0.12))),
      ),
      child: SafeArea(
        left: false,
        child: Column(
          children: [
            _header(total),
            if (!_selecting) _autoUploadRow(),
            if (!_selecting) _retentionOffer(),
            Expanded(
              child: widget.clips.isEmpty
                  ? Center(child: Text('NOTHING RECORDED YET', style: Cam.cap(13, color: Cam.paper.withValues(alpha: 0.35))))
                  : ListView.separated(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      itemCount: widget.clips.length,
                      separatorBuilder: (_, __) => Divider(height: 1, color: Cam.paper.withValues(alpha: 0.07)),
                      itemBuilder: (_, i) => _row(widget.clips[i]),
                    ),
            ),
            if (_selecting) _deleteBar(),
          ],
        ),
      ),
    );
  }

  /// The one setting that decides what this camera does with a finished bout.
  ///
  /// It lives here rather than behind a menu because this is the screen where
  /// the question is asked: the operator is looking at a list of clips and what
  /// became of each of them. Off means the footage stays on the phone until
  /// somebody sends it — every row still has its own UPLOAD button.
  Widget _autoUploadRow() {
    final on = widget.autoUpload;

    return InkWell(
      onTap: () => widget.onAutoUpload(!on),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          border: Border(bottom: BorderSide(color: Cam.paper.withValues(alpha: 0.07))),
        ),
        child: Row(
          children: [
            Icon(
              on ? Icons.cloud_done_outlined : Icons.cloud_off_outlined,
              size: 16,
              color: on ? Cam.live : Cam.paper.withValues(alpha: 0.45),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'AUTO UPLOAD',
                    style: Cam.cap(12, color: on ? Cam.live : Cam.paper.withValues(alpha: 0.75)),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    on
                        ? 'Each bout is sent as soon as it ends'
                        : 'Bouts stay on this phone until you send them',
                    style: Cam.body(11, color: Cam.paper.withValues(alpha: 0.45)),
                  ),
                ],
              ),
            ),
            Switch(
              value: on,
              onChanged: widget.onAutoUpload,
              activeThumbColor: Cam.ink,
              activeTrackColor: Cam.live,
              inactiveThumbColor: Cam.paper.withValues(alpha: 0.7),
              inactiveTrackColor: Cam.paper.withValues(alpha: 0.12),
            ),
          ],
        ),
      ),
    );
  }

  /// Offers to clear footage that is already safely on the server.
  ///
  /// An OFFER, never a sweep. The operator decides: this pre-selects the
  /// eligible clips and hands them the normal delete bar, so nothing is removed
  /// without the same confirmation any other deletion gets.
  ///
  /// Clips that were never uploaded are not counted and cannot be selected this
  /// way — see CameraClip.isExpendable. The footage of a bout that was fought
  /// once exists in one place until somebody sends it.
  Widget _retentionOffer() {
    final stale = widget.clips.where((c) => c.isExpendable).toList();

    if (stale.isEmpty) return const SizedBox.shrink();

    final bytes = stale.fold<int>(0, (sum, c) => sum + (c.bytes ?? 0));
    final days = CameraClip.keepFor.inDays;

    return InkWell(
      onTap: () => setState(() {
        _selecting = true;
        _selected
          ..clear()
          ..addAll(stale.map((c) => c.file));
      }),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        color: Cam.paper.withValues(alpha: 0.06),
        child: Row(
          children: [
            Icon(Icons.auto_delete_outlined, size: 16, color: Cam.paper.withValues(alpha: 0.55)),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                '${stale.length} CLIP${stale.length == 1 ? '' : 'S'} UPLOADED OVER $days DAYS AGO'
                '${bytes > 0 ? ' · ${_size(bytes)}' : ''}',
                style: Cam.cap(11, color: Cam.paper.withValues(alpha: 0.55)),
              ),
            ),
            Text('FREE UP', style: Cam.cap(11, color: Cam.live)),
          ],
        ),
      ),
    );
  }

  Widget _header(int total) {
    if (_selecting) {
      return Container(
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        color: Cam.gold.withValues(alpha: 0.12),
        child: Row(
          children: [
            Expanded(
              child: Text(
                '${_selected.length} SELECTED · ${_size(_chosenBytes)}',
                style: Cam.cap(14, color: Cam.gold),
              ),
            ),
            GestureDetector(
              onTap: () => setState(() {
                _selecting = false;
                _selected.clear();
              }),
              behavior: HitTestBehavior.opaque,
              child: SizedBox(
                height: 48,
                child: Center(child: Text('CANCEL', style: Cam.cap(13, color: Cam.paper.withValues(alpha: 0.7)))),
              ),
            ),
          ],
        ),
      );
    }

    return Container(
      height: 52,
      padding: const EdgeInsets.only(left: 14),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: Cam.paper.withValues(alpha: 0.08))),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              'TODAY · ${widget.clips.length} ${widget.clips.length == 1 ? 'CLIP' : 'CLIPS'} · ${_size(total)}',
              style: Cam.cap(14, color: Cam.paper.withValues(alpha: 0.85)),
            ),
          ),
          if (widget.clips.isNotEmpty)
            GestureDetector(
              onTap: () => setState(() => _selecting = true),
              behavior: HitTestBehavior.opaque,
              child: SizedBox(
                height: 48,
                child: Center(child: Text('SELECT', style: Cam.cap(13, color: Cam.gold))),
              ),
            ),
          GestureDetector(
            onTap: widget.onClose,
            behavior: HitTestBehavior.opaque,
            child: const SizedBox(
              width: 48,
              height: 48,
              child: Icon(Icons.close, size: 20, color: Cam.paper),
            ),
          ),
        ],
      ),
    );
  }

  Widget _row(CameraClip clip) {
    final chosen = _selected.contains(clip.file);
    final saved = clip.uri != null;
    final minutes = clip.length.inMinutes.toString().padLeft(2, '0');
    final seconds = (clip.length.inSeconds % 60).toString().padLeft(2, '0');
    final size = clip.bytes != null ? ' · ${_size(clip.bytes!)}' : '';

    return GestureDetector(
      onTap: () {
        if (!_selecting) {
          widget.onPlay(clip);

          return;
        }

        setState(() => chosen ? _selected.remove(clip.file) : _selected.add(clip.file));
      },
      // Long-press is the second way into multi-select, for a volunteer who did
      // not notice SELECT.
      onLongPress: () => setState(() {
        _selecting = true;
        _selected.add(clip.file);
      }),
      behavior: HitTestBehavior.opaque,
      child: Container(
        height: 64,
        color: chosen ? Cam.gold.withValues(alpha: 0.08) : Colors.transparent,
        padding: const EdgeInsets.only(left: 12),
        child: Row(
          children: [
            SizedBox(
              width: 38,
              child: Text(
                clip.matchNumber ?? '—',
                style: Cam.num(14, color: Cam.gold),
              ),
            ),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    clip.subtitle.toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Cam.cap(13, color: Cam.paper.withValues(alpha: 0.9), tracking: 0.06),
                  ),
                  const SizedBox(height: 3),
                  // One line, spans rather than a Row: a 340dp drawer cannot
                  // fit three separate boxes at their natural width, and the
                  // first build of this overflowed by 137px on exactly the
                  // rows that matter (a long pair of names). As spans it
                  // ellipsizes instead of clipping.
                  Text.rich(
                    TextSpan(
                      style: Cam.cap(11, color: Cam.paper.withValues(alpha: 0.45), tracking: 0.06),
                      children: [
                        TextSpan(text: '$minutes:$seconds$size · '),
                        // Saved is green, not-saved is amber: the volunteer's
                        // cue that a clip is not yet where a coach can find it.
                        TextSpan(
                          text: saved ? 'IN GALLERY' : 'NOT SAVED',
                          style: Cam.cap(11, color: saved ? Cam.live : Cam.gold, tracking: 0.06),
                        ),
                        // …and whether the footage has reached the platform.
                        if (clip.playVideoKey != null)
                          TextSpan(
                            text: ' · UPLOADED',
                            style: Cam.cap(11, color: Cam.live, tracking: 0.06),
                          )
                        else if (clip.playStatus == 'uploading')
                          TextSpan(
                            text: ' · UPLOADING ${(clip.uploadProgress * 100).round()}%',
                            style: Cam.cap(11, color: Cam.gold, tracking: 0.06),
                          )
                        else if (clip.playStatus == 'failed')
                          TextSpan(
                            text: ' · UPLOAD FAILED',
                            style: Cam.cap(11, color: Cam.rec, tracking: 0.06),
                          ),
                        if (saved)
                          const WidgetSpan(
                            alignment: PlaceholderAlignment.middle,
                            child: Padding(
                              padding: EdgeInsets.only(left: 3),
                              child: Icon(Icons.check, size: 12, color: Cam.live),
                            ),
                          ),
                      ],
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            if (_selecting)
              Padding(
                padding: const EdgeInsets.only(right: 14),
                child: Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: chosen ? Cam.gold : Colors.transparent,
                    borderRadius: BorderRadius.circular(5),
                    border: Border.all(color: chosen ? Cam.gold : Cam.paper.withValues(alpha: 0.35), width: 1.5),
                  ),
                  child: chosen ? const Icon(Icons.check, size: 15, color: Cam.ink) : null,
                ),
              )
            else ...[
              _action(Icons.play_arrow, () => widget.onPlay(clip)),
              if (!saved)
                _action(
                  widget.saving == clip.file ? Icons.hourglass_top : Icons.save_alt,
                  () => widget.onSave(clip),
                  color: Cam.gold,
                ),
              _action(Icons.more_vert, () => _menu(clip)),
            ],
          ],
        ),
      ),
    );
  }

  Widget _action(IconData icon, VoidCallback onTap, {Color? color}) => GestureDetector(
        onTap: onTap,
        behavior: HitTestBehavior.opaque,
        child: SizedBox(
          width: 48,
          height: 48,
          child: Icon(icon, size: 20, color: color ?? Cam.paper.withValues(alpha: 0.75)),
        ),
      );

  /// One clip's own menu. Delete lives HERE and not on the row, so it cannot be
  /// hit by a thumb reaching for play.
  void _menu(CameraClip clip) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Cam.panel,
      builder: (sheet) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(clip.title.toUpperCase(), style: Cam.cap(14)),
              subtitle: Text(clip.subtitle, style: Cam.body(12)),
            ),
            if (clip.uri == null)
              ListTile(
                leading: const Icon(Icons.save_alt, color: Cam.gold),
                title: Text('SAVE TO GALLERY', style: Cam.cap(14, color: Cam.gold)),
                onTap: () {
                  Navigator.of(sheet).pop();
                  widget.onSave(clip);
                },
              ),
            // The bout goes to the video platform WITH the bout: the server
            // attaches competitors, clubs, corners, officials, division, result
            // and the officiating timeline once the file lands. Absent once it
            // is there, because sending a second copy of a bout is never what
            // somebody meant.
            if (clip.playVideoKey == null)
              ListTile(
                leading: Icon(
                  clip.playStatus == 'uploading' ? Icons.stop_circle_outlined : Icons.cloud_upload_outlined,
                  color: Cam.live,
                ),
                title: Text(
                  clip.playStatus == 'uploading'
                      ? 'STOP UPLOAD · ${(clip.uploadProgress * 100).round()}%'
                      : 'UPLOAD TO TAKEONE',
                  style: Cam.cap(14, color: Cam.live),
                ),
                subtitle: Text(
                  clip.matchNumber != null
                      ? 'Sends the video and the bout — competitors, clubs, result and '
                          'the timeline — to ${Config.base.host}.'
                      : 'Not attached to a bout: uploads as footage only, to '
                          '${Config.base.host}.',
                  style: Cam.body(11),
                ),
                // Live while it uploads, because pressing it then means STOP.
                // It used to be disabled, which is how a stalled upload became
                // something the operator could only wait out.
                onTap: () {
                  Navigator.of(sheet).pop();
                  widget.onUpload(clip);
                },
              )
            else
              ListTile(
                leading: const Icon(Icons.cloud_done_outlined, color: Cam.live),
                title: Text('ON TAKEONE', style: Cam.cap(14, color: Cam.live)),
                // The host this camera is paired to, which is where the footage
                // actually went. This used to print a video.takeone.bh address —
                // that platform was disconnected, so the line named somebody
                // else's box and sent an operator to a page that is not ours.
                subtitle: Text('Filed on ${Config.base.host}.', style: Cam.body(11)),
              ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: Cam.rec),
              title: Text('DELETE THIS CLIP', style: Cam.cap(14, color: Cam.rec)),
              onTap: () {
                Navigator.of(sheet).pop();
                _confirm([clip]);
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _deleteBar() {
    final enabled = _selected.isNotEmpty && !_deleting;

    return Container(
      height: 64,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        border: Border(top: BorderSide(color: Cam.paper.withValues(alpha: 0.08))),
      ),
      child: Center(
        child: Opacity(
          opacity: enabled ? 1 : 0.4,
          child: GestureDetector(
            onTap: enabled ? () => _confirm(_chosen) : null,
            behavior: HitTestBehavior.opaque,
            child: Container(
              height: 44,
              width: double.infinity,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                // Outlined, not filled: the filled red belongs to the final
                // hold, one step further in.
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Cam.rec, width: 1.5),
              ),
              child: Text(
                'DELETE ${_selected.length} CLIP${_selected.length == 1 ? '' : 'S'}…',
                style: Cam.cap(14, color: Cam.rec),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _confirm(List<CameraClip> clips) async {
    if (clips.isEmpty) return;

    final bytes = clips.fold<int>(0, (sum, c) => sum + (c.bytes ?? 0));

    final ok = await showDialog<bool>(
      context: context,
      barrierDismissible: true,
      builder: (_) => _DeleteDialog(
          count: clips.length,
          freed: _size(bytes),
          notUploaded: clips.where((c) => !c.isSafelyUploaded).length,
        ),
    );

    if (ok != true || !mounted) return;

    setState(() => _deleting = true);
    await widget.onDelete(clips);

    if (!mounted) return;

    setState(() {
      _deleting = false;
      _selecting = false;
      _selected.clear();
    });
  }
}

/// "Delete 3 clips?" — and the hold that means it.
///
/// A tap is too cheap for the only irreversible action on this device, and a
/// volunteer's thumb next to a live mat is not a careful instrument. 800ms of
/// deliberate contact, with the fill showing how far through they are, and
/// releasing early cancels.
class _DeleteDialog extends StatefulWidget {
  const _DeleteDialog({required this.count, required this.freed, this.notUploaded = 0});

  final int count;
  final String freed;

  /// How many of these have never reached the server. Called out on its own
  /// line, because for those clips this phone holds the only copy of a bout
  /// that was fought once.
  final int notUploaded;

  @override
  State<_DeleteDialog> createState() => _DeleteDialogState();
}

class _DeleteDialogState extends State<_DeleteDialog> with SingleTickerProviderStateMixin {
  late final AnimationController _hold = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 800),
  )..addStatusListener((status) {
      if (status == AnimationStatus.completed) Navigator.of(context).pop(true);
    });

  @override
  void dispose() {
    _hold.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      child: Container(
        width: 360,
        padding: const EdgeInsets.all(22),
        decoration: BoxDecoration(
          color: Cam.panel,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Cam.paper.withValues(alpha: 0.16)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('DELETE ${widget.count} CLIP${widget.count == 1 ? '' : 'S'}?', style: Cam.cap(18)),
            const SizedBox(height: 10),
            Text(
              'Frees ${widget.freed}. Removes the files from this phone and the '
              'clips from the organiser’s console. This cannot be undone.',
              style: Cam.body(14),
            ),
            if (widget.notUploaded > 0) ...[
              const SizedBox(height: 10),
              Text(
                '${widget.notUploaded} of these ${widget.notUploaded == 1 ? 'has' : 'have'} '
                'not been uploaded. This phone holds the only copy.',
                style: Cam.body(14).copyWith(color: Cam.gold),
              ),
            ],
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(
                  child: GestureDetector(
                    onTap: () => Navigator.of(context).pop(false),
                    behavior: HitTestBehavior.opaque,
                    child: Container(
                      height: 48,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Cam.paper.withValues(alpha: 0.25)),
                      ),
                      child: Text('CANCEL', style: Cam.cap(14)),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: GestureDetector(
                    onTapDown: (_) => _hold.forward(),
                    onTapUp: (_) => _hold.reverse(),
                    onTapCancel: () => _hold.reverse(),
                    behavior: HitTestBehavior.opaque,
                    child: Container(
                      height: 48,
                      clipBehavior: Clip.antiAlias,
                      decoration: BoxDecoration(
                        color: Cam.rec,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          // The progress of the hold, sweeping left to right.
                          AnimatedBuilder(
                            animation: _hold,
                            builder: (_, __) => FractionallySizedBox(
                              alignment: Alignment.centerLeft,
                              widthFactor: _hold.value,
                              child: Container(color: Colors.white.withValues(alpha: 0.22)),
                            ),
                          ),
                          Text('HOLD TO DELETE', style: Cam.cap(14, color: Colors.white, weight: FontWeight.w700)),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
