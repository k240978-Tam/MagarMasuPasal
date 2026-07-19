<?php

namespace Modules\PaymentManager\Providers;

use Modules\PaymentManager\Contracts\PaymentGatewayRegistryInterface;
use Modules\PaymentManager\Gateways\CashGateway;
use Modules\PaymentManager\Gateways\ManualQrGateway;
use Modules\PaymentManager\Services\PaymentGatewayRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PaymentManagerServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'PaymentManager';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'paymentmanager';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(PaymentGatewayRegistryInterface::class, function ($app) {
            $registry = new PaymentGatewayRegistry($app);
            $registry->register('cash', CashGateway::class);
            $registry->register('manual_qr', ManualQrGateway::class);

            return $registry;
        });
    }
}
