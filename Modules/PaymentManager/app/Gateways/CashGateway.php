<?php

namespace Modules\PaymentManager\Gateways;

use LogicException;
use Modules\PaymentManager\Contracts\PaymentGatewayInterface;
use Modules\PaymentManager\DTOs\CreatePaymentDTO;
use Modules\PaymentManager\DTOs\PaymentResultDTO;
use Modules\PaymentManager\DTOs\RefundPaymentDTO;
use Modules\PaymentManager\DTOs\WebhookPayloadDTO;
use Modules\PaymentManager\Enums\PaymentStatus;
use Modules\PaymentManager\Models\PaymentTransaction;

/**
 * Cash tendered at the register — captured the instant it's counted, since
 * there is no external settlement step. Built in (not a "real" gateway) so
 * the POS always has at least one working payment method out of the box.
 */
class CashGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'cash';
    }

    public function createPayment(CreatePaymentDTO $dto): PaymentResultDTO
    {
        $transaction = PaymentTransaction::create([
            'business_id' => $dto->businessId,
            'payable_type' => $dto->payableType,
            'payable_id' => $dto->payableId,
            'gateway_key' => $this->key(),
            'amount' => $dto->amount,
            'currency' => $dto->currency,
            'status' => PaymentStatus::Captured->value,
            'meta' => $dto->meta,
            'processed_at' => now(),
        ]);

        return $this->toResult($transaction);
    }

    public function verifyPayment(string $transactionPublicId): PaymentResultDTO
    {
        return $this->toResult($this->find($transactionPublicId));
    }

    public function cancelPayment(string $transactionPublicId): PaymentResultDTO
    {
        $transaction = $this->find($transactionPublicId);
        $transaction->update(['status' => PaymentStatus::Cancelled->value]);

        return $this->toResult($transaction);
    }

    public function refundPayment(RefundPaymentDTO $dto): PaymentResultDTO
    {
        $transaction = $this->find($dto->transactionPublicId);
        $transaction->update(['status' => PaymentStatus::Refunded->value]);

        return $this->toResult($transaction);
    }

    public function getStatus(string $transactionPublicId): PaymentStatus
    {
        return PaymentStatus::from($this->find($transactionPublicId)->status);
    }

    public function handleWebhook(WebhookPayloadDTO $payload): PaymentResultDTO
    {
        throw new LogicException('The cash gateway has no external settlement and cannot receive webhooks.');
    }

    protected function find(string $transactionPublicId): PaymentTransaction
    {
        return PaymentTransaction::withoutTenantScope()->where('public_id', $transactionPublicId)->firstOrFail();
    }

    protected function toResult(PaymentTransaction $transaction): PaymentResultDTO
    {
        return new PaymentResultDTO(
            transactionPublicId: $transaction->public_id,
            status: PaymentStatus::from($transaction->status),
            gatewayReference: $transaction->gateway_reference,
            raw: $transaction->toArray(),
        );
    }
}
