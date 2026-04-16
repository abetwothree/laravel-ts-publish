<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Writers\ViteEnvWriter;
use Illuminate\Filesystem\Filesystem;

test('returns empty string when disabled', function () {
    config()->set('ts-publish.vite_env.enabled', false);

    $writer = new ViteEnvWriter(new Filesystem);
    $content = $writer->write();

    expect($content)->toBe('');
});

test('returns empty string when source file does not exist', function () {
    config()->set('ts-publish.vite_env.enabled', true);
    config()->set('ts-publish.vite_env.source_file', 'nonexistent-env-file');

    $writer = new ViteEnvWriter(new Filesystem);
    $content = $writer->write();

    expect($content)->toBe('');
});

test('prefers .env over .env.example when no source file configured', function () {
    $originalBasePath = base_path();

    $tmpDir = sys_get_temp_dir().'/laravel-ts-publish-vite-env-priority-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents("$tmpDir/.env", "VITE_FROM_ENV=yes\n");
    file_put_contents("$tmpDir/.env.example", "VITE_FROM_EXAMPLE=yes\n");

    // Point base_path to our temp dir so the writer resolves .env and .env.example there
    app()->useEnvironmentPath($tmpDir);
    app()->setBasePath($tmpDir);

    config()->set('ts-publish.vite_env.enabled', true);
    config()->set('ts-publish.vite_env.source_file', null);
    config()->set('ts-publish.output_to_files', false);

    $writer = new ViteEnvWriter(new Filesystem);
    $content = $writer->write();

    expect($content)
        ->toContain('readonly VITE_FROM_ENV: string;')
        ->not->toContain('VITE_FROM_EXAMPLE');

    // Cleanup
    (new Filesystem)->deleteDirectory($tmpDir);
    app()->setBasePath($originalBasePath);
});

test('falls back to .env.example when .env does not exist', function () {
    $originalBasePath = base_path();

    $tmpDir = sys_get_temp_dir().'/laravel-ts-publish-vite-env-fallback-'.uniqid();
    mkdir($tmpDir, 0755, true);
    // Only create .env.example, no .env
    file_put_contents("$tmpDir/.env.example", "VITE_FROM_EXAMPLE=yes\n");

    app()->useEnvironmentPath($tmpDir);
    app()->setBasePath($tmpDir);

    config()->set('ts-publish.vite_env.enabled', true);
    config()->set('ts-publish.vite_env.source_file', null);
    config()->set('ts-publish.output_to_files', false);

    $writer = new ViteEnvWriter(new Filesystem);
    $content = $writer->write();

    expect($content)
        ->toContain('readonly VITE_FROM_EXAMPLE: string;');

    // Cleanup
    (new Filesystem)->deleteDirectory($tmpDir);
    app()->setBasePath($originalBasePath);
});

test('extracts VITE_ variables from env file', function () {
    $tmpDir = sys_get_temp_dir().'/laravel-ts-publish-vite-env-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents("$tmpDir/.env.example", <<<'ENV'
APP_NAME=Laravel
APP_ENV=local

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"

# A comment
DB_CONNECTION=mysql
VITE_APP_URL=http://localhost
ENV);

    config()->set('ts-publish.vite_env.enabled', true);
    config()->set('ts-publish.vite_env.source_file', "$tmpDir/.env.example");
    config()->set('ts-publish.output_to_files', false);

    $writer = new ViteEnvWriter(new Filesystem);
    $content = $writer->write();

    expect($content)
        ->toContain('/// <reference types="vite/client" />')
        ->toContain('interface ImportMetaEnv')
        ->toContain('readonly VITE_APP_NAME: string;')
        ->toContain('readonly VITE_APP_URL: string;')
        ->toContain('readonly VITE_PUSHER_APP_KEY: string;')
        ->toContain('interface ImportMeta')
        ->toContain('readonly env: ImportMetaEnv;')
        ->not->toContain('APP_ENV')
        ->not->toContain('DB_CONNECTION');

    // Variables should be sorted
    $namePos = strpos($content, 'VITE_APP_NAME');
    $urlPos = strpos($content, 'VITE_APP_URL');
    $pusherPos = strpos($content, 'VITE_PUSHER_APP_KEY');
    expect($namePos)->toBeLessThan($urlPos)
        ->and($urlPos)->toBeLessThan($pusherPos);

    // Cleanup
    (new Filesystem)->deleteDirectory($tmpDir);
});

test('writes vite-env.d.ts to disk', function () {
    $tmpDir = sys_get_temp_dir().'/laravel-ts-publish-vite-env-disk-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents("$tmpDir/.env.example", "VITE_FOO=bar\n");

    config()->set('ts-publish.vite_env.enabled', true);
    config()->set('ts-publish.vite_env.source_file', "$tmpDir/.env.example");
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.output_directory', $tmpDir);

    $writer = new ViteEnvWriter(new Filesystem);
    $writer->write();

    expect(file_exists("$tmpDir/vite-env.d.ts"))->toBeTrue();
    expect(file_get_contents("$tmpDir/vite-env.d.ts"))
        ->toContain('readonly VITE_FOO: string;');

    // Cleanup
    (new Filesystem)->deleteDirectory($tmpDir);
});

test('uses custom output_path when configured', function () {
    $tmpDir = sys_get_temp_dir().'/laravel-ts-publish-vite-env-custom-'.uniqid();
    $customOut = "$tmpDir/custom-output";
    mkdir($tmpDir, 0755, true);
    file_put_contents("$tmpDir/.env.example", "VITE_BAR=baz\n");

    config()->set('ts-publish.vite_env.enabled', true);
    config()->set('ts-publish.vite_env.source_file', "$tmpDir/.env.example");
    config()->set('ts-publish.vite_env.output_path', $customOut);
    config()->set('ts-publish.output_to_files', true);

    $writer = new ViteEnvWriter(new Filesystem);
    $writer->write();

    expect(file_exists("$customOut/vite-env.d.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($tmpDir);
});

test('returns empty string when no VITE_ variables found', function () {
    $tmpDir = sys_get_temp_dir().'/laravel-ts-publish-vite-env-empty-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents("$tmpDir/.env.example", "APP_NAME=Laravel\nDB_CONNECTION=mysql\n");

    config()->set('ts-publish.vite_env.enabled', true);
    config()->set('ts-publish.vite_env.source_file', "$tmpDir/.env.example");
    config()->set('ts-publish.output_to_files', false);

    $writer = new ViteEnvWriter(new Filesystem);
    $content = $writer->write();

    expect($content)->toBe('');

    // Cleanup
    (new Filesystem)->deleteDirectory($tmpDir);
});
