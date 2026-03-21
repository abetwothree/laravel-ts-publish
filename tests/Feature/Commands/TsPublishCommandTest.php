<?php

use Illuminate\Filesystem\Filesystem;

use function Orchestra\Testbench\workbench_path;

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

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Models:')
        ->expectsOutputToContain('export interface User');
});

test('ts:publish preview shows barrel files', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Enum Barrel File:')
        ->expectsOutputToContain('Model Barrel File:');
});

test('ts:publish writes files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-test-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful()
        ->expectsOutputToContain("Published to: {$outputDir}");

    expect(is_dir("$outputDir/enums"))->toBeTrue()
        ->and(is_dir("$outputDir/models"))->toBeTrue()
        ->and(file_exists("$outputDir/enums/status.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/models/user.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/enums/index.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/models/index.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish returns success exit code', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertExitCode(0);
});

test('ts:publish writes model split template files', function () {
    $outputDir = workbench_path('resources/js/types/split-template-example');

    // Cleanup before test
    $filesystem = new Filesystem;
    if ($filesystem->exists($outputDir)) {
        $filesystem->deleteDirectory($outputDir);
    }

    config()->set('ts-publish.model_template', 'laravel-ts-publish::model-split');
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.modular_publishing', false);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();
});

test('ts:publish writes model full template files', function () {
    $outputDir = workbench_path('resources/js/types/full-template-example');

    // Cleanup before test
    $filesystem = new Filesystem;
    if ($filesystem->exists($outputDir)) {
        $filesystem->deleteDirectory($outputDir);
    }

    config()->set('ts-publish.model_template', 'laravel-ts-publish::model-full');
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.modular_publishing', false);

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();
});

test('ts:publish writes modular files to namespace-based directories', function () {
    $outputDir = workbench_path('resources/js/types/modular-example');

    // Cleanup before test
    $filesystem = new Filesystem;
    if ($filesystem->exists($outputDir)) {
        $filesystem->deleteDirectory($outputDir);
    }

    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.modular_publishing', true);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $this->artisan('ts:publish', ['--preview' => 'false'])
        ->assertSuccessful();

    // App models and enums should be in app/ subdirectories
    expect(file_exists("$outputDir/app/models/user.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/app/enums/status.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/app/models/index.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/app/enums/index.ts"))->toBeTrue();

    // Accounting module files should be in accounting/ subdirectories
    expect(file_exists("$outputDir/accounting/models/invoice.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/accounting/enums/invoice-status.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/accounting/models/index.ts"))->toBeTrue()
        ->and(file_exists("$outputDir/accounting/enums/index.ts"))->toBeTrue();

    // Old flat directories should NOT exist
    expect(is_dir("$outputDir/enums"))->toBeFalse()
        ->and(is_dir("$outputDir/models"))->toBeFalse();

    // Verify import paths in a modular model file
    $invoiceContent = file_get_contents("$outputDir/accounting/models/invoice.ts");
    expect($invoiceContent)
        ->toContain("from '../enums'")
        ->toContain("from '../../app/models'");
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

test('ts:publish --source writes file to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-source-test-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--source' => 'Workbench\App\Enums\Status'])
        ->assertSuccessful();

    expect(file_exists("$outputDir/enums/status.ts"))->toBeTrue();

    // Cleanup
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
        ->doesntExpectOutputToContain('Enums:');
});

test('ts:publish --only-enums writes only enum files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-only-enums-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--only-enums' => true])
        ->assertSuccessful();

    expect(is_dir("$outputDir/enums"))->toBeTrue()
        ->and(is_dir("$outputDir/models"))->toBeFalse();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish --only-models writes only model files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-only-models-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--only-models' => true])
        ->assertSuccessful();

    expect(is_dir("$outputDir/models"))->toBeTrue()
        ->and(is_dir("$outputDir/enums"))->toBeFalse();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish fails when both --only-enums and --only-models are passed', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true, '--only-models' => true])
        ->assertFailed();
});

test('ts:publish warns and exits when both config types are disabled', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.publish_enums', false);
    config()->set('ts-publish.publish_models', false);
    config()->set('ts-publish.publish_resources', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Nothing to publish');
});

test('ts:publish respects publish_enums false in config', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.publish_enums', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Models:')
        ->doesntExpectOutputToContain('Enums:');
});

test('ts:publish respects publish_models false in config', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.publish_models', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful()
        ->expectsOutputToContain('Enums:')
        ->doesntExpectOutputToContain('Models:');
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

    // Cleanup
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

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish quiet mode produces no output', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-quiet-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--quiet' => true])
        ->assertSuccessful()
        ->doesntExpectOutput();

    // Files should still be written
    expect(is_dir("$outputDir/enums"))->toBeTrue()
        ->and(is_dir("$outputDir/models"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish quiet mode with --source produces no output', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-quiet-source-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $this->artisan('ts:publish', ['--preview' => 'false', '--source' => 'Workbench\App\Enums\Status', '--quiet' => true])
        ->assertSuccessful()
        ->doesntExpectOutput();

    expect(file_exists("$outputDir/enums/status.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('ts:publish --source exits successfully when both config types disabled', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.publish_enums', false);
    config()->set('ts-publish.publish_models', false);
    config()->set('ts-publish.publish_resources', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--source' => 'Workbench\App\Enums\Status'])
        ->assertSuccessful()
        ->expectsOutputToContain('Nothing to publish');
});

test('ts:publish --only-enums exits when config enums disabled and non-interactive', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.publish_enums', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

test('ts:publish --only-enums overrides when user confirms interactively', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.publish_enums', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-enums' => true])
        ->expectsConfirmation('Config has enums publishing disabled. Override and publish enums anyway?', 'yes')
        ->assertSuccessful();
});

test('ts:publish --only-models exits when config models disabled and non-interactive', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.publish_models', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-models' => true, '--no-interaction' => true])
        ->assertSuccessful();
});

test('ts:publish preview shows modular barrel files', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.modular_publishing', true);
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
    config()->set('ts-publish.publish_resources', false);

    $this->artisan('ts:publish', ['--preview' => 'true', '--only-resources' => true, '--no-interaction' => true])
        ->assertSuccessful();
});
