{{-- Live refresh: a new answer pushes {action:'refresh', form} on the `forms`
     channel; the open page re-reads its panels in place. Example mode shows
     made-up answers and does not listen. Handler is deduped on window
     because the admin shell re-runs page init on every visit. --}}
@unless($example)
<script>
(function () {
    if (window.__formsLive) window.removeEventListener('realtime:forms', window.__formsLive);
    window.__formsLive = async function (e) {
        const box = document.getElementById('fa-panels');
        if (!box || !e.detail || e.detail.form !== box.closest('[data-form]')?.dataset.form) return;
        try {
            const r = await fetch(location.pathname + '?fragment=panels', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) return;
            const t = document.createElement('template');
            t.innerHTML = (await r.text()).trim();
            const fresh = t.content.querySelector('#fa-panels');
            if (fresh) box.replaceWith(fresh);
        } catch (err) {}
    };
    window.addEventListener('realtime:forms', window.__formsLive);
})();
</script>
@endunless
