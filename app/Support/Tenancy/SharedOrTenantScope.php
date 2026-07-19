<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * For tables where business_id IS NULL means "platform default, visible to
 * every tenant" (e.g. Units' seeded kg/g/pcs) rather than "no tenant owns
 * this row" — TenantScope's strict equality would hide the shared defaults
 * entirely, so this scope keeps rows where business_id is null OR matches
 * the current tenant.
 */
class SharedOrTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        $column = $model->qualifyColumn('business_id');

        $builder->where(function (Builder $query) use ($column, $context) {
            $query->whereNull($column);

            if ($context->hasBusiness()) {
                $query->orWhere($column, $context->businessId());
            }
        });
    }
}
