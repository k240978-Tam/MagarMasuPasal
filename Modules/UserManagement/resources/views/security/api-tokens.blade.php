<x-layouts.app title="API Tokens">
    <div class="flex flex-col gap-6 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink text-balance">API Tokens</h1>
            <p class="mt-1 text-sm text-ink-soft">Personal access tokens for integrating a mobile app or external tool with the /api/v1 endpoints. Each token acts as you, scoped to this business.</p>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif

        @if ($plainTextToken)
            <div class="rounded-xl border border-warning bg-surface p-4">
                <h2 class="text-sm font-semibold">Your new token</h2>
                <p class="mt-1 text-xs text-ink-soft">Copy it now — it will not be shown again.</p>
                <div class="mt-3 break-all rounded-lg bg-surface-2 px-3 py-2 font-mono text-sm">{{ $plainTextToken }}</div>
            </div>
        @endif

        <div class="rounded-xl border border-line bg-surface p-4">
            <h2 class="text-sm font-semibold">Active Tokens</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-ink-soft">
                            <th class="pb-2 pr-4">Name</th>
                            <th class="pb-2 pr-4">Last Used</th>
                            <th class="pb-2 pr-4">Created</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            <tr class="border-t border-line">
                                <td class="py-2 pr-4">{{ $token->name }}</td>
                                <td class="py-2 pr-4 text-ink-soft">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="py-2 pr-4 text-ink-soft">{{ $token->created_at->format('d M Y') }}</td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('api-tokens.destroy', $token->id) }}" onsubmit="return confirm('Revoke this token? Anything using it will stop working immediately.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-critical hover:underline">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-ink-soft">No API tokens yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('api-tokens.store') }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-ink-soft">Token Name</label>
                    <input type="text" name="name" required placeholder="e.g. Mobile App" class="mt-1 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm">
                </div>
                <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Create Token</button>
            </form>
        </div>
    </div>
</x-layouts.app>
