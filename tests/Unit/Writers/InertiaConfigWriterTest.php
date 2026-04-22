<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Writers\InertiaConfigWriter;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.inertia.output_path', null);
    config()->set('ts-publish.routes.output_path', null);
});

// ─── write() rendering ───────────────────────────────────────────

test('renders inertia config with shared props type', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ appName: string, userId: number }',
        'withAllErrors' => false,
        'importStatements' => [],
    ]);

    expect($content)
        ->toContain("declare module '@inertiajs/core'")
        ->toContain('sharedPageProps: { appName: string, userId: number }')
        ->not->toContain('errorValueType');
});

test('renders errorValueType when withAllErrors is true', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ flash: string }',
        'withAllErrors' => true,
        'importStatements' => [],
    ]);

    expect($content)
        ->toContain("declare module '@inertiajs/core'")
        ->toContain('sharedPageProps: { flash: string }')
        ->toContain('errorValueType: string[]');
});

test('does not include errorValueType when withAllErrors is false', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ name: string }',
        'withAllErrors' => false,
        'importStatements' => [],
    ]);

    expect($content)->not->toContain('errorValueType');
});

// ─── write() file output ─────────────────────────────────────────

test('writes file to disk when output_to_files is enabled with inertia output_path', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-inertia-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.inertia.output_path', $outputDir);

    $writer = resolve(InertiaConfigWriter::class);
    $writer->write([
        'sharedPageProps' => '{ test: boolean }',
        'withAllErrors' => false,
        'importStatements' => [],
    ]);

    expect(file_exists("{$outputDir}/inertia-config.d.ts"))->toBeTrue();

    $content = file_get_contents("{$outputDir}/inertia-config.d.ts");
    expect($content)->toContain('sharedPageProps: { test: boolean }');

    (new Filesystem)->deleteDirectory($outputDir);
});

test('falls back to routes output_path when inertia output_path is null', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-inertia-route-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.inertia.output_path', null);
    config()->set('ts-publish.routes.output_path', $outputDir);

    $writer = resolve(InertiaConfigWriter::class);
    $writer->write([
        'sharedPageProps' => '{ fallback: string }',
        'withAllErrors' => false,
        'importStatements' => [],
    ]);

    expect(file_exists("{$outputDir}/inertia-config.d.ts"))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('falls back to output_directory when both inertia and routes output_path are null', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-inertia-default-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.inertia.output_path', null);
    config()->set('ts-publish.routes.output_path', null);
    config()->set('ts-publish.output_directory', $outputDir);

    $writer = resolve(InertiaConfigWriter::class);
    $writer->write([
        'sharedPageProps' => '{ default: number }',
        'withAllErrors' => false,
        'importStatements' => [],
    ]);

    expect(file_exists("{$outputDir}/inertia-config.d.ts"))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('does not write file when output_to_files is disabled', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-inertia-nowrite-test-'.uniqid();
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.inertia.output_path', $outputDir);

    $writer = resolve(InertiaConfigWriter::class);
    $writer->write([
        'sharedPageProps' => '{ nowrite: string }',
        'withAllErrors' => false,
        'importStatements' => [],
    ]);

    expect(file_exists("{$outputDir}/inertia-config.d.ts"))->toBeFalse();
});

// ─── write() import statements ───────────────────────────────────

test('renders import statements before module declaration', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ auth: AuthData, flash: FlashData }',
        'withAllErrors' => false,
        'importStatements' => [
            "import type { AuthData } from '@js/types/auth';",
            "import type { FlashData } from '@js/types/flash';",
        ],
    ]);

    expect($content)
        ->toContain("import type { AuthData } from '@js/types/auth';")
        ->toContain("import type { FlashData } from '@js/types/flash';")
        ->toContain("declare module '@inertiajs/core'");

    // Imports should appear before the declare module block
    $importPos = strpos($content, 'import type { AuthData }');
    $declarePos = strpos($content, 'declare module');
    expect($importPos)->toBeLessThan($declarePos);
});

test('omits import block when importStatements is empty', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ appName: string }',
        'withAllErrors' => false,
        'importStatements' => [],
    ]);

    expect($content)
        ->not->toContain('import type')
        ->toContain("declare module '@inertiajs/core'");
});
