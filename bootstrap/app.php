<?php

use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Railway/any TLS-terminating proxy the app receives plain
        // HTTP; trusting the proxy's X-Forwarded-* headers lets Laravel see
        // the original HTTPS scheme so generated URLs and cookies are secure.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'tenant' => ResolveTenant::class,
        ]);

        $middleware->appendToGroup('web', ResolveTenant::class);
        $middleware->appendToGroup('api', ResolveTenant::class);

        // ResolveTenant must run before SubstituteBindings — otherwise an
        // implicit route-model-bound tenant-scoped model (e.g. {terminal})
        // resolves before TenantContext is set, so its global TenantScope
        // silently no-ops on the very query that's supposed to enforce it.
        // A plain appendToGroup() lands after SubstituteBindings in the
        // default 'web'/'api' group order, so priority is needed to fix
        // the relative position regardless of registration order.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenant::class,
        );

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
