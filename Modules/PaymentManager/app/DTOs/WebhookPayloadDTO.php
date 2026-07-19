<?php

namespace Modules\PaymentManager\DTOs;

readonly class WebhookPayloadDTO
{
    public function __construct(
        public string $gatewayKey,
        public array $headers,
        public array $payload,
        public string $rawBody,
    ) {}
}
