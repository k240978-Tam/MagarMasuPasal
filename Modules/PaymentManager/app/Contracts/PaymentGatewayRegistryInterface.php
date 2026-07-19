<?php

namespace Modules\PaymentManager\Contracts;

interface PaymentGatewayRegistryInterface
{
    public function resolve(string $gatewayKey): PaymentGatewayInterface;

    /**
     * Gateway keys enabled for the given tenant (e.g. 'cash', 'manual_qr'
     * today; a tenant that later configures eSewa credentials in Settings
     * would see 'esewa' appear here too — POS renders one payment button
     * per entry, no UI changes needed when a gateway is added).
     *
     * @return string[]
     */
    public function availableForBusiness(int $businessId): array;
}
