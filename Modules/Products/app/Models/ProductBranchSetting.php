<?php

namespace Modules\Products\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Models\Branch;

class ProductBranchSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'min_stock',
        'max_stock',
        'selling_price_override',
    ];

    protected $casts = [
        'min_stock' => 'decimal:3',
        'max_stock' => 'decimal:3',
        'selling_price_override' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
