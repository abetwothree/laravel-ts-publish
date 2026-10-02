<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EnumResourceArmsCastClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EnumResourceArmsCastWhenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EnumResourceArmsClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EnumResourceArmsInlineClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EnumResourceArmsInlineWhenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EnumResourceArmsWhenResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PostStateResource;
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
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
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
    $filesystem->shouldNotReceive('exists');
    $filesystem->shouldNotReceive('put');

    $writer = new ResourceWriter($filesystem);
    $transformer = new ResourceTransformer(PostResource::class);

    config()->set('ts-publish.output_to_files', false);

    $writer->write($transformer);
});

test('writes to namespace-based directory', function () {
    $transformer = new ResourceTransformer(PostResource::class);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(fn (string $path) => str_contains($path, $transformer->namespacePath));

    $writer = new ResourceWriter($filesystem);

    config()->set('ts-publish.output_to_files', true);

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

// The resource's only enum resources sit in a union of two, so the union alone must bring `AsEnum` and the consts in.
test('imports AsEnum and each enum const for a resource whose only enum resources sit in a union of several', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.use_tolki_package', true);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer(PostStateResource::class));

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe([
        "import { type AsEnum } from '@tolki/ts';",
        "import { Status, Visibility } from '../../enums';",
    ]);
});

// Each union below holds a `null` beside its two enum resources, so it is not read as a union of enum resources, which
// an inline member and a cast key would not honour. Each case reads the file's import lines and its one key.
test('publishes a conditional over two enum resources and a null as the enums\' own types, imported', function (string $resource, string $key) {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.use_tolki_package', true);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer($resource));

    expect(array_values(preg_grep('/^(import |    state\??: )/', explode("\n", $content)) ?: []))->toBe([
        "import type { StatusType, VisibilityType } from '../../../../workbench/app/enums';",
        $key,
    ]);
})->with([
    'a `when()` and its default' => [EnumResourceArmsWhenResource::class, '    state: StatusType | VisibilityType | null;'],
    'a closure' => [EnumResourceArmsClosureResource::class, '    state?: StatusType | VisibilityType | null;'],
    'a `when()` and its default, in an inline array' => [
        EnumResourceArmsInlineWhenResource::class,
        '    state: { inner: StatusType | VisibilityType | null };',
    ],
    'a closure, in an inline array' => [
        EnumResourceArmsInlineClosureResource::class,
        '    state: { inner?: StatusType | VisibilityType | null };',
    ],
]);

test('leaves a `#[TsCasts]` type over such a conditional as written, with no import', function (string $resource, string $key) {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.use_tolki_package', true);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer($resource));

    expect(array_values(preg_grep('/^(import |    state\??: )/', explode("\n", $content)) ?: []))->toBe([$key]);
})->with([
    'a `when()` and its default' => [EnumResourceArmsCastWhenResource::class, '    state: string | null;'],
    'a closure' => [EnumResourceArmsCastClosureResource::class, '    state?: string | null;'],
]);

test('resource without TsExtends renders plain interface', function () {
    $writer = new ResourceWriter(new Filesystem);
    $transformer = new ResourceTransformer(PostResource::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface PostResource')
        ->not->toContain('extends');
});
