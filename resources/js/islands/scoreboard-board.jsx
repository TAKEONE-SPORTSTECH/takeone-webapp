/**
 * scoreboard-board.jsx — the Vite entry for the Brazilian Jiu-Jitsu MAT SCREEN.
 *
 * Loaded only by `bjj/screen/mat-react.blade.php`, and that shell is served only
 * when `features.react_scoreboard` is on. Registering the island at module top
 * level is safe: mountIsland only RECORDS it here, and the mount is driven by
 * the runtime in ../island.js.
 */

import React from 'react';
import { mountIsland } from '../island';
import BoardIsland from './scoreboard/Board';

mountIsland('#scoreboard-island', (el) => {
    let props = {};
    try {
        props = JSON.parse(el.getAttribute('data-island-props') || '{}');
    } catch (e) {
        console.error('[scoreboard island] bad props', e);
    }
    return <BoardIsland props={props} />;
});
