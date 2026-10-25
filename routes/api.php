<?php

use App\Http\Controllers\Api\Content\EntryController;
use App\Http\Controllers\Api\Content\LifecycleController;
use App\Http\Controllers\Api\Content\MediaController;
use App\Http\Controllers\Api\Content\RevisionController;
use App\Http\Controllers\Api\Content\SchemaController;
use App\Http\Controllers\Api\MediaImportController;
use Illuminate\Support\Facades\Route;

Route::post('media/import', MediaImportController::class)
    ->middleware([
        'content.request-id',
        'api-peer-throttle:media-import-api,60',
        'api-key.resolve',
        'throttle:media-import-api',
        'api-key.auth',
        'api-key.scope:media:import',
        'api-key.idempotency',
    ])
    ->name('api.media.import');

Route::prefix('content/v1')
    ->name('api.content.v1.')
    ->middleware([
        'content.request-id',
        'api-peer-throttle:content-api,120',
        'api-key.resolve',
        'throttle:content-api',
        'api-key.auth',
    ])
    ->group(function (): void {
        Route::get('schema', SchemaController::class)
            ->middleware('api-key.scope:content:read')
            ->name('schema');
        Route::get('media', MediaController::class)
            ->middleware('api-key.scope:media:read')
            ->name('media.index');

        Route::get('entries', [EntryController::class, 'index'])
            ->middleware('api-key.scope:content:read')
            ->name('entries.index');
        Route::post('entries', [EntryController::class, 'store'])
            ->middleware([
                'api-key.scope:content:draft',
                'api-key.idempotency',
            ])
            ->name('entries.store');
        Route::get('entries/{entry}', [EntryController::class, 'show'])
            ->whereNumber('entry')
            ->middleware('api-key.scope:content:read')
            ->name('entries.show');
        Route::post('entries/{entry}/validate', [EntryController::class, 'validateCandidate'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:draft',
                'api-key.idempotency',
            ])
            ->name('entries.validate');

        Route::get('entries/{entry}/revisions', [RevisionController::class, 'index'])
            ->whereNumber('entry')
            ->middleware('api-key.scope:content:read')
            ->name('revisions.index');
        Route::post('entries/{entry}/revisions', [RevisionController::class, 'store'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:draft',
                'api-key.idempotency',
            ])
            ->name('revisions.store');
        Route::get('entries/{entry}/revisions/{revision}', [RevisionController::class, 'show'])
            ->whereNumber('entry')
            ->whereNumber('revision')
            ->middleware('api-key.scope:content:read')
            ->name('revisions.show');

        Route::post('entries/{entry}/preview', [LifecycleController::class, 'preview'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:preview',
                'api-key.idempotency',
            ])
            ->name('entries.preview');
        Route::post('entries/{entry}/publish', [LifecycleController::class, 'publish'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:publish',
                'api-key.idempotency',
            ])
            ->name('entries.publish');
        Route::post('entries/{entry}/schedule', [LifecycleController::class, 'schedule'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:schedule',
                'api-key.idempotency',
            ])
            ->name('entries.schedule');
        Route::delete('entries/{entry}/schedule', [LifecycleController::class, 'cancelSchedule'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:schedule',
                'api-key.idempotency',
            ])
            ->name('entries.schedule.destroy');
        Route::post('entries/{entry}/cancel-schedule', [LifecycleController::class, 'cancelSchedule'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:schedule',
                'api-key.idempotency',
            ])
            ->name('entries.cancel-schedule');
        Route::post('entries/{entry}/rollback', [LifecycleController::class, 'rollback'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:rollback',
                'api-key.idempotency',
            ])
            ->name('entries.rollback');
        Route::post('entries/{entry}/archive', [LifecycleController::class, 'archive'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:publish',
                'api-key.idempotency',
            ])
            ->name('entries.archive');
        Route::post('entries/{entry}/restore', [LifecycleController::class, 'restore'])
            ->whereNumber('entry')
            ->middleware([
                'api-key.scope:content:draft',
                'api-key.idempotency',
            ])
            ->name('entries.restore');
    });
