{{--
    Running a package action without leaving the page.

    The event type offers verbs — "create the draw", "close entries" — and
    PersonalEventController::performAction answers them in JSON. Three consoles
    posted them with a plain form element, so the browser NAVIGATED to that JSON
    and the organiser landed on a page showing {"success":true,…} (reported as
    "it takes me to a black screen", 2026-09-06: the phone renders a raw JSON
    document on its dark reader background).

    So the submit is intercepted here instead: one delegated listener, posting
    the same form to the same URL with an application/json Accept header,
    reporting through the usual toast, and then doing whatever the page asked
    for — nothing at all, a board refresh, or the redirect the server named.

    Usage: mark the form with data-event-action, and say what happens after with
    data-after —

      go       (or omitted) follow the `redirect` the response carries; the old
               behaviour of a form post, minus the JSON page.
      bracket  the draw board is on this page: reload it in place (No-Reload
               rule) and stay put.
      reload   re-render this page.

    An optional data-failed carries the sentence to toast when the request
    itself fails.

    ⚠️ Nothing in this block may nest a Blade comment, write an echo tag, or
    even SPELL a comment terminator. Blade's comment matcher is non-greedy, so
    the first close sequence it meets ends the outer comment and everything
    after it renders as visible text on the page. That shipped twice here: once
    from a nested comment in the usage example, and once from quoting the
    terminator in this very warning.

    Delegated off `document` and guarded by a window flag, so it survives the
    mobile/admin shells swapping content and re-running scripts.
--}}
@once
@push('scripts')
<script>
(function () {
    if (window.__eventActionRunner) return;
    window.__eventActionRunner = true;

    document.addEventListener('submit', async function (e) {
        const form = e.target.closest('form[data-event-action]');
        if (! form) return;

        e.preventDefault();

        if (form.dataset.busy === '1') return;
        form.dataset.busy = '1';

        const button = form.querySelector('button[type=submit]');
        if (button) button.disabled = true;

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                credentials: 'same-origin',
                body: new FormData(form),
            });

            const data = await res.json().catch(() => ({}));

            if (! res.ok || data.success === false) {
                window.showToast('error', data.message || form.dataset.failed || 'That did not work.');
                return;
            }

            if (data.message) window.showToast('success', data.message);

            const after = form.dataset.after || 'go';

            if (after === 'bracket' && window.BracketBoard && window.BracketBoard.reload) {
                window.BracketBoard.reload();
                return;
            }
            if (after === 'reload') {
                setTimeout(() => window.location.reload(), 400);
                return;
            }
            if (data.redirect) {
                setTimeout(() => { window.location.href = data.redirect; }, 400);
            }
        } catch (err) {
            window.showToast('error', form.dataset.failed || 'That did not work.');
        } finally {
            form.dataset.busy = '0';
            if (button) button.disabled = false;
        }
    });
})();
</script>
@endpush
@endonce
