<?php

namespace Modules\Tenancy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tenancy\Models\BusinessType;

/**
 * @extends Factory<BusinessType>
 */
class BusinessTypeFactory extends Factory
{
    protected $model = BusinessType::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2, false),
            'name' => fake()->words(2, true),
            'default_config' => [
                'default_units' => ['pcs'],
                'sell_by_weight_default' => false,
                'default_attributes' => [],
                'default_customer_groups' => ['Retail'],
                'receipt_sections' => ['items', 'totals', 'thank_you'],
            ],
        ];
    }
}
