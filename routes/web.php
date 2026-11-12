<?php

use Illuminate\Support\Facades\Route;
use KhattakBits\Core\Http\Middleware\EnsureUserActive;
use KhattakBits\Core\Support\ModuleRegistry;

/*
|--------------------------------------------------------------------------
| Product routes
|--------------------------------------------------------------------------
|
| Core routes (login, password reset, profile, attachments, document activity)
| ab package se aate hain — `KhattakBits\Core\CoreServiceProvider` unhein
| `loadRoutesFrom()` se register karta hai, is liye yahan dobara likhne ki
| zaroorat nahi.
|
| Yahan sirf product/module routes hain. Har module apni `routes.php` rakhta
| hai aur woh isi `auth + EnsureUserActive` group ke andar load hoti hai, taake
| module routes par bhi disabled-user lockout aur auth lazmi rahe.
|
*/

Route::middleware(['auth', EnsureUserActive::class])->group(function () {
    foreach (app(ModuleRegistry::class)->all() as $module) {
        if (is_file($file = $module['path'].'/routes.php')) {
            require $file;
        }
    }
});
