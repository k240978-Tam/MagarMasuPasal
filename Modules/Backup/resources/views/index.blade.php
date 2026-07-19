<x-layouts.app title="Backups">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink text-balance">Backups</h1>
                <p class="mt-1 text-sm text-ink-soft">Database + media, zipped into one restorable archive. Scheduled nightly, or trigger one now.</p>
            </div>
            <form method="POST" action="{{ route('backups.store') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink">Back Up Now</button>
            </form>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-critical px-4 py-2 text-sm text-critical">{{ session('error') }}</div>
        @endif

        <div class="rounded-xl border border-line bg-surface p-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-ink-soft">
                            <th class="pb-2 pr-4">Started</th>
                            <th class="pb-2 pr-4">Trigger</th>
                            <th class="pb-2 pr-4">Status</th>
                            <th class="pb-2 pr-4 text-right">Size</th>
                            <th class="pb-2">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr class="border-t border-line">
                                <td class="py-2 pr-4">{{ $run->started_at->format('d M, h:i A') }}</td>
                                <td class="py-2 pr-4 text-ink-soft">{{ ucfirst($run->trigger) }}</td>
                                <td class="py-2 pr-4">
                                    <span @class([
                                        'rounded-full border px-2 py-0.5 text-xs font-medium',
                                        'border-success text-success' => $run->status === 'success',
                                        'border-warning text-warning' => $run->status === 'running',
                                        'border-critical text-critical' => $run->status === 'failed',
                                    ])>{{ ucfirst($run->status) }}</span>
                                    @if ($run->status === 'failed' && $run->failure_reason)
                                        <div class="mt-1 text-xs text-critical">{{ $run->failure_reason }}</div>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums text-ink-soft">{{ $run->sizeHuman() ?? '—' }}</td>
                                <td class="py-2 text-ink-soft">{{ $run->triggeredBy?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-ink-soft">No backups yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $runs->links() }}
    </div>
</x-layouts.app>
