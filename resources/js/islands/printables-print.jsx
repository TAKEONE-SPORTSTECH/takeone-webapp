/**
 * printables-print.jsx — a design, page after page, at its real size, for the
 * browser's own Print / Save as PDF. Drawn by the SAME PageView as the editor,
 * so the paper matches the screen. Live fields show their sample values here;
 * a real recipient's print fills them from `values`.
 */
import React, { useEffect, useState } from 'react';
import { mountIsland } from '../island';
import { PageView } from './designer/Renderer';
import { setFonts, ensureFonts, fontsToLoad } from './designer/fonts';
import { documentFonts, documentTexts } from './designer/model';
import './designer/designer.css';

function Print({ boot }) {
    const [ready, setReady] = useState(false);
    // One recipient (values + stamp), or — for a print run — many, each drawn as the design's pages in turn.
    const items = boot.items || [{ values: boot.values || null, stamp: boot.stamp || null }];
    useEffect(() => {
        setFonts(boot.fonts);
        // The values filling the {tokens} count too: a design set in a Latin face prints
        // an Arabic club name with its Arabic companion, not with nothing.
        const texts = [...documentTexts(boot.document), ...items.flatMap((it) => Object.values(it.values || {})), ...(boot.fields || []).map((f) => f.sample)];
        ensureFonts(fontsToLoad(documentFonts(boot.document), texts)).then(() => document.fonts.ready).then(() => setReady(true));
    }, [boot]);

    const base = {
        assets: Object.fromEntries((boot.assets || []).map((a) => [a.uuid, a])),
        fields: Object.fromEntries((boot.fields || []).map((f) => [f.key, f])),
        print: true,
    };

    return (
        <div className="dzp" data-ready={ready ? '1' : '0'}>
            {items.map((it, i) => {
                const ctx = { ...base, values: it.values || null };
                return boot.document.pages.map((p) => (
                    <div key={`${i}-${p.id}`} className="dzp-sheet" style={{ width: boot.size.w, height: boot.size.h, position: 'relative', overflow: 'hidden' }}>
                        <PageView page={p} size={boot.size} ctx={ctx} />
                        {it.stamp && <div className="dzp-stamp" style={{ fontSize: Math.min(boot.size.w, boot.size.h) / 13 }}>{it.stamp}</div>}
                    </div>
                ));
            })}
        </div>
    );
}

mountIsland('#printables-print', () => <Print boot={JSON.parse(document.getElementById('printables-boot').textContent)} />);
