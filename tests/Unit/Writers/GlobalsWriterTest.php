<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use Illuminate\Filesystem\Filesystem;

test('writes globals content when enabled', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('declare global')
        ->toContain('export namespace workbench.app.models')
        ->toContain('export namespace workbench.app.enums');
});

test('returns empty string when globals output is disabled', function () {
    config()->set('ts-publish.globals.enabled', false);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)->toBe('');
});

test('writes globals file to disk when output_to_files is enabled', function () {
    config()->set('ts-publish.globals.enabled', true);

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
    config()->set('ts-publish.globals.enabled', true);
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
    config()->set('ts-publish.globals.enabled', true);
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
    config()->set('ts-publish.globals.enabled', true);
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

test('globals content does not contain AsEnum<typeof ...> with namespace_strip_prefix', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);
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

test('globals content resolves aliased types to namespace-qualified names', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);
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

    // Cross-namespace relations must be fully qualified with their source namespace.
    // manager points to App\Models\User — same namespace as Warehouse → stays bare.
    // primary_contact and secondary_contact point to Crm\Models\User → qualified.
    expect($content)
        ->toContain('manager: User | null')
        ->toContain('primary_contact: crm.models.User | null')
        ->toContain('secondary_contact: crm.models.User | null');

    // Enum column/mutator types must use the correct namespace-qualified type aliases
    expect($content)
        ->toContain('status: app.enums.StatusType | null')
        ->toContain('current_crm_status: crm.enums.StatusType | null');

    // MorphTo union with same-basename models must not produce duplicate qualified names.
    // Image is in app.models, so app.models.User stays bare; crm.models.User is qualified.
    expect($content)
        ->toContain('imageable: Post | Product | User | crm.models.User')
        ->not->toContain('imageable: Post | Product | crm.models.User | crm.models.User');
});
