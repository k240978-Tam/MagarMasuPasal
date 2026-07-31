<?php

namespace Modules\Purchases\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Products\Models\Product;

class PurchaseOrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'purchase_order_id',
        'product_id',
        'ordered_qty',
        'unit_cost',
        'received_qty',
    ];

    protected $casts = [
        'ordered_qty' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'received_qty' => 'decimal:3',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outstandingQty(): float
    {
        return (float) $this->ordered_qty - (float) $this->received_qty;
    }
}
