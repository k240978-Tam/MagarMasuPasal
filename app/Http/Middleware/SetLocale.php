<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the interface language for the request. A signed-in user's own
 * preference wins; otherwise the session remembers what a guest picked on
 * the login screen. Anything unrecognised falls back to the configured
 * default rather than half-translating the page.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('locales.supported', []));
        $sessionKey = config('locales.session_key', 'locale');

        $locale = $request->user()?->locale
            ?? $request->session()->get($sessionKey);

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
