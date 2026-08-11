@php
    $tabs = [
        ['label' => 'Sales', 'route' => 'reports.sales', 'active' => request()->routeIs('reports.*')],
        ['label' => 'Profit & Loss', 'route' => 'accounting.reports.profit-loss', 'active' => request()->routeIs('accounting.reports.profit-loss')],
        ['label' => 'Trial Balance', 'route' => 'accounting.reports.trial-balance', 'active' => request()->routeIs('accounting.reports.trial-balance')],
        ['label' => 'Balance Sheet', 'route' => 'accounting.reports.balance-sheet', 'active' => request()->routeIs('accounting.reports.balance-sheet')],
        ['label' => 'Cash Book', 'route' => 'accounting.reports.cash-book', 'active' => request()->routeIs('accounting.reports.cash-book')],
        ['label' => 'Ledger', 'route' => 'accounting.reports.ledger', 'active' => request()->routeIs('accounting.reports.ledger')],
    ];
@endphp

<div class="flex flex-wrap gap-1 rounded-xl border border-line bg-surface p-1">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab['active'] ? 'bg-surface-2 text-ink' : 'text-ink-soft hover:bg-surface-2 hover:text-ink' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
