<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InvalidModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ModelMetadataProviderModel;
use AbeTwoThree\LaravelTsPublish\Writers\WatcherJsonWriter;
use Illuminate\Filesystem\Filesystem;

test('writes watcher json content when enabled', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);

    expect($decoded)->toBeArray()
        ->and(count($decoded))->toBeGreaterThan(0);
});

test('returns empty string when watcher json output is disabled', function () {
    config()->set('ts-publish.watcher.enabled', false);
    config()->set('ts-publish.output_to_files', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    expect($content)->toBe('');
});

test('watcher json contains file paths', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);

    // Values should be file paths ending with .php
    expect(collect($decoded)->every(fn ($path) => str_ends_with($path, '.php')))->toBeTrue();
});

test('writes watcher json file to disk when output_to_files is enabled', function () {
    config()->set('ts-publish.watcher.enabled', true);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'laravel-ts-collected-files.json');
        });

    config()->set('ts-publish.output_to_files', true);

    $writer = new WatcherJsonWriter($filesystem);
    $writer->write();
});

test('watcher json includes both enum and model paths based on config', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', true);
    config()->set('ts-publish.models.enabled', true);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Enum')))->toBeTrue()
        ->and($paths->contains(fn ($p) => str_contains($p, 'Model')))->toBeTrue();
});

test('watcher json excludes enums when publish_enums config is false', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', true);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Enum')))->toBeFalse()
        ->and($paths->contains(fn ($p) => str_contains($p, 'Model')))->toBeTrue();
});

test('watcher json excludes models when publish_models config is false', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', true);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.model_metadata.enabled', false);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Enum')))->toBeTrue()
        ->and($paths->contains(fn ($p) => str_contains($p, 'Model')))->toBeFalse();
});

test('watcher json includes metadata model paths when model publishing is disabled', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $paths = collect(json_decode($writer->write(), true));

    expect($paths->contains(fn ($path) => str_contains($path, 'Model')))->toBeTrue();
});

test('watcher json includes the custom model metadata provider path', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.provider_class', CustomModelMetadataProvider::class);

    $writer = new WatcherJsonWriter(new Filesystem);
    $paths = collect(json_decode($writer->write(), true));

    expect($paths->contains(
        fn (string $path): bool => str_ends_with($path, 'CustomModelMetadataProvider.php'),
    ))->toBeTrue();
});

test('watcher json follows container substitutions for the model metadata provider', function () {
    $provider = new CustomModelMetadataProvider;
    app()->instance(InvalidModelMetadataProvider::class, $provider);
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.provider_class', InvalidModelMetadataProvider::class);

    $paths = collect(json_decode((new WatcherJsonWriter(new Filesystem))->write(), true));

    expect($paths->contains(
        fn (string $path): bool => str_ends_with($path, 'CustomModelMetadataProvider.php'),
    ))->toBeTrue();
});

test('watcher json rejects an invalid custom model metadata provider', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.provider_class', InvalidModelMetadataProvider::class);

    expect(fn () => (new WatcherJsonWriter(new Filesystem))->write())
        ->toThrow(InvalidArgumentException::class, 'must implement');
});

test('watcher json deduplicates a file collected as both a model and metadata provider', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.included', [ModelMetadataProviderModel::class]);
    config()->set('ts-publish.model_metadata.provider_class', ModelMetadataProviderModel::class);

    $paths = json_decode((new WatcherJsonWriter(new Filesystem))->write(), true);
    $providerPaths = array_filter(
        $paths,
        fn (string $path): bool => str_ends_with($path, 'ModelMetadataProviderModel.php'),
    );

    expect($providerPaths)->toHaveCount(1);
});

test('watcher json includes controllers when routes.enabled config is true', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', true);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Controller')))->toBeTrue();
});

test('watcher json includes resource paths when publish_resources is enabled', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.resources.enabled', true);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Resource')))->toBeTrue();
});

test('watcher json excludes resources when publish_resources is false', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.resources.enabled', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Resources/')))->toBeFalse();
});

test('watcher json includes form request paths when form_requests is enabled', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', true);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Requests/')))->toBeTrue();
});

test('watcher json excludes form request paths when form_requests is disabled', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Requests/')))->toBeFalse();
});

test('watcher json includes broadcast event paths when broadcast_events is enabled', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', true);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Events/')))->toBeTrue();
});

test('watcher json excludes broadcast event paths when broadcast_events is disabled', function () {
    config()->set('ts-publish.watcher.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Events/')))->toBeFalse();
});
