<?php

namespace Modules\Accounting\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BankReconciliationItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'bank_reconciliation_id',
        'journal_entry_line_id',
        'is_matched',
    ];

    protected $casts = [
        'is_matched' => 'boolean',
    ];

    public function bankReconciliation()
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function journalEntryLine()
    {
        return $this->belongsTo(JournalEntryLine::class);
    }
}
