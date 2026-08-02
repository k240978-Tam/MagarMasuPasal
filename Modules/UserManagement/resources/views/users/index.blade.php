<x-layouts.app title="Manage Users">
    <div class="flex flex-col gap-6 max-w-4xl">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink text-balance">Manage Users</h1>
                <p class="mt-1 text-sm text-ink-soft">Create, edit, and manage team members across your branches.</p>
            </div>
            <a href="{{ route('users.create') }}" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-ink">Add User</a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-line bg-surface px-4 py-2 text-sm text-ink">{{ session('status') }}</div>
        @endif

        @if ($users->isEmpty())
            <div class="rounded-xl border border-line bg-surface p-8 text-center">
                <p class="text-ink-soft">No users yet. Create your first team member to get started.</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-line">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface text-left text-xs uppercase tracking-wide text-ink-soft">
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Last Login</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-t border-line">
                                <td class="px-6 py-4 font-medium">{{ $user->name }}</td>
                                <td class="px-6 py-4 text-ink-soft">{{ $user->email }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-accent/10 px-2 py-1 text-xs font-semibold text-accent">
                                        {{ $user->roles->first()?->name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($user->deleted_at)
                                        <span class="text-xs font-medium text-critical">Deleted</span>
                                    @elseif ($user->status === 'suspended')
                                        <span class="text-xs font-medium text-warning">Suspended</span>
                                    @else
                                        <span class="text-xs font-medium text-success">Active</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-ink-soft">
                                    {{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('users.edit', $user) }}" class="text-xs font-medium text-accent hover:underline">Edit</a>
                                    @if ($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete this user? This action cannot be undone.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ml-3 text-xs font-medium text-critical hover:underline">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
