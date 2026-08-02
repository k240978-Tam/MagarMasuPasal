<x-layouts.app title="Edit User">
    <div class="flex flex-col gap-6 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Edit User</h1>
            <p class="mt-1 text-sm text-ink-soft">Update user details and change their role or branch assignment.</p>
        </div>

        <form method="POST" action="{{ route('users.update', $user) }}" class="rounded-xl border border-line bg-surface p-6">
            @csrf @method('PATCH')

            <div class="flex flex-col gap-6">
                <div>
                    <label for="name" class="block text-sm font-semibold text-ink">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('name') border-critical @enderror">
                    @error('name')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-ink">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('email') border-critical @enderror">
                    @error('email')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-semibold text-ink">Phone <span class="text-ink-soft">(Optional)</span></label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('phone') border-critical @enderror">
                    @error('phone')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="role" class="block text-sm font-semibold text-ink">Role</label>
                    <select id="role" name="role" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('role') border-critical @enderror">
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(old('role', $userRole) === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="default_branch_id" class="block text-sm font-semibold text-ink">Default Branch</label>
                    <select id="default_branch_id" name="default_branch_id" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('default_branch_id') border-critical @enderror">
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('default_branch_id', $user->default_branch_id) == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('default_branch_id')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-semibold text-ink">Status</label>
                    <select id="status" name="status" required class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('status') border-critical @enderror">
                        <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                        <option value="suspended" @selected(old('status', $user->status) === 'suspended')>Suspended</option>
                    </select>
                    @error('status')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-lg bg-surface-2 p-3">
                    <p class="text-xs text-ink-soft">Leave the password fields empty to keep the current password.</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-ink">New Password <span class="text-ink-soft">(Optional)</span></label>
                    <input type="password" id="password" name="password" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm @error('password') border-critical @enderror" placeholder="Leave blank to keep current password">
                    @error('password')<p class="mt-1 text-xs text-critical">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-ink">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="mt-1 w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm">
                </div>

                <div class="flex gap-3 border-t border-line pt-6">
                    <a href="{{ route('users.index') }}" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold hover:bg-surface-2">Cancel</a>
                    <button type="submit" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-ink">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.app>
