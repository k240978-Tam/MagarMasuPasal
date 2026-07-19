<?php

namespace Modules\AuditLog\Services;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Modules\AuditLog\Models\AuditLog;

/**
 * Single write path for audit_logs. The Auditable trait calls this
 * automatically for create/update/delete; services fire domain-specific
 * actions through it directly (e.g. 'sale.refunded', 'permission.changed')
 * for events that aren't a plain model mutation.
 */
class AuditLogger
{
    public function __construct(protected TenantContext $context) {}

    public function record(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $businessId = null,
    ): AuditLog {
        return AuditLog::withoutTenantScope()->create([
            'business_id' => $businessId ?? $this->context->businessId(),
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
