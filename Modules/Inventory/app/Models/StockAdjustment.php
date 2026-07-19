<?php

namespace Modules\Inventory\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\AuditLog\Traits\Auditable;
use Modules\Tenancy\Models\Branch;

class StockAdjustment extends Model
{
    use Auditable, BelongsToTenant, HasPublicId;

    protected $fillable = [
        'business_id',
        'branch_id',
        'reason',
        'notes',
        'created_by',
        'approved_by',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
