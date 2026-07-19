<?php

namespace Modules\Notification\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Notifications\Concerns\RespectsPreferences;

class LowStockAlert extends Notification
{
    use RespectsPreferences;

    /**
     * @param  array<int, array{product: string, branch: string, on_hand: float, threshold: float}>  $items
     */
    public function __construct(protected string $branchName, protected array $items) {}

    public function via($notifiable): array
    {
        return $this->channelsFor($notifiable, 'low_stock');
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Low stock at {$this->branchName}")
            ->line(count($this->items).' product(s) are at or below their reorder threshold at '.$this->branchName.':');

        foreach ($this->items as $item) {
            $mail->line("- {$item['product']}: {$item['on_hand']} on hand (threshold {$item['threshold']})");
        }

        return $mail->action('Open Dashboard', url('/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return [
            'branch' => $this->branchName,
            'items' => $this->items,
        ];
    }
}
