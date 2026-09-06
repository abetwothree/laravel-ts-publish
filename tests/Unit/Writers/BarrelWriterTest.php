<?php

declare(strict_types=1);

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
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
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
    $filesystem->shouldNotReceive('exists');
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

test('writeModular writes barrels to the global output_directory by default', function () {
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.output_directory', '/tmp/default-output');

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(fn (string $path) => $path === '/tmp/default-output/workbench/app/enums/index.ts');

    $generators = collect([
        resolve(EnumGenerator::class, ['findable' => Status::class]),
    ]);

    $writer = new BarrelWriter($filesystem);
    $writer->writeModular($generators);
});

test('writeModular writes barrels to the provided output base override', function () {
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.output_directory', '/tmp/default-output');

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(fn (string $path) => $path === '/tmp/custom-broadcast/workbench/app/enums/index.ts');

    $generators = collect([
        resolve(EnumGenerator::class, ['findable' => Status::class]),
    ]);

    $writer = new BarrelWriter($filesystem);
    $writer->writeModular($generators, '/tmp/custom-broadcast');
});

test('writeModular falls back to output_directory when override is an empty string', function () {
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.output_directory', '/tmp/default-output');

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(fn (string $path) => $path === '/tmp/default-output/workbench/app/enums/index.ts');

    $generators = collect([
        resolve(EnumGenerator::class, ['findable' => Status::class]),
    ]);

    $writer = new BarrelWriter($filesystem);
    $writer->writeModular($generators, '');
});

test('writeModular rewrites a barrel and drops exports it no longer generates', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-prune-barrel-'.uniqid();
    $barrelDir = "$outputDir/workbench/app/enums";
    $filesystem = new Filesystem;

    try {
        $filesystem->makeDirectory($barrelDir, recursive: true);
        $filesystem->put("$barrelDir/index.ts", "export * from './ghost';\nexport * from './status';");

        config()->set('ts-publish.output_to_files', true);
        config()->set('ts-publish.output_directory', $outputDir);

        $generators = collect([resolve(EnumGenerator::class, ['findable' => Status::class])]);

        (new BarrelWriter($filesystem))->writeModular($generators);

        expect($filesystem->get("$barrelDir/index.ts"))->toBe("export * from './status';");
    } finally {
        $filesystem->deleteDirectory($outputDir);
    }
});

test('writeModularPreserving keeps only the existing exports the predicate approves', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-preserve-barrel-'.uniqid();
    $barrelDir = "$outputDir/workbench/app/enums";
    $filesystem = new Filesystem;

    try {
        $filesystem->makeDirectory($barrelDir, recursive: true);
        $filesystem->put("$barrelDir/index.ts", "export * from './role';\nexport * from './ghost';");

        config()->set('ts-publish.output_to_files', true);
        config()->set('ts-publish.output_directory', $outputDir);

        $generators = collect([resolve(EnumGenerator::class, ['findable' => Status::class])]);

        $content = (new BarrelWriter($filesystem))->writeModularPreserving(
            $generators,
            fn (string $filename): bool => $filename === 'role',
        );

        expect($content['workbench/app/enums'])->toBe("export * from './role';\nexport * from './status';")
            ->and($filesystem->get("$barrelDir/index.ts"))->toBe("export * from './role';\nexport * from './status';");
    } finally {
        $filesystem->deleteDirectory($outputDir);
    }
});

test('writeModularPreserving carries over only export lines, whatever their quote style', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-formatted-barrel-'.uniqid();
    $barrelDir = "$outputDir/workbench/app/enums";
    $filesystem = new Filesystem;

    try {
        $filesystem->makeDirectory($barrelDir, recursive: true);
        // A hand-added header, a Prettier-formatted (double-quoted) export, and a trailing newline.
        $filesystem->put("$barrelDir/index.ts", "/**\n * Shared enums.\n */\nexport * from \"./role\";\n");

        config()->set('ts-publish.output_to_files', true);
        config()->set('ts-publish.output_directory', $outputDir);

        $generators = collect([resolve(EnumGenerator::class, ['findable' => Status::class])]);

        $content = (new BarrelWriter($filesystem))->writeModularPreserving($generators, fn (): bool => true);

        expect($content['workbench/app/enums'])->toBe("export * from './role';\nexport * from './status';");
    } finally {
        $filesystem->deleteDirectory($outputDir);
    }
});

test('writeModularPreserving deduplicates exports from CRLF barrels', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-crlf-barrel-'.uniqid();
    $barrelDir = "$outputDir/workbench/app/enums";
    $filesystem = new Filesystem;

    try {
        $filesystem->makeDirectory($barrelDir, recursive: true);
        $filesystem->put("$barrelDir/index.ts", "export * from './role';\r\nexport * from './status';\r\n");

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.output_directory', $outputDir);

        $generators = collect([resolve(EnumGenerator::class, ['findable' => Status::class])]);

        $content = (new BarrelWriter($filesystem))->writeModularPreserving($generators, fn (): bool => true);

        expect($content['workbench/app/enums'])->toBe("export * from './role';\nexport * from './status';");
    } finally {
        $filesystem->deleteDirectory($outputDir);
    }
});

test('writeModularPreserving reads the existing barrel even when file output is disabled', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-preview-preserve-barrel-'.uniqid();
    $barrelDir = "$outputDir/workbench/app/enums";
    $filesystem = new Filesystem;

    try {
        $filesystem->makeDirectory($barrelDir, recursive: true);
        $filesystem->put("$barrelDir/index.ts", "export * from './role';");

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.output_directory', $outputDir);

        $generators = collect([resolve(EnumGenerator::class, ['findable' => Status::class])]);

        $content = (new BarrelWriter($filesystem))->writeModularPreserving($generators, fn (): bool => true);

        // Preview must show what a real run would write.
        expect($content['workbench/app/enums'])->toBe("export * from './role';\nexport * from './status';")
            ->and($filesystem->get("$barrelDir/index.ts"))->toBe("export * from './role';");
    } finally {
        $filesystem->deleteDirectory($outputDir);
    }
});
