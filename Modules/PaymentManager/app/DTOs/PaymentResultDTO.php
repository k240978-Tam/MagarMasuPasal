<?php

namespace Modules\PaymentManager\DTOs;

use Modules\PaymentManager\Enums\PaymentStatus;

readonly class PaymentResultDTO
{
    public function __construct(
        public string $transactionPublicId,
        public PaymentStatus $status,
        public ?string $gatewayReference = null,
        public array $raw = [],
    ) {}
}
