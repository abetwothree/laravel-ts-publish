<?php

use AbeTwoThree\LaravelTsPublish\Runner;
use AbeTwoThree\LaravelTsPublish\Writers\WatcherJsonWriter;
use Illuminate\Filesystem\Filesystem;

test('writes watcher json content when enabled', function () {
    config()->set('ts-publish.output_collected_files_json', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write($runner);

    $decoded = json_decode($content, true);

    expect($decoded)->toBeArray()
        ->and(count($decoded))->toBeGreaterThan(0);
});

test('returns empty string when watcher json output is disabled', function () {
    config()->set('ts-publish.output_collected_files_json', false);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)->toBe('');
});

test('watcher json contains file paths', function () {
    config()->set('ts-publish.output_collected_files_json', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new WatcherJsonWriter(new Filesystem);
    $content = $writer->write($runner);

    $decoded = json_decode($content, true);

    // Should contain paths ending with .php
    $hasPhpPaths = collect($decoded)->every(fn ($path) => str_ends_with($path, '.php'));
    expect($hasPhpPaths)->toBeTrue();
});

test('writes watcher json file to disk when output_to_files is enabled', function () {
    config()->set('ts-publish.output_collected_files_json', true);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'laravel-ts-collected-files.json');
        });

    config()->set('ts-publish.output_to_files', true);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new WatcherJsonWriter($filesystem);
    $writer->write($runner);
});
