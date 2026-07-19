<?php

namespace Modules\Expenses\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AuditLog\Traits\Auditable;
use Modules\Tenancy\Models\Branch;

class Expense extends Model
{
    use Auditable, BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = [
        'business_id',
        'branch_id',
        'expense_category_id',
        'amount',
        'paid_via',
        'vendor',
        'notes',
        'expense_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
