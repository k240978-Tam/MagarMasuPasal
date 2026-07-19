<x-layouts.guest title="Sign in">
    <h1 class="text-lg font-semibold text-ink text-balance">Sign in to your account</h1>
    <p class="mt-1 text-sm text-ink-soft">Enter your staff email and password to continue.</p>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-critical/30 bg-critical/10 px-3 py-2 text-sm text-critical">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-4">
        @csrf

        <div>
            <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                class="mt-1.5 block w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/30">
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Password</label>
            <input id="password" name="password" type="password" required
                class="mt-1.5 block w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/30">
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="remember" class="rounded border-line text-accent focus:ring-accent">
            Remember me
        </label>

        <button type="submit"
            class="mt-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-accent-ink transition hover:brightness-95">
            Sign in
        </button>
    </form>
</x-layouts.guest>
