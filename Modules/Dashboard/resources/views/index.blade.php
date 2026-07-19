<x-layouts.app title="Dashboard">
    <div class="flex flex-col gap-6" x-data="{}" x-init="
        new Chart($refs.trendChart, {
            type: 'bar',
            data: {
                labels: @js($summary['sales_trend']['labels']),
                datasets: [{
                    label: 'Sales',
                    data: @js($summary['sales_trend']['values']),
                    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-accent-soft') || '#f6e2c2',
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rs ' + v } } },
            },
        })
    ">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Good day, {{ auth()->user()->name }} 👋</h1>
            <p class="mt-1 text-sm text-ink-soft">
                {{ auth()->user()->business?->name }}
                — {{ $summary['scope'] === 'business' ? 'all branches' : auth()->user()->defaultBranch?->name }}
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-xs text-ink-soft">Today's Sales</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-ink">Rs {{ number_format($summary['todays_sales'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-xs text-ink-soft">Transactions</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-ink">{{ $summary['transaction_count'] }}</div>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-xs text-ink-soft">Avg. Sale</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-ink">Rs {{ number_format($summary['avg_sale'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-xs text-ink-soft">Low Stock Items</div>
                <div class="mt-1 text-xl font-semibold tabular-nums {{ $summary['low_stock']->count() ? 'text-warning' : 'text-ink' }}">
                    {{ $summary['low_stock']->count() }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-line bg-surface p-4 lg:col-span-2">
                <h2 class="text-sm font-semibold">Sales Trend — 7 days</h2>
                <div class="mt-3" style="height: 220px;">
                    <canvas x-ref="trendChart"></canvas>
                </div>
            </div>

            <div class="rounded-xl border border-line bg-surface p-4">
                <h2 class="text-sm font-semibold">Top Selling Products (30d)</h2>
                <div class="mt-3 flex flex-col gap-2">
                    @forelse ($summary['top_products'] as $product)
                        <div class="flex items-center justify-between text-sm">
                            <span class="truncate">{{ $product->name }}</span>
                            <span class="tabular-nums text-ink-soft">{{ number_format($product->quantity_sold, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-ink-soft">No sales yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Recent Sales</h2>
                    <a href="{{ route('reports.sales') }}" class="text-xs font-semibold text-info">View report</a>
                </div>
                <div class="mt-3 flex flex-col gap-2">
                    @forelse ($summary['recent_sales'] as $sale)
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $sale->invoice_no }}</span>
                            <span class="tabular-nums text-ink-soft">Rs {{ number_format($sale->total_amount, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-ink-soft">No sales yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-line bg-surface p-4">
                <h2 class="text-sm font-semibold">Alerts</h2>
                <div class="mt-3 flex flex-col gap-2">
                    @forelse ($summary['low_stock'] as $stock)
                        <div class="flex items-start gap-2 text-sm">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-warning"></span>
                            <span>{{ $stock->product->name }} below minimum stock ({{ number_format($stock->quantity_on_hand, 2) }} left)</span>
                        </div>
                    @empty
                        <p class="text-sm text-ink-soft">Nothing needs attention.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
