<?php

namespace Modules\Notification\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Fans a notification out to a business's management (Owner/Admin/Manager)
 * — the audience for every alert type this module ships (low stock, dues,
 * daily summary, security, backup) per docs/architecture/08-roadmap.md Phase 5.
 * Cashiers/Accountants/Inventory Managers are deliberately excluded here;
 * a future per-type audience config can widen that without touching callers.
 */
class NotificationDispatchService
{
    public function managementFor(int $businessId): Collection
    {
        return User::where('business_id', $businessId)
            ->get()
            ->filter(fn (User $user) => $user->hasAnyRole(['Owner', 'Admin', 'Manager']));
    }

    public function notifyManagement(int $businessId, Notification $notification): void
    {
        foreach ($this->managementFor($businessId) as $user) {
            $user->notify($notification);
        }
    }
}
