{{--
    The club blocks' styles — shared by the draw report (events::bracket-pdf)
    and the federation Results PDF. Inside a <style>; expects \$c and \$navy.
--}}
        /* Teams — one block per club: crest, name, size, medals; then its athletes. */
        .club { break-inside: avoid; border: .35mm solid #cfd5e0; border-radius: 1.5mm; overflow: hidden; margin-top: 5mm; }
        .club-h { display: flex; align-items: center; gap: 4mm; padding: 2.6mm 4mm; background: {{ $navy }}; color: #fff; }
        .club-h .rk { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic; font-size: 20pt; line-height: 1; color: {{ $c }}; min-width: 7mm; }
        .club-h .lg { width: 13mm; height: 13mm; flex: 0 0 auto; }
        .club-h .lg img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .club-h .lg.none { display: grid; place-items: center; color: {{ $c }}; font-size: 15pt; }
        .club-h .nm { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic; text-transform: uppercase;
                      font-size: 17pt; line-height: 1; letter-spacing: .02em; }
        .club-h .nm small { display: block; font-family: 'Inter', sans-serif; font-style: normal; font-weight: 700; font-size: 7pt;
                            letter-spacing: .16em; color: {{ $c }}; margin-top: 1.2mm; }
        .club-h .md { margin-inline-start: auto; display: flex; gap: 3.5mm; align-items: center; }
        .club-h .md span { display: inline-flex; align-items: center; gap: 1.2mm; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; font-size: 14pt; }
        .club-h .md span.zero { opacity: .35; }
        .club-p { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0 5mm; padding: 1.5mm 4mm 2mm; }
        .ath { display: flex; gap: 1.5mm; align-items: flex-start; padding: 1.4mm 0 1.2mm; border-bottom: .25mm solid #e3e6ee; min-width: 0; }
        .ath .t { min-width: 0; flex: 1; }
        .ath .n { font-size: 8.4pt; font-weight: 700; color: {{ $navy }}; line-height: 1.25; overflow-wrap: anywhere; }
        .ath .d { font-size: 6.6pt; color: #6b7590; line-height: 1.3; margin-top: .3mm; }
        .ath .m { display: flex; gap: .6mm; flex: 0 0 auto; padding-top: .3mm; }

