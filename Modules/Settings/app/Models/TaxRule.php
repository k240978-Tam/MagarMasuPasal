<?php

namespace Modules\Settings\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'name',
        'rate',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
