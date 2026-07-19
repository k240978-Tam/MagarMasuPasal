<?php

namespace Modules\Units\Models;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Modules\AuditLog\Traits\Auditable;

/**
 * Units are special-cased relative to BelongsToTenant: a row with
 * business_id = null is a platform default visible to every tenant, while
 * a non-null business_id is one tenant's own custom unit. Queries therefore
 * use scopeForCurrentTenant() (defaults + this tenant's own) rather than the
 * strict equality global scope every other tenant-owned table uses.
 */
class Unit extends Model
{
    use Auditable;

    protected $fillable = [
        'business_id',
        'name',
        'symbol',
        'base_unit_id',
        'conversion_factor',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
    ];

    public function scopeForCurrentTenant($query)
    {
        $businessId = app(TenantContext::class)->businessId();

        return $query->where(function ($q) use ($businessId) {
            $q->whereNull('business_id');

            if ($businessId) {
                $q->orWhere('business_id', $businessId);
            }
        });
    }

    public function baseUnit()
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }

    /**
     * Converts a quantity expressed in this unit into its base unit
     * (e.g. 250 g -> 0.25 kg), or returns it unchanged if this unit has no base.
     */
    public function toBaseQuantity(float $quantity): float
    {
        return $this->base_unit_id ? $quantity * (float) $this->conversion_factor : $quantity;
    }
}
