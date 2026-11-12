<?php

namespace App\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use KhattakBits\Core\Support\Audit;

class PasswordResetController extends Controller
{
    /** Hamesha same jawab: account majood hai ya nahi, is ka pata nahi chalta */
    private const NEUTRAL = 'If an account exists for that email, a reset link has been sent.';

    public function requestForm()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::query()->where('email', mb_strtolower(trim($data['email'])))->first();

        if ($user) {
            Audit::log('auth.password_requested', $user, $user->email);

            // Disabled account ko link kabhi nahi jata
            if ($user->is_active) {
                $token = Password::broker()->createToken($user);
                $user->sendPasswordResetNotification($token);
            }
        }

        return back()->with('success', self::NEUTRAL);
    }

    public function resetForm(Request $request, string $token)
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(10), 'confirmed'],
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => $password,               // 'hashed' cast khud hash karta hai
                'remember_token' => Str::random(60),   // purane sessions yaad nahi rakhe jate
            ])->save();

            Audit::log('auth.password_reset', $user, $user->email);
        });

        if ($status !== Password::PASSWORD_RESET) {
            // Expired aur invalid mein farq nahi bataya jata
            throw ValidationException::withMessages([
                'email' => 'This reset link is invalid or has expired.',
            ]);
        }

        // Auto-login nahi: user khud naye password se sign in karta hai
        return redirect('/login')->with('success', 'Your password has been changed. Sign in with the new password.');
    }
}
