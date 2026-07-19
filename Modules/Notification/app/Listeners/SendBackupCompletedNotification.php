<?php

namespace Modules\Notification\Listeners;

use App\Models\User;
use Modules\Backup\Events\BackupCompleted;
use Modules\Notification\Notifications\BackupCompletedAlert;

/**
 * Backups are platform-wide, not tenant-scoped, so there's no single
 * business to notify — this alerts every Owner across every business
 * instead of going through NotificationDispatchService's per-business lookup.
 */
class SendBackupCompletedNotification
{
    public function handle(BackupCompleted $event): void
    {
        $run = $event->run;

        $owners = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Owner'))
            ->get();

        $notification = new BackupCompletedAlert($run->status, $run->sizeHuman(), $run->failure_reason);

        foreach ($owners as $owner) {
            $owner->notify($notification);
        }
    }
}
