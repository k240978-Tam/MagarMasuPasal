<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'business_id',
        'bank_account_id',
        'statement_date',
        'statement_closing_balance',
        'created_by',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_closing_balance' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BankReconciliationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class);
    }
}
