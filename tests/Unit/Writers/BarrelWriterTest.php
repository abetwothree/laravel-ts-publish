<?php

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\Shipping\Enums\Status as ShippingStatus;

test('writes barrel export content from generators', function () {
    config()->set('ts-publish.output_to_files', false);

    $generators = collect([
        resolve(EnumGenerator::class, ['findable' => Status::class]),
        resolve(EnumGenerator::class, ['findable' => Role::class]),
    ]);

    $writer = new BarrelWriter(new Filesystem);
    $content = $writer->write($generators, 'index', 'enums');

    expect($content)
        ->toContain("export * from './role';")
        ->toContain("export * from './status';");
});

test('barrel exports are sorted and unique', function () {
    config()->set('ts-publish.output_to_files', false);

    $generators = collect([
        resolve(EnumGenerator::class, ['findable' => Status::class]),
        resolve(EnumGenerator::class, ['findable' => Role::class]),
    ]);

    $writer = new BarrelWriter(new Filesystem);
    $content = $writer->write($generators, 'index', 'enums');

    $lines = explode("\n", $content);

    // role should come before status alphabetically
    $roleIdx = array_search("export * from './role';", $lines);
    $statusIdx = array_search("export * from './status';", $lines);

    expect($roleIdx)->not->toBeFalse()
        ->and($statusIdx)->not->toBeFalse()
        ->and($roleIdx)->toBeLessThan($statusIdx);
});

test('writes barrel file to disk when output_to_files is enabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'index.ts') && str_contains($content, 'export * from');
        });

    $generators = collect([
        resolve(EnumGenerator::class, ['findable' => Status::class]),
    ]);

    config()->set('ts-publish.output_to_files', true);

    $writer = new BarrelWriter($filesystem);
    $writer->write($generators, 'index', 'enums');
});

test('does not write barrel file to disk when output_to_files is disabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('ensureDirectoryExists');
    $filesystem->shouldNotReceive('put');

    $generators = collect([
        resolve(EnumGenerator::class, ['findable' => Status::class]),
    ]);

    config()->set('ts-publish.output_to_files', false);

    $writer = new BarrelWriter($filesystem);
    $writer->write($generators, 'index', 'enums');
});

test('barrel uses TsEnum custom name for kebab-cased export', function () {
    config()->set('ts-publish.output_to_files', false);

    $generators = collect([
        resolve(EnumGenerator::class, ['findable' => Status::class]),
        resolve(EnumGenerator::class, ['findable' => ShippingStatus::class]),
    ]);

    $writer = new BarrelWriter(new Filesystem);
    $content = $writer->write($generators, 'index', 'enums');

    expect($content)
        ->toContain("export * from './status';")
        ->toContain("export * from './shipment-status';");
});
