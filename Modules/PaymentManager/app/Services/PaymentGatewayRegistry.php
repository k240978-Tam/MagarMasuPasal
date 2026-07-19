<?php

namespace Modules\PaymentManager\Services;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Modules\PaymentManager\Contracts\PaymentGatewayInterface;
use Modules\PaymentManager\Contracts\PaymentGatewayRegistryInterface;

class PaymentGatewayRegistry implements PaymentGatewayRegistryInterface
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    protected array $gateways = [];

    public function __construct(protected Container $container) {}

    public function register(string $key, string $gatewayClass): void
    {
        $this->gateways[$key] = $gatewayClass;
    }

    public function resolve(string $gatewayKey): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$gatewayKey])) {
            throw new InvalidArgumentException("No payment gateway registered for key [{$gatewayKey}].");
        }

        return $this->container->make($this->gateways[$gatewayKey]);
    }

    /**
     * Both built-in gateways are available to every tenant today. A real
     * provider (esewa, khalti, ...) would only appear here once the tenant
     * has configured its credentials in Settings.
     */
    public function availableForBusiness(int $businessId): array
    {
        return array_keys($this->gateways);
    }
}
