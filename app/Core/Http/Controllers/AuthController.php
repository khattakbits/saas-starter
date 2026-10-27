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

        // Disabled user login nahi kar sakta
        if (!Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            Audit::log('auth.failed', null, $credentials['email']);
            throw ValidationException::withMessages(['email' => 'These credentials do not match, or the account is disabled.']);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::log('auth.login', $request->user(), $request->user()->email);

        return redirect()->intended('/');
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