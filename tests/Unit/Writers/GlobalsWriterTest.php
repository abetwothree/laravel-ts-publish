<?php

use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use Illuminate\Filesystem\Filesystem;

test('writes globals content when enabled', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('declare global')
        ->toContain('export namespace models')
        ->toContain('export namespace enums');
});

test('returns empty string when globals output is disabled', function () {
    config()->set('ts-publish.output_globals_file', false);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)->toBe('');
});

test('writes globals file to disk when output_to_files is enabled', function () {
    config()->set('ts-publish.output_globals_file', true);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'laravel-ts-global.d.ts') && str_contains($content, 'declare global');
        });

    config()->set('ts-publish.output_to_files', true);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter($filesystem);
    $writer->write($runner);
});

test('globals content contains model interfaces', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('export interface User')
        ->toContain('id: number')
        ->toContain('name: string');
});

test('globals content contains enum interfaces', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('export interface Status')
        ->toContain('Draft')
        ->toContain('Published');
});
