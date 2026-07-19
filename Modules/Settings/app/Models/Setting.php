<?php

namespace Modules\Settings\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
