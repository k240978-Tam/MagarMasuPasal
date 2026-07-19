<?php

namespace Modules\Customers\Services;

use Illuminate\Support\Str;
use Modules\Customers\Models\CustomerGroup;

class CustomerGroupService
{
    /**
     * Creates a tenant's starting customer groups from its BusinessType's
     * default_config['default_customer_groups'] (e.g. meat shop: Retail,
     * Credit; restaurant: Table, Takeaway, Delivery). A group whose name
     * contains "credit" gets credit enabled with a conservative starting
     * limit — the tenant can change or delete any of it afterward.
     */
    public function applyBusinessTypeDefaults(int $businessId, array $defaultConfig): void
    {
        foreach ($defaultConfig['default_customer_groups'] ?? ['Retail'] as $name) {
            $isCredit = Str::contains(Str::lower($name), 'credit');

            CustomerGroup::updateOrCreate(
                ['business_id' => $businessId, 'name' => $name],
                [
                    'allow_credit' => $isCredit,
                    'credit_limit' => $isCredit ? 20000 : null,
                ],
            );
        }
    }
}
