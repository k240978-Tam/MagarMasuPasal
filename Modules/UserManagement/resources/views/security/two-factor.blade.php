<x-layouts.app title="Two-Factor Authentication">
    <div class="flex flex-col gap-6 max-w-lg">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">Two-Factor Authentication</h1>
            <p class="mt-1 text-sm text-ink-soft">Require an authenticator app code, in addition to your password, when signing in.</p>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-critical px-4 py-2 text-sm text-critical">{{ $errors->first() }}</div>
        @endif

        @if ($recoveryCodes)
            <div class="rounded-xl border border-warning bg-surface p-4">
                <h2 class="text-sm font-semibold">Save your recovery codes</h2>
                <p class="mt-1 text-xs text-ink-soft">Each code can be used once to sign in if you lose access to your authenticator app. They will not be shown again.</p>
                <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                    @foreach ($recoveryCodes as $code)
                        <div class="rounded-lg bg-surface-2 px-3 py-1.5">{{ $code }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($enabled)
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-success"></span>
                    <span class="text-sm font-medium text-ink">Two-factor authentication is enabled</span>
                </div>

                <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Confirm Password</label>
                        <input type="password" name="password" required class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                    </div>
                    <button type="submit" class="rounded-lg border border-line px-4 py-1.5 text-sm font-medium text-ink hover:bg-surface-2">Regenerate Recovery Codes</button>
                </form>

                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3 flex flex-wrap items-end gap-3 border-t border-line pt-3">
                    @csrf
                    @method('DELETE')
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Confirm Password</label>
                        <input type="password" name="password" required class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                    </div>
                    <button type="submit" class="rounded-lg border border-critical px-4 py-1.5 text-sm font-medium text-critical hover:bg-critical/5">Disable Two-Factor</button>
                </form>
            </div>
        @elseif ($pendingSecret)
            <div class="rounded-xl border border-line bg-surface p-4">
                <h2 class="text-sm font-semibold">Scan this QR code</h2>
                <p class="mt-1 text-xs text-ink-soft">Use Google Authenticator, Authy, or any TOTP app, then enter the 6-digit code below.</p>
                <div class="mt-3 flex justify-center">
                    <img src="data:image/svg+xml;base64,{{ $qrSvg }}" width="180" height="180">
                </div>
                <p class="mt-2 break-all text-center font-mono text-xs text-ink-soft">{{ $manualKey }}</p>

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">6-digit code</label>
                        <input type="text" name="code" inputmode="numeric" maxlength="6" required autofocus class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm tracking-widest">
                    </div>
                    <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Confirm</button>
                </form>
            </div>
        @else
            <div class="rounded-xl border border-line bg-surface p-4">
                <p class="text-sm text-ink-soft">Two-factor authentication is currently disabled.</p>
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Enable Two-Factor Authentication</button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.app>
