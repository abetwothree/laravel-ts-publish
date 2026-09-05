<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\FailingModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\NonMergingBarrelWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Broadcast;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Models\Post;
use Workbench\App\Models\PostMeta;
use Workbench\App\Models\User;
use Workbench\App\Providers\AstInferredModelMetadataProvider;

test('ts:publish command runs successfully', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('ts:publish')
        ->expectsOutputToContain('All done');
});

test('ts:publish preview shows enum content', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Previewing generated TypeScript declarations')
        ->expectsOutputToContain('Enums:')
        ->expectsOutputToContain('export const Status');
});

test('ts:publish preview shows model content', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Models:')
        ->expectsOutputToContain('export interface User')
        ->expectsOutputToContain('Model Metadata:')
        ->expectsOutputToContain('export const UserModelMetadata');
});

test('ts:publish preview shows model metadata warnings', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.models.included', [User::class]);
    config()->set('ts-publish.model_metadata.provider_class', FailingModelMetadataProvider::class);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain(User::class.': '.RuntimeException::class.': Metadata is unavailable for this model.');
});

test('ts:publish source keeps model metadata provider failures strict', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.provider_class', FailingModelMetadataProvider::class);

    $this->artisan('ts:publish', [
        '--preview' => 'true',
        '--source' => User::class,
        '--only-model-metadata' => true,
    ])->assertFailed();
});

test('ts:publish preserves last-known-good metadata output after a provider failure', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-metadata-fallback-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.models.included', [User::class]);

    $this->artisan('ts:publish', ['--preview' => 'false'])->assertSuccessful();

    config()->set('ts-publish.model_metadata.provider_class', FailingModelMetadataProvider::class);

    $this->artisan('ts:publish', ['--preview' => 'false'])->assertSuccessful();

    $metadataPath = "$outputDir/workbench/app/models/user_meta.ts";
    $barrelPath = "$outputDir/workbench/app/models/index.ts";

    expect($metadataPath)->toBeFile()
        ->and(file_get_contents($barrelPath))->toContain("export * from './user_meta';");

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish preview shows barrel files', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Enum Barrel Files:')
        ->expectsOutputToContain('Model Barrel Files:');
});

test('ts:publish preview shows form request content', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Form Requests:')
        ->expectsOutputToContain('StorePostRequest');
});

test('ts:publish writes files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-test-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.model_metadata.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful()
        ->expectsOutputToContain("Published to: {$outputDir}");

    expect(is_dir("$outputDir/workbench/app/enums"))->toBeTrue()
        ->and(is_dir("$outputDir/workbench/app/models"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/enums/status.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/user.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/user_meta.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/enums/index.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/index.ts"))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('model metadata does not overwrite a model whose name ends in Meta', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-meta-model-collision-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.models.included', [Post::class, PostMeta::class]);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();

    $modelPath = "$outputDir/workbench/app/models/post-meta.ts";
    $metadataPath = "$outputDir/workbench/app/models/post_meta.ts";
    $barrelPath = "$outputDir/workbench/app/models/index.ts";

    expect(file_get_contents($modelPath))
        ->toContain('export interface PostMeta')
        ->not->toContain('export const PostModelMetadata')
        ->and(file_get_contents($metadataPath))
        ->toContain('export const PostModelMetadata')
        ->not->toContain('export interface PostMeta')
        ->and(file_get_contents($barrelPath))
        ->toContain("export * from './post-meta';")
        ->toContain("export * from './post_meta';");

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish returns success exit code', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertExitCode(0);
});

test('ts:publish writes model split template files', function () {
    $outputDir = workbench_path('resources/js/types/data/split-template-example');

    config()->set('ts-publish.models.template', 'laravel-ts-publish::model-split');
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.routes.enabled', true);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.included', [User::class]);
    config()->set('ts-publish.model_metadata.provider_class', AstInferredModelMetadataProvider::class);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();

    expect(file_exists("$outputDir/app/http/controllers/post-controller.ts"))->toBeTrue()
        ->and(file_get_contents("$outputDir/app/models/user_meta.ts"))
        ->toContain("import type { RoleType } from '../enums';")
        ->toContain('limits: { minimum: number; maximum: null };');
});

test('ts:publish writes model full template files', function () {
    $outputDir = workbench_path('resources/js/types/data/full-template-example');

    config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.routes.enabled', true);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.included', [User::class]);
    config()->set('ts-publish.model_metadata.provider_class', AstInferredModelMetadataProvider::class);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();

    expect(file_exists("$outputDir/app/http/controllers/post-controller.ts"))->toBeTrue()
        ->and(file_get_contents("$outputDir/app/models/user_meta.ts"))
        ->toContain("import type { RoleType } from '../enums';")
        ->toContain('limits: { minimum: number; maximum: null };');
});

test('ts:publish writes modular files to namespace-based directories', function () {
    $outputDir = workbench_path('resources/js/types/data/default-example');

    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
    config()->set('ts-publish.routes.enabled', true);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.model_metadata.included', [User::class]);
    config()->set('ts-publish.model_metadata.provider_class', AstInferredModelMetadataProvider::class);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();

    expect(file_exists("$outputDir/app/http/controllers/post-controller.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/app/http/controllers/index.ts"))->toBeTrue();

    expect(file_exists("$outputDir/app/models/user.ts"))->toBeTrue()
        ->and(file_get_contents("$outputDir/app/models/user_meta.ts"))
        ->toContain("import type { RoleType } from '../enums';")
        ->toContain('limits: { minimum: number; maximum: null };')
        ->and(file_exists("$outputDir/app/enums/status.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/app/models/index.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/app/enums/index.ts"))->toBeTrue();

    expect(file_exists("$outputDir/accounting/models/invoice.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/accounting/enums/invoice-status.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/accounting/models/index.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/accounting/enums/index.ts"))->toBeTrue();

    expect(is_dir("$outputDir/enums"))->toBeFalse()
        ->and(is_dir("$outputDir/models"))->toBeFalse();

    $invoiceContent = file_get_contents("$outputDir/accounting/models/invoice.ts");
    expect($invoiceContent)
        ->toContain("from '../enums'")
        ->toContain("from '../../app/models'");

    // The naming-convention branch's first, Resource-suffixed candidate resolves when published.
    $supplierSummaryCollectionContent = file_get_contents("$outputDir/app/http/resources/supplier-summary-collection.ts");
    expect($supplierSummaryCollectionContent)
        ->toContain("import type { SupplierSummaryResource } from '.';")
        ->toContain('data: SupplierSummaryResource[];');

    // The naming-convention branch falls through to its bare, unsuffixed candidate when published.
    $adminStoreCollectionContent = file_get_contents("$outputDir/app/http/resources/admin/store-collection.ts");
    expect($adminStoreCollectionContent)
        ->toContain("import type { Store } from '.';")
        ->toContain('data: Store[];');

    // Both candidates exist but are #[TsExclude]d, so the gate rejects both and no import leaks.
    $ledgerCollectionContent = file_get_contents("$outputDir/app/http/resources/ledger-collection.ts");
    expect($ledgerCollectionContent)
        ->toContain('data: unknown;')
        ->not->toContain('import type');
});

test('ts:publish --source with enum FQCN runs successfully', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--source' => 'Workbench\App\Enums\Status'])
        ->assertSuccessful()
        ->expectsOutputToContain('ts:publish --source')
        ->expectsOutputToContain('export const Status');
});

test('ts:publish --source with model FQCN runs successfully', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--source' => 'Workbench\App\Models\User'])
        ->assertSuccessful()
        ->expectsOutputToContain('export interface User');
});

test('ts:publish --source with file path runs successfully', function () {
    config()->set('ts-publish.output_to_files', false);

    $filePath = workbench_path('app/Enums/Status.php');

    $this->artisan('ts:publish', ['--preview' => 'true', '--source' => $filePath])
        ->assertSuccessful()
        ->expectsOutputToContain('export const Status');
});

test('ts:publish --source with invalid class returns failure', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--source' => 'App\NonExistent\FakeClass'])
        ->assertFailed();
});

test('ts:publish --source with invalid class reports the error under --quiet', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--source' => 'App\NonExistent\FakeClass', '--quiet' => true])
        ->assertFailed()
        ->expectsOutputToContain('ts:publish failed: Class does not exist: App\NonExistent\FakeClass');
});

test('ts:publish --source writes file to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-source-test-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--source' => 'Workbench\App\Enums\Status'])
        ->assertSuccessful();

    expect(file_exists("$outputDir/workbench/app/enums/status.ts"))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish --only-enums shows only enum content in preview', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Enums:')
        ->doesntExpectOutputToContain('Models:');
});

test('ts:publish --only-models shows only model content in preview', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-models' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Models:')
        ->doesntExpectOutputToContain('Model Metadata:')
        ->doesntExpectOutputToContain('Enums:');
});

test('ts:publish --only-model-metadata shows only metadata content in preview', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-model-metadata' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Model Metadata:')
        ->expectsOutputToContain('export const UserModelMetadata')
        ->doesntExpectOutputToContain('Models:')
        ->doesntExpectOutputToContain('Enums:');
});

test('ts:publish --only-enums writes only enum files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-only-enums-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--only-enums' => true])
        ->assertSuccessful();

    expect(is_dir("$outputDir/workbench/app/enums"))->toBeTrue()
        ->and(is_dir("$outputDir/workbench/app/models"))->toBeFalse();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish --only-models writes only model files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-only-models-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--only-models' => true])
        ->assertSuccessful();

    expect(is_dir("$outputDir/workbench/app/models"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/user.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/user_meta.ts"))->toBeFalse()
        ->and(file_get_contents("$outputDir/workbench/app/models/index.ts"))
        ->toContain("export * from './user';")
        ->and(is_dir("$outputDir/workbench/app/enums"))->toBeFalse();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish --only-model-metadata writes only metadata files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-only-model-metadata-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.model_metadata.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--only-model-metadata' => true])
        ->assertSuccessful();

    expect(file_exists("$outputDir/workbench/app/models/user_meta.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/user.ts"))->toBeFalse()
        ->and(file_get_contents("$outputDir/workbench/app/models/index.ts"))
        ->toContain("export * from './user_meta';")
        ->and(file_exists("$outputDir/workbench/app/enums/status.ts"))->toBeFalse();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('partial model runs preserve exports from the skipped companion phase', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-partial-model-barrel-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.model_metadata.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'false'])->assertSuccessful();
    $this->artisan('ts:publish', ['--preview' => 'false', '--only-functional' => true])->assertSuccessful();

    expect(file_exists("$outputDir/workbench/app/models/user.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/user_meta.ts"))->toBeTrue()
        ->and(file_get_contents("$outputDir/workbench/app/models/index.ts"))
        ->toContain("export * from './user';")
        ->toContain("export * from './user_meta';");

    $this->artisan('ts:publish', ['--preview' => 'false', '--only-model-metadata' => true])->assertSuccessful();

    $barrel = file_get_contents("$outputDir/workbench/app/models/index.ts");

    expect(file_exists("$outputDir/workbench/app/models/user.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/user_meta.ts"))->toBeTrue()
        ->and($barrel)
        ->toContain("export * from './user';")
        ->toContain("export * from './user_meta';");

    $this->artisan('ts:publish', ['--preview' => 'false', '--only-models' => true])->assertSuccessful();

    expect(file_exists("$outputDir/workbench/app/models/user.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/workbench/app/models/user_meta.ts"))->toBeTrue()
        ->and(file_get_contents("$outputDir/workbench/app/models/index.ts"))
        ->toContain("export * from './user';")
        ->toContain("export * from './user_meta';");

    (new Filesystem)->deleteDirectory($outputDir);
});

test('partial model runs create missing barrels with custom writers without merge support', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-custom-partial-barrel-'.uniqid();
    config()->set('ts-publish.barrel_writer_class', NonMergingBarrelWriter::class);
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    try {
        $this->artisan('ts:publish', ['--preview' => 'false', '--only-models' => true])->assertSuccessful();

        expect(file_exists("$outputDir/workbench/app/models/user.ts"))->toBeTrue()
            ->and(file_get_contents("$outputDir/workbench/app/models/index.ts"))
            ->toContain("export * from './user';");
    } finally {
        (new Filesystem)->deleteDirectory($outputDir);
    }
});

test('partial model runs preserve existing barrels with custom writers without merge support', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-custom-existing-barrel-'.uniqid();
    $barrelDirectory = "$outputDir/workbench/app/models";
    $filesystem = new Filesystem;
    $filesystem->makeDirectory($barrelDirectory, recursive: true);
    $filesystem->put("$barrelDirectory/index.ts", "export * from './existing';");

    config()->set('ts-publish.barrel_writer_class', NonMergingBarrelWriter::class);
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    try {
        $this->artisan('ts:publish', ['--preview' => 'false', '--only-models' => true])
            ->assertSuccessful();

        expect(file_get_contents("$barrelDirectory/index.ts"))
            ->toBe("export * from './existing';");
    } finally {
        (new Filesystem)->deleteDirectory($outputDir);
    }
});

test('ts:publish fails when both --only-enums and --only-models are passed', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true, '--only-models' => true])
        ->assertFailed();
});

test('ts:publish warns and exits when every publishing phase is disabled', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.model_metadata.enabled', false);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_channels.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Nothing to publish');
});

test('ts:publish respects publish_enums false in config', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Models:')
        ->doesntExpectOutputToContain('Enums:');
});

test('ts:publish respects publish_models false in config', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.model_metadata.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Enums:')
        ->expectsOutputToContain('Model Metadata:')
        ->doesntExpectOutputToContain('Models:');
});

test('ts:publish disables model metadata independently from models', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Models:')
        ->doesntExpectOutputToContain('Model Metadata:');
});

test('ts:publish verbose mode shows detailed tables', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-verbose-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '-v' => true])
        ->assertSuccessful()
        ->expectsOutputToContain("Published to: {$outputDir}")
        ->expectsOutputToContain('Cases')
        ->expectsOutputToContain('Columns');

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish normal verbosity shows compact summary', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-normal-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful()
        ->expectsOutputToContain("Published to: {$outputDir}")
        ->doesntExpectOutputToContain('Cases')
        ->doesntExpectOutputToContain('Columns');

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish quiet mode produces no output', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-quiet-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--quiet' => true])
        ->assertSuccessful()
        ->doesntExpectOutput();

    expect(is_dir("$outputDir/workbench/app/enums"))->toBeTrue()
        ->and(is_dir("$outputDir/workbench/app/models"))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish quiet mode with --source produces no output', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-quiet-source-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--source' => 'Workbench\App\Enums\Status', '--quiet' => true])
        ->assertSuccessful()
        ->doesntExpectOutput();

    expect(file_exists("$outputDir/workbench/app/enums/status.ts"))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish --source exits successfully when every publishing phase is disabled', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.models.enabled', false);
    config()->set('ts-publish.model_metadata.enabled', false);
    config()->set('ts-publish.resources.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_channels.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--source' => 'Workbench\App\Enums\Status'])
        ->assertSuccessful()
        ->expectsOutputToContain('Nothing to publish');
});

test('ts:publish --only-enums exits when config enums disabled and non-interactive', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

test('ts:publish --only-enums overrides when user confirms interactively', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true])
        ->expectsConfirmation('Config has enums publishing disabled. Override and publish enums anyway?', 'yes')
        ->assertSuccessful();
});

test('ts:publish --only-models exits when config models disabled and non-interactive', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.models.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-models' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

test('ts:publish --only-model-metadata exits when metadata is disabled and non-interactive', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-model-metadata' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

test('ts:publish preview shows modular barrel files', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Enum Barrel Files:')
        ->expectsOutputToContain('Model Barrel Files:');
});

test('ts:publish fails when both --only-enums and --only-resources are passed', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true, '--only-resources' => true])
        ->assertFailed();
});

test('ts:publish fails when both --only-models and --only-resources are passed', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-models' => true, '--only-resources' => true])
        ->assertFailed();
});

test('ts:publish reports multiple --only-* options failure under --quiet', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true, '--only-models' => true, '--quiet' => true])
        ->assertFailed()
        ->expectsOutputToContain('ts:publish failed: Cannot use multiple --only-* options together');
});

test('ts:publish reports unexpected writer failures under --quiet', function () {
    // Writers raise ErrorException/RuntimeException too — here from an output path blocked by a file —
    // not only the InvalidArgumentException the handler originally caught.
    $blockedFile = sys_get_temp_dir().'/ts-publish-blocked-'.uniqid();
    file_put_contents($blockedFile, '');

    config()->set('ts-publish.output_directory', $blockedFile.'/nested');
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--quiet' => true])
        ->assertFailed()
        ->expectsOutputToContain('ts:publish failed: Unable to create output directory');

    unlink($blockedFile);
});

test('ts:publish --only-resources shows only resource content in preview', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-resources' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Resources:')
        ->doesntExpectOutputToContain('Enums:')
        ->doesntExpectOutputToContain('Models:');
});

test('ts:publish --only-resources exits when config resources disabled and non-interactive', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.resources.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-resources' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

test('ts:publish preview shows route content', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.routes.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Routes:')
        ->expectsOutputToContain('defineRoute(');
});

test('ts:publish --only-routes shows only route content in preview', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.routes.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-routes' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Routes:')
        ->doesntExpectOutputToContain('Enums:')
        ->doesntExpectOutputToContain('Models:');
});

test('ts:publish --only-routes exits when config routes disabled and non-interactive', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.routes.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-routes' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

test('ts:publish fails when both --only-routes and --only-enums are passed', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-routes' => true, '--only-enums' => true])
        ->assertFailed();
});

test('ts:publish --only-routes writes only route files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-only-routes-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.routes.enabled', true);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $this->artisan('ts:publish', ['--preview' => 'false', '--only-routes' => true])
        ->assertSuccessful();

    expect(is_dir("$outputDir"))->toBeTrue()
        ->and(is_dir("$outputDir/models"))->toBeFalse()
        ->and(is_dir("$outputDir/enums"))->toBeFalse();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish --only-functional publishes runtime TypeScript output', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.enabled', true);
    config()->set('ts-publish.routes.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-functional' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('only functional content')
        ->expectsOutputToContain('Enums:')
        ->expectsOutputToContain('Model Metadata:')
        ->expectsOutputToContain('Routes:')
        ->doesntExpectOutputToContain('Models:')
        ->doesntExpectOutputToContain('Resources:');
});

test('ts:publish --only-functional warns when all functional options disabled', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.enabled', false);
    config()->set('ts-publish.model_metadata.enabled', false);
    config()->set('ts-publish.routes.enabled', false);
    config()->set('ts-publish.form_requests.enabled', false);
    config()->set('ts-publish.broadcast_channels.enabled', false);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-functional' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('All functional options are disabled');
});

test('ts:publish --only-functional ignores other --only flags', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.routes.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-functional' => true, '--only-enums' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('only functional content');
});

test('ts:publish --only-routes overrides when user confirms interactively', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.routes.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-routes' => true])
        ->expectsConfirmation('Config has routes publishing disabled. Override and publish routes anyway?', 'yes')
        ->assertSuccessful();
});

test('ts:publish preview shows broadcast channels content', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.broadcast_channels.enabled', true);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Broadcast Channels:')
        ->expectsOutputToContain('BroadcastChannel');
});

test('ts:publish fails gracefully when broadcast channels have conflicting parameter names', function () {
    // The workbench already registers 'orders.{orderId}', so adding 'orders.{slug}.timeline' gives the
    // 'orders' segment two different wildcard names.
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.broadcast_channels.enabled', true);

    Broadcast::channel('orders.{slug}.timeline', fn () => true);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertFailed()
        ->expectsOutputToContain('conflicting parameter names');
});

test('ts:publish --only-broadcast-channels publishes only the broadcast-channels file', function () {
    $outputDir = sys_get_temp_dir().'/ts-publish-bc-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.broadcast_channels.enabled', true);

    $this->artisan('ts:publish', ['--only-broadcast-channels' => true, '--preview' => 'false'])
        ->assertSuccessful();

    expect(file_exists($outputDir.'/broadcast-channels.ts'))->toBeTrue()
        ->and(file_get_contents($outputDir.'/broadcast-channels.ts'))
        ->toContain('export type BroadcastChannel')
        ->toContain('export const BroadcastChannels');

    expect(is_dir($outputDir.'/workbench/app/enums'))->toBeFalse();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish --only-broadcast-channels published file contains $channel accessor for overlapping channels', function () {
    // The workbench registers both 'chat.{roomId}' and 'chat.{roomId}.messages'.
    $outputDir = sys_get_temp_dir().'/ts-publish-bc-overlap-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.broadcast_channels.enabled', true);

    $this->artisan('ts:publish', ['--only-broadcast-channels' => true, '--preview' => 'false'])
        ->assertSuccessful();

    $content = file_get_contents($outputDir.'/broadcast-channels.ts');
    expect($content)
        ->toContain('$channel: `chat.${roomId}` as const')
        ->toContain('messages: `chat.${roomId}.messages` as const');

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish broadcast channels disabled in config skips the file', function () {
    $outputDir = sys_get_temp_dir().'/ts-publish-bc-disabled-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.broadcast_channels.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();

    expect(file_exists($outputDir.'/broadcast-channels.ts'))->toBeFalse();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish preview shows broadcast events content', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.broadcast_events.enabled', true);
    config()->set('ts-publish.broadcast_events.echo_augmentation.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('BroadcastEvent');
});

test('ts:publish --only-broadcast-events runs successfully', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.broadcast_events.enabled', true);
    config()->set('ts-publish.broadcast_events.echo_augmentation.enabled', false);

    $this->artisan('ts:publish', ['--only-broadcast-events' => true, '--preview' => 'true'])
        ->assertSuccessful();
});

test('ts:publish --only-broadcast-events publishes only the broadcast events files', function () {
    $outputDir = sys_get_temp_dir().'/ts-publish-be-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.broadcast_events.enabled', true);
    config()->set('ts-publish.broadcast_events.echo_augmentation.enabled', false);

    $this->artisan('ts:publish', ['--only-broadcast-events' => true, '--preview' => 'false'])
        ->assertSuccessful();

    expect(file_exists($outputDir.'/broadcast-events.ts'))->toBeTrue()
        ->and(file_get_contents($outputDir.'/broadcast-events.ts'))
        ->toContain('export type BroadcastEvent')
        ->toContain('export const BroadcastEvents');

    expect(is_dir($outputDir.'/workbench/app/enums'))->toBeFalse();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish broadcast events disabled in config skips the files', function () {
    $outputDir = sys_get_temp_dir().'/ts-publish-be-disabled-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.broadcast_events.enabled', false);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();

    expect(file_exists($outputDir.'/broadcast-events.ts'))->toBeFalse();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('full run completes and announces ts:publish', function () {
    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->expectsOutputToContain('ts:publish')
        ->expectsOutputToContain('All done')
        ->assertExitCode(0);
});

test('quiet run produces no ts:publish output', function () {
    $this->artisan('ts:publish', ['--preview' => 'false', '--quiet' => true])
        ->doesntExpectOutputToContain('ts:publish')
        ->doesntExpectOutputToContain('All done')
        ->assertExitCode(0);
});

test('summary callout lists generated type counts and a totals footer', function () {
    // Mockery satisfies one substring expectation per write; 'models' and 'All done' are separate writes.
    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->expectsOutputToContain('models')
        ->expectsOutputToContain('files')
        ->expectsOutputToContain('All done')
        ->assertExitCode(0);
});

test('source run summary only mentions the published type', function () {
    $this->artisan('ts:publish', ['--preview' => 'false', '--source' => 'Workbench\App\Models\User'])
        ->expectsOutputToContain('model')
        ->doesntExpectOutputToContain('route controller')
        ->assertExitCode(0);
});

test('intro shows the output directory context', function () {
    $outputDir = config('ts-publish.output_directory');

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->expectsOutputToContain('Output:')
        ->expectsOutputToContain($outputDir)
        ->assertExitCode(0);
});

test('verbose mode labels each detail table with a section heading', function () {
    $this->artisan('ts:publish', ['--preview' => 'false', '-vvv' => true])
        ->expectsOutputToContain('Enums')
        ->expectsOutputToContain('Models')
        ->assertExitCode(0);
});
