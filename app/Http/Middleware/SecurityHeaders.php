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

        // Responses that set their own policy (document downloads) keep it.
        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Internal pages must not be kept by browsers or proxies (shared devices).
        if ($request->user()) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Scripts and styles only from this site (D66). Alpine evaluates its
     * attribute expressions, which needs 'unsafe-eval'; there are no inline
     * scripts or handlers, and no user-supplied HTML is ever rendered.
     * Style attributes (chart sizes, colours) need 'unsafe-inline' for styles.
     */
    private function contentSecurityPolicy(Request $request): string
    {
        $dev = '';
        if (app()->environment('local') && is_file(public_path('hot'))) {
            $origin = rtrim((string) file_get_contents(public_path('hot')));
            $ws = preg_replace('#^http#', 'ws', $origin);
            $dev = " {$origin} {$ws}";
        }

        return implode('; ', array_filter([
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'".$dev,
            "style-src 'self' 'unsafe-inline'".$dev,
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'".$dev,
            "manifest-src 'self'",
            "worker-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            $request->isSecure() ? 'upgrade-insecure-requests' : null,
        ]));
    }
}
