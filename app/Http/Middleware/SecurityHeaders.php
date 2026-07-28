<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline response hardening (CLAUDE.md §14 Security Hardening), applied
 * globally to every response. Deliberately does NOT set a
 * Content-Security-Policy: the app relies heavily on inline Alpine.js
 * `x-data`/`x-on:` attributes and Blade-inlined `<script>`/`<style>` (e.g.
 * the services/appointments cascading pickers), so a CSP strict enough to
 * matter would either break those or need `unsafe-inline` — which adds
 * little real protection while creating an ongoing maintenance burden as
 * new inline attributes are added. A nonce-based CSP is a deliberate,
 * documented future improvement, not something to ship half-effective here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
