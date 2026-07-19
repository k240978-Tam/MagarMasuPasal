<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Settings\Models\Setting;

/**
 * Typed access to the per-tenant settings store. Values are cached per
 * business+key since Settings::get() is called on hot paths (e.g. POS
 * reading pos.default_discount_max_percent on every cart mutation).
 */
class SettingsService
{
    /**
     * The full catalog of togglable features, keyed by flag id => label.
     * Every flag defaults to enabled — a business opts OUT, not in, so
     * existing tenants (and every test fixture that never touches this
     * settings key) keep today's behavior unchanged.
     */
    public const FEATURE_FLAGS = [
        'stock_transfers' => 'Multi-branch stock transfers',
        'email_notifications' => 'Email notifications (in addition to in-app alerts)',
    ];

    public function isFeatureEnabled(int $businessId, string $flag): bool
    {
        $overrides = $this->get($businessId, 'features', []);

        return (bool) ($overrides[$flag] ?? true);
    }

    public function get(int $businessId, string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            "settings.{$businessId}.{$key}",
            fn () => Setting::withoutTenantScope()
                ->where('business_id', $businessId)
                ->where('key', $key)
                ->value('value') ?? $default
        );
    }

    public function set(int $businessId, string $key, mixed $value): Setting
    {
        Cache::forget("settings.{$businessId}.{$key}");

        return Setting::withoutTenantScope()->updateOrCreate(
            ['business_id' => $businessId, 'key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Seeds a new business's configurable defaults (units/attributes/customer
     * groups it should start with) from its BusinessType.default_config —
     * copied once at creation so the tenant can freely diverge afterward.
     */
    public function applyBusinessTypeDefaults(int $businessId, array $defaultConfig): void
    {
        $this->set($businessId, 'business_type_defaults', $defaultConfig);
    }
}
