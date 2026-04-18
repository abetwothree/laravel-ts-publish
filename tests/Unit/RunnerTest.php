<?php

declare(strict_types=1);

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

    expect($runner->enumModularBarrels)->toBeArray()
        ->toHaveKey('workbench/app/enums')
        ->and($runner->enumModularBarrels['workbench/app/enums'])
        ->toContain("export * from './status'");
});

test('runner generates model barrel content', function () {
    $runner = new Runner;
    $runner->run();

    expect($runner->modelModularBarrels)->toBeArray()
        ->toHaveKey('workbench/app/models')
        ->and($runner->modelModularBarrels['workbench/app/models'])
        ->toContain("export * from './user'");
});

test('runner generates globals content when enabled', function () {
    config()->set('ts-publish.globals.enabled', true);

    $runner = new Runner;
    $runner->run();

    expect($runner->globalsContent)
        ->toContain('declare global')
        ->toContain('export namespace workbench.app.models');
});

test('runner generates empty globals content when disabled', function () {
    config()->set('ts-publish.globals.enabled', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->globalsContent)->toBe('');
});

test('runner generates json content when enabled', function () {
    config()->set('ts-publish.json.enabled', true);

    $runner = new Runner;
    $runner->run();

    $decoded = json_decode($runner->jsonContent, true);

    expect($decoded)->toHaveKey('models')
        ->and($decoded)->toHaveKey('enums');
});

test('runner generates empty json content when disabled', function () {
    config()->set('ts-publish.json.enabled', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->jsonContent)->toBe('');
});

test('runner generates watcher json content when enabled', function () {
    config()->set('ts-publish.watcher.enabled', true);

    $runner = new Runner;
    $runner->run();

    $decoded = json_decode($runner->watcherJsonContent, true);

    expect($decoded)->toBeArray()
        ->and(count($decoded))->toBeGreaterThan(0);
});

test('runner generates empty watcher json content when disabled', function () {
    config()->set('ts-publish.watcher.enabled', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->watcherJsonContent)->toBe('');
});

describe('Runner namespaced output', function () {
    beforeEach(function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        // Include Blog module classes in collector discovery
        $existingModels = config()->array('ts-publish.models.additional_directories');
        config()->set('ts-publish.models.additional_directories', [
            ...$existingModels,
            Article::class,
            Reaction::class,
        ]);

        $existingEnums = config()->array('ts-publish.enums.additional_directories');
        config()->set('ts-publish.enums.additional_directories', [
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

    test('runner generates combined modular barrels', function () {
        $runner = new Runner;
        $runner->run();

        expect($runner->enumModularBarrels)->toBeArray()->not->toBeEmpty();
        expect($runner->modelModularBarrels)->toBeArray()->not->toBeEmpty();
    });

    test('runner generates modular globals when enabled', function () {
        config()->set('ts-publish.globals.enabled', true);

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

describe('Runner conditional publishing', function () {
    test('skips enums when shouldPublishEnums is false', function () {
        $runner = new Runner;
        $runner->shouldPublishEnums = false;
        $runner->run();

        expect($runner->enumGenerators)->toBeEmpty()
            ->and($runner->enumModularBarrels)->toBe([])
            ->and($runner->modelGenerators)->not->toBeEmpty();
    });

    test('skips models when shouldPublishModels is false', function () {
        $runner = new Runner;
        $runner->shouldPublishModels = false;
        $runner->run();

        expect($runner->modelGenerators)->toBeEmpty()
            ->and($runner->modelModularBarrels)->toBe([])
            ->and($runner->enumGenerators)->not->toBeEmpty();
    });

    test('skips both when both flags are false', function () {
        $runner = new Runner;
        $runner->shouldPublishEnums = false;
        $runner->shouldPublishModels = false;
        $runner->run();

        expect($runner->enumGenerators)->toBeEmpty()
            ->and($runner->modelGenerators)->toBeEmpty()
            ->and($runner->enumModularBarrels)->toBe([])
            ->and($runner->modelModularBarrels)->toBe([]);
    });

    test('globals only contains enums when models are skipped', function () {
        config()->set('ts-publish.globals.enabled', true);

        $runner = new Runner;
        $runner->shouldPublishModels = false;
        $runner->run();

        expect($runner->globalsContent)
            ->toContain('declare global')
            ->toContain('export namespace workbench.app.enums')
            ->not->toContain('export namespace workbench.app.models');
    });

    test('globals only contains models when enums are skipped', function () {
        config()->set('ts-publish.globals.enabled', true);

        $runner = new Runner;
        $runner->shouldPublishEnums = false;
        $runner->run();

        expect($runner->globalsContent)
            ->toContain('declare global')
            ->toContain('export namespace workbench.app.models')
            ->not->toContain('export namespace workbench.app.enums');
    });

    test('json output only contains enums when models are skipped', function () {
        config()->set('ts-publish.json.enabled', true);

        $runner = new Runner;
        $runner->shouldPublishModels = false;
        $runner->run();

        $decoded = json_decode($runner->jsonContent, true);

        expect($decoded)->toHaveKey('enums')
            ->and($decoded)->toHaveKey('models')
            ->and($decoded['enums'])->not->toBeEmpty()
            ->and($decoded['models'])->toBeEmpty();
    });

    test('watcher json includes all config-enabled file paths regardless of runner publish flags', function () {
        config()->set('ts-publish.watcher.enabled', true);

        $runner = new Runner;
        $runner->shouldPublishModels = false;
        $runner->run();

        $decoded = json_decode($runner->watcherJsonContent, true);

        expect($decoded)->toBeArray()->not->toBeEmpty();

        $paths = collect($decoded);

        // Watcher JSON should include both enum and model paths because
        // both publish_enums and publish_models are true in config,
        // even though the runner skipped model generation.
        expect($paths->contains(fn ($p) => str_contains($p, 'Enum')))->toBeTrue()
            ->and($paths->contains(fn ($p) => str_contains($p, 'Model')))->toBeTrue();
    });

    test('respects publish_enums config value', function () {
        config()->set('ts-publish.enums.enabled', false);

        $runner = new Runner;
        $runner->shouldPublishEnums = config()->boolean('ts-publish.enums.enabled');
        $runner->run();

        expect($runner->enumGenerators)->toBeEmpty()
            ->and($runner->modelGenerators)->not->toBeEmpty();
    });

    test('respects publish_models config value', function () {
        config()->set('ts-publish.models.enabled', false);

        $runner = new Runner;
        $runner->shouldPublishModels = config()->boolean('ts-publish.models.enabled');
        $runner->run();

        expect($runner->modelGenerators)->toBeEmpty()
            ->and($runner->enumGenerators)->not->toBeEmpty();
    });
});
