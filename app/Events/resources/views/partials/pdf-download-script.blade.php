{{--
    window.participantsPdf(url) — fetch one of the event's server-printed PDFs
    and hand it to the browser as a download, with a toast while Chromium
    works and a toast if it fails (No-Reload rule: a failure is never a page
    away). Shared by every ⋯ menu row that downloads an event PDF: the entry
    lists on the participants page and the registration form on the poster.
    Defined once — the shell re-runs inline scripts on every swap.
--}}
        <script>
        (function () {
            if (window.participantsPdf) return;
            let busy = false;
            window.participantsPdf = async function (url) {
                if (busy) return;
                busy = true;
                window.showToast && window.showToast('info', @js(__('events::events.bracket_pdf_working')));
                try {
                    const res = await fetch(url, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json, application/pdf' },
                    });
                    const type = res.headers.get('Content-Type') || '';
                    if (! res.ok || type.indexOf('application/pdf') === -1) {
                        let msg = @js(__('events::events.bracket_pdf_failed'));
                        try { const j = await res.json(); if (j && j.message) msg = j.message; } catch (e) { /* not json */ }
                        window.showToast && window.showToast('error', msg);
                        return;
                    }
                    const name = ((res.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/) || [])[1] || 'participants.pdf';
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(await res.blob());
                    link.download = name;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(link.href), 10000);
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('events::events.bracket_pdf_failed')));
                } finally {
                    busy = false;
                }
            };
        })();
        </script>
