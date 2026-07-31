<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Categories\Models\Category;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Services\CustomerGroupService;
use Modules\Expenses\Models\ExpenseCategory;
use Modules\Expenses\Services\ExpenseService;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseOrder;
use Modules\Purchases\Services\PurchaseService;
use Modules\Sales\DTOs\FinalizeSaleDTO;
use Modules\Sales\Services\SaleService;
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
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

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
        $created = $this->seedCatalog($business);

        // Everything above this line is safe to re-run (updateOrCreate
        // throughout). Opening capital, the first purchase, expenses, and
        // the demo sale are one-time financial *events*, not reference
        // data — re-running php artisan migrate --seed on an already-
        // seeded database must not double-post them, which is exactly
        // what a plain ::create() call would do (and did, before this
        // guard: a UNIQUE constraint violation on purchase_orders'
        // reference_no the moment anyone re-ran the seeder).
        $firstRun = ! PurchaseOrder::where('business_id', $business->id)->where('reference_no', 'PO-0001')->exists();

        if ($firstRun) {
            $this->seedOpeningCapital($business, $branch, $owner);
            $this->seedFirstPurchase($business, $branch, $owner, $created);
            $this->seedExpenses($business, $branch, $owner);
            $this->seedDemoSale($business, $branch, $terminal, $owner, $created['Chicken Boneless']);
        }

        $this->command?->info("POS terminal ready: /pos/terminals/{$terminal->public_id}");
        $this->command?->info("Customer display ready: /display/terminals/{$terminal->public_id}");
    }

    private function seedOpeningCapital(Business $business, Branch $branch, User $owner): void
    {
        app(JournalEntryService::class)->post(
            businessId: $business->id,
            branchId: $branch->id,
            description: "Owner's opening capital",
            entryDate: now()->subDays(30)->toDateString(),
            lines: [
                ['account_code' => ChartOfAccountsService::BANK, 'debit' => 100000],
                ['account_code' => ChartOfAccountsService::OWNERS_EQUITY, 'credit' => 100000],
            ],
            createdBy: $owner->id,
        );
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

    private function seedExpenses(Business $business, Branch $branch, User $owner): void
    {
        $rent = ExpenseCategory::updateOrCreate(['business_id' => $business->id, 'name' => 'Rent']);
        $utilities = ExpenseCategory::updateOrCreate(['business_id' => $business->id, 'name' => 'Utilities']);

        $expenseService = app(ExpenseService::class);

        $expenseService->record([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'expense_category_id' => $rent->id,
            'amount' => 25000,
            'paid_via' => 'bank',
            'vendor' => 'Halchowk Landlord',
            'expense_date' => now()->startOfMonth()->toDateString(),
            'created_by' => $owner->id,
        ]);

        $expenseService->record([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'expense_category_id' => $utilities->id,
            'amount' => 3200,
            'paid_via' => 'cash',
            'vendor' => 'Nepal Electricity Authority',
            'expense_date' => now()->toDateString(),
            'created_by' => $owner->id,
        ]);
    }

    private function seedDemoSale(Business $business, Branch $branch, BranchTerminal $terminal, User $owner, Product $chicken): void
    {
        app(SaleService::class)->finalize(new FinalizeSaleDTO(
            businessId: $business->id,
            branchId: $branch->id,
            terminalId: $terminal->id,
            cashierId: $owner->id,
            items: [
                ['product_id' => $chicken->id, 'quantity' => 1.5, 'unit_price' => (float) $chicken->selling_price],
            ],
            payments: [
                ['gateway_key' => 'cash', 'amount' => round(1.5 * (float) $chicken->selling_price, 2)],
            ],
        ));
    }

    /**
     * The real launch catalog: "Tatha Anya Tarkari" (and other vegetables)
     * in the business name means this is a meat shop that also sells
     * everyday vegetables, not a pure butcher — reflected in the category
     * mix below. Expiry windows vary by category (fresh meat spoils fastest,
     * eggs keep longest) rather than the single flat offset the Phase 0-1
     * demo data used. Product/category upserts only — always safe to
     * re-run, so it stays outside the $firstRun guard in run().
     *
     * @return array<string, Product>
     */
    private function seedCatalog(Business $business): array
    {
        $categories = collect(['Chicken', 'Mutton', 'Buff', 'Pork', 'Eggs', 'Vegetables'])
            ->mapWithKeys(fn (string $name) => [$name => Category::updateOrCreate(
                ['business_id' => $business->id, 'slug' => Str::slug($name)],
                ['name' => $name],
            )]);

        $created = [];

        foreach ($this->productCatalog() as $p) {
            $product = Product::updateOrCreate(
                ['business_id' => $business->id, 'name' => $p['name']],
                [
                    'unit_id' => $p['unit']->id,
                    'sell_by_weight' => $p['weight'],
                    'track_batches' => true,
                    'track_expiry' => true,
                    'cost_price' => $p['cost'],
                    'selling_price' => $p['sell'],
                    'min_stock' => 5,
                    'status' => 'active',
                ],
            );

            $product->categories()->syncWithoutDetaching([$categories[$p['category']]->id]);
            $created[$p['name']] = $product;
        }

        return $created;
    }

    /**
     * The opening stock delivery — a one-time financial/inventory event
     * (creates a PurchaseOrder, receives it into stock via FIFO batches),
     * not reference data, so this only ever runs once per business (see
     * the $firstRun guard in run()). $products here must mirror the same
     * list seedCatalog() just upserted, for the per-product stock/cost/
     * expiry figures.
     *
     * @param  array<string, Product>  $created
     */
    private function seedFirstPurchase(Business $business, Branch $branch, User $owner, array $created): void
    {
        $products = $this->productCatalog();

        $supplier = Supplier::updateOrCreate(
            ['business_id' => $business->id, 'name' => 'Kalimati Traders'],
            ['contact_person' => 'Hari Bahadur', 'phone' => '98410000'.rand(10, 99), 'status' => 'active'],
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
            'batch_number' => 'B-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'expiry_date' => now()->addDays($products[$index]['expiry_days'])->toDateString(),
        ])->all());
    }

    /**
     * The single source of truth for the launch catalog — used by both
     * seedCatalog() (product/category upserts, always safe to re-run) and
     * seedFirstPurchase() (the one-time opening stock delivery), so the
     * two never drift out of sync with each other.
     */
    private function productCatalog(): array
    {
        $kg = Unit::where('symbol', 'kg')->whereNull('business_id')->firstOrFail();
        $dz = Unit::where('symbol', 'dz')->whereNull('business_id')->firstOrFail();

        return [
            ['name' => 'Chicken Whole', 'category' => 'Chicken', 'unit' => $kg, 'cost' => 380, 'sell' => 440, 'weight' => true, 'stock' => 25, 'expiry_days' => 3],
            ['name' => 'Chicken Boneless', 'category' => 'Chicken', 'unit' => $kg, 'cost' => 420, 'sell' => 480, 'weight' => true, 'stock' => 20, 'expiry_days' => 3],
            ['name' => 'Chicken Breast', 'category' => 'Chicken', 'unit' => $kg, 'cost' => 440, 'sell' => 500, 'weight' => true, 'stock' => 15, 'expiry_days' => 3],
            ['name' => 'Chicken Wings', 'category' => 'Chicken', 'unit' => $kg, 'cost' => 320, 'sell' => 380, 'weight' => true, 'stock' => 12, 'expiry_days' => 3],
            ['name' => 'Chicken Drumstick', 'category' => 'Chicken', 'unit' => $kg, 'cost' => 340, 'sell' => 400, 'weight' => true, 'stock' => 15, 'expiry_days' => 3],
            ['name' => 'Chicken Liver', 'category' => 'Chicken', 'unit' => $kg, 'cost' => 280, 'sell' => 340, 'weight' => true, 'stock' => 6, 'expiry_days' => 2],

            ['name' => 'Mutton Leg', 'category' => 'Mutton', 'unit' => $kg, 'cost' => 1050, 'sell' => 1200, 'weight' => true, 'stock' => 10, 'expiry_days' => 3],
            ['name' => 'Mutton Curry Cut', 'category' => 'Mutton', 'unit' => $kg, 'cost' => 980, 'sell' => 1150, 'weight' => true, 'stock' => 12, 'expiry_days' => 3],
            ['name' => 'Mutton Ribs', 'category' => 'Mutton', 'unit' => $kg, 'cost' => 900, 'sell' => 1050, 'weight' => true, 'stock' => 8, 'expiry_days' => 3],
            ['name' => 'Mutton Liver', 'category' => 'Mutton', 'unit' => $kg, 'cost' => 850, 'sell' => 1000, 'weight' => true, 'stock' => 5, 'expiry_days' => 2],

            ['name' => 'Buff Boneless', 'category' => 'Buff', 'unit' => $kg, 'cost' => 560, 'sell' => 650, 'weight' => true, 'stock' => 15, 'expiry_days' => 3],
            ['name' => 'Buff Curry Cut', 'category' => 'Buff', 'unit' => $kg, 'cost' => 480, 'sell' => 570, 'weight' => true, 'stock' => 18, 'expiry_days' => 3],
            ['name' => 'Buff Mince (Keema)', 'category' => 'Buff', 'unit' => $kg, 'cost' => 520, 'sell' => 610, 'weight' => true, 'stock' => 10, 'expiry_days' => 2],

            ['name' => 'Pork Belly', 'category' => 'Pork', 'unit' => $kg, 'cost' => 480, 'sell' => 560, 'weight' => true, 'stock' => 10, 'expiry_days' => 3],
            ['name' => 'Pork Curry Cut', 'category' => 'Pork', 'unit' => $kg, 'cost' => 440, 'sell' => 520, 'weight' => true, 'stock' => 8, 'expiry_days' => 3],

            ['name' => 'Farm Eggs', 'category' => 'Eggs', 'unit' => $dz, 'cost' => 180, 'sell' => 210, 'weight' => false, 'stock' => 12, 'expiry_days' => 18],
            ['name' => 'Duck Eggs', 'category' => 'Eggs', 'unit' => $dz, 'cost' => 260, 'sell' => 300, 'weight' => false, 'stock' => 6, 'expiry_days' => 18],

            ['name' => 'Mustard Greens', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 40, 'sell' => 60, 'weight' => true, 'stock' => 25, 'expiry_days' => 5],
            ['name' => 'Potato', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 45, 'sell' => 60, 'weight' => true, 'stock' => 40, 'expiry_days' => 14],
            ['name' => 'Onion', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 70, 'sell' => 90, 'weight' => true, 'stock' => 35, 'expiry_days' => 14],
            ['name' => 'Tomato', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 60, 'sell' => 85, 'weight' => true, 'stock' => 20, 'expiry_days' => 5],
            ['name' => 'Cauliflower', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 50, 'sell' => 70, 'weight' => true, 'stock' => 18, 'expiry_days' => 5],
            ['name' => 'Spinach', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 35, 'sell' => 55, 'weight' => true, 'stock' => 15, 'expiry_days' => 3],
            ['name' => 'Green Beans', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 65, 'sell' => 90, 'weight' => true, 'stock' => 14, 'expiry_days' => 5],
            ['name' => 'Radish', 'category' => 'Vegetables', 'unit' => $kg, 'cost' => 30, 'sell' => 45, 'weight' => true, 'stock' => 20, 'expiry_days' => 7],
        ];
    }
}
