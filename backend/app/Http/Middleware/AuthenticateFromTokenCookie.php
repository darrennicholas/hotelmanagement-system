<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFromTokenCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && $request->cookie('hotel_token')) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie('hotel_token'));
        }

        return $next($request);
    }
}
