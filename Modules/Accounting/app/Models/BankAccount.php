<?php

namespace Modules\Accounting\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

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

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_accounts_id');
    }
}
