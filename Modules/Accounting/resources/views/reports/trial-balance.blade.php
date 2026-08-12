<x-layouts.app title="Trial Balance">
    <div class="flex flex-col gap-6 max-w-4xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Trial Balance</h1>
            <p class="mt-1 text-sm text-ink-soft">As of {{ $period->asOf()->toDateString() }} ({{ $period->label }}) — total debits must equal total credits.</p>
        </div>

        @include('accounting::reports.partials.tabs')

        @include('accounting::reports.partials.period-picker', ['exportRoute' => 'accounting.reports.export.trial-balance'])

        <div class="overflow-x-auto rounded-xl border border-line">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3 text-right">Debit</th>
                        <th class="px-4 py-3 text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-line">
                            <td class="px-4 py-3 font-medium">{{ $row['code'] }} — {{ $row['name'] }}</td>
                            <td class="px-4 py-3 text-ink-soft">{{ ucfirst($row['type']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['debit'] > 0 ? 'Rs '.number_format($row['debit'], 2) : '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['credit'] > 0 ? 'Rs '.number_format($row['credit'], 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-line">
                            <td colspan="4" class="px-4 py-6 text-center text-ink-soft">No journal activity yet.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t border-line bg-surface font-semibold">
                            <td class="px-4 py-3" colspan="2">Totals {{ $totalDebit === $totalCredit ? '✓ balanced' : '✗ OUT OF BALANCE' }}</td>
                            <td class="px-4 py-3 text-right">Rs {{ number_format($totalDebit, 2) }}</td>
                            <td class="px-4 py-3 text-right">Rs {{ number_format($totalCredit, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-layouts.app>
