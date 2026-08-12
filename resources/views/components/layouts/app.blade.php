<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
                        <div class="text-xs text-ink-soft">{{ auth()->user()->defaultBranch?->name ?? __('nav.all_branches') }}</div>
                    </div>
                </div>

                @php
                    $navTerminal = \Modules\Tenancy\Models\BranchTerminal::where('business_id', auth()->user()->business_id)
                        ->when(auth()->user()->default_branch_id, fn ($q) => $q->where('branch_id', auth()->user()->default_branch_id))
                        ->first();
                @endphp
                <nav class="hidden items-center gap-1 md:flex">
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('dashboard') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.dashboard') }}</a>
                    @can('pos.operate')
                        @if ($navTerminal)
                            <a href="{{ route('pos.screen', $navTerminal->public_id) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">{{ __('nav.pos') }}</a>
                        @endif
                    @endcan
                    @can('products.manage')
                        <a href="{{ route('products.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('products.*') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.products') }}</a>
                    @endcan
                    @can('reports.view')
                        <a href="{{ route('reports.sales') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('reports.*') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.reports') }}</a>
                    @endcan
                    @can('sales.manage')
                        <a href="{{ route('online-orders.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('online-orders.*') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.online_orders') }}</a>
                    @endcan
                    @can('inventory.manage')
                        @if (app(\Modules\Settings\Services\SettingsService::class)->isFeatureEnabled(auth()->user()->business_id, 'stock_transfers'))
                            <a href="{{ route('inventory.transfers.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('inventory.*') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.transfers') }}</a>
                        @endif
                    @endcan
                    @can('settings.manage')
                        <a href="{{ route('settings.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('settings.*') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.settings') }}</a>
                    @endcan
                    @can('users.manage')
                        <a href="{{ route('users.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('users.*') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.users') }}</a>
                    @endcan
                    @can('business.manage')
                        <a href="{{ route('backups.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('backups.*') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.backups') }}</a>
                        <a href="{{ route('api-tokens.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink {{ request()->routeIs('api-tokens.*') ? 'bg-surface-2 text-ink' : '' }}">{{ __('nav.api_tokens') }}</a>
                    @endcan
                </nav>

                <div class="flex items-center gap-3">
                    <a href="{{ route('notifications.index') }}" class="relative flex h-8 w-8 items-center justify-center rounded-lg border border-line text-ink-soft hover:bg-surface-2" aria-label="{{ __('nav.notifications') }}">
                        🔔
                        @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                        @if ($unread > 0)
                            <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-critical px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>
                    <form method="POST" action="{{ route('locale.update') }}" class="flex items-center">
                        @csrf
                        <label for="locale" class="sr-only">{{ __('nav.language') }}</label>
                        <select id="locale" name="locale" onchange="this.form.submit()"
                            class="h-8 rounded-lg border border-line bg-surface px-2 text-xs font-medium text-ink-soft hover:bg-surface-2">
                            @foreach (config('locales.supported') as $code => $meta)
                                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $meta['native'] }}</option>
                            @endforeach
                        </select>
                    </form>

                    <button type="button" @click="theme = theme === 'dark' ? 'light' : 'dark'"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-ink-soft hover:bg-surface-2" aria-label="{{ __('nav.toggle_dark_mode') }}">
                        <span x-show="theme === 'light'">🌙</span>
                        <span x-show="theme === 'dark'">☀️</span>
                    </button>

                    <a href="{{ route('two-factor.show') }}" class="text-right leading-tight hover:opacity-80" title="{{ __('nav.account_security') }}">
                        <div class="text-sm font-medium text-ink">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-ink-soft">{{ auth()->user()->getRoleNames()->first() ?? __('nav.staff') }}</div>
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-soft hover:bg-surface-2 hover:text-ink">
                            {{ __('nav.sign_out') }}
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
