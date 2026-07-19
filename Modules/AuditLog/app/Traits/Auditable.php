<?php

namespace Modules\AuditLog\Traits;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Modules\AuditLog\Services\AuditLogger;

/**
 * Opt-in trait for models that should generate an audit trail automatically
 * on create/update/delete, e.g. `use Auditable;` on Branch, Product, User.
 * Domain events with no natural model mutation (a refund, a permission
 * change) call AuditLogger::record() directly from the owning Service instead.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => static::writeAuditLog(
            $model, 'created', [], static::redactAuditFields($model, $model->getAttributes())
        ));

        static::updated(function (Model $model) {
            $changes = static::redactAuditFields($model, $model->getChanges());
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $old = static::redactAuditFields($model, array_intersect_key($model->getOriginal(), $changes));

            static::writeAuditLog($model, 'updated', $old, $changes);
        });

        static::deleted(fn (Model $model) => static::writeAuditLog(
            $model, 'deleted', static::redactAuditFields($model, $model->getAttributes()), []
        ));
    }

    /**
     * Models with sensitive columns (User::password, two_factor_secret, ...)
     * define `protected array $auditExcept = [...]` to keep them out of the
     * audit trail entirely — the log records that a change happened, not the value.
     */
    protected static function redactAuditFields(Model $model, array $attributes): array
    {
        $except = $model->auditExcept ?? [];

        return array_diff_key($attributes, array_flip($except));
    }

    protected static function writeAuditLog(Model $model, string $event, array $old, array $new): void
    {
        $businessId = $model->getAttribute('business_id') ?? app(TenantContext::class)->businessId();

        if (! $businessId) {
            return;
        }

        $action = strtolower(class_basename($model)).'.'.$event;

        app(AuditLogger::class)->record($action, $model, $old, $new, $businessId);
    }
}
