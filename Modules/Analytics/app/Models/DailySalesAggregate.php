<?php

namespace Modules\Analytics\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DailySalesAggregate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'branch_id',
        'date',
        'total_sales',
        'total_tax',
        'total_discount',
        'transaction_count',
        'gross_profit',
    ];

    protected $casts = [
        'total_sales' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'gross_profit' => 'decimal:2',
    ];
}
