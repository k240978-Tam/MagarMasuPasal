<x-layouts.app title="Balance Sheet">
    <div class="flex flex-col gap-6 max-w-4xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Balance Sheet</h1>
            <p class="mt-1 text-sm text-ink-soft">As of {{ $asOf }}.</p>
        </div>

        @include('accounting::reports.partials.tabs')

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-line bg-surface p-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">As of</label>
                <input type="date" name="as_of" value="{{ $asOf }}" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Apply</button>
        </form>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Assets</p>
                <p class="mt-1 text-xl font-semibold text-ink">Rs {{ number_format($report['assets'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Liabilities</p>
                <p class="mt-1 text-xl font-semibold text-ink">Rs {{ number_format($report['liabilities'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Equity + Retained Earnings</p>
                <p class="mt-1 text-xl font-semibold text-ink">Rs {{ number_format($report['equity'] + $report['retained_earnings'], 2) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-line">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3 text-right">Balance</th>
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
                            <td colspan="3" class="px-4 py-6 text-center text-ink-soft">No journal activity yet.</td>
                        </tr>
                    @endforelse
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-medium">Retained Earnings (accumulated profit)</td>
                        <td class="px-4 py-3 text-ink-soft">Equity</td>
                        <td class="px-4 py-3 text-right">Rs {{ number_format($report['retained_earnings'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
