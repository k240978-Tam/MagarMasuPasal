<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Categories\Models\Category;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Services\CustomerGroupService;
use Modules\Products\Models\Product;
use Modules\Purchases\Services\PurchaseService;
use Modules\Settings\Services\SettingsService;
use Modules\Suppliers\Models\Supplier;
use Modules\Tenancy\Models\Branch;
use Modules\Tenancy\Models\BranchTerminal;
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
        app(CustomerGroupService::class)->applyBusinessTypeDefaults($business->id, $businessType->default_config);

        $branch = Branch::updateOrCreate(
            ['business_id' => $business->id, 'name' => 'Halchowk'],
            [
                'address' => 'Halchowk, Kathmandu, Nepal',
                'is_main' => true,
                'status' => 'active',
            ],
        );

        $terminal = BranchTerminal::updateOrCreate(
            ['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Counter 1'],
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

        $this->seedCustomers($business);
        $this->seedCatalogAndFirstPurchase($business, $branch, $owner);

        $this->command?->info("POS terminal ready: /pos/terminals/{$terminal->public_id}");
        $this->command?->info("Customer display ready: /display/terminals/{$terminal->public_id}");
    }

    private function seedCustomers(Business $business): void
    {
        $retail = CustomerGroup::where('business_id', $business->id)->where('name', 'Retail')->first();
        $credit = CustomerGroup::where('business_id', $business->id)->where('name', 'Credit')->first();

        Customer::updateOrCreate(
            ['business_id' => $business->id, 'phone' => '9841000001'],
            ['name' => 'Bikash Shrestha', 'customer_group_id' => $retail?->id],
        );

        Customer::updateOrCreate(
            ['business_id' => $business->id, 'phone' => '9841000002'],
            ['name' => 'Hotel Everest Kitchen', 'customer_group_id' => $credit?->id],
        );
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
            ['name' => 'Chicken Boneless', 'category' => 'Chicken', 'unit' => $kg, 'cost' => 420, 'sell' => 480, 'weight' => true, 'stock' => 20],
            ['name' => 'Mutton Leg', 'category' => 'Mutton', 'unit' => $kg, 'cost' => 1050, 'sell' => 1200, 'weight' => true, 'stock' => 10],
            ['name' => 'Buff Boneless', 'category' => 'Buff', 'unit' => $kg, 'cost' => 560, 'sell' => 650, 'weight' => true, 'stock' => 15],
            ['name' => 'Mustard Greens', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 40, 'sell' => 60, 'weight' => true, 'stock' => 25],
            ['name' => 'Farm Eggs', 'category' => 'Vegetables', 'unit' => $dz, 'cost' => 180, 'sell' => 210, 'weight' => false, 'stock' => 12],
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
            collect($products)->map(fn ($p) => [
                'business_id' => $business->id,
                'product_id' => $created[$p['name']]->id,
                'ordered_qty' => $p['stock'],
                'unit_cost' => $p['cost'],
            ])->all(),
        );

        $purchaseService->markOrdered($order);

        $purchaseService->receiveGoods($order, $order->items->map(fn ($item, $index) => [
            'purchase_order_item_id' => $item->id,
            'quantity' => $products[$index]['stock'],
            'unit_cost' => $products[$index]['cost'],
            'batch_number' => 'B-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'expiry_date' => now()->addDays(4)->toDateString(),
        ])->all());
    }
}
