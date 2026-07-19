<?php

namespace Modules\Settings\Services;

use Modules\Settings\Models\TaxRule;

/**
 * The tax rule engine: a product is only taxed if it explicitly references
 * an active TaxRule. A business with no tax rules configured at all taxes
 * nothing, which keeps every existing sale/report untouched until an Owner
 * opts a category of goods in (e.g. fresh meat exempt, packaged snacks
 * taxed at 13% VAT) — a real distinction Nepali retailers need, not a
 * flat per-business rate.
 */
class TaxCalculationService
{
    public function rateForRule(int $businessId, ?int $taxRuleId): float
    {
        if (! $taxRuleId) {
            return 0.0;
        }

        $rule = TaxRule::withoutTenantScope()
            ->where('id', $taxRuleId)
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        return $rule ? (float) $rule->rate : 0.0;
    }
}
