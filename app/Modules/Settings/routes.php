<?php

use App\Modules\Settings\Http\Controllers\AuditLogController;
use App\Modules\Settings\Http\Controllers\EmailAccountController;
use App\Modules\Settings\Http\Controllers\EmailLogController;
use App\Modules\Settings\Http\Controllers\EmailSettingsController;
use App\Modules\Settings\Http\Controllers\EmailTemplateController;
use App\Modules\Settings\Http\Controllers\RecycleBinController;
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
        Route::get('audit-log/users', [AuditLogController::class, 'users']);
        Route::get('audit-log/{log}', [AuditLogController::class, 'show'])->whereNumber('log');
    });

    /* System settings */
    Route::get('system', [SystemController::class, 'show'])->middleware('can:settings.system.view');
    Route::put('system', [SystemController::class, 'update'])->middleware('can:settings.system.update');

    /* Recycle Bin */
    Route::middleware('can:settings.recycle_bin.view')->group(function () {
        Route::get('recycle-bin', [RecycleBinController::class, 'index']);
        Route::get('recycle-bin/data', [RecycleBinController::class, 'data']);
    });
    Route::post('recycle-bin/restore', [RecycleBinController::class, 'restore'])->middleware('can:settings.recycle_bin.restore');
    Route::post('recycle-bin/purge', [RecycleBinController::class, 'purge'])->middleware('can:settings.recycle_bin.purge');

    /* Email accounts */
    Route::middleware('can:settings.email_accounts.view')->group(function () {
        Route::get('email-accounts', [EmailAccountController::class, 'index']);
        Route::get('email-accounts/data', [EmailAccountController::class, 'data']);
    });
    Route::middleware('can:settings.email_accounts.create')->group(function () {
        Route::get('email-accounts/create', [EmailAccountController::class, 'create']);
        Route::post('email-accounts', [EmailAccountController::class, 'store']);
    });
    Route::middleware('can:settings.email_accounts.update')->group(function () {
        Route::get('email-accounts/{account}', [EmailAccountController::class, 'edit'])->whereNumber('account');
        Route::put('email-accounts/{account}', [EmailAccountController::class, 'update'])->whereNumber('account');
        Route::post('email-accounts/{account}/test', [EmailAccountController::class, 'test'])
            ->whereNumber('account')
            ->middleware('throttle:5,1');
    });
    Route::delete('email-accounts/{account}', [EmailAccountController::class, 'destroy'])
        ->whereNumber('account')
        ->middleware('can:settings.email_accounts.delete');

    /* Email log (read-only report) */
    Route::middleware('can:settings.email_log.view')->group(function () {
        Route::get('email-log', [EmailLogController::class, 'index']);
        Route::get('email-log/data', [EmailLogController::class, 'data']);
        Route::get('email-log/{log}', [EmailLogController::class, 'show'])->whereNumber('log');
    });

    /* Email settings */
    Route::get('email', [EmailSettingsController::class, 'show'])->middleware('can:settings.email.view');
    Route::put('email', [EmailSettingsController::class, 'update'])->middleware('can:settings.email.update');

    /* Email templates (overrides only; defaults live in code) */
    Route::middleware('can:settings.email_templates.view')->group(function () {
        Route::get('email-templates', [EmailTemplateController::class, 'index']);
        Route::get('email-templates/{key}', [EmailTemplateController::class, 'edit'])->where('key', '[a-z0-9_]+');
    });
    Route::middleware('can:settings.email_templates.update')->group(function () {
        Route::put('email-templates/{key}', [EmailTemplateController::class, 'update'])->where('key', '[a-z0-9_]+');
        Route::delete('email-templates/{key}', [EmailTemplateController::class, 'destroy'])->where('key', '[a-z0-9_]+');
        Route::post('email-templates/{key}/preview', [EmailTemplateController::class, 'preview'])
            ->where('key', '[a-z0-9_]+')->middleware('throttle:30,1');
        Route::post('email-templates/{key}/test', [EmailTemplateController::class, 'send'])
            ->where('key', '[a-z0-9_]+')->middleware('throttle:10,1');
    });
});
