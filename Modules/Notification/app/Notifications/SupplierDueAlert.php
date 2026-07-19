<?php

namespace Modules\Notification\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Notifications\Concerns\RespectsPreferences;

class SupplierDueAlert extends Notification
{
    use RespectsPreferences;

    /**
     * @param  array<int, array{name: string, current_due: float}>  $suppliers
     */
    public function __construct(protected array $suppliers, protected float $threshold) {}

    public function via($notifiable): array
    {
        return $this->channelsFor($notifiable, 'supplier_due');
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(count($this->suppliers).' supplier balance(s) above Rs '.number_format($this->threshold, 2))
            ->line('The following suppliers are owed more than the configured threshold:');

        foreach ($this->suppliers as $supplier) {
            $mail->line("- {$supplier['name']}: Rs {$supplier['current_due']}");
        }

        return $mail->action('Open Dashboard', url('/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return ['suppliers' => $this->suppliers, 'threshold' => $this->threshold];
    }
}
