<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect('/login')->with('error', 'Your account has been disabled.');
        }

        // Roles/permissions sirf login user ke liye, aur sirf aik baar (har User query par nahi)
        $user?->loadMissing('roles.permissions', 'permissions');

        return $next($request);
    }
}
