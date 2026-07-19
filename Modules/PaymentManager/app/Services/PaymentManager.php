<?php

namespace Modules\PaymentManager\Services;

use Modules\PaymentManager\Contracts\PaymentGatewayRegistryInterface;
use Modules\PaymentManager\DTOs\CreatePaymentDTO;
use Modules\PaymentManager\DTOs\PaymentResultDTO;
use Modules\PaymentManager\DTOs\RefundPaymentDTO;
use Modules\PaymentManager\Enums\PaymentStatus;

/**
 * The only thing the POS (and later Sales, refunds, etc.) talks to for
 * payments — never a gateway directly. See
 * docs/architecture/01-system-architecture.md §1.6.
 */
class PaymentManager
{
    public function __construct(protected PaymentGatewayRegistryInterface $registry) {}

    public function createPayment(CreatePaymentDTO $dto): PaymentResultDTO
    {
        return $this->registry->resolve($dto->gatewayKey)->createPayment($dto);
    }

    public function verifyPayment(string $gatewayKey, string $transactionPublicId): PaymentResultDTO
    {
        return $this->registry->resolve($gatewayKey)->verifyPayment($transactionPublicId);
    }

    public function cancelPayment(string $gatewayKey, string $transactionPublicId): PaymentResultDTO
    {
        return $this->registry->resolve($gatewayKey)->cancelPayment($transactionPublicId);
    }

    public function refundPayment(string $gatewayKey, RefundPaymentDTO $dto): PaymentResultDTO
    {
        return $this->registry->resolve($gatewayKey)->refundPayment($dto);
    }

    public function getStatus(string $gatewayKey, string $transactionPublicId): PaymentStatus
    {
        return $this->registry->resolve($gatewayKey)->getStatus($transactionPublicId);
    }

    /** @return string[] */
    public function availableGateways(int $businessId): array
    {
        return $this->registry->availableForBusiness($businessId);
    }
}
