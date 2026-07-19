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
