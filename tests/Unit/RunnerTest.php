<?php

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Runner;

beforeEach(function () {
    config()->set('ts-publish.output_to_files', false);
});

test('runner populates enumGenerators collection', function () {
    $runner = new Runner;
    $runner->run();

    expect($runner->enumGenerators)->toBeCollection()
        ->and($runner->enumGenerators)->not->toBeEmpty()
        ->and($runner->enumGenerators->first())->toBeInstanceOf(EnumGenerator::class);
});

test('runner populates modelGenerators collection', function () {
    $runner = new Runner;
    $runner->run();

    expect($runner->modelGenerators)->toBeCollection()
        ->and($runner->modelGenerators)->not->toBeEmpty()
        ->and($runner->modelGenerators->first())->toBeInstanceOf(ModelGenerator::class);
});

test('runner generates enum barrel content', function () {
    $runner = new Runner;
    $runner->run();

    expect($runner->enumBarrelContent)
        ->toBeString()
        ->toContain("export * from './status'");
});

test('runner generates model barrel content', function () {
    $runner = new Runner;
    $runner->run();

    expect($runner->modelBarrelContent)
        ->toBeString()
        ->toContain("export * from './user'");
});

test('runner generates globals content when enabled', function () {
    config()->set('ts-publish.output_globals_file', true);

    $runner = new Runner;
    $runner->run();

    expect($runner->globalsContent)
        ->toContain('declare global')
        ->toContain('export namespace models');
});

test('runner generates empty globals content when disabled', function () {
    config()->set('ts-publish.output_globals_file', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->globalsContent)->toBe('');
});

test('runner generates json content when enabled', function () {
    config()->set('ts-publish.output_json_file', true);

    $runner = new Runner;
    $runner->run();

    $decoded = json_decode($runner->jsonContent, true);

    expect($decoded)->toHaveKey('models')
        ->and($decoded)->toHaveKey('enums');
});

test('runner generates empty json content when disabled', function () {
    config()->set('ts-publish.output_json_file', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->jsonContent)->toBe('');
});

test('runner generates watcher json content when enabled', function () {
    config()->set('ts-publish.output_collected_files_json', true);

    $runner = new Runner;
    $runner->run();

    $decoded = json_decode($runner->watcherJsonContent, true);

    expect($decoded)->toBeArray()
        ->and(count($decoded))->toBeGreaterThan(0);
});

test('runner generates empty watcher json content when disabled', function () {
    config()->set('ts-publish.output_collected_files_json', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->watcherJsonContent)->toBe('');
});
