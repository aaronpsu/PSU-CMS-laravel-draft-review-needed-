<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsRequestGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        abort_unless(in_array(strtolower($request->getHost()), ['127.0.0.1', 'localhost'], true), 400, 'Untrusted host.');

        if ($request->request->has('_method') || $request->query->has('_method')
            || ($request->isJson() && $request->json()->has('_method'))
            || $request->headers->has('X-HTTP-METHOD-OVERRIDE')
            || ($request->getRealMethod() === 'POST' && $request->getMethod() !== 'POST')) {
            abort(400, 'HTTP method override is not supported.');
        }

        return $next($request);
    }
}
