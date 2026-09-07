<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AstEmptyValuesModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AstUnimportableModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EmptyValuesModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TupleShapeMetadataProvider;
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

test('renders a body-inferred enum import identically to a TsCasts one', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.provider_class', AstUnimportableModelMetadataProvider::class);

    expect(rtrim((new ModelMetadataWriter(new Filesystem))->write(new ModelMetadataTransformer(User::class))))
        ->toBe(<<<'TYPESCRIPT'
import type { RoleType } from '../enums';

export const UserModelMetadata = {
    role: 'Admin',
} as const satisfies {
    role: RoleType;
};
TYPESCRIPT);
});

test('renders empty containers in the spelling their types require', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.provider_class', EmptyValuesModelMetadataProvider::class);

    $content = (new ModelMetadataWriter(new Filesystem))->write(new ModelMetadataTransformer(User::class));

    expect($content)
        ->toContain('    flags: {},')
        ->toContain('    tags: [],')
        ->toContain('    nested: {items: {}, ids: []},')
        ->toContain('    rows: [{}, {}],')
        ->toContain('    opaque: [],')
        ->toContain('    explicit: {},')
        ->toContain('    maybe: [],')
        ->toContain("import type { OpaqueShape } from '@/types/opaque-shape';");
});

test('renders body-inferred empty containers', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.provider_class', AstEmptyValuesModelMetadataProvider::class);

    expect((new ModelMetadataWriter(new Filesystem))->write(new ModelMetadataTransformer(User::class)))
        ->toContain('    empty: [],')
        ->toContain('    nested: {items: []},')
        ->toContain('    flags: {},');
});

test('renders a list whose type declares numeric object keys', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.provider_class', TupleShapeMetadataProvider::class);

    expect((new ModelMetadataWriter(new Filesystem))->write(new ModelMetadataTransformer(User::class)))
        ->toContain('    tuple: [{}, []],');
});
