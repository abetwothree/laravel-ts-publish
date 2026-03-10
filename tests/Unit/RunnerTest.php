<?php

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use Workbench\Blog\Enums\ArticleStatus;
use Workbench\Blog\Enums\ContentType;
use Workbench\Blog\Models\Article;
use Workbench\Blog\Models\Reaction;

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

describe('Runner modular publishing', function () {
    beforeEach(function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        // Include Blog module classes in collector discovery
        $existingModels = config()->array('ts-publish.additional_model_directories');
        config()->set('ts-publish.additional_model_directories', [
            ...$existingModels,
            Article::class,
            Reaction::class,
        ]);

        $existingEnums = config()->array('ts-publish.additional_enum_directories');
        config()->set('ts-publish.additional_enum_directories', [
            ...$existingEnums,
            ArticleStatus::class,
            ContentType::class,
        ]);
    });

    test('runner generates modular enum barrels grouped by namespace', function () {
        $runner = new Runner;
        $runner->run();

        expect($runner->enumModularBarrels)->toBeArray()
            ->and($runner->enumModularBarrels)->toHaveKey('app/enums')
            ->and($runner->enumModularBarrels['app/enums'])->toContain("export * from './status'");

        // Module enums should have their own barrel
        expect($runner->enumModularBarrels)->toHaveKey('blog/enums')
            ->and($runner->enumModularBarrels['blog/enums'])->toContain("export * from './article-status'");

        expect($runner->enumModularBarrels)->toHaveKey('accounting/enums')
            ->and($runner->enumModularBarrels['accounting/enums'])->toContain("export * from './invoice-status'");
    });

    test('runner generates modular model barrels grouped by namespace', function () {
        $runner = new Runner;
        $runner->run();

        expect($runner->modelModularBarrels)->toBeArray()
            ->and($runner->modelModularBarrels)->toHaveKey('app/models')
            ->and($runner->modelModularBarrels['app/models'])->toContain("export * from './user'");

        expect($runner->modelModularBarrels)->toHaveKey('blog/models')
            ->and($runner->modelModularBarrels['blog/models'])->toContain("export * from './article'");

        expect($runner->modelModularBarrels)->toHaveKey('accounting/models')
            ->and($runner->modelModularBarrels['accounting/models'])->toContain("export * from './invoice'");
    });

    test('runner generates combined barrel content for modular mode', function () {
        $runner = new Runner;
        $runner->run();

        expect($runner->enumBarrelContent)->toBeString()->not->toBeEmpty();
        expect($runner->modelBarrelContent)->toBeString()->not->toBeEmpty();
    });

    test('runner generates modular globals when enabled', function () {
        config()->set('ts-publish.output_globals_file', true);

        $runner = new Runner;
        $runner->run();

        expect($runner->globalsContent)
            ->toContain('declare global')
            ->toContain('export namespace app.models')
            ->toContain('export namespace app.enums')
            ->toContain('export namespace blog.models')
            ->toContain('export namespace accounting.enums');
    });
});
