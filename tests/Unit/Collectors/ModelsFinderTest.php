<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\CoreCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use Illuminate\Support\Collection;

use function Orchestra\Testbench\workbench_path;

test('models collector works correctly', function () {
    $collector = resolve(ModelsCollector::class);

    $models = $collector->collect();

    expect($models)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(61)
        ->toContain('Workbench\App\Models\TrackingEvent')
        ->toContain('Workbench\App\Models\PostMeta');
});

test('models collector excludes classes with #[TsExclude]', function () {
    $models = resolve(ModelsCollector::class)->collect();

    expect($models)
        ->not->toContain('Workbench\App\Models\ExcludedModel')
        ->toContain('Workbench\App\Models\ExcludableModel');
});

test('models collector includes only classes from a directory', function () {
    config()->set('ts-publish.models.included', [
        workbench_path('modules/Accounting/Models'),
    ]);

    $models = resolve(ModelsCollector::class)->collect();

    expect($models)
        ->toHaveCount(2)
        ->toContain('Workbench\Accounting\Models\Invoice')
        ->toContain('Workbench\Accounting\Models\Payment');
});

test('models collector includes a mix of class names and directories', function () {
    config()->set('ts-publish.models.included', [
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
    config()->set('ts-publish.models.excluded', [
        workbench_path('app/Models'),
    ]);

    $models = resolve(ModelsCollector::class)->collect();

    // DatabaseNotification, Invoice, and Shipment should remain (added via additional_model_directories)
    expect($models)
        ->toHaveCount(9)
        ->toContain('Illuminate\Notifications\DatabaseNotification')
        ->toContain('Workbench\Accounting\Models\Invoice')
        ->toContain('Workbench\Shipping\Models\Shipment');
});

test('models collector excludes a mix of class names and directories', function () {
    config()->set('ts-publish.models.additional_directories', [
        'Illuminate\Notifications\DatabaseNotification',
        workbench_path('modules/Accounting/Models'),
    ]);
    config()->set('ts-publish.models.excluded', [
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

test('collect scans each directory through the same per-process class map cache', function () {
    CoreCollector::flushClassMapCache();
    // No included/excluded entries, so only collect()'s own directory scan can find the model.
    config()->set('ts-publish.models.included', []);
    config()->set('ts-publish.models.excluded', []);
    config()->set('ts-publish.models.additional_directories', [workbench_path('app/Models')]);

    $collector = resolve(ModelsCollector::class);

    expect($collector->collect())->toContain('Workbench\\App\\Models\\User');

    // Blank every cached map: a second collect() must consult the cache, not rescan the directory.
    $cache = new ReflectionProperty(CoreCollector::class, 'classMaps');
    $cache->setValue(null, array_map(fn (): array => [], $cache->getValue()));

    expect($collector->collect())->not->toContain('Workbench\\App\\Models\\User');

    CoreCollector::flushClassMapCache();

    expect($collector->collect())->toContain('Workbench\\App\\Models\\User');
});

test('collect resolves an excluded directory through the same per-process class map cache', function () {
    CoreCollector::flushClassMapCache();
    // The model arrives as a class name, so only resolving the excluded directory needs a scan.
    config()->set('ts-publish.models.included', []);
    config()->set('ts-publish.models.additional_directories', ['Workbench\\App\\Models\\User']);
    config()->set('ts-publish.models.excluded', [workbench_path('app/Models')]);

    $collector = resolve(ModelsCollector::class);

    expect($collector->collect())->not->toContain('Workbench\\App\\Models\\User');

    // Blank every cached map: the second collect() must resolve the exclusion from the cache, not rescan.
    $cache = new ReflectionProperty(CoreCollector::class, 'classMaps');
    $cache->setValue(null, array_map(fn (): array => [], $cache->getValue()));

    expect($collector->collect())->toContain('Workbench\\App\\Models\\User');

    CoreCollector::flushClassMapCache();

    expect($collector->collect())->not->toContain('Workbench\\App\\Models\\User');
});
