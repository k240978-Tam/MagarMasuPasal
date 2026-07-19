<?php

namespace Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Products\Models\Product;

class StockAdjustmentItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'stock_adjustment_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function stockAdjustment()
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
