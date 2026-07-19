<x-layouts.app title="Dashboard">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Good day, {{ auth()->user()->name }} 👋</h1>
            <p class="mt-1 text-sm text-ink-soft">
                {{ auth()->user()->business?->name }} — foundation is live. Sales, inventory, and
                report widgets land here as those modules ship.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['label' => "Today's Sales", 'value' => 'Rs 0'],
                ['label' => 'Transactions', 'value' => '0'],
                ['label' => 'Branches', 'value' => auth()->user()->business?->branches()->count() ?? 0],
                ['label' => 'Staff', 'value' => \App\Models\User::count()],
            ] as $stat)
                <div class="rounded-xl border border-line bg-surface p-4">
                    <div class="text-xs text-ink-soft">{{ $stat['label'] }}</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-ink">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-dashed border-line-strong bg-surface-2 p-8 text-center">
            <p class="text-sm text-ink-soft">
                POS, Inventory, and Reports widgets are scheduled for later roadmap phases —
                see <code class="rounded bg-surface px-1.5 py-0.5 text-xs">docs/architecture/08-roadmap.md</code>.
            </p>
        </div>
    </div>
</x-layouts.app>
