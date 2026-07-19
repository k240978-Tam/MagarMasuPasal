<?php

namespace Modules\PaymentManager\DTOs;

readonly class RefundPaymentDTO
{
    public function __construct(
        public string $transactionPublicId,
        public float $amount,
        public ?string $reason = null,
    ) {}
}
