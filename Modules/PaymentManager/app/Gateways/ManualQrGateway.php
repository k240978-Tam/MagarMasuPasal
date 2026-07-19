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
 * The shop's own static bank/wallet QR code, shown on-screen for the
 * customer to scan and pay outside the system. There is no API to confirm
 * against, so verifyPayment() IS the confirmation step: the cashier calls
 * it once they've visually confirmed the transfer landed, which is the only
 * thing distinguishing this from a real gateway's automatic verification.
 * A future esewa/khalti/fonepay gateway's verifyPayment() would instead
 * call that provider's status API — POS code calling verifyPayment() does
 * not change.
 */
class ManualQrGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'manual_qr';
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
            'status' => PaymentStatus::Pending->value,
            'meta' => $dto->meta,
        ]);

        return $this->toResult($transaction);
    }

    public function verifyPayment(string $transactionPublicId): PaymentResultDTO
    {
        $transaction = $this->find($transactionPublicId);

        if ($transaction->status === PaymentStatus::Pending->value) {
            $transaction->update(['status' => PaymentStatus::Captured->value, 'processed_at' => now()]);
        }

        return $this->toResult($transaction);
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
        throw new LogicException('The manual QR gateway has no provider webhook — confirmation happens via verifyPayment().');
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
