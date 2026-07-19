<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Models\Customer;
use Modules\PaymentManager\Models\PaymentTransaction;
use Modules\Suppliers\Models\Supplier;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Database\Factories\BusinessFactory;
use Tests\TestCase;

/**
 * Proves sensitive fields are actually encrypted at rest — not just that
 * the model returns the right value (a raw column check catches a mistyped
 * cast key that Eloquent would otherwise silently mask), and that a
 * frequently-searched field like Customer.phone was deliberately left
 * plaintext rather than accidentally caught by a blanket encryption pass.
 */
class EncryptedFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_pan_vat_number_is_encrypted_at_rest(): void
    {
        $business = BusinessFactory::new()->create(['pan_vat_number' => '301234567']);

        $raw = DB::table('businesses')->where('id', $business->id)->value('pan_vat_number');
        $this->assertNotSame('301234567', $raw);
        $this->assertSame('301234567', $business->fresh()->pan_vat_number);
    }

    public function test_supplier_pan_vat_number_and_bank_details_are_encrypted_at_rest(): void
    {
        $branch = BranchFactory::new()->create();
        $supplier = Supplier::create([
            'business_id' => $branch->business_id,
            'name' => 'Kalimati Traders',
            'pan_vat_number' => '600112233',
            'bank_details' => 'NIC Asia, A/C 001-002-003',
        ]);

        $raw = DB::table('suppliers')->where('id', $supplier->id)->first();
        $this->assertNotSame('600112233', $raw->pan_vat_number);
        $this->assertNotSame('NIC Asia, A/C 001-002-003', $raw->bank_details);

        $supplier->refresh();
        $this->assertSame('600112233', $supplier->pan_vat_number);
        $this->assertSame('NIC Asia, A/C 001-002-003', $supplier->bank_details);
    }

    public function test_payment_transaction_meta_is_encrypted_at_rest(): void
    {
        $branch = BranchFactory::new()->create();
        $transaction = PaymentTransaction::create([
            'business_id' => $branch->business_id,
            'payable_type' => 'test',
            'payable_id' => 1,
            'gateway_key' => 'manual_qr',
            'amount' => 100,
            'status' => 'captured',
            'meta' => ['reference' => 'QR-REF-12345'],
        ]);

        $raw = DB::table('payment_transactions')->where('id', $transaction->id)->value('meta');
        $this->assertStringNotContainsString('QR-REF-12345', (string) $raw);
        $this->assertSame(['reference' => 'QR-REF-12345'], $transaction->fresh()->meta);
    }

    public function test_customer_phone_stays_plaintext_and_searchable(): void
    {
        $branch = BranchFactory::new()->create();
        $customer = Customer::create([
            'business_id' => $branch->business_id,
            'name' => 'Hotel Everest',
            'phone' => '9800000000',
        ]);

        // Deliberately plaintext: POS looks customers up by phone, which an
        // `encrypted` cast (non-deterministic ciphertext) would break.
        $raw = DB::table('customers')->where('id', $customer->id)->value('phone');
        $this->assertSame('9800000000', $raw);

        $found = Customer::withoutTenantScope()->where('phone', '9800000000')->first();
        $this->assertSame($customer->id, $found->id);
    }
}
