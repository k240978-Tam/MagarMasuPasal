<x-layouts.app title="Sales Report">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink text-balance">Sales Report</h1>
                <p class="mt-1 text-sm text-ink-soft">{{ $from }} to {{ $to }}</p>
            </div>
            <a href="{{ route('reports.sales.export', request()->query()) }}"
                class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">
                Export CSV
            </a>
        </div>

        @include('accounting::reports.partials.tabs')

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-line bg-surface p-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">From</label>
                <input type="date" name="from" value="{{ $from }}" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">To</label>
                <input type="date" name="to" value="{{ $to }}" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Branch</label>
                <select name="branch_id" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Apply</button>
        </form>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-xs text-ink-soft">Total Sales</div>
                <div class="mt-1 text-xl font-semibold tabular-nums">Rs {{ number_format($totalSales, 2) }}</div>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-xs text-ink-soft">Transactions</div>
                <div class="mt-1 text-xl font-semibold tabular-nums">{{ $transactionCount }}</div>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-xs text-ink-soft">Avg. Sale</div>
                <div class="mt-1 text-xl font-semibold tabular-nums">Rs {{ $transactionCount ? number_format($totalSales / $transactionCount, 2) : '0.00' }}</div>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-xs text-ink-soft">Categories Sold</div>
                <div class="mt-1 text-xl font-semibold tabular-nums">{{ $byCategory->count() }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-line bg-surface p-4 lg:col-span-2">
                <h2 class="text-sm font-semibold">Sales</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-ink-soft">
                                <th class="pb-2">Invoice</th>
                                <th class="pb-2">Date</th>
                                <th class="pb-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sales as $sale)
                                <tr class="border-t border-line">
                                    <td class="py-2">
                                        <a href="{{ route('sales.receipt', $sale->public_id) }}" target="_blank" class="text-info hover:underline">{{ $sale->invoice_no }}</a>
                                    </td>
                                    <td class="py-2 text-ink-soft">{{ \Illuminate\Support\Carbon::parse($sale->completed_at)->format('d M, h:i A') }}</td>
                                    <td class="py-2 text-right tabular-nums">Rs {{ number_format($sale->total_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-ink-soft">No sales in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-xl border border-line bg-surface p-4">
                    <h2 class="text-sm font-semibold">Top Products</h2>
                    <div class="mt-3 flex flex-col gap-2">
                        @forelse ($topProducts as $product)
                            <div class="flex justify-between text-sm">
                                <span>{{ $product->name }}</span>
                                <span class="tabular-nums text-ink-soft">{{ number_format($product->quantity_sold, 2) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-ink-soft">No data.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-line bg-surface p-4">
                    <h2 class="text-sm font-semibold">By Category</h2>
                    <div class="mt-3 flex flex-col gap-2">
                        @forelse ($byCategory as $row)
                            <div class="flex justify-between text-sm">
                                <span>{{ $row->name }}</span>
                                <span class="tabular-nums text-ink-soft">Rs {{ number_format($row->revenue, 2) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-ink-soft">No data.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-line bg-surface p-4">
            <h2 class="text-sm font-semibold">Peak Selling Hours</h2>
            <div class="mt-3 flex items-end gap-1" style="height: 80px;">
                @php $maxRevenue = $peakHours->max('revenue') ?: 1; @endphp
                @for ($h = 0; $h < 24; $h++)
                    @php $row = $peakHours->firstWhere('hour', str_pad($h, 2, '0', STR_PAD_LEFT)); @endphp
                    <div class="flex-1 rounded-t bg-accent-soft" style="height: {{ $row ? max(4, ($row->revenue / $maxRevenue) * 80) : 2 }}px" title="{{ $h }}:00 — Rs {{ $row->revenue ?? 0 }}"></div>
                @endfor
            </div>
            <div class="mt-1 flex justify-between text-[10px] text-ink-soft"><span>12am</span><span>12pm</span><span>11pm</span></div>
        </div>
    </div>
</x-layouts.app>
