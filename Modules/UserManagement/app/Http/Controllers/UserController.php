<?php

namespace Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\Branch;
use Modules\UserManagement\Http\Requests\StoreUserRequest;
use Modules\UserManagement\Http\Requests\UpdateUserRequest;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $businessId = $request->user()->business_id;

        $users = User::where('business_id', $businessId)
            ->withTrashed()
            ->orderBy('name')
            ->get();

        return view('usermanagement::users.index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request): View
    {
        $businessId = $request->user()->business_id;

        $branches = Branch::where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $roles = Role::where('business_id', null)
            ->where('name', '!=', 'Owner')
            ->orderBy('name')
            ->get();

        return view('usermanagement::users.create', [
            'branches' => $branches,
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['business_id'] = $request->user()->business_id;
        $validated['password'] = bcrypt($validated['password']);

        $user = User::create($validated);
        $user->branches()->sync([$validated['default_branch_id'] => ['is_default' => true]]);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.index')->with('status', 'User created successfully.');
    }

    public function edit(Request $request, User $user): View
    {
        $request->user()->can('users.manage') || abort(403);
        $user->business_id === $request->user()->business_id || abort(403);

        $businessId = $request->user()->business_id;

        $branches = Branch::where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $roles = Role::where('business_id', null)
            ->where('name', '!=', 'Owner')
            ->orderBy('name')
            ->get();

        $userRole = $user->getRoleNames()->first();

        return view('usermanagement::users.edit', [
            'user' => $user,
            'branches' => $branches,
            'roles' => $roles,
            'userRole' => $userRole,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $request->user()->business_id === $user->business_id || abort(403);

        $validated = $request->validated();

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.index')->with('status', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->can('users.manage') || abort(403);
        $request->user()->business_id === $user->business_id || abort(403);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('status', 'User deleted.');
    }
}
