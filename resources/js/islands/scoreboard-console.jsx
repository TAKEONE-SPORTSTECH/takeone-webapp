/**
 * scoreboard-console.jsx — the Vite entry for the SCORING TABLE, both layouts.
 *
 * One entry, two instruments: the server has already decided which of them this
 * device is (the `isMobile` flag from the DetectDevice middleware, exactly as
 * the Blade path decides which document to render), and says so in the props.
 * They share every rule through console-state.js — only the layout differs.
 */

import React from 'react';
import { mountIsland } from '../island';
import ConsoleIsland from './scoreboard/Console';
import ConsoleMobileIsland from './scoreboard/ConsoleMobile';

mountIsland('#scoreboard-console-island', (el) => {
    let props = {};
    try {
        props = JSON.parse(el.getAttribute('data-island-props') || '{}');
    } catch (e) {
        console.error('[scoreboard console island] bad props', e);
    }

    return props.layout === 'mobile'
        ? <ConsoleMobileIsland props={props} />
        : <ConsoleIsland props={props} />;
});
