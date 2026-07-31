<?php

namespace Modules\Expenses\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccount;

class ExpenseCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'name',
        'chart_of_accounts_id',
    ];

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_accounts_id');
    }
}
