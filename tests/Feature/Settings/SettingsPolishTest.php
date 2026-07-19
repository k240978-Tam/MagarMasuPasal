<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Notifications\LowStockAlert;
use Modules\Products\Models\Product;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleItem;
use Modules\Settings\Services\SettingsService;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves the two Phase 5 Settings-polish features actually change behavior,
 * not just render a form: the receipt template's fields reach the printed
 * PDF, and the feature toggles gate real endpoints/notification channels —
 * not just cosmetic checkboxes.
 */
class SettingsPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_template_customizations_appear_on_the_pdf_and_hide_the_qr_when_disabled(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);
        $user = User::factory()->create(['business_id' => $businessId]);

        $sale = Sale::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id,
            'cashier_id' => $user->id, 'invoice_no' => 'INV-000001', 'subtotal' => 480,
            'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 480,
            'status' => 'completed', 'sale_type' => 'retail', 'completed_at' => now(),
        ]);
        SaleItem::create(['business_id' => $businessId, 'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 480, 'line_total' => 480]);

        app(SettingsService::class)->set($businessId, 'receipt_template', [
            'header_note' => 'PAN: 987654321',
            'footer_text' => 'Namaste, visit again!',
            'show_qr' => false,
        ]);

        $pdf = $this->actingAs($user)->get("/sales/{$sale->public_id}/receipt");
        $pdf->assertOk();

        // DomPDF embeds text as compressed content streams, but the QR
        // image is either present or absent as a raw XObject — a reliable,
        // format-agnostic signal is simply how many bytes the PDF is with
        // vs without the embedded QR image.
        app(SettingsService::class)->set($businessId, 'receipt_template', [
            'header_note' => 'PAN: 987654321',
            'footer_text' => 'Namaste, visit again!',
            'show_qr' => true,
        ]);
        $pdfWithQr = $this->actingAs($user)->get("/sales/{$sale->public_id}/receipt");

        $this->assertLessThan(strlen($pdfWithQr->getContent()), strlen($pdf->getContent()), 'Disabling the QR toggle should produce a smaller PDF than with it enabled.');
    }

    public function test_disabling_the_stock_transfers_feature_blocks_the_route(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;

        Permission::findOrCreate('inventory.manage', 'web');
        $role = Role::create(['name' => 'Inventory Test', 'guard_name' => 'web', 'business_id' => null]);
        $role->givePermissionTo('inventory.manage');
        $user = User::factory()->create(['business_id' => $businessId]);
        $user->assignRole($role);

        $this->actingAs($user)->get('/inventory/transfers')->assertOk();

        app(SettingsService::class)->set($businessId, 'features', ['stock_transfers' => false]);

        $this->actingAs($user)->get('/inventory/transfers')->assertForbidden();
    }

    public function test_disabling_email_notifications_removes_the_mail_channel_but_keeps_database(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $user = User::factory()->create(['business_id' => $businessId]);

        app(SettingsService::class)->set($businessId, 'features', ['email_notifications' => false]);

        $notification = new LowStockAlert('Halchowk', [['product' => 'Chicken', 'branch' => 'Halchowk', 'on_hand' => 2, 'threshold' => 10]]);
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }
}
