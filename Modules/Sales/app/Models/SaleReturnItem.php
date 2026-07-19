<?php

namespace Modules\Sales\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'sale_return_id',
        'sale_item_id',
        'quantity',
        'refund_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'refund_amount' => 'decimal:2',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}
