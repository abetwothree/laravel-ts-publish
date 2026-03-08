<?php

use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use Illuminate\Support\Collection;

use function Orchestra\Testbench\workbench_path;

test('models collector works correctly', function () {
    $collector = resolve(ModelsCollector::class);

    $models = $collector->collect();

    expect($models)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(16)
        ->toContain('Workbench\App\Models\TrackingEvent');
});

test('models collector includes only classes from a directory', function () {
    config()->set('ts-publish.included_models', [
        workbench_path('modules/Accounting/Models'),
    ]);

    $models = resolve(ModelsCollector::class)->collect();

    expect($models)
        ->toHaveCount(2)
        ->toContain('Workbench\Accounting\Models\Invoice')
        ->toContain('Workbench\Accounting\Models\Payment');
});

test('models collector includes a mix of class names and directories', function () {
    config()->set('ts-publish.included_models', [
        'Workbench\App\Models\User',
        workbench_path('modules/Accounting/Models'),
    ]);

    $models = resolve(ModelsCollector::class)->collect();

    expect($models)
        ->toHaveCount(3)
        ->toContain('Workbench\App\Models\User')
        ->toContain('Workbench\Accounting\Models\Invoice')
        ->toContain('Workbench\Accounting\Models\Payment');
});

test('models collector excludes classes from a directory', function () {
    config()->set('ts-publish.excluded_models', [
        workbench_path('app/Models'),
    ]);

    $models = resolve(ModelsCollector::class)->collect();

    // DatabaseNotification, Invoice, and Shipment should remain (added via additional_model_directories)
    expect($models)
        ->toHaveCount(3)
        ->toContain('Illuminate\Notifications\DatabaseNotification')
        ->toContain('Workbench\Accounting\Models\Invoice')
        ->toContain('Workbench\Shipping\Models\Shipment');
});

test('models collector excludes a mix of class names and directories', function () {
    config()->set('ts-publish.additional_model_directories', [
        'Illuminate\Notifications\DatabaseNotification',
        workbench_path('modules/Accounting/Models'),
    ]);
    config()->set('ts-publish.excluded_models', [
        'Workbench\App\Models\User',
        workbench_path('modules/Accounting/Models'),
    ]);

    $models = resolve(ModelsCollector::class)->collect();

    expect($models)
        ->not->toContain('Workbench\App\Models\User')
        ->not->toContain('Workbench\Accounting\Models\Invoice')
        ->not->toContain('Workbench\Accounting\Models\Payment')
        ->toContain('Illuminate\Notifications\DatabaseNotification');
});
