<?php

namespace Modules\Sales\Models;

use App\Models\User;
use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\AuditLog\Traits\Auditable;

class SaleReturn extends Model
{
    use Auditable, BelongsToTenant, HasPublicId;

    protected $fillable = [
        'business_id',
        'sale_id',
        'reason',
        'refund_method',
        'refund_amount',
        'approved_by',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
