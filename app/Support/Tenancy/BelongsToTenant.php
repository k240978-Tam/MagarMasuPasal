<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * Applied to every tenant-scoped Eloquent model. Auto-filters all queries by
 * the current TenantContext and stamps business_id on create when it isn't
 * already set. This is the single enforcement point for tenant isolation at
 * the query layer — see docs/architecture/01-system-architecture.md §1.4.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (empty($model->business_id)) {
                $context = app(TenantContext::class);

                if ($context->hasBusiness()) {
                    $model->business_id = $context->businessId();
                }
            }
        });
    }

    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
