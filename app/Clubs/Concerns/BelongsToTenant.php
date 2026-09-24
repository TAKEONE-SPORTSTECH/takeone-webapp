<?php

namespace App\Clubs\Concerns;

use App\Clubs\Models\Scopes\TenantScope;

trait BelongsToTenant
{
    /**
     * Register the TenantScope global scope on every model that uses this trait.
     * Laravel calls boot{TraitName}() automatically during model boot.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
