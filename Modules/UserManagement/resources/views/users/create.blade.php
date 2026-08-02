<x-layouts.app title="Add User">
    <div class="flex flex-col gap-6 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Add User</h1>
            <p class="mt-1 text-sm text-ink-soft">Create a new team member account and assign them a role.</p>
        </div>

        <form method="POST" action="{{ route('users.store') }}" class="rounded-xl border border-line bg-surface p-6">
            @csrf

            <div class="flex flex-col gap-6">
                <div>
                    <label for="name" class="block text-sm font-semibold text-ink">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('name') border-critical @enderror" placeholder="e.g. John Doe">
                    @error('name')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-ink">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('email') border-critical @enderror" placeholder="john@example.com">
                    @error('email')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-semibold text-ink">Phone <span class="text-ink-soft">(Optional)</span></label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('phone') border-critical @enderror" placeholder="+977 ...">
                    @error('phone')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="role" class="block text-sm font-semibold text-ink">Role</label>
                    <select id="role" name="role" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('role') border-critical @enderror">
                        <option value="">Select a role...</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="default_branch_id" class="block text-sm font-semibold text-ink">Default Branch</label>
                    <select id="default_branch_id" name="default_branch_id" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('default_branch_id') border-critical @enderror">
                        <option value="">Select a branch...</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('default_branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('default_branch_id')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-ink">Password</label>
                    <input type="password" id="password" name="password" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('password') border-critical @enderror" placeholder="Minimum 8 characters">
                    @error('password')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-ink">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm" placeholder="Repeat password">
                </div>

                <div class="flex gap-3 border-t border-line pt-6">
                    <a href="{{ route('users.index') }}" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold hover:bg-surface-2">Cancel</a>
                    <button type="submit" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-ink">Create User</button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.app>
