<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Categories\Models\Category;
use Modules\Products\Models\Product;
use Modules\Purchases\Services\PurchaseService;
use Modules\Settings\Services\SettingsService;
use Modules\Suppliers\Models\Supplier;
use Modules\Tenancy\Models\Branch;
use Modules\Tenancy\Models\Business;
use Modules\Tenancy\Models\BusinessType;
use Modules\Units\Models\Unit;

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

        $owner = null;

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

            if ($member['role'] === 'Owner') {
                $owner = $user;
            }
        }

        $this->seedCatalogAndFirstPurchase($business, $branch, $owner);
    }

    private function seedCatalogAndFirstPurchase(Business $business, Branch $branch, User $owner): void
    {
        $categories = collect(['Chicken', 'Mutton', 'Buff', 'Vegetables'])
            ->mapWithKeys(fn (string $name) => [$name => Category::updateOrCreate(
                ['business_id' => $business->id, 'slug' => \Illuminate\Support\Str::slug($name)],
                ['name' => $name],
            )]);

        $kg = Unit::where('symbol', 'kg')->whereNull('business_id')->firstOrFail();
        $dz = Unit::where('symbol', 'dz')->whereNull('business_id')->firstOrFail();

        $products = [
            ['name' => 'Chicken Boneless', 'category' => 'Chicken', 'unit' => $kg, 'cost' => 420, 'sell' => 480, 'weight' => true],
            ['name' => 'Mutton Leg', 'category' => 'Mutton', 'unit' => $kg, 'cost' => 1050, 'sell' => 1200, 'weight' => true],
            ['name' => 'Buff Boneless', 'category' => 'Buff', 'unit' => $kg, 'cost' => 560, 'sell' => 650, 'weight' => true],
            ['name' => 'Mustard Greens', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 40, 'sell' => 60, 'weight' => true],
            ['name' => 'Farm Eggs', 'category' => 'Vegetables', 'unit' => $dz, 'cost' => 180, 'sell' => 210, 'weight' => false],
        ];

        $created = [];

        foreach ($products as $p) {
            $product = Product::updateOrCreate(
                ['business_id' => $business->id, 'name' => $p['name']],
                [
                    'unit_id' => $p['unit']->id,
                    'sell_by_weight' => $p['weight'],
                    'track_batches' => true,
                    'track_expiry' => $p['weight'],
                    'cost_price' => $p['cost'],
                    'selling_price' => $p['sell'],
                    'min_stock' => 5,
                    'status' => 'active',
                ],
            );

            $product->categories()->syncWithoutDetaching([$categories[$p['category']]->id]);
            $created[$p['name']] = $product;
        }

        $supplier = Supplier::updateOrCreate(
            ['business_id' => $business->id, 'name' => 'Kalimati Traders'],
            ['contact_person' => 'Hari Bahadur', 'phone' => '98410000' . rand(10, 99), 'status' => 'active'],
        );

        $purchaseService = app(PurchaseService::class);

        $order = $purchaseService->createOrder(
            [
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'reference_no' => 'PO-0001',
                'created_by' => $owner->id,
            ],
            [
                ['business_id' => $business->id, 'product_id' => $created['Chicken Boneless']->id, 'ordered_qty' => 20, 'unit_cost' => 420],
                ['business_id' => $business->id, 'product_id' => $created['Mutton Leg']->id, 'ordered_qty' => 10, 'unit_cost' => 1050],
            ],
        );

        $purchaseService->markOrdered($order);

        $purchaseService->receiveGoods($order, [
            [
                'purchase_order_item_id' => $order->items[0]->id,
                'quantity' => 20,
                'unit_cost' => 420,
                'batch_number' => 'B-0001',
                'expiry_date' => now()->addDays(4)->toDateString(),
            ],
            [
                'purchase_order_item_id' => $order->items[1]->id,
                'quantity' => 10,
                'unit_cost' => 1050,
                'batch_number' => 'B-0002',
                'expiry_date' => now()->addDays(4)->toDateString(),
            ],
        ]);
    }
}
