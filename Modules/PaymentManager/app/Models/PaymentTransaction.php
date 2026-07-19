<?php

namespace Modules\PaymentManager\Models;

use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'business_id',
        'payable_type',
        'payable_id',
        'gateway_key',
        'amount',
        'currency',
        'status',
        'gateway_reference',
        'meta',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        // `array`, not just `encrypted` — meta will eventually hold real
        // gateway references/tokens (eSewa/Khalti) once those ship; never
        // queried by WHERE clause, so encrypting it at rest costs nothing now.
        'meta' => 'encrypted:array',
        'processed_at' => 'datetime',
    ];

    public function payable()
    {
        return $this->morphTo();
    }
}
