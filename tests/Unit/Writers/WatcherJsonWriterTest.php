<?php

declare(strict_types=1);

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
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
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
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write();

    $decoded = json_decode($content, true);
    $paths = collect($decoded);

    expect($paths->contains(fn ($p) => str_contains($p, 'Enum')))->toBeTrue()
        ->and($paths->contains(fn ($p) => str_contains($p, 'Model')))->toBeFalse();
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
