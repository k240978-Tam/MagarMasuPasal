<?php

namespace Modules\PaymentManager\DTOs;

/**
 * @param  class-string|null  $payableType
 */
readonly class CreatePaymentDTO
{
    public function __construct(
        public int $businessId,
        public float $amount,
        public string $gatewayKey,
        public ?string $payableType = null,
        public ?int $payableId = null,
        public string $currency = 'NPR',
        public array $meta = [],
    ) {}
}
