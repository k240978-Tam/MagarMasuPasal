<?php

namespace Modules\Notification\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Notifications\Concerns\RespectsPreferences;

class LargeDiscountWarning extends Notification
{
    use RespectsPreferences;

    public function __construct(
        protected string $invoiceNo,
        protected string $cashierName,
        protected float $discountPercent,
        protected float $discountAmount,
    ) {}

    public function via($notifiable): array
    {
        return $this->channelsFor($notifiable, 'large_discount');
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Large discount on {$this->invoiceNo}")
            ->line(sprintf(
                '%s applied a %.1f%% discount (Rs %s) on sale %s.',
                $this->cashierName,
                $this->discountPercent,
                number_format($this->discountAmount, 2),
                $this->invoiceNo,
            ))
            ->action('Open Dashboard', url('/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return [
            'invoice_no' => $this->invoiceNo,
            'cashier_name' => $this->cashierName,
            'discount_percent' => $this->discountPercent,
            'discount_amount' => $this->discountAmount,
        ];
    }
}
