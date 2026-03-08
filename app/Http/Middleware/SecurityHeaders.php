<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
  /**
   * Add security headers to all responses.
   *
   * These headers protect against:
   * - XSS (Content-Security-Policy, X-XSS-Protection)
   * - Clickjacking (X-Frame-Options)
   * - MIME sniffing (X-Content-Type-Options)
   * - Referrer leakage (Referrer-Policy)
   * - Feature abuse (Permissions-Policy)
   * - Downgrade attacks (Strict-Transport-Security)
   */
  public function handle(Request $request, Closure $next): Response
  {
    $response = $next($request);

    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    $response->headers->set(
      'Content-Security-Policy',
      "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'"
    );

    if (config('app.env') === 'production') {
      $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }

    return $response;
  }
}
