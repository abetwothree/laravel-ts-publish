<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\EnumWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Enums\PaymentMethod;
use Workbench\App\Enums\Status;

test('writes enum content from transformer', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export const Status')
        ->toContain('Draft')
        ->toContain('Published')
        ->toContain('export type StatusType');
});

test('writes enum content with metadata enabled', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enum_metadata_enabled', true);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('_cases:')
        ->toContain('_methods:')
        ->toContain('_static:');
});

test('writes enum content with metadata disabled', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enum_metadata_enabled', false);

    $content = $writer->write($transformer);

    expect($content)
        ->not->toContain('_cases:')
        ->not->toContain('_methods:')
        ->not->toContain('_static:');
});

test('writes enum file to disk when output_to_files is enabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'status.ts') && str_contains($content, 'export const Status');
        });

    $writer = new EnumWriter($filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', true);

    $writer->write($transformer);
});

test('does not write enum file to disk when output_to_files is disabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('ensureDirectoryExists');
    $filesystem->shouldNotReceive('put');

    $writer = new EnumWriter($filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);

    $writer->write($transformer);
});

test('writes backed enum Kind type', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)->toContain('export type StatusKind');
});

test('omits _methods and _static when enum has no methods or static methods', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(PaymentMethod::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enum_metadata_enabled', true);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('_cases:')
        ->not->toContain('_methods:')
        ->not->toContain('_static:');
});

test('includes _methods when enum has instance methods', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enum_metadata_enabled', true);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('_cases:')
        ->toContain('_methods:')
        ->toContain('_static:');
});
