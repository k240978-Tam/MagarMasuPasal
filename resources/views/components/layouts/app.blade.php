<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} · {{ config('app.name') }}</title>
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-bg text-ink antialiased" x-data="{ theme: localStorage.getItem('theme') || 'light' }" x-init="$watch('theme', v => { localStorage.setItem('theme', v); document.documentElement.setAttribute('data-theme', v); })">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent text-sm font-bold text-accent-ink">M</span>
                    <div class="leading-tight">
                        <div class="text-sm font-semibold text-ink">{{ auth()->user()->business?->name ?? config('app.name') }}</div>
                        <div class="text-xs text-ink-soft">{{ auth()->user()->defaultBranch?->name ?? 'All branches' }}</div>
                    </div>
                </div>

                @php
                    $navTerminal = \Modules\Tenancy\Models\BranchTerminal::where('business_id', auth()->user()->business_id)
                        ->when(auth()->user()->default_branch_id, fn ($q) => $q->where('branch_id', auth()->user()->default_branch_id))
                        ->first();
                @endphp
                <nav class="hidden items-center gap-1 md:flex">
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('dashboard') ? 'bg-surface-2 text-ink' : '' }}">Dashboard</a>
                    @can('pos.operate')
                        @if ($navTerminal)
                            <a href="{{ route('pos.screen', $navTerminal->public_id) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">Point of Sale</a>
                        @endif
                    @endcan
                    @can('reports.view')
                        <a href="{{ route('reports.sales') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('reports.*') ? 'bg-surface-2 text-ink' : '' }}">Reports</a>
                    @endcan
                    @can('inventory.manage')
                        @if (app(\Modules\Settings\Services\SettingsService::class)->isFeatureEnabled(auth()->user()->business_id, 'stock_transfers'))
                            <a href="{{ route('inventory.transfers.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('inventory.*') ? 'bg-surface-2 text-ink' : '' }}">Transfers</a>
                        @endif
                    @endcan
                    @can('settings.manage')
                        <a href="{{ route('settings.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('settings.*') ? 'bg-surface-2 text-ink' : '' }}">Settings</a>
                    @endcan
                    @can('business.manage')
                        <a href="{{ route('backups.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('backups.*') ? 'bg-surface-2 text-ink' : '' }}">Backups</a>
                    @endcan
                </nav>

                <div class="flex items-center gap-3">
                    <a href="{{ route('notifications.index') }}" class="relative flex h-8 w-8 items-center justify-center rounded-lg border border-line text-ink-soft hover:bg-surface-2" aria-label="Notifications">
                        🔔
                        @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                        @if ($unread > 0)
                            <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-critical px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>
                    <button type="button" @click="theme = theme === 'dark' ? 'light' : 'dark'"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-ink-soft hover:bg-surface-2" aria-label="Toggle dark mode">
                        <span x-show="theme === 'light'">🌙</span>
                        <span x-show="theme === 'dark'">☀️</span>
                    </button>

                    <a href="{{ route('two-factor.show') }}" class="text-right leading-tight hover:opacity-80" title="Account security">
                        <div class="text-sm font-medium text-ink">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-ink-soft">{{ auth()->user()->getRoleNames()->first() ?? 'Staff' }}</div>
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
