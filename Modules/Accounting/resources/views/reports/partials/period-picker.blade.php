@php
    // Included (not a component) so it works without registering a module
    // component namespace. $accounts/$account are optional — only the ledger
    // needs the account selector.
    $accounts = $accounts ?? null;
    $account = $account ?? null;
    $exportRoute = $exportRoute ?? null;
    $exportParams = $period->queryParameters() + request()->only('account');
@endphp

{{-- One picker for every financial report: pick a Nepali month (the way
     books are actually closed here), or fall back to an explicit AD range. --}}
<form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-line bg-surface p-4">
    @if ($accounts)
        <div>
            <label for="account" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ __('reports.account') }}</label>
            <select id="account" name="account" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                @foreach ($accounts as $option)
                    <option value="{{ $option->code }}" @selected($account && $option->code === $account->code)>{{ $option->code }} — {{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label for="bs_month" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ __('reports.nepali_month') }}</label>
        <select id="bs_month" name="bs_month" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
            <option value="">— {{ __('reports.custom_range') }} —</option>
            @foreach ($nepaliMonths as $month)
                <option value="{{ $month['value'] }}" @selected($period->bsMonth === $month['value'])>{{ $month['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="from" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ __('reports.from') }} (AD)</label>
        <input type="date" id="from" name="from" value="{{ $period->from->toDateString() }}" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
    </div>

    <div>
        <label for="to" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ __('reports.to') }} (AD)</label>
        <input type="date" id="to" name="to" value="{{ $period->to->toDateString() }}" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
    </div>

    <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">{{ __('reports.apply') }}</button>

    @if ($exportRoute)
        <span class="flex items-center gap-2">
            <a href="{{ route($exportRoute, $exportParams + ['format' => 'pdf']) }}"
                class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">
                {{ __('reports.export_pdf') }}
            </a>
            <a href="{{ route($exportRoute, $exportParams + ['format' => 'xlsx']) }}"
                class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">
                {{ __('reports.export_excel') }}
            </a>
            <a href="{{ route($exportRoute, $exportParams + ['format' => 'csv']) }}"
                class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">
                {{ __('reports.export_csv') }}
            </a>
        </span>
    @endif

    <div class="w-full text-xs text-ink-soft">
        {{ $period->label }} — {{ __('reports.range_bs') }} {{ $period->fromBs() }} → {{ $period->toBs() }}
        · {{ __('reports.range_ad') }} {{ $period->from->toDateString() }} → {{ $period->to->toDateString() }}
        <a href="{{ route('accounting.reports.monthly-pack', $period->queryParameters() + ['format' => 'pdf']) }}"
            class="ml-2 font-semibold text-accent hover:underline">{{ __('reports.download_pack') }}</a>
    </div>
</form>
