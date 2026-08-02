<x-layouts.app title="Online Orders">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Online Orders</h1>
            <p class="mt-1 text-sm text-ink-soft">Orders from your online website with real-time inventory sync.</p>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif

        @if ($orders->isEmpty())
            <div class="rounded-xl border border-line bg-surface p-8 text-center">
                <p class="text-ink-soft">No online orders yet.</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-line">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                            <th class="px-6 py-3">Order ID</th>
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Items</th>
                            <th class="px-6 py-3">Total</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Created</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr class="border-t border-line">
                                <td class="px-6 py-4 font-medium">{{ $order->public_id }}</td>
                                <td class="px-6 py-4">{{ $order->customer_name }}</td>
                                <td class="px-6 py-4 text-ink-soft text-xs">{{ $order->customer_email }}</td>
                                <td class="px-6 py-4">{{ $order->items->count() }} item{{ $order->items->count() !== 1 ? 's' : '' }}</td>
                                <td class="px-6 py-4 font-semibold">Rs. {{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $order->status === 'pending' ? 'bg-warning/10 text-warning' : ($order->status === 'delivered' ? 'bg-success/10 text-success' : ($order->status === 'cancelled' ? 'bg-critical/10 text-critical' : 'bg-info/10 text-info')) }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-ink-soft">{{ $order->created_at->format('d M Y') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('online-orders.show', $order) }}" class="text-xs font-medium text-accent hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-center">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
