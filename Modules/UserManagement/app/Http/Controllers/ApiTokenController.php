<?php

namespace Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\UserManagement\Http\Requests\StoreApiTokenRequest;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        return view('usermanagement::security.api-tokens', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            // Pulled (read-once): the plaintext token must never survive a page refresh.
            'plainTextToken' => $request->session()->pull('api_token.plain_text'),
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken($request->string('name'));

        $request->session()->put('api_token.plain_text', $token->plainTextToken);

        return redirect()->route('api-tokens.index')->with('status', 'API token created — copy it now, it will not be shown again.');
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return redirect()->route('api-tokens.index')->with('status', 'Token revoked.');
    }
}
