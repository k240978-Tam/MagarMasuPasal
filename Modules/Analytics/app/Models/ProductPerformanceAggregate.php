<?php

namespace Modules\Analytics\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Products\Models\Product;

class ProductPerformanceAggregate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'date',
        'quantity_sold',
        'revenue',
        'profit',
    ];

    protected $casts = [
        'quantity_sold' => 'decimal:3',
        'revenue' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
