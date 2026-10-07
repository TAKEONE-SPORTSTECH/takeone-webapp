{{-- The dancing robot — Ask TAKEONE's icon. Drawn here once; motion lives in
     takeone-v2.css (.t1-bot) and stops for reduced-motion users. --}}
<svg class="t1-bot" viewBox="0 0 32 32" aria-hidden="true">
    <defs><linearGradient id="t1BotG{{ $uid ?? '' }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#a78bfa"/><stop offset=".55" stop-color="#8b5cf6"/><stop offset="1" stop-color="#f472b6"/></linearGradient></defs>
    <g class="b-all">
        <line x1="16" y1="3.5" x2="16" y2="7" stroke="var(--t1-bot-ink)" stroke-width="1.6" stroke-linecap="round"/>
        <circle class="b-light" cx="16" cy="3" r="1.9" fill="#fbbf24"/>
        <g class="b-armL"><rect x="3.2" y="15.5" width="6.5" height="2.6" rx="1.3" fill="var(--t1-bot-b)"/><circle cx="3.6" cy="16.8" r="1.6" fill="var(--t1-bot-ink)"/></g>
        <g class="b-armR"><rect x="22.3" y="15.5" width="6.5" height="2.6" rx="1.3" fill="var(--t1-bot-b)"/><circle cx="28.4" cy="16.8" r="1.6" fill="var(--t1-bot-ink)"/></g>
        <rect class="b-legL" x="11.5" y="24.5" width="3" height="5" rx="1.5" fill="var(--t1-bot-ink)"/>
        <rect class="b-legR" x="17.5" y="24.5" width="3" height="5" rx="1.5" fill="var(--t1-bot-ink)"/>
        <rect x="9.5" y="15" width="13" height="10.5" rx="3.5" fill="url(#t1BotG{{ $uid ?? '' }})"/>
        <circle cx="16" cy="20.2" r="1.7" fill="#fff" opacity=".85"/>
        <rect x="7.5" y="6.5" width="17" height="10" rx="4.5" fill="url(#t1BotG{{ $uid ?? '' }})"/>
        <rect x="9.6" y="8.6" width="12.8" height="5.8" rx="2.9" fill="var(--t1-bot-face)"/>
        <g class="b-eyes"><circle cx="13.3" cy="11.5" r="1.25" fill="#5eead4"/><circle cx="18.7" cy="11.5" r="1.25" fill="#5eead4"/></g>
    </g>
</svg>
