<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Writers\InertiaConfigWriter;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.inertia.output_directory', '');
    config()->set('ts-publish.routes.output_directory', '');
});

// ─── write() rendering ───────────────────────────────────────────

test('renders inertia config with shared props type', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ appName: string, userId: number }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
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
        'typeImports' => [],
        'valueImports' => [],
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
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect($content)->not->toContain('errorValueType');
});

// ─── write() file output ─────────────────────────────────────────

test('writes file to disk when output_to_files is enabled with inertia output_path', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-inertia-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.inertia.output_directory', $outputDir);

    $writer = resolve(InertiaConfigWriter::class);
    $writer->write([
        'sharedPageProps' => '{ test: boolean }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect(file_exists("{$outputDir}/inertia-config.d.ts"))->toBeTrue();

    $content = file_get_contents("{$outputDir}/inertia-config.d.ts");
    expect($content)->toContain('sharedPageProps: { test: boolean }');

    (new Filesystem)->deleteDirectory($outputDir);
});

test('falls back to routes output_path when inertia output_path is null', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-inertia-route-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.inertia.output_directory', '');
    config()->set('ts-publish.routes.output_directory', $outputDir);

    $writer = resolve(InertiaConfigWriter::class);
    $writer->write([
        'sharedPageProps' => '{ fallback: string }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect(file_exists("{$outputDir}/inertia-config.d.ts"))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('falls back to output_directory when both inertia and routes output_path are null', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-inertia-default-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.inertia.output_directory', '');
    config()->set('ts-publish.routes.output_directory', '');
    config()->set('ts-publish.output_directory', $outputDir);

    $writer = resolve(InertiaConfigWriter::class);
    $writer->write([
        'sharedPageProps' => '{ default: number }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect(file_exists("{$outputDir}/inertia-config.d.ts"))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('does not write file when output_to_files is disabled', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-inertia-nowrite-test-'.uniqid();
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.inertia.output_directory', $outputDir);

    $writer = resolve(InertiaConfigWriter::class);
    $writer->write([
        'sharedPageProps' => '{ nowrite: string }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect(file_exists("{$outputDir}/inertia-config.d.ts"))->toBeFalse();
});

// ─── write() type imports ────────────────────────────────────────

test('renders type imports before module declaration', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ auth: AuthData, flash: FlashData }',
        'withAllErrors' => false,
        'typeImports' => [
            '@js/types/auth' => ['AuthData'],
            '@js/types/flash' => ['FlashData'],
        ],
        'valueImports' => [],
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

test('renders multiple type import paths above the declaration', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ auth: { user: User | null }, flash: FlashData }',
        'withAllErrors' => false,
        'typeImports' => [
            './app/models' => ['User'],
            '@js/types/flash' => ['FlashData'],
        ],
        'valueImports' => [],
    ]);

    expect($content)->toContain("import type { User } from './app/models';")
        ->and(strpos($content, 'import type { User }'))->toBeLessThan(strpos($content, 'import type { FlashData }'))
        ->and(strpos($content, 'import type { FlashData }'))->toBeLessThan(strpos($content, 'declare global'));
});

test('omits import block when typeImports is empty', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ appName: string }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect($content)
        ->not->toContain('import type')
        ->toContain("declare module '@inertiajs/core'");
});

// ─── write() value imports ───────────────────────────────────────

test('renders the tolki AsEnum import and the enum value imports above the type imports', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ role: AsEnum<typeof Role>, bareRole: RoleType }',
        'withAllErrors' => false,
        'typeImports' => ['./app/enums' => ['RoleType']],
        'valueImports' => ['./app/enums' => ['Role']],
    ]);

    expect($content)
        ->toContain("import { type AsEnum } from '@tolki/ts';")
        ->toContain("import { Role } from './app/enums';")
        ->toContain("import type { RoleType } from './app/enums';")
        ->and(strpos($content, 'type AsEnum'))->toBeLessThan(strpos($content, 'import { Role }'))
        ->and(strpos($content, 'import { Role }'))->toBeLessThan(strpos($content, 'import type { RoleType }'))
        ->and(strpos($content, 'import type { RoleType }'))->toBeLessThan(strpos($content, 'declare global'));
});

test('omits the tolki AsEnum import when the tolki package is disabled', function () {
    config()->set('ts-publish.enums.use_tolki_package', false);

    $content = resolve(InertiaConfigWriter::class)->write([
        'sharedPageProps' => '{ role: RoleType }',
        'withAllErrors' => false,
        'typeImports' => ['./app/enums' => ['RoleType']],
        'valueImports' => ['./app/enums' => ['Role']],
    ]);

    expect($content)->not->toContain('@tolki/ts');
});

test('a value import alone still separates the import block from declare global', function () {
    $content = resolve(InertiaConfigWriter::class)->write([
        'sharedPageProps' => '{ role: AsEnum<typeof Role> }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => ['./app/enums' => ['Role']],
    ]);

    expect($content)
        ->not->toContain('import type')
        ->toContain("import { Role } from './app/enums';\n\ndeclare global {");
});

// ─── declare global / ES module output ───────────────────────────

test('renders declare global namespace Inertia SharedData block', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ appName: string }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect($content)
        ->toContain('declare global')
        ->toContain('namespace Inertia')
        ->toContain('type SharedData = { appName: string }');
});

test('renders export {} at end to make file an ES module', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ appName: string }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect($content)->toContain('export {};');
});

test('SharedData type in declare global matches sharedPageProps in declare module', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $sharedType = '{ auth: { user: unknown }, appName: string }';

    $content = $writer->write([
        'sharedPageProps' => $sharedType,
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    expect(substr_count($content, $sharedType))->toBe(2);
});

test('declare global block appears before declare module block', function () {
    $writer = resolve(InertiaConfigWriter::class);

    $content = $writer->write([
        'sharedPageProps' => '{ appName: string }',
        'withAllErrors' => false,
        'typeImports' => [],
        'valueImports' => [],
    ]);

    $globalPos = strpos($content, 'declare global');
    $modulePos = strpos($content, "declare module '@inertiajs/core'");

    expect($globalPos)->toBeLessThan($modulePos);
});
