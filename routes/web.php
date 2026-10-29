<?php

use App\Core\Http\Controllers\AttachmentController;
use App\Core\Http\Controllers\AuthController;
use App\Core\Http\Controllers\DocumentActivityController;
use App\Core\Http\Controllers\ProfileController;
use App\Core\Http\Middleware\EnsureUserActive;
use App\Core\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth', EnsureUserActive::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    Route::get('/attachments', [AttachmentController::class, 'index']);
    Route::post('/attachments', [AttachmentController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show']);
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // Document activity: views counter + lifecycle timeline
    Route::post('/documents/{type}/{id}/views', [DocumentActivityController::class, 'store'])
        ->where(['type' => '[a-z0-9_]+', 'id' => '[0-9]+'])
        ->middleware('throttle:120,1');
    Route::get('/documents/{type}/{id}/activity', [DocumentActivityController::class, 'show'])
        ->where(['type' => '[a-z0-9_]+', 'id' => '[0-9]+']);

    foreach (app(ModuleRegistry::class)->all() as $module) {
        if (is_file($file = $module['path'].'/routes.php')) {
            require $file;
        }
    }
});
