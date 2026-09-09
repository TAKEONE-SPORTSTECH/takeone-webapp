@props([
    /*
     * The destroy endpoint for this bout's footage. Required: a control that
     * points nowhere is a dead end (Navigation Integrity), and the caller is
     * the only one who knows which bout this tile is.
     */
    'url',
    /*
     * What is about to be destroyed, in the confirm dialog's own words — the
     * two names, usually. Null is fine; the dialog then speaks generally.
     */
    'label' => null,
    /*
     * What leaves the page when the server says yes, as selectors: the TILE
     * itself, and the block it sat in — removed too when the tile was the last
     * one in it, because a division heading over an empty strip reads as a
     * loading failure rather than as an empty shelf.
     */
    'tile' => '[data-bout-tile]',
    'shelf' => '[data-bout-shelf]',
    /* Sits over a thumbnail's corner. Pass your own classes to place it. */
    'class' => 'absolute top-1.5 end-1.5 z-10 w-8 h-8 rounded-full grid place-items-center text-white bg-black/45 border border-white/35 backdrop-blur active:scale-90 transition-transform',
])

{{--
    Delete this bout's footage — the control, wherever a bout is shown.

    ── Why this is a component and not four lines in a view ───────────────────

    Because it is the one control on the platform with nothing behind it.
    Competition video cannot be filmed again, so the confirmation, the CSRF
    header, the error handling and the in-place removal have to be identical
    everywhere it appears — and the moment that logic is pasted into a second
    view, one copy gets a fix the other does not.

    ── Standalone (Part 4) ────────────────────────────────────────────────────

    Owns its markup, its request and its DOM patching, and carries its own
    dependency: `<x-confirm-dialog>` is included here rather than assumed of the
    host layout, because the public event pages sit outside the app shell and
    have none of the shell's helpers. Its own `@once` means a page whose layout
    already includes it still renders exactly one dialog.

    Handlers are DELEGATED off `document` (never bound per button), so a tile
    that arrives from a later fetch — a gallery re-render, a realtime refresh —
    is live without re-initialising anything.

    Feedback prefers `window.showToast` and falls back to `window.notice`, the
    small on-palette notice the public event pages define for exactly this. Both
    absent is silent rather than a native dialog, which is never acceptable
    here.

    ── The server is the authority ────────────────────────────────────────────

    Rendering this control is a UI decision (show it only to somebody who may
    use it); ALLOWING the deletion is not. `me.events.bout.video.destroy`
    re-checks `EventAccess::canManage` on every request, so a hand-made call
    from a page that should not have shown the button gets a 403.

    Usage:
        <div class="relative" data-bout-tile>
            …the thumbnail…
            <x-bout-video-delete :url="route('me.events.bout.video.destroy', [...])"
                                 :label="$b['a'].' — '.$b['b']" />
        </div>
--}}

<button type="button"
        data-bout-video-delete
        data-url="{{ $url }}"
        data-label="{{ $label }}"
        data-tile="{{ $tile }}"
        data-shelf="{{ $shelf }}"
        aria-label="{{ __('events.bout_card_delete') }}"
        title="{{ __('events.bout_card_delete') }}"
        {{ $attributes->merge(['class' => $class]) }}>
    <i class="bi bi-trash3 text-[13px]"></i>
</button>

@once
    {{-- The dialog this control asks with. Its own @once keeps it to one copy
         per page even where the layout includes it as well. --}}
    <x-confirm-dialog />

    @push('scripts')
    <script>
    /*
     * One delegated listener for every delete control on the page.
     *
     * Guarded on window because the mobile shell re-runs inline scripts on each
     * navigation and pushed blocks can be swapped in again — without this the
     * listeners stack and one press sends three DELETEs.
     */
    if (! window.__boutVideoDelete) {
        window.__boutVideoDelete = true;

        document.addEventListener('click', async (ev) => {
            const btn = ev.target.closest('[data-bout-video-delete]');

            if (! btn) return;

            // The tile around it is usually itself a control (it opens the
            // player), so this press must not also count as that one.
            ev.preventDefault();
            ev.stopPropagation();

            if (btn.dataset.busy === '1') return;

            const say = (type, message) => {
                if (typeof window.showToast === 'function') return window.showToast(type, message);
                if (typeof window.notice === 'function') return window.notice(message);
            };

            const sure = typeof window.confirmAction === 'function'
                ? await window.confirmAction({
                    title: @js(__('events.bout_card_delete_title')),
                    message: btn.dataset.label
                        ? btn.dataset.label + ' — ' + @js(__('events.bout_card_delete_body'))
                        : @js(__('events.bout_card_delete_body')),
                    type: 'danger',
                    confirmText: @js(__('events.bout_card_delete')),
                })
                : false;

            if (! sure) return;

            btn.dataset.busy = '1';
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';

            try {
                const res = await fetch(btn.dataset.url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                // A signed-in organiser whose session has lapsed gets HTML from
                // the auth stack rather than JSON, which is a failure like any
                // other and must not be read as success.
                const data = res.headers.get('content-type')?.includes('json')
                    ? await res.json().catch(() => ({}))
                    : {};

                if (! res.ok || data.success === false) {
                    say('error', data.message || @js(__('personal.event_verify_failed')));
                    btn.dataset.busy = '';
                    btn.innerHTML = '<i class="bi bi-trash3 text-[13px]"></i>';

                    return;
                }

                say('success', data.message || @js(__('events.bout_card_deleted')));

                const tile = btn.closest(btn.dataset.tile || '[data-bout-tile]');

                if (! tile) return;

                const shelf = tile.closest(btn.dataset.shelf || '[data-bout-shelf]');

                tile.style.transition = 'opacity .22s ease, transform .22s ease';
                tile.style.opacity = '0';
                tile.style.transform = 'scale(.94)';

                setTimeout(() => {
                    tile.remove();

                    // The heading goes with the last tile under it.
                    if (shelf && ! shelf.querySelector('[data-bout-tile]')) {
                        shelf.remove();
                    }
                }, 240);
            } catch (e) {
                say('error', @js(__('personal.event_verify_failed')));
                btn.dataset.busy = '';
                btn.innerHTML = '<i class="bi bi-trash3 text-[13px]"></i>';
            }
        });
    }
    </script>
    @endpush
@endonce
