<?php

namespace Modules\Inventory\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;
use Modules\Tenancy\Models\Branch;

class StockMovement extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'batch_id',
        'type',
        'quantity_change',
        'reference_type',
        'reference_id',
        'unit_cost_at_movement',
        'created_by',
    ];

    protected $casts = [
        'quantity_change' => 'decimal:3',
        'unit_cost_at_movement' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<PurchaseBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PurchaseBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
