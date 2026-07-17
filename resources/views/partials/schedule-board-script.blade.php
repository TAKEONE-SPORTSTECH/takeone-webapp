{{-- Shared schedule board — powers both the mobile and desktop schedule pages
     identically (same data, same MQTT/live-update behavior). Expects the DOM
     ids `sched-stat-count`, `sched-stat-done`, `sched-stat-vol`,
     `sched-who-toggle` (with `button[data-who]` children), `sched-strip`, and
     `sched-sessions` to exist in the including page. Set `data-layout="grid"`
     on `#sched-sessions` to lay same-day cards out in a responsive grid
     instead of a single column (desktop only — mobile omits the attribute). --}}
<script>
// ===== Schedule board: renders the dynamic regions from a JS-held array so
// create / edit / delete patch the UI in place (no reload). =====
(function () {
    var root = document.getElementById('sched-sessions');
    if (!root) return;

    var MEMBERS  = {{ Illuminate\Support\Js::from($members) }};
    var WEEKDAYS = {{ Illuminate\Support\Js::from($weekDays) }};
    var TODAY    = @json($todayKey);
    var SESSIONS = {{ Illuminate\Support\Js::from($sessions) }};
    var SHOW_URL = "{{ url('/me/schedule') }}";
    var DATA_URL = "{{ route('me.schedule.data') }}";
    var GRID_LAYOUT = root.dataset.layout === 'grid';
    var ORDER = { sunday:0, monday:1, tuesday:2, wednesday:3, thursday:4, friday:5, saturday:6 };
    var WEEKDAY_KEYS  = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
    var WEEKDAY_SHORT = ['{{ __("personal.personal_schedule_day_sun") }}','{{ __("personal.personal_schedule_day_mon") }}','{{ __("personal.personal_schedule_day_tue") }}','{{ __("personal.personal_schedule_day_wed") }}','{{ __("personal.personal_schedule_day_thu") }}','{{ __("personal.personal_schedule_day_fri") }}','{{ __("personal.personal_schedule_day_sat") }}'];

    // ===== Browser-LOCAL time helpers =====
    // The server runs in UTC, so deriving "today"/status from it rolls over hours late
    // for users ahead of UTC (e.g. just after local midnight). We compute today, the
    // week strip and every card's status from the user's REAL local clock instead — and
    // tick a live countdown to each upcoming class.
    function localTodayKey() { return WEEKDAY_KEYS[new Date().getDay()]; }
    function startOfLocalWeek() {
        var d = new Date(); d.setHours(0, 0, 0, 0);
        d.setDate(d.getDate() - d.getDay());          // back to Sunday
        return d;
    }
    function buildWeekDays() {
        var ws = startOfLocalWeek();
        var todayMid = new Date(); todayMid.setHours(0, 0, 0, 0);
        var out = [];
        for (var i = 0; i < 7; i++) {
            var d = new Date(ws); d.setDate(ws.getDate() + i);
            out.push({ key: WEEKDAY_KEYS[i], short: WEEKDAY_SHORT[i], d: String(d.getDate()),
                       isToday: d.getTime() === todayMid.getTime(),
                       isPast:  d.getTime() <  todayMid.getTime() });
        }
        return out;
    }
    // This week's local Date for a weekday + "HH:MM[:SS]".
    function occurrenceDate(dayKey, hhmm) {
        var ws = startOfLocalWeek();
        var d = new Date(ws); d.setDate(ws.getDate() + (ORDER[dayKey] || 0));
        var p = String(hhmm || '00:00').split(':');
        d.setHours(parseInt(p[0], 10) || 0, parseInt(p[1], 10) || 0, 0, 0);
        return d;
    }
    // Local YYYY-MM-DD (not toISOString, which would shift by the UTC offset).
    function ymdLocal(d) {
        var m = d.getMonth() + 1, day = d.getDate();
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }
    function fmtCountdown(ms) {
        if (ms < 0) ms = 0;
        var t = Math.floor(ms / 1000);
        var days = Math.floor(t / 86400), h = Math.floor((t % 86400) / 3600),
            mn = Math.floor((t % 3600) / 60), sc = t % 60;
        var p = function (n) { return (n < 10 ? '0' : '') + n; };
        return (days > 0 ? days + 'd ' : '') + p(h) + ':' + p(mn) + ':' + p(sc);
    }

    // Override the server's UTC-derived week/today with the user's local clock.
    WEEKDAYS = buildWeekDays();
    TODAY    = localTodayKey();

    // Open on the day from the URL (?day=…) when present — so returning from a card
    // detail keeps the day you were browsing instead of snapping back to today.
    var _urlDay = new URLSearchParams(window.location.search).get('day');
    var state = { who: 'all', day: (_urlDay && ORDER[_urlDay] !== undefined) ? _urlDay : TODAY };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }
    // Time-aware status for a session this week: done (ended) · live (in progress) ·
    // today (starts later today) · upcoming (a future day).
    function statusFor(s) {
        if (typeof s === 'string') s = { day: s };            // legacy day-only callers
        var now = new Date();
        var start = occurrenceDate(s.day, s.start_raw || s.start || '00:00');
        var end = s.end_raw ? occurrenceDate(s.day, s.end_raw)
                            : new Date(start.getTime() + durMins(s) * 60000);
        if (end.getTime() <= start.getTime()) end = new Date(end.getTime() + 86400000); // crosses midnight
        if (now >= end)   return 'done';
        if (now >= start) return 'live';
        return s.day === TODAY ? 'today' : 'upcoming';
    }
    // Avatar circle: profile photo when available, initials fallback.
    // sizeClass/textClass are literal Tailwind tokens (so they survive purge).
    function avatarHTML(m, sizeClass, textClass) {
        if (m && m.avatar) {
            return '<span class="' + sizeClass + ' rounded-full overflow-hidden flex-shrink-0 block">'
                + '<img src="' + esc(m.avatar) + '" alt="" class="' + sizeClass + ' object-cover"></span>';
        }
        return '<span class="' + sizeClass + ' rounded-full overflow-hidden flex-shrink-0 grid place-items-center text-white ' + textClass + ' font-bold" style="background:' + esc((m && m.color) || '#7c3aed') + ';">' + esc((m && m.initials) || '') + '</span>';
    }
    function durMins(s) {
        var n = parseInt(String(s.duration || '').replace(/[^0-9]/g, ''), 10);
        return isNaN(n) ? 0 : n;
    }
    function visibleForWho(s) { return state.who === 'all' || s.who === 'me'; }

    // ---- Hero stats ----
    function renderStats() {
        var vis = SESSIONS.filter(visibleForWho);
        document.getElementById('sched-stat-count').textContent = vis.length;
        document.getElementById('sched-stat-done').textContent = vis.filter(function (s) { return statusFor(s) === 'done'; }).length;
        var mins = vis.reduce(function (a, s) { return a + durMins(s); }, 0);
        document.getElementById('sched-stat-vol').textContent = (Math.round(mins / 6) / 10) + 'h';
    }

    // ---- Week-day strip ----
    function renderStrip() {
        var html = WEEKDAYS.map(function (wd) {
            var count = SESSIONS.filter(function (s) { return s.day === wd.key && visibleForWho(s); }).length;
            var active = state.day === wd.key;
            var dots = '';
            for (var i = 0; i < Math.min(count, 3); i++) {
                dots += '<span class="w-1 h-1 rounded-full ' + (active ? 'bg-white' : 'bg-primary') + '"></span>';
            }
            var todayLine = wd.isToday ? '<span class="' + (active ? 'text-white' : 'text-primary') + '">{{ __("personal.personal_schedule_today") }}</span>' : '';
            return '<button type="button" data-day="' + wd.key + '" '
                + 'class="m-press flex-1 min-w-0 flex flex-col items-center justify-start pt-2 pb-1.5 rounded-xl border transition-colors '
                + (active ? 'bg-primary border-primary text-white' : 'bg-white border-gray-100 text-foreground') + '">'
                + '<span class="text-[10px] uppercase tracking-wide leading-none ' + (active ? 'text-white/80' : 'text-muted-foreground') + '">' + esc(wd.short) + '</span>'
                + '<span class="text-lg font-black leading-none mt-1.5">' + esc(wd.d) + '</span>'
                + '<span class="mt-1.5 h-1 flex items-center justify-center gap-0.5">' + dots + '</span>'
                + '<span class="mt-auto h-3 flex items-center text-[8px] font-bold leading-none">' + todayLine + '</span>'
                + '</button>';
        }).join('');
        document.getElementById('sched-strip').innerHTML = html;
    }

    // ---- One session card (mirrors the original Blade card markup) ----
    function cardHTML(s) {
        var m = MEMBERS[s.who] || MEMBERS.me || { color: '#7c3aed', initials: '', relation: '' };
        var status = statusFor(s);
        var statusBadge = '';
        if (s.is_cancelled) {
            statusBadge = '';                                   // the "Cancelled" pill says it all
        } else if (status === 'done') {
            statusBadge = '<span class="ms-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-600"><i class="bi bi-check2"></i> {{ __("shared.done") }}</span>';
        } else if (status === 'live') {
            statusBadge = '<span class="ms-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> {{ __("personal.personal_schedule_live_now") }}</span>';
        } else {
            // today (later) or upcoming → live countdown to the start time
            var startMs = occurrenceDate(s.day, s.start_raw || s.start || '00:00').getTime();
            statusBadge = '<span class="ms-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-accent text-primary inline-flex items-center gap-1 js-cd" data-cd="' + startMs + '">'
                + '<i class="bi bi-hourglass-split"></i> <span class="cd-val">' + fmtCountdown(startMs - Date.now()) + '</span></span>';
        }

        // Package name line (club classes carry the package as `discipline`).
        var pkgLine = s.discipline
            ? '<p class="text-[11px] font-semibold mt-0.5 truncate" style="color:' + esc(s.color) + ';"><i class="bi bi-box-seam text-[10px] me-1"></i>' + esc(s.discipline) + '</p>'
            : '';

        // meta line: location · coach (kept short — no club name)
        var metaParts = [];
        if (s.location) metaParts.push('<i class="bi bi-geo-alt text-[11px]"></i>' + esc(s.location));
        if (s.coach) {
            var coachStr = s.is_substituted
                ? '<i class="bi bi-arrow-left-right text-[11px] text-amber-500"></i><span class="text-amber-600 font-semibold">' + esc(s.coach) + '</span>'
                : esc(s.coach);
            metaParts.push((metaParts.length ? '<span class="text-gray-300">·</span>' : '') + coachStr);
        }
        var metaLine = metaParts.length
            ? '<p class="text-xs text-muted-foreground mt-0.5 truncate flex items-center gap-1.5">' + metaParts.join(' ') + '</p>'
            : '';

        // right-hand pill: short tag only (no club name — keeps the card compact)
        var pill;
        if (s.is_cancelled) {
            pill = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-50 text-red-600 inline-flex items-center gap-1"><i class="bi bi-calendar-x"></i> {{ __("personal.personal_schedule_cancelled") }}</span>';
        } else if (s.source === 'substituting') {
            pill = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600 inline-flex items-center gap-1"><i class="bi bi-person-check-fill"></i> {{ __("personal.personal_schedule_covering") }}</span>';
        } else if (s.source === 'teaching') {
            pill = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 inline-flex items-center gap-1"><i class="bi bi-person-video3"></i> {{ __("personal.personal_schedule_teaching") }}</span>';
        } else if (s.source === 'synced') {
            pill = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-50 text-sky-600 inline-flex items-center gap-1"><i class="bi bi-arrow-repeat"></i> {{ __("personal.personal_schedule_synced") }}</span>';
        } else if (s.intensity) {
            pill = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background:' + esc(s.color) + '1a; color:' + esc(s.color) + ';">' + esc(s.intensity) + '</span>';
        } else {
            pill = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background:' + esc(s.color) + '1a; color:' + esc(s.color) + ';">{{ __("personal.personal_schedule_personal") }}</span>';
        }

        var inner =
            '<div class="flex">'
            + '<div class="w-1.5 flex-shrink-0" style="background:' + esc(s.color) + ';"></div>'
            + '<div class="flex-1 p-3.5">'
            + '<div class="flex items-start gap-3">'
            + '<div class="w-12 h-12 rounded-2xl grid place-items-center text-white flex-shrink-0" style="background: linear-gradient(160deg, ' + esc(s.color) + ', ' + esc(s.color) + 'd0);">'
            + '<i class="bi ' + esc(s.icon) + ' text-xl"></i></div>'
            + '<div class="min-w-0 flex-1">'
            + '<div class="flex items-center gap-2">'
            + '<span class="text-[11px] font-bold text-foreground">' + esc(s.start || '') + '</span>'
            + '<span class="text-[10px] text-muted-foreground">· ' + esc(s.duration || '') + '</span>'
            + statusBadge
            + '</div>'
            + '<h3 class="font-bold text-foreground mt-0.5 truncate ' + (s.is_cancelled ? 'line-through opacity-60' : '') + '">' + esc(s.title) + '</h3>'
            + pkgLine
            + metaLine
            + '</div></div>'
            + '<div class="flex items-center justify-between mt-2.5">'
            + '<span class="inline-flex items-center gap-1.5">'
            + avatarHTML(m, 'w-5 h-5', 'text-[8px]')
            + '<span class="text-[11px] font-medium text-muted-foreground">' + esc(m.relation) + '</span></span>'
            + pill
            + '</div>'
            + '</div></div>';

        if (s.source === 'synced' || s.source === 'teaching' || s.source === 'substituting') {
            // Club class (enrolled, taught, or covering) — tappable to its detail.
            // Carry THIS occurrence's date so the detail shows the exact session tapped.
            var durl = (s.detail_url || '#');
            var onYmd = ymdLocal(occurrenceDate(s.day, s.start_raw || s.start || '00:00'));
            if (durl !== '#') durl += (durl.indexOf('?') >= 0 ? '&' : '?') + 'on=' + onYmd;
            return '<a href="' + durl + '" data-shell-link data-route="me.schedule" '
                + 'class="block m-card m-press rounded-2xl overflow-hidden">' + inner + '</a>';
        }
        return '<a href="' + SHOW_URL + '/' + s.id + '" data-shell-link data-route="me.schedule" '
            + 'class="block m-card m-press rounded-2xl overflow-hidden">' + inner + '</a>';
    }

    // ---- Sessions list for the selected day ----
    function renderSessions() {
        var day = state.day;
        var dayAll = SESSIONS.filter(function (s) { return s.day === day; });
        var dayVis = dayAll.filter(visibleForWho)
            .sort(function (a, b) { return (a.start_raw || a.start || '').localeCompare(b.start_raw || b.start || ''); });

        var html;
        if (!dayAll.length) {
            html = '<div class="bg-white rounded-2xl border border-gray-100 px-5 py-12 text-center">'
                + '<div class="w-16 h-16 mx-auto rounded-3xl bg-accent text-primary grid place-items-center"><i class="bi bi-cup-hot text-2xl m-float"></i></div>'
                + '<p class="text-sm font-bold text-foreground mt-3">{{ __("personal.personal_schedule_rest_day") }}</p>'
                + '<p class="text-xs text-muted-foreground mt-1">{{ __("personal.personal_schedule_no_training") }}</p></div>';
        } else if (!dayVis.length) {
            html = '<div class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">'
                + '<i class="bi bi-cup-hot text-2xl text-gray-300 m-float"></i>'
                + '<p class="text-sm text-muted-foreground mt-2">{{ __("personal.personal_schedule_no_personal") }}</p></div>';
        } else {
            html = dayVis.map(cardHTML).join('');
        }
        var wrapClass = GRID_LAYOUT ? 'grid grid-cols-1 xl:grid-cols-2 gap-3' : 'space-y-3';
        root.innerHTML = '<div class="' + wrapClass + '">' + html + '</div>';
    }

    function renderAll() {
        renderStats();
        renderStrip();
        renderSessions();
    }

    // ---- Events: toggle who / pick day ----
    document.getElementById('sched-who-toggle').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-who]');
        if (!b) return;
        state.who = b.getAttribute('data-who');
        this.querySelectorAll('button[data-who]').forEach(function (btn) {
            var on = btn.getAttribute('data-who') === state.who;
            btn.classList.toggle('bg-primary', on);
            btn.classList.toggle('text-white', on);
            btn.classList.toggle('text-muted-foreground', !on);
        });
        renderAll();
    });
    document.getElementById('sched-strip').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-day]');
        if (!b) return;
        state.day = b.getAttribute('data-day');
        // Reflect the selected day in the URL so back (in-page or device) restores it.
        try { history.replaceState(history.state || { shell: true }, '', '?day=' + state.day); } catch (e2) {}
        renderAll();
    });

    // ---- Live updates from the create/edit sheet ----
    function upsert(session) {
        if (!session || session.id == null) return;
        var i = SESSIONS.findIndex(function (s) { return String(s.id) === String(session.id); });
        if (i >= 0) SESSIONS.splice(i, 1, session); else SESSIONS.push(session);
        state.day = session.day || state.day;            // jump to the affected day
        renderAll();
    }
    function removeById(id) {
        var i = SESSIONS.findIndex(function (s) { return String(s.id) === String(id); });
        if (i >= 0) { SESSIONS.splice(i, 1); renderAll(); }
    }

    // Re-pull the whole schedule live (used for club-class changes where each
    // user's cards differ) and re-render in place — no manual refresh.
    var reloading = false;
    async function reloadData() {
        if (reloading || !document.getElementById('sched-sessions')) return;
        reloading = true;
        try {
            var res = await fetch(DATA_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            var data = await res.json();
            if (Array.isArray(data.sessions)) SESSIONS = data.sessions;
            if (data.members) MEMBERS = data.members;
            WEEKDAYS = buildWeekDays();   // keep the strip & "today" on the user's local week
            TODAY    = localTodayKey();
            renderAll();
        } catch (e) { /* best-effort */ }
        finally { reloading = false; }
    }

    // ---- Live updates (dedup listeners across shell navigations) ----
    if (window.__schedSaved)   window.removeEventListener('schedule-session-saved', window.__schedSaved);
    if (window.__schedDeleted) window.removeEventListener('schedule-session-deleted', window.__schedDeleted);
    if (window.__schedRT)      window.removeEventListener('realtime:schedule', window.__schedRT);

    window.__schedSaved   = function (e) { upsert(e.detail && e.detail.session); };
    window.__schedDeleted = function (e) { removeById(e.detail && e.detail.id); };
    // Realtime from the server (this device + others): a card payload patches in
    // place; a bare {action:'refresh'} re-pulls everything.
    window.__schedRT = function (e) {
        var d = e.detail || {};
        if (d.action === 'refresh') { reloadData(); return; }
        if (d.action === 'deleted') { removeById(d.session && d.session.id); return; }
        if (d.session) upsert(d.session);
    };

    window.addEventListener('schedule-session-saved', window.__schedSaved);
    window.addEventListener('schedule-session-deleted', window.__schedDeleted);
    window.addEventListener('realtime:schedule', window.__schedRT);

    // ---- Live countdown ticker: updates each "upcoming" pill every second ----
    function tickCountdowns() {
        if (!document.getElementById('sched-sessions')) return;   // left the schedule shell
        if (localTodayKey() !== TODAY) {                          // crossed midnight while open
            WEEKDAYS = buildWeekDays(); TODAY = localTodayKey();
            renderAll(); return;
        }
        var now = Date.now(), rerender = false;
        root.querySelectorAll('[data-cd]').forEach(function (el) {
            var target = parseInt(el.getAttribute('data-cd'), 10);
            if (now >= target) { rerender = true; return; }       // class just started
            var v = el.querySelector('.cd-val'); if (v) v.textContent = fmtCountdown(target - now);
        });
        if (rerender) renderSessions();                          // flip the started card to "Live now"
    }
    if (window.__schedTick) clearInterval(window.__schedTick);
    window.__schedTick = setInterval(tickCountdowns, 1000);

    renderAll();
})();
</script>
