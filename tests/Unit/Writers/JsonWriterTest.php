<?php

use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Writers\JsonWriter;
use Illuminate\Filesystem\Filesystem;

test('writes json content when enabled', function () {
    config()->set('ts-publish.output_json_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);

    $decoded = json_decode($content, true);

    expect($decoded)
        ->toHaveKey('models')
        ->toHaveKey('enums')
        ->and($decoded['models'])->toHaveKey('User')
        ->and($decoded['enums'])->toHaveKey('Status');
});

test('returns empty string when json output is disabled', function () {
    config()->set('ts-publish.output_json_file', false);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)->toBe('');
});

test('json models contain columns as name/type pairs', function () {
    config()->set('ts-publish.output_json_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);

    $decoded = json_decode($content, true);
    $userFields = $decoded['models']['User'];

    $nameField = collect($userFields)->firstWhere('name', 'name');
    expect($nameField)->toBe(['name' => 'name', 'type' => 'string']);
});

test('json enums contain cases and methods', function () {
    config()->set('ts-publish.output_json_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);

    $decoded = json_decode($content, true);
    $status = $decoded['enums']['Status'];

    expect($status)
        ->toHaveKey('cases')
        ->toHaveKey('caseKinds')
        ->toHaveKey('caseTypes')
        ->toHaveKey('methods')
        ->toHaveKey('staticMethods');
});

test('writes json file to disk when output_to_files is enabled', function () {
    config()->set('ts-publish.output_json_file', true);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'laravel-ts-definitions.json') && str_contains($content, '"models"');
        });

    config()->set('ts-publish.output_to_files', true);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter($filesystem);
    $writer->write($runner);
});
