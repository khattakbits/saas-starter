<?php

namespace App\Core\Http\Controllers;

use App\Core\Support\Audit;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim((string) $credentials['email']));

        // Disabled user login nahi kar sakta
        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            Audit::log('auth.failed', null, $email);
            throw ValidationException::withMessages(['email' => 'These credentials do not match, or the account is disabled.']);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::log('auth.login', $request->user(), $request->user()->email);

        // Safe cross-product redirect for satellite SaaS apps
        $target = $request->input('redirect_uri') ?? $request->input('return_to') ?? $request->query('redirect_uri') ?? $request->query('return_to');
        if ($target && is_string($target) && $this->isSafeRedirect($target, $request)) {
            return redirect()->to($target);
        }

        return redirect()->intended('/');
    }

    private function isSafeRedirect(string $target, Request $request): bool
    {
        if (str_starts_with($target, '//') || str_starts_with($target, '/\\')) {
            return false;
        }

        if (str_starts_with($target, '/')) {
            return true;
        }

        $parsed = parse_url($target);
        if (! isset($parsed['host'])) {
            return false;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: $request->getHost();
        $allowedHosts = array_filter(array_map('trim', explode(',', (string) env('ALLOWED_SSO_HOSTS', ''))));
        $allowedHosts[] = $appHost;

        return in_array($parsed['host'], $allowedHosts, true);
    }

    public function destroy(Request $request)
    {
        if ($request->user()) {
            Audit::log('auth.logout', $request->user(), $request->user()->email);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
