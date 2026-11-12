<?php

use App\Modules\Examples\Http\Controllers\EntryController;
use Illuminate\Support\Facades\Route;

Route::prefix('examples')->group(function () {
    Route::inertia('list', 'Examples/List');
    Route::inertia('report', 'Examples/Report');
    Route::inertia('new-form', 'Examples/NewForm');
    Route::inertia('submitted', 'Examples/Submitted');
    Route::inertia('settings', 'Examples/Settings');
    Route::inertia('empty', 'Examples/Empty');

    // B-2: 403 page ka demo route sirf local/testing mein. Production mein
    // yeh route maujood hi nahi hona chahiye.
    if (app()->environment(['local', 'testing'])) {
        Route::get('forbidden', fn () => abort(403));
    }
});

Route::prefix('examples/entries')->group(function () {
    Route::middleware('can:examples.entries.view')->group(function () {
        Route::get('/', [EntryController::class, 'index']);
        Route::get('data', [EntryController::class, 'data']);
        Route::get('{entry}', [EntryController::class, 'show'])->whereNumber('entry');
    });
    Route::middleware('can:examples.entries.create')->group(function () {
        Route::get('create', [EntryController::class, 'create']);
        Route::post('/', [EntryController::class, 'store']);
    });
    Route::put('{entry}', [EntryController::class, 'update'])->whereNumber('entry')->middleware('can:examples.entries.update');
    Route::post('{entry}/submit', [EntryController::class, 'submit'])->whereNumber('entry')->middleware('can:examples.entries.submit');
    Route::post('{entry}/cancel', [EntryController::class, 'cancel'])->whereNumber('entry')->middleware('can:examples.entries.cancel');
    Route::post('{entry}/amend', [EntryController::class, 'amend'])->whereNumber('entry')->middleware('can:examples.entries.amend');
    Route::delete('{entry}', [EntryController::class, 'destroy'])->whereNumber('entry')->middleware('can:examples.entries.delete');
    Route::post('bulk-delete', [EntryController::class, 'bulkDestroy'])->middleware('can:examples.entries.delete');
});
