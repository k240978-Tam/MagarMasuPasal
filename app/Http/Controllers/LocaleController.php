<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch the interface language. Stored on the user when signed in so it
     * follows them to any device, and in the session otherwise so the login
     * screen and customer display can be switched too.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('locales.supported')))],
        ]);

        $request->session()->put(config('locales.session_key', 'locale'), $validated['locale']);

        $request->user()?->forceFill(['locale' => $validated['locale']])->save();

        return back()->with('status', __('common.language_changed'));
    }
}
