<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Apply baseline security headers to every web response. CSP is intentionally
     * omitted here because the app mixes Inertia, Filament, Vite HMR, YouTube
     * embeds and arbitrary news image hosts — a strict policy would need to be
     * tuned per-page and verified end-to-end before rolling it out.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        return $response;
    }
}
