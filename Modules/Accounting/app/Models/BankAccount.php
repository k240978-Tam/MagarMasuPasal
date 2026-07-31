<?php

namespace Modules\Accounting\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'name',
        'bank_name',
        'account_number',
        'opening_balance',
        'chart_of_accounts_id',
    ];

    protected $casts = [
        'account_number' => 'encrypted',
        'opening_balance' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_accounts_id');
    }
}
