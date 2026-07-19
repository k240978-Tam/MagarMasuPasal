<?php

namespace Modules\Inventory\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\AuditLog\Traits\Auditable;
use Modules\Tenancy\Models\Branch;

class StockTransfer extends Model
{
    use Auditable, BelongsToTenant, HasPublicId;

    protected $fillable = [
        'business_id',
        'from_branch_id',
        'to_branch_id',
        'status',
        'notes',
        'created_by',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
