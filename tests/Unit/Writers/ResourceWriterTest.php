<?php

use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\WarehouseResource;

test('writes resource content from transformer', function () {
    $writer = new ResourceWriter(new Filesystem);
    $transformer = new ResourceTransformer(PostResource::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface PostResource')
        ->toContain('id: number')
        ->toContain('status: AsEnum<typeof Status>');
});

test('writes resource file to disk when output_to_files is enabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'post-resource.ts') && str_contains($content, 'export interface PostResource');
        });

    $writer = new ResourceWriter($filesystem);
    $transformer = new ResourceTransformer(PostResource::class);

    config()->set('ts-publish.output_to_files', true);

    $writer->write($transformer);
});

test('does not write resource file when output_to_files is disabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('ensureDirectoryExists');
    $filesystem->shouldNotReceive('put');

    $writer = new ResourceWriter($filesystem);
    $transformer = new ResourceTransformer(PostResource::class);

    config()->set('ts-publish.output_to_files', false);

    $writer->write($transformer);
});

test('writes to resources subdirectory in flat mode', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once()
        ->withArgs(fn (string $path) => str_ends_with($path, '/resources'));
    $filesystem->shouldReceive('put')->once();

    $writer = new ResourceWriter($filesystem);
    $transformer = new ResourceTransformer(PostResource::class);

    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.modular_publishing', false);

    $writer->write($transformer);
});

test('writes to namespace-based directory in modular mode', function () {
    $transformer = new ResourceTransformer(PostResource::class);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once()
        ->withArgs(fn (string $path) => str_contains($path, $transformer->namespacePath));
    $filesystem->shouldReceive('put')->once();

    $writer = new ResourceWriter($filesystem);

    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.modular_publishing', true);

    $writer->write($transformer);
});

test('renders extends clause from TsExtends attribute', function () {
    $writer = new ResourceWriter(new Filesystem);
    $transformer = new ResourceTransformer(WarehouseResource::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface WarehouseResource extends BaseResource')
        ->toContain("import type { BaseResource } from '@/types/base'");
});

test('resource without TsExtends renders plain interface', function () {
    $writer = new ResourceWriter(new Filesystem);
    $transformer = new ResourceTransformer(PostResource::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface PostResource')
        ->not->toContain('extends');
});
