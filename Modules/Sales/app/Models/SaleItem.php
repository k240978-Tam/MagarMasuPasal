<?php

namespace Modules\Sales\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;

class SaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'sale_id',
        'product_id',
        'batch_id',
        'quantity',
        'unit_price',
        'cost_price_at_sale',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'cost_price_at_sale' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(PurchaseBatch::class, 'batch_id');
    }
}
