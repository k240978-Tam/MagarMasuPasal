<x-layouts.app title="Notifications">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink text-balance">Notifications</h1>
                <p class="mt-1 text-sm text-ink-soft">Alerts sent to your account — low stock, dues, security, and daily summaries.</p>
            </div>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">Mark all read</button>
            </form>
        </div>

        <div class="rounded-xl border border-line bg-surface p-4">
            <div class="flex flex-col divide-y divide-line">
                @forelse ($notifications as $notification)
                    @php
                        $type = class_basename($notification->type);
                        $data = $notification->data;
                        $summary = match ($type) {
                            'LowStockAlert' => count($data['items'] ?? []).' product(s) low at '.($data['branch'] ?? ''),
                            'DailySummaryDigest' => 'Sales report for '.($data['date'] ?? '').': Rs '.number_format($data['total_sales'] ?? 0, 2).' across '.($data['transaction_count'] ?? 0).' sale(s)',
                            'CustomerDueAlert' => count($data['customers'] ?? []).' customer(s) near/over credit limit',
                            'SupplierDueAlert' => count($data['suppliers'] ?? []).' supplier balance(s) above threshold',
                            'LargeDiscountWarning' => sprintf('%s discounted %s%% (Rs %s) on %s', $data['cashier_name'] ?? '', number_format($data['discount_percent'] ?? 0, 1), number_format($data['discount_amount'] ?? 0, 2), $data['invoice_no'] ?? ''),
                            'FailedLoginAlert' => 'Failed sign-in for '.($data['attempted_email'] ?? '').' from '.($data['ip'] ?? ''),
                            'BackupCompletedAlert' => 'Backup '.($data['status'] ?? '').(($data['size'] ?? null) ? ' — '.$data['size'] : ''),
                            default => 'Notification',
                        };
                    @endphp
                    <div class="flex items-start justify-between gap-3 py-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-line-strong' : 'bg-accent' }}"></span>
                            <div>
                                <div class="text-sm text-ink">{{ $summary }}</div>
                                <div class="mt-0.5 text-xs text-ink-soft">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                        @unless ($notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-info hover:underline">Mark read</button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-soft">No notifications yet.</p>
                @endforelse
            </div>
        </div>

        {{ $notifications->links() }}
    </div>
</x-layouts.app>
