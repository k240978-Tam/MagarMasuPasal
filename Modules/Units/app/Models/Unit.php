<?php

namespace Modules\Units\Models;

use App\Support\Tenancy\SharedOrTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AuditLog\Traits\Auditable;

/**
 * Units are special-cased relative to BelongsToTenant: a row with
 * business_id = null is a platform default visible to every tenant, while
 * a non-null business_id is one tenant's own custom unit. SharedOrTenantScope
 * enforces this automatically on every query (defaults + this tenant's own),
 * the same way TenantScope does for every other tenant-owned table — so a
 * plain `Unit::all()` can never leak another business's custom units.
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

    protected static function booted(): void
    {
        static::addGlobalScope(new SharedOrTenantScope);
    }

    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(SharedOrTenantScope::class);
    }

    public function baseUnit(): BelongsTo
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
