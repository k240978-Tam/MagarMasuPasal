<?php

namespace Modules\Sales\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'fiscal_year',
        'fiscal_sequence',
        'buyer_name',
        'buyer_pan',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'status',
        'sale_type',
        'notes',
        'completed_at',
        'print_count',
        'first_printed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'ird_sync_status',
        'ird_synced_at',
        'ird_sync_error',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
        'first_printed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'ird_synced_at' => 'datetime',
        'buyer_pan' => 'encrypted',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<BranchTerminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(BranchTerminal::class, 'terminal_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
}
