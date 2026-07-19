<?php

namespace Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\UserManagement\Http\Requests\ConfirmTwoFactorRequest;
use Modules\UserManagement\Http\Requests\DisableTwoFactorRequest;
use Modules\UserManagement\Services\TwoFactorAuthService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $pendingSecret = $request->session()->get('two_factor.pending_secret');

        $qrSvg = null;
        if ($pendingSecret) {
            $url = app(TwoFactorAuthService::class)->qrCodeUrl($user->business->name, $user->email, $pendingSecret);
            $qrSvg = base64_encode(QrCode::format('svg')->size(180)->margin(0)->generate($url));
        }

        return view('usermanagement::security.two-factor', [
            'enabled' => (bool) $user->two_factor_confirmed_at,
            'pendingSecret' => $pendingSecret,
            'qrSvg' => $qrSvg,
            'manualKey' => $pendingSecret,
            // Pulled (read-once): recovery codes must never survive a page refresh.
            'recoveryCodes' => $request->session()->pull('two_factor.recovery_codes'),
        ]);
    }

    public function enable(Request $request, TwoFactorAuthService $twoFactor): RedirectResponse
    {
        $request->session()->put('two_factor.pending_secret', $twoFactor->generateSecretKey());

        return redirect()->route('two-factor.show');
    }

    public function confirm(ConfirmTwoFactorRequest $request, TwoFactorAuthService $twoFactor): RedirectResponse
    {
        $secret = $request->session()->get('two_factor.pending_secret');

        if (! $secret || ! $twoFactor->verifyCode($secret, $request->string('code'))) {
            return back()->withErrors(['code' => 'That code is incorrect or has expired. Try again.']);
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();

        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => json_encode($twoFactor->hashRecoveryCodes($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('two_factor.pending_secret');
        $request->session()->put('two_factor.recovery_codes', $recoveryCodes);

        return redirect()->route('two-factor.show')->with('status', 'Two-factor authentication is now enabled. Save your recovery codes — they will not be shown again.');
    }

    public function disable(DisableTwoFactorRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('two-factor.show')->with('status', 'Two-factor authentication has been disabled.');
    }

    public function regenerateRecoveryCodes(DisableTwoFactorRequest $request, TwoFactorAuthService $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->two_factor_confirmed_at, 403);

        $recoveryCodes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => json_encode($twoFactor->hashRecoveryCodes($recoveryCodes))])->save();

        $request->session()->put('two_factor.recovery_codes', $recoveryCodes);

        return redirect()->route('two-factor.show')->with('status', 'New recovery codes generated — your old codes no longer work.');
    }
}
