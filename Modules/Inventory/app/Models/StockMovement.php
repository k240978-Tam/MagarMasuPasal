<?php

namespace Modules\Inventory\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(PurchaseBatch::class, 'batch_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
