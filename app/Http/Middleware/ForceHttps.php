<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && (app()->isProduction() || config('app.force_https'))) {
            $secureUrl = preg_replace('/^http:\/\//i', 'https://', $request->fullUrl()) ?: $request->fullUrl();

            return redirect()->to($secureUrl, 301);
        }

        return $next($request);
    }
}
