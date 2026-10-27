<?php

use Illuminate\Support\Facades\Route;

Route::prefix('examples')->group(function () {
    Route::inertia('list', 'Examples/List');
    Route::inertia('report', 'Examples/Report');
    Route::inertia('new-form', 'Examples/NewForm');
    Route::inertia('submitted', 'Examples/Submitted');
    Route::inertia('settings', 'Examples/Settings');
    Route::inertia('empty', 'Examples/Empty');
    Route::get('forbidden', fn() => abort(403));
});