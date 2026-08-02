<?php

namespace Modules\Ecommerce\Models;

use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tenancy\Models\Business;

class OnlineOrder extends Model
{
    use BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = [
        'business_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'delivery_address',
        'status',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnlineOrderItem::class);
    }
}
