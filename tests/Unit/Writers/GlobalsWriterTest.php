<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use Illuminate\Filesystem\Filesystem;

test('writes globals content when enabled', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('declare global')
        ->toContain('export namespace models')
        ->toContain('export namespace enums');
});

test('returns empty string when globals output is disabled', function () {
    config()->set('ts-publish.output_globals_file', false);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)->toBe('');
});

test('writes globals file to disk when output_to_files is enabled', function () {
    config()->set('ts-publish.output_globals_file', true);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'laravel-ts-global') && str_contains($content, 'declare global');
        });

    config()->set('ts-publish.output_to_files', true);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter($filesystem);
    $writer->write($runner);
});

test('globals content contains model interfaces', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('export interface User')
        ->toContain('id: number')
        ->toContain('name: string');
});

test('globals content contains enum interfaces', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('export interface Status')
        ->toContain('Draft')
        ->toContain('Published');
});

test('globals content does not contain AsEnum<typeof ...> (typeof namespace member is illegal in declare global)', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    // AsEnum<typeof namespace.Member> is illegal in declare global {} — must be absent
    expect($content)->not->toContain('AsEnum<typeof');

    // Enum resource types should appear as qualified type aliases instead
    expect($content)->toContain('enums.StatusType');
});

test('globals content resolves aliased types to namespace-qualified names (non-modular)', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.modular_publishing', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    // Aliases used in per-file imports must NOT appear literally in the globals file
    expect($content)
        ->not->toContain('ManagerUser')
        ->not->toContain('CrmUser')
        ->not->toContain('WorkbenchStatusType')
        ->not->toContain('CrmStatusType');

    // In non-modular mode all models share the 'models' namespace (the skip namespace),
    // so all relation types resolve to bare names regardless of source namespace.
    expect($content)
        ->toContain('manager: User | null')
        ->toContain('primary_contact: User | null')
        ->toContain('secondary_contact: User | null');

    // Enum column/mutator types are namespace-qualified
    expect($content)
        ->toContain('status: enums.StatusType | null')
        ->toContain('current_crm_status: enums.StatusType | null');
});

test('globals content does not contain AsEnum<typeof ...> in modular mode (typeof namespace member is illegal in declare global)', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.modular_publishing', true);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    // AsEnum<typeof namespace.Member> is illegal in declare global {} — must be absent
    expect($content)->not->toContain('AsEnum<typeof');

    // Enum resource types should appear as namespace-qualified type aliases instead
    expect($content)->toContain('enums.StatusType');
});

test('globals content resolves aliased types to namespace-qualified names (modular)', function () {
    config()->set('ts-publish.output_globals_file', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.modular_publishing', true);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    // Raw per-file import aliases must NOT appear literally in the modular globals file
    expect($content)
        ->not->toContain('ManagerUser')
        ->not->toContain('CrmUser')
        ->not->toContain('WorkbenchStatusType')
        ->not->toContain('CrmStatusType');

    // Cross-namespace relations must be fully qualified with their source namespace
    expect($content)
        ->toContain('manager: crm.models.User | null')
        ->toContain('primary_contact: crm.models.User | null')
        ->toContain('secondary_contact: crm.models.User | null');

    // Enum column/mutator types must use the correct namespace-qualified type aliases
    expect($content)
        ->toContain('status: app.enums.StatusType | null')
        ->toContain('current_crm_status: crm.enums.StatusType | null');
});
