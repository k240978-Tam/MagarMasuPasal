<?php

namespace Modules\UserManagement\Http\Controllers\Api;

use App\Http\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\UserManagement\Http\Requests\ApiLoginRequest;
use Modules\UserManagement\Services\TwoFactorAuthService;

/**
 * Stateless token issuance for future mobile/integration clients — see
 * docs/architecture/06-api-design.md §6.1. Deliberately never touches the
 * session guard (no Auth::attempt/Auth::login): a Sanctum personal access
 * token is the only credential this flow produces.
 */
class ApiAuthController extends Controller
{
    use ApiResponds;

    public function login(ApiLoginRequest $request, TwoFactorAuthService $twoFactor): JsonResponse
    {
        $user = User::withoutTenantScope()->where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            event(new Failed('sanctum', $user, ['email' => $request->input('email')]));

            return $this->respond(['message' => 'These credentials do not match our records.'], status: 401);
        }

        if ($user->two_factor_confirmed_at) {
            $code = (string) $request->input('two_factor_code', '');

            if ($code === '') {
                return $this->respond(['message' => 'A two-factor code is required.'], status: 428);
            }

            if (! $this->verifyTwoFactor($user, $code, $twoFactor)) {
                event(new Failed('sanctum', $user, ['email' => $request->input('email')]));

                return $this->respond(['message' => 'That two-factor code is incorrect.'], status: 401);
            }
        }

        $token = $user->createToken((string) $request->input('device_name'))->plainTextToken;

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        return $this->respond([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], status: 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->respond(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->respond($this->userPayload($request->user()));
    }

    protected function verifyTwoFactor(User $user, string $code, TwoFactorAuthService $twoFactor): bool
    {
        if ($twoFactor->verifyCode($user->two_factor_secret, $code)) {
            return true;
        }

        $recoveryCodes = json_decode($user->two_factor_recovery_codes ?? '[]', true);
        $matchedIndex = $twoFactor->matchRecoveryCode($code, $recoveryCodes);

        if ($matchedIndex === null) {
            return false;
        }

        unset($recoveryCodes[$matchedIndex]);
        $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($recoveryCodes))])->save();

        return true;
    }

    protected function userPayload(User $user): array
    {
        return [
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'business_id' => $user->business_id,
            'default_branch_id' => $user->default_branch_id,
            'roles' => $user->getRoleNames(),
        ];
    }
}
