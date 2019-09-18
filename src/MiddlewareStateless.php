<?php

namespace Nouvic\Google2FALaravel;

use Closure;
use Nouvic\Google2FALaravel\Support\Authenticator;

class MiddlewareStateless
{
    public function handle($request, Closure $next)
    {
        $authenticator = app(Authenticator::class)->bootStateless($request);

        if ($authenticator->isAuthenticated()) {
            return $next($request);
        }

        return $authenticator->makeRequestOneTimePasswordResponse();
    }
}
