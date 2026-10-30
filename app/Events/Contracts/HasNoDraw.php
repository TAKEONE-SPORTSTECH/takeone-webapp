<?php

namespace App\Events\Contracts;

/**
 * An event type that never has a draw — an open mat, a sparring session.
 *
 * The shared scoring screens send their operator "back to the draw" through
 * me.events.bracket, because for a championship that is where the next bout
 * is. For these types the draw desk is empty and has no way back to the
 * console, which is where the session is ENDED — so an operator who started a
 * bout could never end the session (found 2026-10-30). The draw routes send a
 * type that declares this to its console instead.
 */
interface HasNoDraw
{
}
