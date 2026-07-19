<?php

namespace Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Products\Models\Product;

class StockTransferItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'stock_transfer_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
