<?php

namespace Modules\Notification\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Modules\Notification\Notifications\FailedLoginAlert;
use Modules\Notification\Services\NotificationDispatchService;

/**
 * Only fires when the attempted email actually belongs to a user — a
 * typo'd email with no match can't be attributed to any business, so
 * there's no one to alert (and no account was actually at risk).
 */
class SendFailedLoginAlert
{
    public function __construct(protected NotificationDispatchService $dispatch, protected Request $request) {}

    public function handle(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        if (! $user || ! $user->business_id) {
            return;
        }

        $this->dispatch->notifyManagement($user->business_id, new FailedLoginAlert(
            attemptedEmail: $event->credentials['email'] ?? $user->email,
            ip: $this->request->ip() ?? 'unknown',
        ));
    }
}
