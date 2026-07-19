<?php

namespace Modules\Notification\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Notifications\Concerns\RespectsPreferences;

class CustomerDueAlert extends Notification
{
    use RespectsPreferences;

    /**
     * @param  array<int, array{name: string, current_due: float, credit_limit: float}>  $customers
     */
    public function __construct(protected array $customers) {}

    public function via($notifiable): array
    {
        return $this->channelsFor($notifiable, 'customer_due');
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(count($this->customers).' customer(s) near or over their credit limit')
            ->line('The following customers are at or above 90% of their credit limit:');

        foreach ($this->customers as $customer) {
            $mail->line("- {$customer['name']}: Rs {$customer['current_due']} due of Rs {$customer['credit_limit']} limit");
        }

        return $mail->action('Open Dashboard', url('/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return ['customers' => $this->customers];
    }
}
