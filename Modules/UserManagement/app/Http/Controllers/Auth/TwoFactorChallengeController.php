<?php

namespace Modules\UserManagement\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\UserManagement\Http\Requests\TwoFactorChallengeRequest;
use Modules\UserManagement\Services\TwoFactorAuthService;

/**
 * The login-time half of 2FA: verifies the pending user stashed in the
 * session by LoginController (never a full auth() user — that's the whole
 * point) against a TOTP code or a single-use recovery code.
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('two_factor.pending_user_id')) {
            return redirect()->route('login');
        }

        return view('usermanagement::auth.two-factor-challenge');
    }

    public function verify(TwoFactorChallengeRequest $request, TwoFactorAuthService $twoFactor): RedirectResponse
    {
        $userId = $request->session()->get('two_factor.pending_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::withoutTenantScope()->findOrFail($userId);
        $code = trim($request->string('code'));

        $verified = $twoFactor->verifyCode($user->two_factor_secret, $code);

        if (! $verified) {
            $recoveryCodes = json_decode($user->two_factor_recovery_codes ?? '[]', true);
            $matchedIndex = $twoFactor->matchRecoveryCode($code, $recoveryCodes);

            if ($matchedIndex !== null) {
                unset($recoveryCodes[$matchedIndex]);
                $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($recoveryCodes))])->save();
                $verified = true;
            }
        }

        if (! $verified) {
            return back()->withErrors(['code' => 'That code is incorrect.']);
        }

        $remember = $request->session()->get('two_factor.remember', false);
        $request->session()->forget(['two_factor.pending_user_id', 'two_factor.remember']);
        $request->session()->regenerate();

        Auth::login($user, $remember);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }
}
