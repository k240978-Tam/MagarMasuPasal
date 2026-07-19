<?php

namespace Modules\Notification\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Notifications\Concerns\RespectsPreferences;

class FailedLoginAlert extends Notification
{
    use RespectsPreferences;

    public function __construct(protected string $attemptedEmail, protected string $ip) {}

    public function via($notifiable): array
    {
        return $this->channelsFor($notifiable, 'failed_login');
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Failed sign-in attempt')
            ->line("A failed sign-in attempt was made for {$this->attemptedEmail} from {$this->ip}.")
            ->line('If this wasn\'t you or your staff, consider resetting that account\'s password.');
    }

    public function toArray($notifiable): array
    {
        return ['attempted_email' => $this->attemptedEmail, 'ip' => $this->ip];
    }
}
