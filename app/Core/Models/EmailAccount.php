<?php

namespace App\Core\Models;

use App\Core\Support\Mailer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EmailAccount extends Model
{
    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_test_ok' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Pehla active account khud default ban jata hai; inactive account kabhi default nahi
        static::creating(function (self $account) {
            if ($account->is_active === null) {
                $account->is_active = true;
            }
            if (! $account->is_active) {
                $account->is_default = false;

                return;
            }
            if (! static::query()->where('is_active', true)->exists()) {
                $account->is_default = true;
            }
        });

        static::created(function (self $account) {
            if ($account->is_default) {
                static::makeDefault($account);
            }
        });

        // Default account delete ho to sab se purana active account default ban jaye
        static::deleted(function (self $account) {
            if ($account->is_default) {
                static::ensureDefault();
            }
        });

        static::saved(fn () => Mailer::forgetReady());
        static::deleted(fn () => Mailer::forgetReady());
    }

    /** Sirf aik default: baqi sab isi transaction mein un-default ho jate hain */
    public static function makeDefault(self $account): void
    {
        DB::transaction(function () use ($account) {
            static::query()
                ->whereKeyNot($account->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            if (! $account->is_default) {
                $account->forceFill(['is_default' => true])->save();
            }
        });
    }

    /** Koi default na bache to sab se purana active account default ban jata hai */
    public static function ensureDefault(): void
    {
        if (static::query()->where('is_default', true)->exists()) {
            return;
        }

        $next = static::query()->where('is_active', true)->orderBy('id')->first();
        if ($next) {
            static::makeDefault($next);
        }
    }
}
