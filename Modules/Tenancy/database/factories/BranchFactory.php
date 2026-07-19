<?php

namespace Modules\Tenancy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tenancy\Models\Branch;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'business_id' => BusinessFactory::new(),
            'name' => fake()->city(),
            'address' => fake()->address(),
            'is_main' => true,
            'status' => 'active',
        ];
    }
}
