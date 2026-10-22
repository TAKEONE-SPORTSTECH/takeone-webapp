{{--
    The officials' cards' styles — shared by the draw report (events::bracket-pdf)
    and the federation competition report. Inside a <style>; expects \$c, \$navy and \$goldInk.
--}}
        /* Officials & management — one bordered card per person: the face at
           3:4 (the platform's portrait ratio), the job in gold, the name, the club. */
        .tgrid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4.5mm 6mm; }
        .tsec + .tsec { margin-top: 7mm; }
        .tsec > .h2 { margin-top: 7mm; }
        .pcard { display: flex; gap: 4mm; border: .35mm solid #cfd5e0; border-radius: 1.5mm; overflow: hidden; background: #fff; min-height: 34mm; }
        .pcard .ph { width: 25.5mm; height: 34mm; flex: 0 0 auto; background: #eef1f6; border-inline-end: .35mm solid #cfd5e0; }
        .pcard .ph img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pcard .ph img.ph-none { opacity: .55; }
        .pcard .tx { padding: 3mm 3.5mm 2.5mm 0; min-width: 0; display: flex; flex-direction: column; }
        .pcard .job { font-size: 7.2pt; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: {{ $goldInk }}; line-height: 1.25; }
        .pcard .nm { font-size: 12pt; font-weight: 800; color: {{ $navy }}; line-height: 1.2; margin-top: 1mm; overflow-wrap: anywhere; }
        .pcard .tx { flex: 1; }
        .pcard .cl { margin: auto -3.5mm -2.5mm 0; padding: 2mm 3.5mm; display: flex; align-items: center; gap: 1.8mm;
                     background: {{ $navy }}; color: #fff; font-size: 8.6pt; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; line-height: 1.25; }
        .pcard .cl i { font-style: normal; font-size: 6pt; font-weight: 800; letter-spacing: .14em; color: {{ $c }}; }
        .pcard .cl img { width: 6mm; height: 6mm; object-fit: contain; flex: 0 0 auto; }

