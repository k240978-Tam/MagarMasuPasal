<?php

namespace Modules\Notification\Console;

use Illuminate\Console\Command;
use Modules\Customers\Models\Customer;
use Modules\Notification\Notifications\CustomerDueAlert;
use Modules\Notification\Notifications\SupplierDueAlert;
use Modules\Notification\Services\NotificationDispatchService;
use Modules\Settings\Services\SettingsService;
use Modules\Suppliers\Models\Supplier;
use Modules\Tenancy\Models\Business;

class SendDueAlertsCommand extends Command
{
    protected $signature = 'notifications:dues';

    protected $description = 'Notify management of customers near/over their credit limit and suppliers owed above the configured threshold.';

    public function handle(NotificationDispatchService $dispatch, SettingsService $settings): int
    {
        Business::where('status', 'active')->each(function (Business $business) use ($dispatch, $settings) {
            $this->sendCustomerDues($business->id, $dispatch);
            $this->sendSupplierDues($business->id, $dispatch, $settings);
        });

        $this->info('Due alerts dispatched.');

        return self::SUCCESS;
    }

    protected function sendCustomerDues(int $businessId, NotificationDispatchService $dispatch): void
    {
        $customers = Customer::withoutTenantScope()
            ->where('business_id', $businessId)
            ->with('group')
            ->get()
            ->filter(function (Customer $customer) {
                $available = $customer->creditAvailable();

                if ($available === null || (float) $customer->group->credit_limit <= 0) {
                    return false;
                }

                return $available <= (float) $customer->group->credit_limit * 0.1;
            });

        if ($customers->isEmpty()) {
            return;
        }

        $dispatch->notifyManagement($businessId, new CustomerDueAlert($customers->map(fn (Customer $c) => [
            'name' => $c->name,
            'current_due' => (float) $c->current_due,
            'credit_limit' => (float) $c->group->credit_limit,
        ])->values()->all()));
    }

    protected function sendSupplierDues(int $businessId, NotificationDispatchService $dispatch, SettingsService $settings): void
    {
        $threshold = (float) $settings->get($businessId, 'notifications.supplier_due_threshold', 5000);

        $suppliers = Supplier::withoutTenantScope()
            ->where('business_id', $businessId)
            ->where('current_due', '>', $threshold)
            ->get();

        if ($suppliers->isEmpty()) {
            return;
        }

        $dispatch->notifyManagement($businessId, new SupplierDueAlert($suppliers->map(fn (Supplier $s) => [
            'name' => $s->name,
            'current_due' => (float) $s->current_due,
        ])->values()->all(), $threshold));
    }
}
