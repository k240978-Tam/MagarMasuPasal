<?php

namespace Modules\Customers\Models;

use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AuditLog\Traits\Auditable;

class Customer extends Model
{
    use Auditable, BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = [
        'business_id',
        'customer_group_id',
        'name',
        'phone',
        'email',
        'address',
        'current_due',
    ];

    protected $casts = [
        'current_due' => 'decimal:2',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function allowsCredit(): bool
    {
        return (bool) $this->group?->allow_credit;
    }

    public function creditAvailable(): ?float
    {
        if (! $this->allowsCredit() || $this->group->credit_limit === null) {
            return null;
        }

        return (float) $this->group->credit_limit - (float) $this->current_due;
    }
}
