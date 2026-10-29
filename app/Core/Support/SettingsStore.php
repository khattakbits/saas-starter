<?php

namespace App\Core\Support;

use App\Core\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class SettingsStore
{
    private const CACHE_KEY = 'settings.all';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
