<?php

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
    $filesystem = new Illuminate\Filesystem\Filesystem;
    $filesystem->deleteDirectory($outputDir);
});

test('ts:publish returns success exit code', function () {
    config()->set('ts-publish.output_to_files', false);

    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertExitCode(0);
});
