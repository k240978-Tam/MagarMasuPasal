<x-layouts.guest title="Two-Factor Verification">
    <h1 class="text-lg font-semibold text-ink text-balance">Two-factor verification</h1>
    <p class="mt-1 text-sm text-ink-soft">Enter the 6-digit code from your authenticator app, or a recovery code.</p>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-critical/30 bg-critical/10 px-3 py-2 text-sm text-critical">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 flex flex-col gap-4">
        @csrf

        <div>
            <label for="code" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Code</label>
            <input id="code" name="code" type="text" inputmode="numeric" required autofocus autocomplete="one-time-code"
                class="mt-1.5 block w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink tracking-widest outline-none focus:border-accent focus:ring-2 focus:ring-accent/30">
        </div>

        <button type="submit"
            class="mt-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-accent-ink transition hover:brightness-95">
            Verify
        </button>
    </form>

    <a href="{{ route('login') }}" class="mt-4 inline-block text-xs font-medium text-ink-soft hover:underline">Back to sign in</a>
</x-layouts.guest>
