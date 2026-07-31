<?php

namespace Modules\Sales\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'sale_return_id',
        'sale_item_id',
        'quantity',
        'refund_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'refund_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<SaleReturn, $this>
     */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /**
     * @return BelongsTo<SaleItem, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
