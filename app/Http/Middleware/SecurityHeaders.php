<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add headers for normal HTTP responses
        if (!$response instanceof BinaryFileResponse) {
            $this->addSecurityHeaders($request, $response);
        }

        return $response;
    }

    /**
     * Add security headers to the response.
     */
    private function addSecurityHeaders(Request $request, Response $response): void
    {
        // HSTS - Force HTTPS
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer information
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        // Restrict browser APIs
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        // Legacy XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Content Security Policy (production only)
        if (config('app.env') === 'production') {
            $csp =
                "default-src 'self'; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                "style-src 'self' 'unsafe-inline'; " .
                "img-src 'self' data: https:; " .
                "font-src 'self'; " .
                "connect-src 'self'; " .
                "frame-ancestors 'self'; " .
                "base-uri 'self'; " .
                "form-action 'self';";

            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Disable caching for API responses
        if ($request->is('api/*')) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }
}
