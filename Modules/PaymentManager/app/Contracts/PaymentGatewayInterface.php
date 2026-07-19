<?php

namespace Modules\PaymentManager\Contracts;

use Modules\PaymentManager\DTOs\CreatePaymentDTO;
use Modules\PaymentManager\DTOs\PaymentResultDTO;
use Modules\PaymentManager\DTOs\RefundPaymentDTO;
use Modules\PaymentManager\DTOs\WebhookPayloadDTO;
use Modules\PaymentManager\Enums\PaymentStatus;

/**
 * Provider-independent payment contract. The POS and Sales modules depend
 * only on this interface (via PaymentGatewayRegistryInterface) — never on a
 * concrete gateway. Adding a real provider later (eSewa, Khalti, FonePay,
 * a card processor) means writing one class implementing this interface and
 * registering it; no POS/Sales/Accounting code changes.
 * See docs/architecture/01-system-architecture.md §1.6 and
 * docs/architecture/06-api-design.md §6.3.
 */
interface PaymentGatewayInterface
{
    /**
     * The key this gateway is registered under, e.g. 'cash', 'manual_qr',
     * 'esewa' (future).
     */
    public function key(): string;

    public function createPayment(CreatePaymentDTO $dto): PaymentResultDTO;

    public function verifyPayment(string $transactionPublicId): PaymentResultDTO;

    public function cancelPayment(string $transactionPublicId): PaymentResultDTO;

    public function refundPayment(RefundPaymentDTO $dto): PaymentResultDTO;

    public function getStatus(string $transactionPublicId): PaymentStatus;

    public function handleWebhook(WebhookPayloadDTO $payload): PaymentResultDTO;
}
