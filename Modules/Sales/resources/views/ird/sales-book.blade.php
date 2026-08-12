<x-layouts.app title="Sales Book (IRD)">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-ink text-balance">Sales Book — Fiscal Year {{ $fiscalYear }}</h1>
                <p class="mt-1 text-sm text-ink-soft">Every invoice in number order, including cancelled ones, as IRD requires the बिक्री खाता to be kept.</p>
            </div>
            <a href="{{ route('ird.sales-book.export', ['fiscal_year' => $fiscalYear]) }}"
                class="shrink-0 rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">
                Export CSV
            </a>
        </div>

        @include('accounting::reports.partials.tabs')

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif

        @error('reason')
            <div class="rounded-lg border border-critical bg-surface px-4 py-2 text-sm text-critical">{{ $message }}</div>
        @enderror

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-line bg-surface p-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Fiscal Year</label>
                <select name="fiscal_year" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected($year === $fiscalYear)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Apply</button>
        </form>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Total Sales</p>
                <p class="mt-1 text-lg font-semibold text-ink">Rs {{ number_format($totals['total'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Taxable</p>
                <p class="mt-1 text-lg font-semibold text-ink">Rs {{ number_format($totals['taxable'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">VAT Collected</p>
                <p class="mt-1 text-lg font-semibold text-ink">Rs {{ number_format($totals['vat'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Tax Exempt</p>
                <p class="mt-1 text-lg font-semibold text-ink">Rs {{ number_format($totals['exempt'], 2) }}</p>
            </div>
        </div>

        @if (! empty($missingSequences))
            <div class="rounded-xl border border-critical bg-surface p-4 text-sm">
                <p class="font-semibold text-critical">Gap in invoice numbering</p>
                <p class="mt-1 text-ink-soft">Missing sequence {{ implode(', ', array_slice($missingSequences, 0, 20)) }}{{ count($missingSequences) > 20 ? '…' : '' }}. IRD expects an unbroken series — an auditor will ask about this.</p>
            </div>
        @endif

        <div class="rounded-xl border border-line bg-surface p-4 text-sm">
            <p class="font-semibold text-ink">IRD real-time reporting (CBMS)</p>
            @if ($cbmsEnabled)
                <p class="mt-1 text-ink-soft">Enabled. {{ $unsyncedCount }} invoice(s) in this fiscal year are awaiting or retrying sync.</p>
            @else
                <p class="mt-1 text-ink-soft">Not enabled. CBMS credentials are issued by IRD after the billing software is submitted and listed as approved; until then invoices are recorded locally only.</p>
            @endif
        </div>

        <div class="overflow-x-auto rounded-xl border border-line">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                        <th class="px-3 py-3">S.N.</th>
                        <th class="px-3 py-3">Date (BS)</th>
                        <th class="px-3 py-3">Invoice No.</th>
                        <th class="px-3 py-3">Buyer</th>
                        <th class="px-3 py-3">PAN</th>
                        <th class="px-3 py-3 text-right">Total</th>
                        <th class="px-3 py-3 text-right">Taxable</th>
                        <th class="px-3 py-3 text-right">VAT</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $index => $row)
                        <tr class="border-t border-line {{ $row['status'] === 'Cancelled' ? 'text-ink-soft line-through decoration-critical/60' : '' }}">
                            <td class="px-3 py-3">{{ $index + 1 }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $row['date_bs'] }}</td>
                            <td class="px-3 py-3 font-medium">
                                <a href="{{ route('sales.receipt', $row['public_id']) }}" target="_blank" class="text-info hover:underline">{{ $row['invoice_no'] }}</a>
                            </td>
                            <td class="px-3 py-3">{{ $row['buyer_name'] }}</td>
                            <td class="px-3 py-3 text-ink-soft">{{ $row['buyer_pan'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-right">Rs {{ number_format($row['total'], 2) }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['taxable'] > 0 ? 'Rs '.number_format($row['taxable'], 2) : '—' }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['vat'] > 0 ? 'Rs '.number_format($row['vat'], 2) : '—' }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $row['status'] === 'Cancelled' ? 'bg-critical/10 text-critical' : 'bg-positive/10 text-positive' }}">
                                    {{ $row['status'] }}
                                </span>
                                @if ($row['cancellation_reason'])
                                    <span class="block text-xs text-ink-soft">{{ $row['cancellation_reason'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                @can('sales.manage')
                                    @if ($row['status'] !== 'Cancelled')
                                        <form method="POST" action="{{ route('sales.cancel', $row['public_id']) }}"
                                            onsubmit="return confirm('Cancel invoice {{ $row['invoice_no'] }}? The invoice and its number are kept and reported as cancelled, and stock is returned.')">
                                            @csrf
                                            <input type="hidden" name="reason" value="">
                                            <button type="button"
                                                onclick="const r = prompt('Reason for cancelling {{ $row['invoice_no'] }} (required by IRD):'); if (r) { this.previousElementSibling.value = r; this.form.requestSubmit(); }"
                                                class="text-sm font-semibold text-critical">Cancel</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t border-line">
                            <td colspan="10" class="px-3 py-6 text-center text-ink-soft">No invoices in this fiscal year yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
