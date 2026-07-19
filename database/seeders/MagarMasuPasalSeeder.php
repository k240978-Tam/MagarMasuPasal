<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Settings\Services\SettingsService;
use Modules\Tenancy\Models\Branch;
use Modules\Tenancy\Models\Business;
use Modules\Tenancy\Models\BusinessType;

/**
 * Bootstraps the platform's first real tenant: Magar Masu Pasal Tatha Anya
 * Tarkari (Halchowk, Kathmandu). Everything here is data, not code — proving
 * that onboarding a meat shop takes zero business-type-specific classes,
 * only a BusinessType selection and the generic seeders already in place.
 */
class MagarMasuPasalSeeder extends Seeder
{
    public function run(): void
    {
        $businessType = BusinessType::where('key', 'meat_shop')->firstOrFail();

        $business = Business::updateOrCreate(
            ['name' => 'Magar Masu Pasal Tatha Anya Tarkari'],
            [
                'legal_name' => 'Magar Masu Pasal Tatha Anya Tarkari',
                'business_type_id' => $businessType->id,
                'currency' => 'NPR',
                'timezone' => 'Asia/Kathmandu',
                'status' => 'active',
            ],
        );

        app(SettingsService::class)->applyBusinessTypeDefaults($business->id, $businessType->default_config);

        $branch = Branch::updateOrCreate(
            ['business_id' => $business->id, 'name' => 'Halchowk'],
            [
                'address' => 'Halchowk, Kathmandu, Nepal',
                'is_main' => true,
                'status' => 'active',
            ],
        );

        $staff = [
            ['name' => 'Sita Magar', 'email' => 'owner@magarmasupasal.test', 'role' => 'Owner'],
            ['name' => 'Ram Thapa', 'email' => 'manager@magarmasupasal.test', 'role' => 'Manager'],
            ['name' => 'Gita Rai', 'email' => 'cashier@magarmasupasal.test', 'role' => 'Cashier'],
        ];

        foreach ($staff as $member) {
            $user = User::updateOrCreate(
                ['business_id' => $business->id, 'email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => 'password',
                    'default_branch_id' => $branch->id,
                    'status' => 'active',
                ],
            );

            $user->branches()->syncWithoutDetaching([$branch->id => ['is_default' => true]]);
            $user->syncRoles([$member['role']]);
        }
    }
}
