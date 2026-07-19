<?php

namespace Modules\UserManagement\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\UserManagement\Http\Requests\LoginRequest;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('usermanagement::auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }

        $user = Auth::user();

        if ($user->two_factor_confirmed_at) {
            // The password check just above already fully logged the user
            // in (and fired the Login event) — step back out immediately so
            // a stolen password alone can never reach an authenticated
            // session, only the challenge screen.
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put('two_factor.pending_user_id', $user->id);
            $request->session()->put('two_factor.remember', $remember);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
