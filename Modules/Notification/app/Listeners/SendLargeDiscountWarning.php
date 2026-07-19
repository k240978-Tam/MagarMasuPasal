<?php

namespace Modules\Notification\Listeners;

use Modules\Notification\Notifications\LargeDiscountWarning;
use Modules\Notification\Services\NotificationDispatchService;
use Modules\Sales\Events\SaleCompleted;
use Modules\Settings\Services\SettingsService;

class SendLargeDiscountWarning
{
    public function __construct(
        protected NotificationDispatchService $dispatch,
        protected SettingsService $settings,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        if ((float) $sale->subtotal <= 0) {
            return;
        }

        $thresholdPercent = (float) $this->settings->get($sale->business_id, 'notifications.large_discount_threshold_percent', 20);
        $discountPercent = ((float) $sale->discount_amount / (float) $sale->subtotal) * 100;

        if ($discountPercent < $thresholdPercent) {
            return;
        }

        $this->dispatch->notifyManagement($sale->business_id, new LargeDiscountWarning(
            invoiceNo: $sale->invoice_no,
            cashierName: $sale->cashier?->name ?? 'Unknown cashier',
            discountPercent: $discountPercent,
            discountAmount: (float) $sale->discount_amount,
        ));
    }
}
