{{-- "My certificates" state, shared by the mobile and desktop pages. Inline and guarded (mobile shell). --}}
<script>
window.myCertificates = window.myCertificates || function (rows, listUrl, strings) {
    return {
        rows, listUrl, strings,
        filter: 'all',
        get shown() { return this.filter === 'all' ? this.rows : this.rows.filter(r => r.type === this.filter); },
        async reload() {
            try {
                const res = await fetch(this.listUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const d = await res.json().catch(() => ({}));
                if (res.ok && d.certificates) this.rows = d.certificates;
            } catch (e) { /* the list on screen stays */ }
        },
        view(row) {
            window.dispatchEvent(new CustomEvent('open-my-certificate', { detail: { src: row.image_url, label: row.number, kind: 'image' } }));
        },
        async share(row) {
            if (! row.verify_url) return;
            const data = { title: row.title, text: row.what + (row.context ? ' — ' + row.context.title : ''), url: row.verify_url };
            try {
                if (navigator.share) { await navigator.share(data); return; }
            } catch (e) { if (e && e.name === 'AbortError') return; }
            try {
                await navigator.clipboard.writeText(row.verify_url);
                window.showToast('success', this.strings.copied);
            } catch (e) {
                window.showToast('info', row.verify_url);
            }
        },
        medal(place) { return place === 1 ? 'gold' : (place === 2 ? 'silver' : (place === 3 ? 'bronze' : null)); },
    };
};
</script>
