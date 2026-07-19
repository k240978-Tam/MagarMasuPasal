<?php

namespace Modules\Notification\Notifications\Concerns;

use App\Models\User;
use Modules\Notification\Models\NotificationPreference;
use Modules\Settings\Services\SettingsService;

/**
 * Every notification type defaults to both database + mail unless a user
 * has an explicit NotificationPreference row turning a channel off — so a
 * fresh install alerts on everything, and opting out is the deliberate act.
 * The business-wide "Email notifications" feature toggle sits above that:
 * it's a kill switch an Owner can flip once, rather than every staff
 * member individually opting out of mail.
 */
trait RespectsPreferences
{
    protected function channelsFor(User $notifiable, string $type): array
    {
        $channels = ['database' => true, 'mail' => true];

        $overrides = NotificationPreference::withoutTenantScope()
            ->where('user_id', $notifiable->id)
            ->where('type', $type)
            ->get();

        foreach ($overrides as $override) {
            $channels[$override->channel] = $override->enabled;
        }

        // The business-wide kill switch always wins over a per-user preference.
        if ($notifiable->business_id && ! app(SettingsService::class)->isFeatureEnabled($notifiable->business_id, 'email_notifications')) {
            $channels['mail'] = false;
        }

        return array_keys(array_filter($channels));
    }
}
