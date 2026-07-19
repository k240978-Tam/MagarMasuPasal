<?php

namespace Modules\Sales\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AuditLog\Traits\Auditable;
use Modules\Customers\Models\Customer;
use Modules\Tenancy\Models\Branch;
use Modules\Tenancy\Models\BranchTerminal;

class Sale extends Model
{
    use Auditable, BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = [
        'business_id',
        'branch_id',
        'terminal_id',
        'customer_id',
        'cashier_id',
        'invoice_no',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'status',
        'sale_type',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function terminal()
    {
        return $this->belongsTo(BranchTerminal::class, 'terminal_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }
}
