<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\RouteCacheSignature;
use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\PostController;

it('returns an empty signature for a controller with no routes', function () {
    expect(RouteCacheSignature::for('App\\Http\\Controllers\\DefinitelyNoRoutesController'))->toBe('');
});

it('changes the signature when a new route is mapped to the controller', function () {
    Route::get('signature-base', [PostController::class, 'index'])->name('signature.base');

    $before = RouteCacheSignature::for(PostController::class);
    expect($before)->not->toBe('');

    Route::post('signature-extra', [PostController::class, 'store'])->name('signature.extra');

    expect(RouteCacheSignature::for(PostController::class))->not->toBe($before);
});

it('is stable when nothing about the controller routes changes', function () {
    Route::get('signature-stable', [PostController::class, 'index'])->name('signature.stable');

    expect(RouteCacheSignature::for(PostController::class))
        ->toBe(RouteCacheSignature::for(PostController::class));
});
