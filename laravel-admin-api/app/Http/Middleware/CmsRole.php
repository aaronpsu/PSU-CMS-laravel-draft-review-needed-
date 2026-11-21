<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = strtolower((string) $request->user()?->role);
        abort_unless(in_array($role, array_map('strtolower', $roles), true), 403, 'You do not have permission for this action.');
        return $next($request);
    }
}
