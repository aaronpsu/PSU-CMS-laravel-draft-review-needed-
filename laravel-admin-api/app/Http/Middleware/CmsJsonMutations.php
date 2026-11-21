<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsJsonMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->request->has('_method') || $request->query->has('_method')
            || ($request->isJson() && $request->json()->has('_method'))
            || $request->headers->has('X-HTTP-METHOD-OVERRIDE')
            || ($request->getRealMethod() === 'POST' && $request->getMethod() !== 'POST')) {
            abort(400, 'HTTP method override is not supported.');
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $contentLength = $request->headers->get('Content-Length');
        if ($contentLength !== null && ! ctype_digit($contentLength)) {
            abort(400, 'The Content-Length header is invalid.');
        }
        if (($contentLength !== null && (int) $contentLength > 8 * 1024 * 1024)
            || strlen($request->getContent()) > 8 * 1024 * 1024) {
            abort(413, 'The request payload is too large.');
        }
        if (! str_contains(strtolower((string) $request->header('Content-Type')), 'application/json')) {
            abort(415, 'Mutating requests must use application/json.');
        }
        $token = (string) $request->header('X-CSRF-TOKEN');
        if ($token === '' || ! hash_equals((string) $request->session()->token(), $token)) {
            abort(419, 'Your session has expired. Refresh and sign in again.');
        }

        return $next($request);
    }
}
