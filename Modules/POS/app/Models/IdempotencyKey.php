<?php

namespace Modules\POS\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'key',
        'response_status',
        'response_body',
    ];

    protected $casts = [
        'response_body' => 'array',
    ];
}
