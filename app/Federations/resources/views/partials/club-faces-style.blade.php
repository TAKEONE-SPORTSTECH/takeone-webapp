{{-- The club lists with a face and a flag (FederationResultsPdf::clubPagesFor) — shared by the
     Results PDF and the competition report. Inside a <style>. --}}
        /* The club lists here carry a face and a flag, at a FIXED height —
           the server lays the pages out by it (FederationResultsPdf::CLUB_ROW_MM). */
        .ath.with-face { height: 15mm; align-items: center; gap: 2.2mm; padding: 0; overflow: hidden; }
        .ath.with-face .ph { width: 8.4mm; height: 11.2mm; flex: 0 0 auto; border-radius: .8mm; }
        /* One line, always: what still does not fit is set smaller (the .pfit script), never wrapped or cut. */
        .ath.with-face .n { display: flex; align-items: center; gap: 1.4mm; white-space: nowrap; overflow: hidden; min-width: 0; }
        .ath.with-face .n .fi { width: 4mm; height: 3mm; flex: 0 0 auto; border-radius: .4mm; box-shadow: 0 0 0 .2mm rgba(11,19,43,.12); }
        .ath.with-face .d { font-size: 6.2pt; line-height: 1.25; }
        .ath.with-face .m { align-self: center; padding-top: 0; }
