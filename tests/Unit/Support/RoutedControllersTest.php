<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Runners\RunnerForSource;
use AbeTwoThree\LaravelTsPublish\Support\RoutedControllers;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiHelloController;
use Illuminate\Support\Facades\Route;

it('reads a route\'s controller in any case, as PHP reads a class name', function () {
    Route::get('ui-hello-cased', strtolower(UiHelloController::class).'@index');

    expect(RoutedControllers::has(UiHelloController::class))->toBeTrue()
        ->and(RoutedControllers::has('\\'.strtoupper(UiHelloController::class)))->toBeTrue();
});

it('reads the routes once until flushed', function () {
    expect(RoutedControllers::has(UiHelloController::class))->toBeFalse();

    Route::get('ui-hello', [UiHelloController::class, 'index']);

    expect(RoutedControllers::has(UiHelloController::class))->toBeFalse();

    RoutedControllers::flush();

    expect(RoutedControllers::has(UiHelloController::class))->toBeTrue();
});

it('is read afresh by each run', function () {
    RoutedControllers::has(UiHelloController::class);
    Route::get('ui-hello', [UiHelloController::class, 'index']);

    (new RunnerForSource('Workbench\App\Enums\Status'))->run();

    expect(RoutedControllers::has(UiHelloController::class))->toBeTrue();
});
