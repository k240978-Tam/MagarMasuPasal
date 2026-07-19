<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Models\Branch;

class JournalEntry extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'business_id',
        'branch_id',
        'reference_type',
        'reference_id',
        'description',
        'entry_date',
        'created_by',
        'status',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
