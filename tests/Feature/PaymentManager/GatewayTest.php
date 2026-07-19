<?php

namespace Tests\Feature\PaymentManager;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\PaymentManager\DTOs\CreatePaymentDTO;
use Modules\PaymentManager\Enums\PaymentStatus;
use Modules\PaymentManager\Services\PaymentManager;
use Modules\Tenancy\Database\Factories\BusinessFactory;
use Tests\TestCase;

/**
 * Proves the provider-independent contract from
 * docs/architecture/06-api-design.md §6.3: POS/Sales only ever talk to
 * PaymentManager, and swapping gateways changes nothing about that contract.
 */
class GatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_payments_are_captured_immediately(): void
    {
        $business = BusinessFactory::new()->create();
        $manager = app(PaymentManager::class);

        $result = $manager->createPayment(new CreatePaymentDTO(businessId: $business->id, amount: 1000, gatewayKey: 'cash'));

        $this->assertSame(PaymentStatus::Captured, $result->status);
    }

    public function test_manual_qr_payments_start_pending_and_are_captured_on_verify(): void
    {
        $business = BusinessFactory::new()->create();
        $manager = app(PaymentManager::class);

        $created = $manager->createPayment(new CreatePaymentDTO(businessId: $business->id, amount: 500, gatewayKey: 'manual_qr'));
        $this->assertSame(PaymentStatus::Pending, $created->status);

        $verified = $manager->verifyPayment('manual_qr', $created->transactionPublicId);
        $this->assertSame(PaymentStatus::Captured, $verified->status);
    }

    public function test_both_built_in_gateways_are_available_to_every_tenant(): void
    {
        $business = BusinessFactory::new()->create();
        $manager = app(PaymentManager::class);

        $this->assertEqualsCanonicalizing(['cash', 'manual_qr'], $manager->availableGateways($business->id));
    }
}
