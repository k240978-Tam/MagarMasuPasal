<x-layouts.app title="General Ledger">
    <div class="flex flex-col gap-6 max-w-4xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">General Ledger</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ $from }} to {{ $to }}{{ $account ? ' — '.$account->code.' '.$account->name : '' }}</p>
        </div>

        @include('accounting::reports.partials.tabs')

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-line bg-surface p-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Account</label>
                <select name="account" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                    @foreach ($accounts as $option)
                        <option value="{{ $option->code }}" @selected($account && $option->code === $account->code)>{{ $option->code }} — {{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
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

        <div class="overflow-x-auto rounded-xl border border-line">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Debit</th>
                        <th class="px-4 py-3 text-right">Credit</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr class="border-t border-line">
                            <td class="px-4 py-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($line->entry_date)->toDateString() }}</td>
                            <td class="px-4 py-3">{{ $line->description }}</td>
                            <td class="px-4 py-3 text-right">{{ (float) $line->debit > 0 ? 'Rs '.number_format((float) $line->debit, 2) : '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ (float) $line->credit > 0 ? 'Rs '.number_format((float) $line->credit, 2) : '—' }}</td>
                            <td class="px-4 py-3 text-right font-medium">Rs {{ number_format($line->running_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-line">
                            <td colspan="5" class="px-4 py-6 text-center text-ink-soft">No entries for this account in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
