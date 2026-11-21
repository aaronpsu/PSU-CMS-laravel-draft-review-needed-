<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalPrototypeOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        // Keep prototype API disabled in production, but do not reject local
        // development requests based on the browser's network interface/IP.
        abort_unless(app()->environment(['local', 'testing']), 403);

        return $next($request);
    }
}
