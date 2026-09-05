<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\ModelMetadataCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Models\User;

test('collects models using independent metadata settings', function () {
    config()->set('ts-publish.models.included', []);
    config()->set('ts-publish.model_metadata.included', [User::class]);

    $models = resolve(ModelMetadataCollector::class)->collect();

    expect($models)->toHaveCount(1)
        ->and($models->first())->toBe(User::class);
});

test('metadata exclusions do not affect model discovery', function () {
    config()->set('ts-publish.model_metadata.excluded', [User::class]);

    $metadataModels = resolve(ModelMetadataCollector::class)->collect();
    $models = resolve(ModelsCollector::class)->collect();

    expect($metadataModels)->not->toContain(User::class)
        ->and($models)->toContain(User::class);
});

test('falls back to each model finder setting by default', function () {
    config()->set('ts-publish.models.included', [User::class]);
    config()->set('ts-publish.models.excluded', []);
    config()->set('ts-publish.models.additional_directories', []);

    $models = resolve(ModelMetadataCollector::class)->collect();

    expect($models)->toHaveCount(1)
        ->and($models->first())->toBe(User::class);
});

test('uses an explicit metadata finder setting instead of its model fallback', function () {
    config()->set('ts-publish.models.included', [User::class]);
    config()->set('ts-publish.model_metadata.included', [__FILE__]);

    expect(resolve(ModelMetadataCollector::class)->collect())->toBeEmpty();
});

test('uses an explicitly empty metadata finder setting instead of its model fallback', function () {
    config()->set('ts-publish.models.included', []);
    config()->set('ts-publish.models.excluded', [User::class]);
    config()->set('ts-publish.model_metadata.excluded', []);

    expect(resolve(ModelMetadataCollector::class)->collect())->toContain(User::class);
});

test('allows an explicitly supplied model included through a directory', function () {
    $modelDirectory = workbench_path('app/Models');
    config()->set('ts-publish.model_metadata.included', [$modelDirectory]);

    expect(resolve(ModelMetadataCollector::class)->allows(User::class))->toBeTrue();
});

test('rejects an explicitly supplied model excluded through a directory', function () {
    $modelDirectory = workbench_path('app/Models');
    config()->set('ts-publish.model_metadata.excluded', [$modelDirectory]);

    expect(resolve(ModelMetadataCollector::class)->allows(User::class))->toBeFalse();
});
