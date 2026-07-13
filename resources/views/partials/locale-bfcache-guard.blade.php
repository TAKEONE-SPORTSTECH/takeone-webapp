{{-- Back/forward cache restores a page exactly as it was rendered — Laravel is
     never asked, so a page cached before a language switch comes back in the old
     locale and direction. Record the live locale on every real load; on a bfcache
     restore, reload only if the restored page disagrees. Everything else keeps
     the instant back-navigation. --}}
<script>
    (function () {
        var KEY = 'takeone:locale';

        function current() {
            return document.documentElement.lang || '';
        }

        try { localStorage.setItem(KEY, current()); } catch (e) { /* private mode */ }

        window.addEventListener('pageshow', function (e) {
            if (! e.persisted) return;   // only bfcache restores

            var latest = null;
            try { latest = localStorage.getItem(KEY); } catch (e) { return; }

            if (latest && latest !== current()) window.location.reload();
        });
    })();
</script>
