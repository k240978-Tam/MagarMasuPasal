<?php

namespace Modules\Tenancy\Models;

use App\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The tenant root. Every tenant-scoped table hangs off business_id; this
 * model itself is deliberately not tenant-scoped, since it IS the tenant.
 */
class Business extends Model
{
    use HasPublicId, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'business_type_id',
        'pan_vat_number',
        'currency',
        'timezone',
        'logo_media_id',
        'status',
    ];

    protected $casts = [
        // Nepal's PAN/VAT registration number — a government tax ID never
        // looked up by WHERE clause, so encrypting it costs nothing.
        'pan_vat_number' => 'encrypted',
    ];

    /**
     * @return BelongsTo<BusinessType, $this>
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
