<?php

namespace Modules\Accounting\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_id',
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
