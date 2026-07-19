<?php

namespace Modules\Purchases\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
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

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function outstandingQty(): float
    {
        return (float) $this->ordered_qty - (float) $this->received_qty;
    }
}
