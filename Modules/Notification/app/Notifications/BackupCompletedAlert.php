<?php

namespace Modules\Notification\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Notifications\Concerns\RespectsPreferences;

class BackupCompletedAlert extends Notification
{
    use RespectsPreferences;

    public function __construct(protected string $status, protected ?string $sizeHuman, protected ?string $failureReason = null) {}

    public function via($notifiable): array
    {
        return $this->channelsFor($notifiable, 'backup_completed');
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->status === 'success' ? 'Backup completed' : 'Backup failed');

        return $this->status === 'success'
            ? $mail->line("The scheduled backup completed successfully ({$this->sizeHuman}).")
            : $mail->line('The scheduled backup failed: '.($this->failureReason ?? 'unknown error.'));
    }

    public function toArray($notifiable): array
    {
        return ['status' => $this->status, 'size' => $this->sizeHuman, 'failure_reason' => $this->failureReason];
    }
}
