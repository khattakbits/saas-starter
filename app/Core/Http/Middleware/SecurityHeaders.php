<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
        ."font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Local par Vite (5173, HMR) rukta hai, is liye CSP aur HSTS sirf local ke ilawa
        if (! app()->environment('local')) {
            if ($request->isSecure()) {
                $h->set('Strict-Transport-Security', 'max-age=31536000');
            }
            $mode = config('security.csp', 'report-only');
            if ($mode !== 'off') {
                $h->set($mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', self::CSP);
            }
        }

        return $response;
    }
}
