<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Collectors\CoreCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Runners\RunnerForSource;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CollidingEnums\Access;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CollidingEnums\AccessKind;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CollidingEnums\AccessType;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CountingTsTypeString;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomBarrelWriter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\FailingModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\HeaderedBarrelWriter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InvalidModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\LateTableModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\LateTableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ListOnlyModelsCollector;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MagicCallModelsCollector;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MarkedModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\PrefixedModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\RecordingModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReindexedValueEnum;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SingleModelMetadataCollector;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SuffixedModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ThrowingResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnreadableRelationFacility;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnreadableRelationTrail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Enums\FreightClass;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\CommentComposedResource;
use Workbench\App\Http\Resources\Registrar as BareRegistrarResource;
use Workbench\App\Http\Resources\RegistrarResource;
use Workbench\App\Models\BaseExtendableModel;
use Workbench\App\Models\Comment;
use Workbench\App\Models\ExcludedModel;
use Workbench\App\Models\Facility;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Packages\Audit\Models\AuditArchive;
use Workbench\App\Packages\Audit\Models\AuditInspector;
use Workbench\App\Packages\Audit\Models\AuditNote;
use Workbench\App\Packages\Audit\Models\AuditTrail;
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

test('runner populates a separate modelMetadataGenerators collection', function () {
    $runner = new Runner;
    $runner->run();

    expect($runner->modelMetadataGenerators)->toBeCollection()
        ->not->toBeEmpty()
        ->and($runner->modelMetadataGenerators->first())->toBeInstanceOf(ModelMetadataGenerator::class);
});

test('runner skips one failing metadata model and records the failure', function () {
    config()->set('ts-publish.models.included', [User::class, Post::class]);
    config()->set('ts-publish.model_metadata.provider_class', FailingModelMetadataProvider::class);

    $runner = new Runner;
    $runner->run();

    expect($runner->modelMetadataGenerators)->toHaveCount(1)
        ->and($runner->modelMetadataGenerators->first()->findable)->toBe(Post::class)
        ->and($runner->modelMetadataFailures)->toBe([
            [
                'subject' => User::class,
                'message' => RuntimeException::class.': Metadata is unavailable for this model.',
            ],
        ])
        ->and(array_column(AnalysisWarnings::all(), 'subject'))->not->toContain(User::class);
});

test('runner rejects an invalid metadata provider before processing models', function () {
    config()->set('ts-publish.model_metadata.provider_class', InvalidModelMetadataProvider::class);

    expect(fn () => (new Runner)->run())
        ->toThrow(InvalidArgumentException::class, 'must implement');
});

test('runner rejects an invalid metadata generator before processing models', function () {
    config()->set('ts-publish.model_metadata.generator_class', ModelGenerator::class);

    expect(fn () => (new Runner)->run())
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

test('runner rejects an invalid metadata transformer before processing models', function () {
    config()->set('ts-publish.model_metadata.transformer_class', stdClass::class);

    expect(fn () => (new Runner)->run())
        ->toThrow(InvalidArgumentException::class, 'Configured model metadata transformer [stdClass] must extend');
});

test('runner rejects an invalid metadata transformer on a run that only preserves its barrel exports', function () {
    $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-bad-transformer-'.uniqid();
    $barrelDirectory = "$outputDirectory/workbench/app/models";
    $filesystem = new Filesystem;
    $filesystem->makeDirectory($barrelDirectory, recursive: true);
    $filesystem->put("$barrelDirectory/index.ts", "export * from './user_meta';");

    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.transformer_class', stdClass::class);
    config()->set('ts-publish.models.included', [User::class]);
    config()->set('ts-publish.output_directory', $outputDirectory);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.watcher.enabled', false);

    try {
        // The metadata phase is skipped, so only the barrel reads the transformer — static-dispatched, so an
        // unguarded class would surface as `Error: Call to undefined method`.
        $runner = new Runner;
        $runner->shouldPublishModelMetadata = false;

        expect(fn () => $runner->run())
            ->toThrow(InvalidArgumentException::class, 'Configured model metadata transformer [stdClass] must extend');
    } finally {
        $filesystem->deleteDirectory($outputDirectory);
    }
});

test('runner omits metadata generators when its phase is disabled', function () {
    $runner = new Runner;
    $runner->shouldPublishModelMetadata = false;
    $runner->run();

    expect($runner->modelGenerators)->not->toBeEmpty()
        ->and($runner->modelMetadataGenerators)->toBeEmpty()
        ->and($runner->modelModularBarrels['workbench/app/models'])
        ->toContain("export * from './user';");
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
        ->toContain("export * from './user'")
        ->toContain("export * from './user_meta'");
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

    test('skips models without skipping model metadata', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-flag-skipped-models-'.uniqid();
        $barrelDirectory = "$outputDirectory/workbench/app/models";
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($barrelDirectory, recursive: true);
        $filesystem->put("$barrelDirectory/index.ts", "export * from './user';\nexport * from './ghost_meta';");

        config()->set('ts-publish.models.included', [User::class]);
        config()->set('ts-publish.output_directory', $outputDirectory);

        try {
            $runner = new Runner;
            $runner->shouldPublishModels = false;
            $runner->run();

            // models.enabled is true in config, so the flag-skipped phase keeps its export; the metadata phase ran,
            // so its stale export is dropped.
            expect($runner->modelGenerators)->toBeEmpty()
                ->and($runner->modelMetadataGenerators)->not->toBeEmpty()
                ->and($runner->modelModularBarrels['workbench/app/models'])
                ->toBe("export * from './user';\nexport * from './user_meta';")
                ->and($runner->enumGenerators)->not->toBeEmpty();
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });

    test('skips enums and models without skipping model metadata', function () {
        $runner = new Runner;
        $runner->shouldPublishEnums = false;
        $runner->shouldPublishModels = false;
        $runner->run();

        expect($runner->enumGenerators)->toBeEmpty()
            ->and($runner->modelGenerators)->toBeEmpty()
            ->and($runner->modelMetadataGenerators)->not->toBeEmpty()
            ->and($runner->enumModularBarrels)->toBe([])
            ->and($runner->modelModularBarrels)->not->toBeEmpty();
    });

    test('skips model metadata without skipping models', function () {
        $runner = new Runner;
        $runner->shouldPublishModelMetadata = false;
        $runner->run();

        expect($runner->modelGenerators)->not->toBeEmpty()
            ->and($runner->modelMetadataGenerators)->toBeEmpty()
            ->and($runner->modelModularBarrels['workbench/app/models'])
            ->toContain("export * from './user';");
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

        // The watcher follows enabled config phases even when this run skips model interfaces.
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
            ->and($runner->modelMetadataGenerators)->not->toBeEmpty()
            ->and($runner->enumGenerators)->not->toBeEmpty();
    });

    test('respects model metadata config independently from models', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-config-disabled-models-'.uniqid();
        $barrelDirectory = "$outputDirectory/workbench/app/models";
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($barrelDirectory, recursive: true);
        $filesystem->put("$barrelDirectory/index.ts", "export * from './user';");

        config()->set('ts-publish.models.enabled', false);
        config()->set('ts-publish.model_metadata.enabled', true);
        config()->set('ts-publish.models.included', [User::class]);
        config()->set('ts-publish.output_directory', $outputDirectory);

        try {
            $runner = new Runner;
            $runner->shouldPublishModels = config()->boolean('ts-publish.models.enabled');
            $runner->shouldPublishModelMetadata = config()->boolean('ts-publish.model_metadata.enabled');
            $runner->run();

            // A phase disabled in config owns nothing: its old export is pruned, not preserved.
            expect($runner->modelGenerators)->toBeEmpty()
                ->and($runner->modelMetadataGenerators)->not->toBeEmpty()
                ->and($runner->modelModularBarrels['workbench/app/models'])
                ->toBe("export * from './user_meta';");
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });

    test('config-disabled metadata prunes its stale barrel exports on a full run', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-config-disabled-metadata-'.uniqid();
        $barrelDirectory = "$outputDirectory/workbench/app/models";
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($barrelDirectory, recursive: true);
        $filesystem->put("$barrelDirectory/index.ts", "export * from './ghost';\nexport * from './user_meta';");

        config()->set('ts-publish.models.enabled', true);
        config()->set('ts-publish.model_metadata.enabled', false);
        config()->set('ts-publish.models.included', [User::class]);
        config()->set('ts-publish.output_directory', $outputDirectory);

        try {
            $runner = new Runner;
            $runner->shouldPublishModels = true;
            $runner->shouldPublishModelMetadata = false;
            $runner->run();

            expect($runner->modelModularBarrels['workbench/app/models'])->toBe("export * from './user';");
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });

    test('flag-skipped metadata keeps its existing barrel exports', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-flag-skipped-metadata-'.uniqid();
        $barrelDirectory = "$outputDirectory/workbench/app/models";
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($barrelDirectory, recursive: true);
        $filesystem->put("$barrelDirectory/index.ts", "export * from './ghost';\nexport * from './user_meta';");

        config()->set('ts-publish.models.enabled', true);
        config()->set('ts-publish.model_metadata.enabled', true);
        config()->set('ts-publish.models.included', [User::class]);
        config()->set('ts-publish.output_directory', $outputDirectory);

        try {
            $runner = new Runner;
            $runner->shouldPublishModelMetadata = false;
            $runner->run();

            expect($runner->modelModularBarrels['workbench/app/models'])
                ->toBe("export * from './user';\nexport * from './user_meta';");
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });

    test('a custom transformer_class decides which barrel exports the metadata phase owns', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-custom-suffix-'.uniqid();
        $barrelDirectory = "$outputDirectory/workbench/app/models";
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($barrelDirectory, recursive: true);
        $filesystem->put("$barrelDirectory/index.ts", "export * from './ghost';\nexport * from './user.meta';");

        config()->set('ts-publish.model_metadata.transformer_class', SuffixedModelMetadataTransformer::class);
        config()->set('ts-publish.models.enabled', true);
        config()->set('ts-publish.model_metadata.enabled', true);
        config()->set('ts-publish.models.included', [User::class]);
        config()->set('ts-publish.output_directory', $outputDirectory);

        try {
            $runner = new Runner;
            $runner->shouldPublishModelMetadata = false;
            $runner->run();

            // Ownership follows the configured transformer's suffix, not the default one.
            expect($runner->modelModularBarrels['workbench/app/models'])
                ->toBe("export * from './user';\nexport * from './user.meta';");
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });

    test('a custom transformer_class names the companions it writes the way it claims them', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-custom-prefix-'.uniqid();
        $filesystem = new Filesystem;

        config()->set('ts-publish.model_metadata.transformer_class', PrefixedModelMetadataTransformer::class);
        config()->set('ts-publish.models.enabled', true);
        config()->set('ts-publish.model_metadata.enabled', true);
        config()->set('ts-publish.models.included', [User::class]);
        config()->set('ts-publish.output_directory', $outputDirectory);
        config()->set('ts-publish.output_to_files', true);

        try {
            $published = new Runner;
            $published->run();

            // The written companion carries the configured transformer's name, not the base suffix.
            expect($published->modelModularBarrels['workbench/app/models'])
                ->toBe("export * from './meta.user';\nexport * from './user';")
                ->and($filesystem->exists("$outputDirectory/workbench/app/models/meta.user.ts"))->toBeTrue();

            $runner = new Runner;
            $runner->shouldPublishModelMetadata = false;
            $runner->run();

            // Ownership asks the same class, so the skipped phase recognizes what the published run wrote.
            expect($runner->modelModularBarrels['workbench/app/models'])
                ->toBe("export * from './meta.user';\nexport * from './user';");
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });

    test('a failed metadata model keeps its last-known-good barrel export', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-failed-metadata-'.uniqid();
        $barrelDirectory = "$outputDirectory/workbench/app/models";
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($barrelDirectory, recursive: true);
        $filesystem->put("$barrelDirectory/index.ts", "export * from './ghost_meta';\nexport * from './user_meta';");

        config()->set('ts-publish.models.included', [User::class, Post::class]);
        config()->set('ts-publish.model_metadata.provider_class', FailingModelMetadataProvider::class);
        config()->set('ts-publish.output_directory', $outputDirectory);

        try {
            $runner = new Runner;
            $runner->run();

            expect($runner->modelMetadataFailures)->toHaveCount(1)
                ->and($runner->modelModularBarrels['workbench/app/models'])
                ->toBe("export * from './post';\nexport * from './post_meta';\nexport * from './user';\nexport * from './user_meta';");
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });

    test('validates metadata configuration before generating anything', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-validate-first-'.uniqid();

        config()->set('ts-publish.model_metadata.enabled', true);
        config()->set('ts-publish.model_metadata.provider_class', InvalidModelMetadataProvider::class);
        config()->set('ts-publish.output_directory', $outputDirectory);
        config()->set('ts-publish.output_to_files', true);

        try {
            $runner = new Runner;

            expect(fn () => $runner->run())->toThrow(InvalidArgumentException::class, 'must implement')
                ->and(is_dir($outputDirectory))->toBeFalse();
        } finally {
            (new Filesystem)->deleteDirectory($outputDirectory);
        }
    });

    test('a flag-skipped metadata phase is not validated', function () {
        config()->set('ts-publish.model_metadata.enabled', true);
        config()->set('ts-publish.model_metadata.provider_class', InvalidModelMetadataProvider::class);
        // The watcher follows config rather than run flags, and resolves the provider to watch its file.
        config()->set('ts-publish.watcher.enabled', false);

        $runner = new Runner;
        $runner->shouldPublishModelMetadata = false;
        $runner->run();

        expect($runner->modelMetadataGenerators)->toBeEmpty()
            ->and($runner->modelGenerators)->not->toBeEmpty();
    });

    test('custom barrel writers inherit preserving behavior for partial runs', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-custom-writer-'.uniqid();
        $barrelDirectory = "$outputDirectory/workbench/app/models";
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($barrelDirectory, recursive: true);
        $filesystem->put("$barrelDirectory/index.ts", "export * from './user_meta';");

        config()->set('ts-publish.barrel_writer_class', CustomBarrelWriter::class);
        config()->set('ts-publish.models.enabled', true);
        config()->set('ts-publish.model_metadata.enabled', true);
        config()->set('ts-publish.models.included', [User::class]);
        config()->set('ts-publish.output_directory', $outputDirectory);
        config()->set('ts-publish.output_to_files', true);

        try {
            $runner = new Runner;
            $runner->shouldPublishModelMetadata = false;
            $runner->run();

            expect($filesystem->get("$barrelDirectory/index.ts"))
                ->toBe("export * from './user';\nexport * from './user_meta';");
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });

    test('a barrel writer that overrides writeModularPreserving keeps its format on a partial run', function () {
        $outputDirectory = sys_get_temp_dir().'/laravel-ts-publish-headered-writer-'.uniqid();
        $barrelDirectory = "$outputDirectory/workbench/app/models";
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($barrelDirectory, recursive: true);
        $filesystem->put("$barrelDirectory/index.ts", HeaderedBarrelWriter::HEADER."\nexport * from './user_meta';");

        config()->set('ts-publish.barrel_writer_class', HeaderedBarrelWriter::class);
        config()->set('ts-publish.models.enabled', true);
        config()->set('ts-publish.model_metadata.enabled', true);
        config()->set('ts-publish.models.included', [User::class]);
        config()->set('ts-publish.output_directory', $outputDirectory);
        config()->set('ts-publish.output_to_files', true);

        try {
            $runner = new Runner;
            $runner->shouldPublishModelMetadata = false;
            $runner->run();

            expect($filesystem->get("$barrelDirectory/index.ts"))
                ->toBe(HeaderedBarrelWriter::HEADER."\nexport * from './user';\nexport * from './user_meta';");
        } finally {
            $filesystem->deleteDirectory($outputDirectory);
        }
    });
});

// ─── Inertia config generation ────────────────────────────────────

test('runner inertiaConfigContent is empty when inertia is disabled', function () {
    config()->set('ts-publish.inertia.enabled', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->inertiaConfigContent)->toBe('');
});

test('runner generates inertiaConfigContent when inertia is enabled with mocked converter', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockSharedData = Mockery::mock(InertiaSharedDataAnalyzer::class);
    $mockSharedData->shouldReceive('setAppPaths')->once();
    $mockSharedData->shouldReceive('analyze')->andReturn([
        'sharedPageProps' => '{ appName: string }',
        'withAllErrors' => true,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    $mockPageAnalyzer = Mockery::mock(InertiaPageAnalyzer::class);
    $mockPageAnalyzer->shouldReceive('analyze')->andReturn(null);

    app()->instance(InertiaSharedDataAnalyzer::class, $mockSharedData);
    app()->instance(InertiaPageAnalyzer::class, $mockPageAnalyzer);

    $runner = new Runner;
    $runner->run();

    expect($runner->inertiaConfigContent)
        ->toContain("declare module '@inertiajs/core'")
        ->toContain('sharedPageProps: { appName: string }')
        ->toContain('errorValueType: string[]');
});

test('runner inertiaConfigContent is empty when converter returns null', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockSharedData = Mockery::mock(InertiaSharedDataAnalyzer::class);
    $mockSharedData->shouldReceive('setAppPaths')->once();
    $mockSharedData->shouldReceive('analyze')->andReturn(null);

    $mockPageAnalyzer = Mockery::mock(InertiaPageAnalyzer::class);
    $mockPageAnalyzer->shouldReceive('analyze')->andReturn(null);

    app()->instance(InertiaSharedDataAnalyzer::class, $mockSharedData);
    app()->instance(InertiaPageAnalyzer::class, $mockPageAnalyzer);

    $runner = new Runner;
    $runner->run();

    expect($runner->inertiaConfigContent)->toBe('');
});

test('runner generates broadcast channels content when enabled', function () {
    config()->set('ts-publish.broadcast_channels.enabled', true);

    $runner = new Runner;
    $runner->run();

    expect($runner->broadcastChannelsContent)
        ->toContain('export type BroadcastChannel')
        ->toContain('export const BroadcastChannels')
        ->toContain('orders')
        ->toContain('public-announcements')
        // 'chat.{roomId}' is registered alongside 'chat.{roomId}.messages' in the
        // workbench fixture — the $channel accessor must appear so both channels
        // are reachable via the BroadcastChannels const.
        ->toContain('$channel: `chat.${roomId}` as const');
});

test('runner skips broadcast channels when disabled', function () {
    config()->set('ts-publish.broadcast_channels.enabled', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->broadcastChannelsContent)->toBe('');
});

test('runner skips broadcast channels when shouldPublishBroadcastChannels is false', function () {
    config()->set('ts-publish.broadcast_channels.enabled', true);

    $runner = new Runner;
    $runner->shouldPublishBroadcastChannels = false;
    $runner->run();

    expect($runner->broadcastChannelsContent)->toBe('');
});

test('runner generates broadcast events content when enabled', function () {
    config()->set('ts-publish.broadcast_events.enabled', true);
    config()->set('ts-publish.broadcast_events.echo_augmentation.enabled', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->broadcastEventsIndexContent)
        ->toContain('export type BroadcastEvent')
        ->toContain('export const BroadcastEvents')
        ->toContain('OrderShipped')
        ->toContain('server.created');

    expect(count($runner->broadcastEventGenerators))->toBeGreaterThanOrEqual(4);
});

test('runner skips broadcast events when disabled', function () {
    config()->set('ts-publish.broadcast_events.enabled', false);

    $runner = new Runner;
    $runner->run();

    expect($runner->broadcastEventsIndexContent)->toBe('');
    expect(count($runner->broadcastEventGenerators))->toBe(0);
});

test('runner skips broadcast events when shouldPublishBroadcastEvents is false', function () {
    config()->set('ts-publish.broadcast_events.enabled', true);

    $runner = new Runner;
    $runner->shouldPublishBroadcastEvents = false;
    $runner->run();

    expect($runner->broadcastEventsIndexContent)->toBe('');
});

test('a run drops the global qualifications an earlier run memoized', function () {
    config()->set('ts-publish.globals.enabled', false);
    $service = new CountingTsTypeString;
    TsTypeString::swap($service);
    TsTypeString::qualifyGlobalType('User', ['app.models' => ['User']]);

    $runner = new Runner;
    $runner->shouldPublishModels = false;
    $runner->shouldPublishModelMetadata = false;
    $runner->shouldPublishResources = false;
    $runner->shouldPublishRoutes = false;
    $runner->shouldPublishFormRequests = false;
    $runner->shouldPublishBroadcastChannels = false;
    $runner->shouldPublishBroadcastEvents = false;
    $runner->run();
    TsTypeString::qualifyGlobalType('User', ['app.models' => ['User']]);

    expect($service->qualifications)->toBe(2);
});

// ─── PublishedResourceRegistry run boundary ────────────────────────

describe('PublishedResourceRegistry run boundary', function () {
    test('a second run narrows the registry to its own collected set', function () {
        $firstRunner = new Runner;
        $firstRunner->run();

        expect(PublishedResourceRegistry::isPublished(RegistrarResource::class))->toBeTrue();

        config()->set('ts-publish.resources.excluded', [RegistrarResource::class]);

        $secondRunner = new Runner;
        $secondRunner->run();

        expect(PublishedResourceRegistry::isPublished(RegistrarResource::class))->toBeFalse();
    });

    test('a run with shouldPublishResources false clears the previous run\'s set', function () {
        $firstRunner = new Runner;
        $firstRunner->run();

        expect(PublishedResourceRegistry::isEmpty())->toBeFalse();

        $secondRunner = new Runner;
        $secondRunner->shouldPublishResources = false;
        $secondRunner->run();

        expect(PublishedResourceRegistry::isEmpty())->toBeTrue();
    });

    test('an excluded resource degrades a dependent property to unknown instead of naming an unemitted symbol', function () {
        $firstRunner = new Runner;
        $firstRunner->run();

        // Both naming candidates for the Registrar model, so the convention loop has nothing
        // left to fall through to and the property degrades to unknown rather than to Registrar.
        config()->set('ts-publish.resources.excluded', [RegistrarResource::class, BareRegistrarResource::class]);

        $secondRunner = new Runner;
        $secondRunner->run();

        $merchantGenerator = $secondRunner->resourceGenerators
            ->first(fn (ResourceGenerator $generator): bool => $generator->filename() === 'merchant-resource');

        expect($merchantGenerator)->toBeInstanceOf(ResourceGenerator::class);

        expect($merchantGenerator->content)
            ->toContain('registrar?: unknown;')
            ->not->toContain('RegistrarResource');
    });
});

// ─── PublishedModelRegistry run boundary ────────────────────────

describe('PublishedModelRegistry run boundary', function () {
    /** The generated content of one model file, by its filename. */
    $modelContent = fn (Runner $runner, string $filename): ?string => $runner->modelGenerators
        ->first(fn (ModelGenerator $generator): bool => $generator->filename() === $filename)
        ?->content;

    test('a run publishes each model a collected model relates to, and the models those relate to in turn', function () use ($modelContent) {
        $runner = new Runner;
        $runner->run();

        expect(PublishedModelRegistry::isPublished(Facility::class))->toBeTrue()
            ->and(PublishedModelRegistry::isPublished(AuditTrail::class))->toBeTrue()
            ->and(PublishedModelRegistry::isPublished(AuditNote::class))->toBeTrue()
            ->and(PublishedModelRegistry::isPublished(AuditInspector::class))->toBeTrue()
            ->and($modelContent($runner, 'facility'))
            ->toContain("import type { AuditInspector, AuditTrail } from '../packages/audit/models';")
            ->toContain('audit_trails: AuditTrail[];')
            ->toContain('inspector: AuditInspector | User | null;')
            ->and($modelContent($runner, 'audit-trail'))->toContain('notes: AuditNote[];')
            ->and($modelContent($runner, 'audit-note'))->toContain('trail: AuditTrail;');
    });

    test('a model with #[TsExclude] is never published on demand, and nothing names it', function () use ($modelContent) {
        $runner = new Runner;
        $runner->run();

        expect(PublishedModelRegistry::isPublished(ExcludedModel::class))->toBeFalse()
            ->and($modelContent($runner, 'excluded-model'))->toBeNull()
            ->and($modelContent($runner, 'facility'))->not->toContain('excluded_records')->not->toContain('ExcludedModel');
    });

    test('a model with no table is never published on demand, and raises no warning', function () use ($modelContent) {
        $runner = new Runner;
        $runner->run();

        expect(PublishedModelRegistry::isPublished(AuditArchive::class))->toBeFalse()
            ->and($modelContent($runner, 'audit-archive'))->toBeNull()
            ->and($modelContent($runner, 'audit-trail'))->not->toContain('archive')
            ->and(array_column(AnalysisWarnings::all(), 'subject'))->not->toContain(AuditArchive::class);
    });

    test('an excluded model is not published on demand, nor is a model only it reaches', function () use ($modelContent) {
        config()->set('ts-publish.models.excluded', [AuditTrail::class]);

        $runner = new Runner;
        $runner->run();

        expect(PublishedModelRegistry::isPublished(AuditTrail::class))->toBeFalse()
            ->and(PublishedModelRegistry::isPublished(AuditNote::class))->toBeFalse()
            ->and($modelContent($runner, 'facility'))->not->toContain('audit_trails')->not->toContain('AuditTrail');
    });

    test('an included allow-list publishes nothing on demand', function () use ($modelContent) {
        config()->set('ts-publish.models.included', [Facility::class]);

        $runner = new Runner;
        $runner->run();

        // A morphTo keeps its key, as one with no known target always has; every target here is outside the list.
        expect($runner->modelGenerators)->toHaveCount(1)
            ->and($modelContent($runner, 'facility'))
            ->not->toContain('audit_trails')
            ->not->toContain('import type')
            ->toContain('inspector: unknown;');
    });

    test('a vendor model the config never lists is published when a collected model relates to it', function () use ($modelContent) {
        // The stock install: Notifiable relates User to a model in no application directory.
        config()->set('ts-publish.models.additional_directories', array_values(array_diff(
            config()->array('ts-publish.models.additional_directories'),
            [DatabaseNotification::class],
        )));

        $runner = new Runner;
        $runner->run();

        expect(PublishedModelRegistry::isPublished(DatabaseNotification::class))->toBeTrue()
            ->and($modelContent($runner, 'database-notification'))->toContain('export interface DatabaseNotification')
            ->and($modelContent($runner, 'user'))->toContain('notifications: DatabaseNotification[];');
    });

    test('the same vendor model is left out, without a warning, while its table does not exist', function () use ($modelContent) {
        config()->set('ts-publish.models.additional_directories', array_values(array_diff(
            config()->array('ts-publish.models.additional_directories'),
            [DatabaseNotification::class],
        )));
        Schema::drop('notifications');

        $runner = new Runner;
        $runner->run();

        expect(PublishedModelRegistry::isPublished(DatabaseNotification::class))->toBeFalse()
            ->and($modelContent($runner, 'database-notification'))->toBeNull()
            ->and($modelContent($runner, 'user'))->not->toContain('DatabaseNotification')
            ->and(array_column(AnalysisWarnings::all(), 'subject'))->not->toContain(DatabaseNotification::class);
    });

    // Collects a model whose relation reaches one with a relation that throws when read on a blank instance.
    $reachUnreadableRelation = function (): void {
        config()->set('ts-publish.models.additional_directories', [
            ...config()->array('ts-publish.models.additional_directories'),
            UnreadableRelationFacility::class,
        ]);
    };

    $unreadableRelationWarning = [[
        'subject' => UnreadableRelationTrail::class,
        'message' => 'Reading its notes() relation threw [Attempt to read property "name" on null], so the relation is left out.',
    ]];

    $trailWarnings = fn (): array => array_values(array_filter(
        AnalysisWarnings::all(),
        fn (array $warning): bool => $warning['subject'] === UnreadableRelationTrail::class,
    ));

    test('a model a relation reaches is published without the relation it cannot read, with one warning', function () use ($modelContent, $reachUnreadableRelation, $unreadableRelationWarning, $trailWarnings) {
        $reachUnreadableRelation();

        $runner = new Runner;
        $runner->run();

        expect(PublishedModelRegistry::isPublished(UnreadableRelationTrail::class))->toBeTrue()
            ->and($modelContent($runner, 'unreadable-relation-trail'))
            ->toContain('facility: UnreadableRelationFacility;')
            ->toContain('trail_notes: AuditNote[];')
            ->not->toContain('    notes: ')
            ->and($modelContent($runner, 'unreadable-relation-facility'))
            ->toContain("import type { UnreadableRelationTrail } from '.';")
            ->toContain('trails: UnreadableRelationTrail[];')
            ->and($trailWarnings())->toBe($unreadableRelationWarning);
    });

    test('a run that reads no table reads the same set, with the same warning', function () use ($reachUnreadableRelation, $unreadableRelationWarning, $trailWarnings) {
        $reachUnreadableRelation();

        $runner = new Runner;
        $runner->shouldPublishModels = false;
        $runner->shouldPublishModelMetadata = false;
        $runner->run();

        expect(PublishedModelRegistry::isPublished(UnreadableRelationTrail::class))->toBeTrue()
            ->and($trailWarnings())->toBe($unreadableRelationWarning);
    });

    test('a run whose models collector finds no model names none', function () {
        config()->set('ts-publish.models.included', ['Workbench\App\Models\NoSuchModel']);

        $runner = new Runner;
        $runner->run();

        $resource = $runner->resourceGenerators
            ->first(fn (ResourceGenerator $generator): bool => $generator->filename() === 'facility-resource');

        expect($runner->modelGenerators)->toBeEmpty()
            ->and(PublishedModelRegistry::isEmpty())->toBeFalse()
            ->and($resource->content)
            ->toContain('audit_trails: unknown;')
            ->not->toContain('AuditTrail')
            ->not->toContain('import type');
    });

    test('a run that skips the model phase still reads the set, so its other phases name the same models', function () {
        $runner = new Runner;
        $runner->shouldPublishModels = false;
        $runner->shouldPublishModelMetadata = false;
        $runner->run();

        $resource = fn (string $filename): string => $runner->resourceGenerators
            ->first(fn (ResourceGenerator $generator): bool => $generator->filename() === $filename)
            ->content;

        expect($runner->modelGenerators)->toBeEmpty()
            ->and(PublishedModelRegistry::isPublished(AuditTrail::class))->toBeTrue()
            ->and(PublishedModelRegistry::isPublished(ExcludedModel::class))->toBeFalse()
            ->and($resource('facility-resource'))
            ->toContain('audit_trails: AuditTrail[];')
            ->toContain('excluded_records: unknown;')
            // A morphTo's targets come from the same pass, so the union is the one a full run publishes.
            ->and($resource('image-morph-resource'))->toContain('imageable: Post | Product | WorkbenchUser | CrmUser;');
    });

    test('a run that publishes nothing after the model phase clears the previous run\'s set and reads none', function () {
        config()->set('ts-publish.inertia.enabled', false);

        (new Runner)->run();

        expect(PublishedModelRegistry::isEmpty())->toBeFalse();

        $secondRunner = new Runner;
        $secondRunner->shouldPublishModels = false;
        $secondRunner->shouldPublishModelMetadata = false;
        $secondRunner->shouldPublishResources = false;
        $secondRunner->shouldPublishRoutes = false;
        $secondRunner->shouldPublishFormRequests = false;
        $secondRunner->shouldPublishBroadcastEvents = false;
        $secondRunner->run();

        expect(PublishedModelRegistry::isEmpty())->toBeTrue();
    });

    test('a run with model publishing turned off in config reads no set', function () {
        config()->set('ts-publish.models.enabled', false);

        $runner = new Runner;
        $runner->shouldPublishModels = false;
        $runner->shouldPublishModelMetadata = false;
        $runner->run();

        expect(PublishedModelRegistry::isEmpty())->toBeTrue();
    });

    // Every phase after the models is off, so only the Inertia config, which every run writes, can still name a model.
    $enumsOnly = function (): Runner {
        $runner = new Runner;
        $runner->shouldPublishModels = false;
        $runner->shouldPublishModelMetadata = false;
        $runner->shouldPublishResources = false;
        $runner->shouldPublishRoutes = false;
        $runner->shouldPublishFormRequests = false;
        $runner->shouldPublishBroadcastEvents = false;

        return $runner;
    };

    test('an enums-only run with Inertia on reads the set, since the Inertia config types the shared data', function () use ($enumsOnly) {
        $enumsOnly()->run();

        expect(PublishedModelRegistry::isEmpty())->toBeFalse();
    });

    // Builds a run's published set, and says what that took and gave: its queries, its models and its signature.
    $buildTheSet = function (bool $generatesModels): array {
        PublishedModelRegistry::reset();

        // The first query of a test migrates the lazily refreshed database, so it must not be counted.
        DB::select('select 1');

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $runner = new class extends Runner
        {
            /** @return list<class-string> */
            public function buildSet(): array
            {
                return $this->buildModelMorphTargetMap();
            }
        };
        $runner->shouldPublishModels = $generatesModels;
        $models = $runner->buildSet();

        return ['queries' => $queries, 'models' => $models, 'signature' => PublishedModelRegistry::signature()];
    };

    test('a run that generates no model builds the set without reading the table of every model', function () use ($buildTheSet) {
        expect($buildTheSet(false)['queries'])->toBeLessThan(resolve(ModelsCollector::class)->collect()->count())
            ->and(array_column(AnalysisWarnings::all(), 'subject'))->not->toContain(BaseExtendableModel::class);
    });

    test('a run that generates the models reads the table of each model while it builds the set', function () use ($buildTheSet) {
        expect($buildTheSet(true)['queries'])->toBeGreaterThan(resolve(ModelsCollector::class)->collect()->count())
            ->and(array_column(AnalysisWarnings::all(), 'subject'))->toContain(BaseExtendableModel::class);
    });

    test('the set built without tables is the set built with them, over every collected model', function () use ($buildTheSet) {
        // Without tables first: built the other way round, it would answer from the contexts the first build cached.
        $withoutTables = $buildTheSet(false);
        $withTables = $buildTheSet(true);

        expect($withoutTables['models'])->toBe($withTables['models'])
            ->toContain(AuditTrail::class)
            ->and($withoutTables['signature'])->toBe($withTables['signature'])
            ->not->toBe('');
    });

    test('a run builds the morph map once through a project\'s buildMorphTargetMap() override', function (bool $generatesModels) {
        $resolver = new RecordingModelAttributeResolver;
        app()->instance(ModelAttributeResolver::class, $resolver);

        $runner = new Runner;
        $runner->shouldPublishModels = $generatesModels;
        $runner->shouldPublishModelMetadata = $generatesModels;
        $runner->run();

        expect($resolver->morphTargetMapBuilds)->toHaveCount(1)
            ->and($resolver->morphTargetMapBuilds[0])->toContain(Facility::class, AuditTrail::class);
    })->with([
        'a run that generates the models' => [true],
        'a run that skips the model phase, so reads no table' => [false],
    ]);

    test('a models collector that only lists its classes is not asked for related models, so a run publishes its list', function () {
        config()->set('ts-publish.models.collector_class', ListOnlyModelsCollector::class);

        $runner = new Runner;
        $runner->run();

        expect($runner->modelGenerators)->toHaveCount(1)
            ->and($runner->modelGenerators->first()->filename())->toBe('facility')
            ->and(PublishedModelRegistry::isPublished(AuditTrail::class))->toBeFalse();
    });

    test('a models collector with no collect() stops the run with a message that names it', function () {
        config()->set('ts-publish.models.collector_class', stdClass::class);

        expect(fn () => (new Runner)->run())
            ->toThrow(InvalidArgumentException::class, 'Configured models collector [stdClass] must offer collect().');
    });

    test('a models collector that serves collect() through __call completes a run and publishes its list', function () {
        config()->set('ts-publish.models.collector_class', MagicCallModelsCollector::class);

        $runner = new Runner;
        $runner->run();

        expect($runner->modelGenerators)->toHaveCount(1)
            ->and($runner->modelGenerators->first()->filename())->toBe('facility');
    });

    test('a run starts with an empty analysis memo, pinned answers included', function () use ($enumsOnly) {
        $memo = resolve(AnalysisMemo::class);
        $memo->remember('an-earlier-run', fn (): string => 'stale', pin: true);

        $enumsOnly()->run();

        expect($memo->remember('an-earlier-run', fn (): string => 'fresh', pin: true))->toBe('fresh');
    });

    test('a resource, an event and an Inertia page name an on-demand model and decline an unpublished one', function () {
        $runner = new Runner;
        $runner->run();

        $resource = $runner->resourceGenerators
            ->first(fn (ResourceGenerator $generator): bool => $generator->filename() === 'facility-resource');
        $event = $runner->broadcastEventGenerators
            ->first(fn ($generator): bool => $generator->filename() === 'FacilityAudited');
        $page = $runner->routeGenerators
            ->first(fn ($generator): bool => $generator->filename() === 'inertia-facility-controller');

        expect($resource->content)
            ->toContain('audit_trails: AuditTrail[];')
            ->toContain('latest_trail: AuditTrail | null;')
            ->toContain('excluded_records: unknown;')
            ->toContain('summary: { trails: AuditTrail[]; excluded: unknown };')
            ->not->toContain('ExcludedModel')
            ->and($event->content)
            ->toContain('trail: Partial<AuditTrail>;')
            ->toContain('record: unknown;')
            ->not->toContain('ExcludedModel')
            ->and($page->content)
            ->toContain('trail: AuditTrail | null, record: unknown }')
            ->not->toContain('ExcludedModel');
    });
});

// ─── Enum names two enums in one namespace both publish ────────────────────────

describe('enum names that collide inside one namespace', function () {
    test('a run warns when one enum\'s const is another enum\'s type name in the same namespace', function () {
        config()->set('ts-publish.enums.additional_directories', [Access::class, AccessType::class]);
        config()->set('ts-publish.enums.included', [Access::class, AccessType::class]);

        (new Runner)->run();

        expect(AnalysisWarnings::all())->toContain([
            'subject' => AccessType::class,
            'message' => 'Publishes the name [AccessType], which ['.Access::class.'] also publishes in the same namespace, '
                .'so their barrel and the globals file do not compile. Give one enum another name with #[TsEnum].',
        ]);
    });

    test('a run warns when one enum\'s const is the kind name a backed enum publishes in the same namespace', function () {
        config()->set('ts-publish.enums.additional_directories', [Access::class, AccessKind::class]);
        config()->set('ts-publish.enums.included', [Access::class, AccessKind::class]);

        (new Runner)->run();

        expect(AnalysisWarnings::all())->toContain([
            'subject' => AccessKind::class,
            'message' => 'Publishes the name [AccessKind], which ['.Access::class.'] also publishes in the same namespace, '
                .'so their barrel and the globals file do not compile. Give one enum another name with #[TsEnum].',
        ]);
    });

    test('a run raises no such warning for enums that share a name across namespaces', function () {
        // Clearance and Crm's ClearanceType cross names too, but each in its own namespace, where an alias settles it.
        (new Runner)->run();

        expect(array_filter(
            AnalysisWarnings::all(),
            fn (array $warning): bool => str_contains($warning['message'], 'also publishes in the same namespace'),
        ))->toBe([]);
    });
});

describe('enum method values an EnumResource response re-indexes', function () {
    $reindexWarning = fn (string $method): string => 'Method ['.$method.'] returns an array whose numeric keys are not '
        .'0 to n-1 in order, so the published enum writes it as an object while an EnumResource response re-indexes it '
        .'into a list. Wrap it in array_values() for a list, or use non-numeric keys for an object.';

    test('warns of an enum method value whose integer keys an EnumResource response re-indexes', function () use ($reindexWarning) {
        (new Runner)->run();

        $warning = ['subject' => Priority::class, 'message' => $reindexWarning('filterByMinimum')];

        expect(array_keys(AnalysisWarnings::all(), $warning, true))->toHaveCount(1);
    });

    test('never warns of an associative array or a list', function () {
        (new Runner)->run();

        expect(array_column(AnalysisWarnings::all(), 'subject'))->not->toContain(FreightClass::class);
    });

    // ConditionallyLoadsAttributes::filter() recurses into arrays only, and re-indexes one whose keys are all numeric.
    test('warns of each value the response re-indexes, and of no other', function () use ($reindexWarning) {
        config()->set('ts-publish.enums.additional_directories', [ReindexedValueEnum::class]);
        config()->set('ts-publish.enums.included', [ReindexedValueEnum::class]);

        (new Runner)->run();

        $messages = array_column(array_filter(
            AnalysisWarnings::all(),
            fn (array $warning): bool => $warning['subject'] === ReindexedValueEnum::class,
        ), 'message');

        expect($messages)->toEqualCanonicalizing([
            $reindexWarning('nestedTiers'),
            $reindexWarning('months'),
            $reindexWarning('sparse'),
        ]);
    });
});

// ─── CoreCollector class map run boundary ────────────────────────

test('a run drops a class map memoized before it so the disk is rescanned', function () {
    $cache = new ReflectionProperty(CoreCollector::class, 'classMaps');
    $cache->setValue(null, ['/a/directory/scanned/by/an/earlier/run' => []]);

    (new Runner)->run();

    expect($cache->getValue())->not->toHaveKey('/a/directory/scanned/by/an/earlier/run');
});

test('runner honors the configured model metadata collector_class', function () {
    config()->set('ts-publish.model_metadata.collector_class', SingleModelMetadataCollector::class);

    $runner = new Runner;
    $runner->run();

    expect($runner->modelMetadataGenerators)->toHaveCount(1)
        ->and($runner->modelMetadataGenerators->first()->findable)->toBe(Post::class);
});

test('runner honors the configured model metadata generator_class', function () {
    config()->set('ts-publish.models.included', [User::class]);
    config()->set('ts-publish.model_metadata.generator_class', MarkedModelMetadataGenerator::class);

    $runner = new Runner;
    $runner->run();

    expect($runner->modelMetadataGenerators)->toHaveCount(1)
        ->and($runner->modelMetadataGenerators->first())->toBeInstanceOf(MarkedModelMetadataGenerator::class)
        ->and($runner->modelMetadataGenerators->first()->content)->toEndWith("// custom generator\n");
});

// ─── Run boundary: process-lifetime state ───────────────────────

describe('ModelAttributeResolver run boundary', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.models.additional_directories', [LateTableModel::class]);
        config()->set('ts-publish.models.included', [LateTableModel::class]);
        config()->set('ts-publish.resources.additional_directories', [LateTableResource::class]);
        config()->set('ts-publish.resources.included', [LateTableResource::class]);
    });

    /** A runner that publishes the late-table model and its resource only. */
    $runner = function (): Runner {
        $runner = new Runner;
        $runner->shouldPublishEnums = false;
        $runner->shouldPublishModelMetadata = false;
        $runner->shouldPublishRoutes = false;
        $runner->shouldPublishFormRequests = false;
        $runner->shouldPublishBroadcastChannels = false;
        $runner->shouldPublishBroadcastEvents = false;

        return $runner;
    };

    /** The missing-table warnings this run recorded for the late-table model. */
    $tableWarnings = fn (): array => array_values(array_filter(
        AnalysisWarnings::all(),
        fn (array $warning): bool => $warning['subject'] === LateTableModel::class
            && str_starts_with($warning['message'], 'Table [late_tables] does not exist'),
    ));

    test('a table created between two runs in one process reaches a resource that reads its column', function () use ($runner) {
        $first = $runner();
        $first->run();
        expect($first->resourceGenerators->first()?->content)->toContain('title: unknown;');

        Schema::create('late_tables', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $second = $runner();
        $second->run();
        expect($second->modelGenerators->first()?->content)->toContain('title: string;')
            ->and($second->resourceGenerators->first()?->content)->toContain('title: string;');
    });

    test('a table still missing on a second run in one process warns again', function () use ($runner, $tableWarnings) {
        $runner()->run();
        expect($tableWarnings())->toHaveCount(1);
        $runner()->run();
        expect($tableWarnings())->toHaveCount(1);
    });

    test('a source run after a full run in one process reads a table created between them', function () use ($runner) {
        $runner()->run();
        Schema::create('late_tables', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $source = new RunnerForSource(LateTableResource::class);
        $source->run();
        expect($source->resourceGenerators->first()?->content)->toContain('title: string;');
    });
});

describe('config-derived maps run boundary', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
    });

    /** A runner that publishes models only, limited to the given ones. */
    $modelsRunner = function (array $models): Runner {
        config()->set('ts-publish.models.included', $models);

        $runner = new Runner;
        $runner->shouldPublishEnums = false;
        $runner->shouldPublishModelMetadata = false;
        $runner->shouldPublishResources = false;
        $runner->shouldPublishRoutes = false;
        $runner->shouldPublishFormRequests = false;
        $runner->shouldPublishBroadcastChannels = false;
        $runner->shouldPublishBroadcastEvents = false;

        return $runner;
    };

    /** The generated content of the Post model file. */
    $post = fn (Runner $runner): ?string => $runner->modelGenerators
        ->first(fn (ModelGenerator $generator): bool => $generator->filename() === 'post')
        ?->content;

    test('a custom_ts_mappings change between two runs in one process reaches the second', function () use ($modelsRunner) {
        $first = $modelsRunner([User::class]);
        $first->run();
        expect($first->modelGenerators->first()?->content)->toContain('email: string;');

        config()->set('ts-publish.custom_ts_mappings', ['varchar' => 'Lowercase<string>']);

        $second = $modelsRunner([User::class]);
        $second->run();
        expect($second->modelGenerators->first()?->content)->toContain('email: Lowercase<string>;');
    });

    test('a relation_nullability_map change between two runs in one process reaches the second', function () use ($modelsRunner, $post) {
        $first = $modelsRunner([Post::class, User::class]);
        $first->run();
        expect($post($first))->toMatch('/\n\s+author: User;/');

        config()->set('ts-publish.models.relation_nullability_map', [BelongsTo::class => 'nullable']);

        $second = $modelsRunner([Post::class, User::class]);
        $second->run();
        expect($post($second))->toMatch('/\n\s+author: User \| null;/');
    });
});

describe('a second run in one process', function () {
    /** The generated content of the comment-composed resource. */
    $composed = fn (Collection $generators): string => $generators
        ->first(fn (ResourceGenerator $generator): bool => $generator->filename() === 'comment-composed-resource')
        ->content;

    /** Asserts the content a run publishes when Comment is excluded, as a fresh process publishes it. */
    $expectsCommentExcluded = function (string $content): void {
        expect($content)
            ->toContain('comments: Record<string, unknown>;')
            ->toContain('comments_limited: Record<string, unknown>;')
            ->toContain("import type { Tag } from '../../models';")
            ->not->toContain('Comment[]');
    };

    test('a second run in one process publishes what a fresh process does', function (Closure $secondRun) use ($composed, $expectsCommentExcluded) {
        $first = new Runner;
        $first->run();
        config()->set('ts-publish.models.excluded', [Comment::class]);
        $second = $secondRun();
        $second->run();

        expect($composed($first->resourceGenerators))
            ->toContain('comments: Comment[];')
            ->toContain("import type { Comment, Tag } from '../../models';");
        $expectsCommentExcluded($composed($second->resourceGenerators));
    })->with([
        'a full run' => [fn (): Runner => new Runner],
        'a --source run' => [fn (): RunnerForSource => new RunnerForSource(CommentComposedResource::class)],
    ]);

    test('a run that throws part-way leaves the next run in the process clean', function () use ($composed, $expectsCommentExcluded) {
        $first = new Runner;
        $first->run();

        config()->set('ts-publish.resources.collector_class', ThrowingResourcesCollector::class);
        $throwing = new Runner;
        expect(fn () => $throwing->run())->toThrow(RuntimeException::class);

        config()->set('ts-publish.resources.collector_class', ResourcesCollector::class);
        config()->set('ts-publish.models.excluded', [Comment::class]);
        $second = new Runner;
        $second->run();

        $expectsCommentExcluded($composed($second->resourceGenerators));
    });
});
