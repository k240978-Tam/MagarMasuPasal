<x-layouts.app title="General Ledger">
    <div class="flex flex-col gap-6 max-w-4xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">General Ledger</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ $period->label }}{{ $account ? ' — '.$account->code.' '.$account->name : '' }}</p>
        </div>

        @include('accounting::reports.partials.tabs')

        @include('accounting::reports.partials.period-picker', ['exportRoute' => 'accounting.reports.export.ledger'])

        <div class="overflow-x-auto rounded-xl border border-line">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                        <th class="px-4 py-3">Date (BS)</th>
                        <th class="px-4 py-3">Date (AD)</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Debit</th>
                        <th class="px-4 py-3 text-right">Credit</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr class="border-t border-line">
                            <td class="px-4 py-3 whitespace-nowrap">{{ \App\Support\Nepali\NepaliDate::format($line->entry_date) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-ink-soft">{{ \Illuminate\Support\Carbon::parse($line->entry_date)->toDateString() }}</td>
                            <td class="px-4 py-3">{{ $line->description }}</td>
                            <td class="px-4 py-3 text-right">{{ (float) $line->debit > 0 ? 'Rs '.number_format((float) $line->debit, 2) : '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ (float) $line->credit > 0 ? 'Rs '.number_format((float) $line->credit, 2) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-medium">Rs {{ number_format($line->running_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-line">
                            <td colspan="6" class="px-4 py-6 text-center text-ink-soft">No entries for this account in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
