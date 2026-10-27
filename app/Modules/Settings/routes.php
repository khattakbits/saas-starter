<?php

use App\Modules\Settings\Http\Controllers\AuditLogController;
use App\Modules\Settings\Http\Controllers\RoleController;
use App\Modules\Settings\Http\Controllers\SystemController;
use App\Modules\Settings\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->group(function () {
    /* Users */
    Route::middleware('can:settings.users.view')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/data', [UserController::class, 'data']);
    });
    Route::middleware('can:settings.users.create')->group(function () {
        Route::get('users/create', [UserController::class, 'create']);
        Route::post('users', [UserController::class, 'store']);
    });
    Route::middleware('can:settings.users.update')->group(function () {
        Route::post('users/bulk-status', [UserController::class, 'bulkStatus']);
        Route::get('users/{user}', [UserController::class, 'edit'])->whereNumber('user');
        Route::put('users/{user}', [UserController::class, 'update'])->whereNumber('user');
    });

    /* Roles */
    Route::middleware('can:settings.roles.view')->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('roles/data', [RoleController::class, 'data']);
    });
    Route::middleware('can:settings.roles.create')->group(function () {
        Route::get('roles/create', [RoleController::class, 'create']);
        Route::post('roles', [RoleController::class, 'store']);
    });
    Route::middleware('can:settings.roles.update')->group(function () {
        Route::get('roles/{role}', [RoleController::class, 'edit'])->whereNumber('role');
        Route::put('roles/{role}', [RoleController::class, 'update'])->whereNumber('role');
    });
    Route::post('roles/bulk-delete', [RoleController::class, 'bulkDestroy'])
        ->middleware('can:settings.roles.delete');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->whereNumber('role')
        ->middleware('can:settings.roles.delete');

    /* Audit log */
    Route::middleware('can:settings.audit.view')->group(function () {
        Route::get('audit-log', [AuditLogController::class, 'index']);
        Route::get('audit-log/data', [AuditLogController::class, 'data']);
    });

    /* System settings */
    Route::get('system', [SystemController::class, 'show'])->middleware('can:settings.system.view');
    Route::put('system', [SystemController::class, 'update'])->middleware('can:settings.system.update');
});