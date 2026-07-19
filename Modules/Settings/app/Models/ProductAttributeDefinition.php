<?php

namespace Modules\Settings\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
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

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }
}
