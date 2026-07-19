<?php

namespace Modules\Notification\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Notifications\Concerns\RespectsPreferences;

class DailySummaryDigest extends Notification
{
    use RespectsPreferences;

    public function __construct(
        protected string $date,
        protected float $totalSales,
        protected int $transactionCount,
        protected ?string $topProduct,
    ) {}

    public function via($notifiable): array
    {
        return $this->channelsFor($notifiable, 'daily_summary');
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Daily summary for {$this->date}")
            ->line('Sales: Rs '.number_format($this->totalSales, 2))
            ->line("Transactions: {$this->transactionCount}")
            ->line('Top product: '.($this->topProduct ?? '—'))
            ->action('Open Dashboard', url('/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return [
            'date' => $this->date,
            'total_sales' => $this->totalSales,
            'transaction_count' => $this->transactionCount,
            'top_product' => $this->topProduct,
        ];
    }
}
