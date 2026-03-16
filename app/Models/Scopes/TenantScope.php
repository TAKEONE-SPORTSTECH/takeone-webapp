<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Add WHERE tenant_id = ? to every query when a tenant is active for
     * the current request.  No-op on routes where no tenant is bound
     * (public pages, super-admin area, member-facing routes).
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('current.tenant')) {
            $builder->where(
                $model->getTable() . '.tenant_id',
                app('current.tenant')->id
            );
        }
    }
}
