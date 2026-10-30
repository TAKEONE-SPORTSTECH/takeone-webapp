<?php

namespace App\Support\Modules;

/**
 * A module that has to listen to something when the platform boots — a model
 * event in another module, say.
 *
 * Without this, the only place for such a listener was a shared provider
 * (EventPackageServiceProvider already carries the certificates' one), which
 * welds the listening module into a file it does not own: delete the module
 * and the provider still names it. Implementing this keeps the listener inside
 * the module's own folder.
 *
 * Called once, from ModuleServiceProvider::boot(), after the module's routes,
 * views and migrations are bound. Keep it cheap: it runs on every request.
 */
interface Boots
{
    public function boot(): void;
}
