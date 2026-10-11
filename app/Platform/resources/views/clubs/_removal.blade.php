{{-- "Remove club" on a clubs list (desktop + mobile): the review sheet, and
     the removed club's card taken off the list in place. Behind
     CLUB_REMOVAL_TOOL — draws nothing while it is off. --}}
@if(config('features.club_removal_tool'))
<x-club-removal />
@push('scripts')
<script>
    (function () {
        // Once per page life: the shells re-run inline scripts on navigation.
        if (window.__clubRemovedHandler) window.removeEventListener('club-removed', window.__clubRemovedHandler);
        window.__clubRemovedHandler = function (e) {
            const id = e.detail && e.detail.id;
            if (!id) return;
            document.querySelectorAll('.club-card-wrapper[data-club-id="' + String(id).replace(/[^0-9]/g, '') + '"]').forEach(function (card) {
                card.style.transition = 'opacity .25s ease, transform .25s ease';
                card.style.opacity = '0'; card.style.transform = 'scale(.96)';
                setTimeout(function () { card.remove(); }, 260);
            });
        };
        window.addEventListener('club-removed', window.__clubRemovedHandler);
    })();
</script>
@endpush
@endif
