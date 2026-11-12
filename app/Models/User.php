<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Core\Support\EmailTemplates;
use App\Core\Support\Mailer;
use App\Core\Support\SettingsStore;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $attributes = ['is_active' => true];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Reset link email 'password_reset' template se jata hai (hard-coded text nahi).
     * Link aur token kabhi audit/email log mein nahi jate.
     */
    public function sendPasswordResetNotification($token): void
    {
        $rendered = app(EmailTemplates::class)->render('password_reset', [
            'user_name' => $this->name,
            'app_name' => (string) app(SettingsStore::class)->get('app_name', config('app.name')),
            'reset_url' => url('/reset-password/'.$token.'?'.http_build_query(['email' => $this->email])),
            'expires_minutes' => (int) config('auth.passwords.users.expire'),
        ]);

        Mailer::send($this->email, $rendered['subject'], $rendered['text'], [
            'template_key' => 'password_reset',
            'user_id' => $this->getKey(),
        ]);
    }
}
