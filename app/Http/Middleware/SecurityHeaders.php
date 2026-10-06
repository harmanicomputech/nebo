<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // camera: asset QR scanning (planned); geolocation: venue capture (planned).
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Internal pages must not be kept by browsers or proxies (shared devices).
        if ($request->user()) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
