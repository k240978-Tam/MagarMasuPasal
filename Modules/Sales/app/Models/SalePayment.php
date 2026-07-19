<?php

namespace Modules\Sales\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\PaymentManager\Models\PaymentTransaction;

class SalePayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'sale_id',
        'payment_transaction_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function transaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
