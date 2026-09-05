<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelMetadataWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Models\User;

test('renders model metadata', function () {
    config()->set('ts-publish.output_to_files', false);

    $writer = new ModelMetadataWriter(new Filesystem);
    $transformer = new ModelMetadataTransformer(User::class);

    expect(rtrim($writer->write($transformer)))
        ->toBe(<<<'TYPESCRIPT'
export const UserModelMetadata = {
    morphClass: 'Workbench\\App\\Models\\User',
} as const satisfies {
    morphClass: string;
};
TYPESCRIPT);
});

test('renders custom metadata types and imports', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.provider_class', CustomModelMetadataProvider::class);

    $writer = new ModelMetadataWriter(new Filesystem);
    $transformer = new ModelMetadataTransformer(User::class);

    expect(rtrim($writer->write($transformer)))
        ->toBe(<<<'TYPESCRIPT'
import type { ModelMetadataDetails } from '@/types/model-metadata';

export const UserModelMetadata = {
    table: 'users',
    details: {exists: false},
} as const satisfies {
    table: string;
    details: ModelMetadataDetails;
};
TYPESCRIPT);
});

test('writes metadata beside its model file', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(fn (string $path, string $content) => str_ends_with($path, '/user_meta.ts')
            && str_contains($content, 'export const UserModelMetadata'));

    config()->set('ts-publish.output_to_files', true);

    $writer = new ModelMetadataWriter($filesystem);
    $writer->write(new ModelMetadataTransformer(User::class));
});

test('does not write metadata when file output is disabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('exists');
    $filesystem->shouldNotReceive('put');

    config()->set('ts-publish.output_to_files', false);

    $writer = new ModelMetadataWriter($filesystem);
    $writer->write(new ModelMetadataTransformer(User::class));
});
