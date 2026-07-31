<?php

namespace Modules\Settings\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Models\BusinessType;

class ProductAttributeDefinition extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'business_type_id',
        'key',
        'label',
        'data_type',
        'options',
        'is_required',
        'applies_to',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    /**
     * @return BelongsTo<BusinessType, $this>
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
