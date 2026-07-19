<?php

namespace Modules\Purchases\Models;

use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Products\Models\Product;
use Modules\Tenancy\Models\Branch;

class PurchaseBatch extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'purchase_order_item_id',
        'batch_number',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'expiry_date',
        'received_at',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'quantity_remaining' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'expiry_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
