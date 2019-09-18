<?php

namespace Nouvic\Google2FALaravel;

use Closure;
use Nouvic\Google2FALaravel\Support\Authenticator;

class Middleware
{
    public function handle($request, Closure $next)
    {
        $authenticator = app(Authenticator::class)->boot($request);

        if ($authenticator->isAuthenticated()) {
            return $next($request);
        }

        return $authenticator->makeRequestOneTimePasswordResponse();
    }
}
