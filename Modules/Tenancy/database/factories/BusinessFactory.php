<?php

namespace Modules\Tenancy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tenancy\Models\Business;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'business_type_id' => BusinessTypeFactory::new(),
            'currency' => 'NPR',
            'timezone' => 'Asia/Kathmandu',
            'status' => 'active',
        ];
    }
}
