<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceArmsCastClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceArmsCastWhenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceArmsClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceArmsInlineClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceArmsInlineWhenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceArmsWhenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastBesideDirectResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastBesideExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastBesideTypeCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastBesideWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastExtendsBesideInlineSharedNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastImportConstNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastImportResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastInlineResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastMethodResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastMixedBesideWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastModelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastReadBesideSharedNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastSingleResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastSpellsEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastTernaryBesideResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastTernaryBesideSharedNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastTernaryBesideTypeCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastTernaryResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWhenNullColumnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWhenNullOtherEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWhenNullPairResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWhenNullSameEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWritesAliasedWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWritesOtherWrapInlineResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWritesSharedWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWritesWrapBesideDirectResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWritesWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWritesWrapsArrayResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceCastWritesWrapsResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Workbench\App\Http\Resources\NestedSignatureResource;
use Workbench\App\Http\Resources\OptionalSignatureCastResource;
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
        "import type { StatusType, VisibilityType } from '../../../../../../workbench/app/enums';",
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

// A cast that spells none of its key's enums leaves the key's enum resources nothing to wrap, so the key publishes as
// written. An enum is imported only for a key that still reads it. Each case reads the file's imports and its keys.
test('publishes a cast that spells none of its key\'s enums as written', function (string $resource, array $lines) {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.use_tolki_package', true);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer($resource));

    expect(array_values(preg_grep('/^(import |    [ak]: )/', explode("\n", $content)) ?: []))->toBe($lines);
})->with([
    'one enum resource' => [EnumResourceCastSingleResource::class, ['    k: string | null;']],
    'one enum resource, the cast on `toArray()`' => [EnumResourceCastMethodResource::class, ['    k: string | null;']],
    'one enum resource, the cast on a helper method that `toArray()` spreads' => [
        EnumResourceCastHelperResource::class,
        ['    k: string | null;'],
    ],
    'one enum resource, the cast on the model' => [EnumResourceCastModelResource::class, ['    k: string | null;']],
    'one enum resource, the cast with an import of its own' => [
        EnumResourceCastImportResource::class,
        ["import type { Label } from '@/types/label';", '    k: Label | null;'],
    ],
    'one enum resource, the cast imports a type named like the enum\'s const' => [
        EnumResourceCastImportConstNameResource::class,
        ["import type { Visibility } from '@/types/visibility';", '    k: Visibility | null;'],
    ],
    'a `whenNull()` over a column' => [EnumResourceCastWhenNullColumnResource::class, ['    k: string | null;']],
    'a `whenNull()` over a read of another enum' => [
        EnumResourceCastWhenNullOtherEnumResource::class,
        ['    k: string | null;'],
    ],
    'a `whenNull()` over a read of the same enum' => [
        EnumResourceCastWhenNullSameEnumResource::class,
        ['    k: string | null;'],
    ],
    'a `whenNull()` over an enum resource' => [EnumResourceCastWhenNullPairResource::class, ['    k: string | null;']],
    'a ternary over two enum resources' => [EnumResourceCastTernaryResource::class, ['    k: string | null;']],
    'a ternary over two enum resources, beside a wrap of one of them' => [
        EnumResourceCastTernaryBesideResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status } from '../../../../../../workbench/app/enums';",
            '    k: string | null;',
            '    a: AsEnum<typeof Status>;',
        ],
    ],
    'a ternary over two enum resources, beside a wrap of a second enum that shares the first one\'s name' => [
        EnumResourceCastTernaryBesideSharedNameResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status as CrmStatus } from '../../../../../../workbench/crm/enums';",
            '    k: string | null;',
            '    a: AsEnum<typeof CrmStatus> | null;',
        ],
    ],
    'an inline array that holds an enum resource' => [EnumResourceCastInlineResource::class, ['    k: string | null;']],
    'one enum resource, beside a direct read of its enum' => [
        EnumResourceCastBesideDirectResource::class,
        [
            "import type { StatusType } from '../../../../../../workbench/app/enums';",
            '    k: string | null;',
            '    a: StatusType;',
        ],
    ],
    'a ternary that wraps an enum in one arm and reads it in the other, beside a wrap of that enum' => [
        EnumResourceCastMixedBesideWrapResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status } from '../../../../../../workbench/app/enums';",
            '    k: string | null;',
            '    a: AsEnum<typeof Status>;',
        ],
    ],
]);

// A cast that writes `AsEnum<typeof Const>` holds no enum type name, yet the file needs `AsEnum` and that const. The
// cast publishes as written, and an enum it does not write is not imported. Each case reads the imports and the keys.
test('imports the enums a cast writes as wraps, and only those', function (string $resource, array $lines) {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.use_tolki_package', true);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer($resource));

    expect(array_values(preg_grep('/^(import |    [ak]: )/', explode("\n", $content)) ?: []))->toBe($lines);
})->with([
    'a ternary over two enum resources, the cast writes both wraps' => [
        EnumResourceCastWritesWrapsResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status, Visibility } from '../../../../../../workbench/app/enums';",
            '    k: AsEnum<typeof Status> | AsEnum<typeof Visibility> | null;',
        ],
    ],
    'one enum resource, the cast writes its wrap, beside a direct read of its enum' => [
        EnumResourceCastWritesWrapBesideDirectResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status } from '../../../../../../workbench/app/enums';",
            "import type { StatusType } from '../../../../../../workbench/app/enums';",
            '    k: AsEnum<typeof Status> | string;',
            '    a: StatusType;',
        ],
    ],
    'one enum resource, the cast writes its wrap' => [
        EnumResourceCastWritesWrapResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status } from '../../../../../../workbench/app/enums';",
            '    k: AsEnum<typeof Status> | string;',
        ],
    ],
    'a ternary over two enum resources, the cast writes both wraps in an array' => [
        EnumResourceCastWritesWrapsArrayResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status, Visibility } from '../../../../../../workbench/app/enums';",
            '    k: (AsEnum<typeof Status> | AsEnum<typeof Visibility>)[];',
        ],
    ],
    'an inline array that holds an enum resource, the cast writes another enum\'s wrap' => [
        EnumResourceCastWritesOtherWrapInlineResource::class,
        ['    k: { inner: AsEnum<typeof Status> };'],
    ],
    'two enums named Status, the cast writes the alias the file gives one const' => [
        EnumResourceCastWritesAliasedWrapResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status as WorkbenchStatus } from '../../../../../../workbench/app/enums';",
            "import { Status as CrmStatus } from '../../../../../../workbench/crm/enums';",
            '    k: AsEnum<typeof WorkbenchStatus> | null;',
            '    a: AsEnum<typeof CrmStatus> | null;',
        ],
    ],
    'two enums named Status, the cast writes the bare const they share' => [
        EnumResourceCastWritesSharedWrapResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status as WorkbenchStatus } from '../../../../../../workbench/app/enums';",
            "import { Status as CrmStatus } from '../../../../../../workbench/crm/enums';",
            '    k: AsEnum<typeof WorkbenchStatus> | null;',
            '    a: AsEnum<typeof CrmStatus> | null;',
        ],
    ],
]);

// A cast that holds the enum's type name publishes as written and imports that type, and a key beside a cast key
// keeps its wrap.
test('publishes a cast holding the enum\'s type as written, and wraps a key beside one', function (string $resource, array $lines) {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.use_tolki_package', true);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer($resource));

    expect(array_values(preg_grep('/^(import |    [ak]: )/', explode("\n", $content)) ?: []))->toBe($lines);
})->with([
    'a cast that spells the enum\'s type' => [
        EnumResourceCastSpellsEnumResource::class,
        [
            "import type { VisibilityType } from '../../../../../../workbench/app/enums';",
            '    k: VisibilityType | null;',
        ],
    ],
    'a wrap of the same enum beside the cast key' => [
        EnumResourceCastBesideWrapResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status } from '../../../../../../workbench/app/enums';",
            '    k: string | null;',
            '    a: AsEnum<typeof Status>;',
        ],
    ],
]);

// A cast that drops an enum resource must leave its type import be while something still needs it. The last two cases
// add a second enum of the same type name, which the prune cannot tell apart. Each case reads the imports, an extends
// clause and the keys.
test('keeps the type import a spelling or a bare read still needs', function (string $resource, array $lines) {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.use_tolki_package', true);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer($resource));

    expect(array_values(preg_grep('/^(import |export interface .* extends |    [a-z]+: )/', explode("\n", $content)) ?: []))->toBe($lines);
})->with([
    'an extends clause' => [
        EnumResourceCastBesideExtendsResource::class,
        [
            "import type { StatusType } from '../../../../../../workbench/app/enums';",
            'export interface EnumResourceCastBesideExtendsResource extends Partial<Record<StatusType, unknown>>',
            '    k: string | null;',
        ],
    ],
    'another key\'s cast' => [
        EnumResourceCastBesideTypeCastResource::class,
        [
            "import type { StatusType } from '../../../../../../workbench/app/enums';",
            '    k: string | null;',
            '    b: StatusType[];',
        ],
    ],
    'another key\'s cast, beside a ternary over two enum resources' => [
        EnumResourceCastTernaryBesideTypeCastResource::class,
        [
            "import type { StatusType, VisibilityType } from '../../../../../../workbench/app/enums';",
            '    k: string | null;',
            '    b: StatusType | VisibilityType;',
        ],
    ],
    'a bare read of the enum, beside a wrap of another enum that shares its type name' => [
        EnumResourceCastReadBesideSharedNameResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status as CrmStatus } from '../../../../../../workbench/crm/enums';",
            "import type { StatusType as WorkbenchStatusType } from '../../../../../../workbench/app/enums';",
            '    k: string | null;',
            '    a: WorkbenchStatusType | null;',
            '    c: AsEnum<typeof CrmStatus> | null;',
        ],
    ],
    'an extends clause, beside an inline wrap of another enum that shares its type name' => [
        EnumResourceCastExtendsBesideInlineSharedNameResource::class,
        [
            "import { type AsEnum } from '@tolki/ts';",
            "import { Status as CrmStatus } from '../../../../../../workbench/crm/enums';",
            "import type { StatusType } from '../../../../../../workbench/app/enums';",
            'export interface EnumResourceCastExtendsBesideInlineSharedNameResource extends Partial<Record<StatusType, unknown>>',
            '    k: string | null;',
            '    inline: { crm: AsEnum<typeof CrmStatus> | null };',
        ],
    ],
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

it('prints a nested index signature bare, with its backslash doubled once', function () {
    config()->set('ts-publish.output_to_files', false);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer(NestedSignatureResource::class));

    expect($content)
        ->toContain('    box: { [key: `${string}_tag`]: string | number | undefined; price_tag: number };')
        ->toContain('    units: { [key: `${string}\\\\unit`]: string | undefined };')
        ->not->toContain('"[key:');
});

// An optional signature cast is settled before any template sees it, so a template published unguarded still compiles.
it('keeps a resource template published before this release compiling', function () {
    $root = sys_get_temp_dir().'/ts-publish-published-'.uniqid();
    $views = $root.'/resources/views/vendor/laravel-ts-publish';
    mkdir($views, recursive: true);
    copy(__DIR__.'/../../views/resource-published-before-signature-guard.blade.php', $views.'/resource.blade.php');
    View::prependNamespace('laravel-ts-publish', $views);
    View::getFinder()->flush();
    config()->set('ts-publish.output_to_files', false);

    try {
        $template = View::getFinder()->find('laravel-ts-publish::resource');
        $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer(OptionalSignatureCastResource::class));
    } finally {
        File::deleteDirectory($root);
    }

    expect($template)->toBe($views.'/resource.blade.php')
        ->and($content)->toContain('    [key: `${string}_tag`]: string | undefined;')
        ->and($content)->toContain('    [key: `${string}_note`]: number | undefined;')
        ->and($content)->not->toContain(']?:');
});
