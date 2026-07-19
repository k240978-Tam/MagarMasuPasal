<?php

namespace Modules\Suppliers\Models;

use App\Support\HasPublicId;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AuditLog\Traits\Auditable;

class Supplier extends Model
{
    use Auditable, BelongsToTenant, HasPublicId, SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'pan_vat_number',
        'bank_details',
        'current_due',
        'status',
    ];

    protected $casts = [
        'bank_details' => 'encrypted',
        'current_due' => 'decimal:2',
    ];

    protected array $auditExcept = ['bank_details'];
}
