{{-- Shared behaviour for the clubs list (desktop + mobile): client-side search
     filtering + live card patch after an edit. Keyed to .club-card-wrapper. --}}
@push('scripts')
<script>
    (function () {
        const search = document.getElementById('clubSearch');
        if (search) {
            search.addEventListener('input', function (e) {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('.club-card-wrapper').forEach(function (card) {
                    const name = (card.getAttribute('data-club-name') || '').toLowerCase();
                    const addr = (card.getAttribute('data-club-address') || '').toLowerCase();
                    const owner = (card.getAttribute('data-club-owner') || '').toLowerCase();
                    card.classList.toggle('hidden', !(name.includes(term) || addr.includes(term) || owner.includes(term)));
                });
            });
        }

        // Patch a club card in place after an edit — no reload.
        window.addEventListener('club-saved', function (e) {
            const detail = e.detail || {};
            if (detail.mode !== 'edit' || !detail.club) return;
            const c = detail.club;
            const wrapper = document.querySelector('.club-card-wrapper[data-club-id="' + c.id + '"]');
            if (!wrapper) return;

            if (c.club_name != null) wrapper.setAttribute('data-club-name', c.club_name);
            wrapper.setAttribute('data-club-address', c.address || '');

            const title = wrapper.querySelector('.club-title');
            if (title && c.club_name != null) title.textContent = c.club_name;

            const addrText = wrapper.querySelector('.club-address');
            if (addrText && c.address != null) addrText.textContent = c.address;

            const cover = wrapper.querySelector('.club-cover-img');
            if (cover && c.cover_image) cover.src = '/storage/' + c.cover_image + '?t=' + Date.now();
            const logo = wrapper.querySelector('.club-logo-img');
            if (logo && c.logo) logo.src = '/storage/' + c.logo + '?t=' + Date.now();
        });
    })();
</script>
@endpush
