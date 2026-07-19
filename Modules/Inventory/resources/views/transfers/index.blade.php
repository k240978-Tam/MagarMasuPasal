<x-layouts.app title="Stock Transfers">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Stock Transfers</h1>
            <p class="mt-1 text-sm text-ink-soft">Move stock between branches — a transfer only updates on-hand quantities once the destination confirms receipt.</p>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-critical px-4 py-2 text-sm text-critical">{{ $errors->first() }}</div>
        @endif

        <div class="rounded-xl border border-line bg-surface p-4"
            x-data="{
                items: [{ product_id: '', quantity: '' }],
                addRow() { this.items.push({ product_id: '', quantity: '' }) },
                removeRow(i) { this.items.splice(i, 1) },
            }">
            <h2 class="text-sm font-semibold">Dispatch a Transfer</h2>

            <form method="POST" action="{{ route('inventory.transfers.store') }}" class="mt-3 flex flex-col gap-3">
                @csrf
                <div class="flex flex-wrap gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">From Branch</label>
                        <select name="from_branch_id" required class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">To Branch</label>
                        <select name="to_branch_id" required class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Notes</label>
                        <input type="text" name="notes" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <template x-for="(item, i) in items" :key="i">
                        <div class="flex items-center gap-2">
                            <select :name="'items[' + i + '][product_id]'" x-model="item.product_id" required class="flex-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                                <option value="">Select product…</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.001" min="0.001" :name="'items[' + i + '][quantity]'" x-model="item.quantity" required placeholder="Qty" class="w-28 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                            <button type="button" @click="removeRow(i)" x-show="items.length > 1" class="text-xs text-critical">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="addRow()" class="self-start text-xs font-medium text-info hover:underline">+ Add another product</button>
                </div>

                <button type="submit" class="mt-1 w-fit rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Dispatch Transfer</button>
            </form>
        </div>

        <div class="rounded-xl border border-line bg-surface p-4">
            <h2 class="text-sm font-semibold">All Transfers</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-ink-soft">
                            <th class="pb-2">Route</th>
                            <th class="pb-2">Items</th>
                            <th class="pb-2">Status</th>
                            <th class="pb-2">Dispatched</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transfers as $transfer)
                            <tr class="border-t border-line align-top">
                                <td class="py-2">{{ $transfer->fromBranch->name }} → {{ $transfer->toBranch->name }}</td>
                                <td class="py-2 text-ink-soft">
                                    @foreach ($transfer->items as $item)
                                        <div>{{ number_format($item->quantity, 3) }} × {{ $item->product->name }}</div>
                                    @endforeach
                                </td>
                                <td class="py-2">
                                    <span @class([
                                        'rounded-full border px-2 py-0.5 text-xs font-medium',
                                        'border-success text-success' => $transfer->status === 'received',
                                        'border-warning text-warning' => $transfer->status === 'in_transit' || $transfer->status === 'pending',
                                        'border-line text-ink-soft' => $transfer->status === 'cancelled',
                                    ])>{{ ucfirst(str_replace('_', ' ', $transfer->status)) }}</span>
                                </td>
                                <td class="py-2 text-ink-soft">{{ $transfer->created_at->format('d M, h:i A') }}</td>
                                <td class="py-2 text-right">
                                    @if (in_array($transfer->status, ['pending', 'in_transit']))
                                        <form method="POST" action="{{ route('inventory.transfers.receive', $transfer) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-info hover:underline">Receive</button>
                                        </form>
                                        <form method="POST" action="{{ route('inventory.transfers.cancel', $transfer) }}" class="inline" onsubmit="return confirm('Cancel this transfer?')">
                                            @csrf
                                            <button type="submit" class="ml-2 text-xs font-medium text-critical hover:underline">Cancel</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-ink-soft">No transfers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
