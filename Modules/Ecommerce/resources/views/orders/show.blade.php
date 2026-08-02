<x-layouts.app title="Order {{ $order->public_id }}">
    <div class="flex flex-col gap-6 max-w-4xl">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Order {{ $order->public_id }}</h1>
                <p class="mt-1 text-sm text-ink-soft">{{ $order->created_at->format('d M Y, H:i') }}</p>
            </div>
            <a href="{{ route('online-orders.index') }}" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold hover:bg-surface-2">Back to Orders</a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-2 gap-6">
            <div class="rounded-xl border border-line bg-surface p-6">
                <h2 class="text-sm font-semibold mb-4">Customer Details</h2>
                <div class="space-y-2 text-sm">
                    <div><span class="text-ink-soft">Name:</span> {{ $order->customer_name }}</div>
                    <div><span class="text-ink-soft">Email:</span> {{ $order->customer_email }}</div>
                    <div><span class="text-ink-soft">Phone:</span> {{ $order->customer_phone }}</div>
                    <div><span class="text-ink-soft">Delivery Address:</span> <p class="mt-1 text-xs">{{ $order->delivery_address }}</p></div>
                </div>
            </div>

            <div class="rounded-xl border border-line bg-surface p-6">
                <h2 class="text-sm font-semibold mb-4">Order Status</h2>
                <form method="POST" action="{{ route('online-orders.update-status', $order) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <select name="status" required class="w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm">
                        <option value="pending" @selected($order->status === 'pending')>Pending</option>
                        <option value="confirmed" @selected($order->status === 'confirmed')>Confirmed</option>
                        <option value="shipped" @selected($order->status === 'shipped')>Shipped</option>
                        <option value="delivered" @selected($order->status === 'delivered')>Delivered</option>
                        <option value="cancelled" @selected($order->status === 'cancelled')>Cancelled</option>
                    </select>
                    <button type="submit" class="w-full rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-ink">Update Status</button>
                </form>
            </div>
        </div>

        <div class="rounded-xl border border-line bg-surface p-6">
            <h2 class="text-sm font-semibold mb-4">Order Items</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-ink-soft">
                            <th class="pb-2">Product</th>
                            <th class="pb-2 text-right">Unit Price</th>
                            <th class="pb-2 text-right">Quantity</th>
                            <th class="pb-2 text-right">Tax</th>
                            <th class="pb-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr class="border-t border-line">
                                <td class="py-3">{{ $item->product->name }}</td>
                                <td class="py-3 text-right">Rs. {{ number_format($item->unit_price, 2) }}</td>
                                <td class="py-3 text-right">{{ $item->quantity }}</td>
                                <td class="py-3 text-right">{{ number_format($item->tax_rate, 1) }}% (Rs. {{ number_format($item->tax_amount, 2) }})</td>
                                <td class="py-3 text-right font-semibold">Rs. {{ number_format($item->line_total + $item->tax_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <div class="w-64">
                    <div class="flex justify-between border-t border-line py-2 text-sm">
                        <span>Subtotal:</span>
                        <span>Rs. {{ number_format($order->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 text-sm">
                        <span>Tax:</span>
                        <span>Rs. {{ number_format($order->tax_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-line py-2 text-lg font-semibold">
                        <span>Total:</span>
                        <span>Rs. {{ number_format($order->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
