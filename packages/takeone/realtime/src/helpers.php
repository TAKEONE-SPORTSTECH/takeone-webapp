<?php

use Takeone\Realtime\RealtimeManager;

if (! function_exists('Realtime')) {
    /**
     * Resolve the realtime manager singleton.
     *
     * Usage: Realtime()->publishToUser($id, 'messages', [...])
     */
    function Realtime(): RealtimeManager
    {
        return app(RealtimeManager::class);
    }
}
