<x-layouts.app title="Profit &amp; Loss">
    <div class="flex flex-col gap-6 max-w-4xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Profit &amp; Loss</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ $from }} to {{ $to }} — derived from posted journal entries.</p>
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
            <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Apply</button>
        </form>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Income</p>
                <p class="mt-1 text-xl font-semibold text-ink">Rs {{ number_format($report['income'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Expenses</p>
                <p class="mt-1 text-xl font-semibold text-ink">Rs {{ number_format($report['expense'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Net {{ $report['net_profit'] >= 0 ? 'Profit' : 'Loss' }}</p>
                <p class="mt-1 text-xl font-semibold {{ $report['net_profit'] >= 0 ? 'text-positive' : 'text-critical' }}">Rs {{ number_format(abs($report['net_profit']), 2) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-line">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['lines'] as $line)
                        <tr class="border-t border-line">
                            <td class="px-4 py-3 font-medium">{{ $line['code'] }} — {{ $line['name'] }}</td>
                            <td class="px-4 py-3 text-ink-soft">{{ ucfirst($line['type']) }}</td>
                            <td class="px-4 py-3 text-right">Rs {{ number_format($line['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-line">
                            <td colspan="3" class="px-4 py-6 text-center text-ink-soft">No journal activity in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
