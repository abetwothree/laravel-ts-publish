<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsAndIntersectTwoPathsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsAndIntersectTypesTwoPathsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsBesideCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsBesideMethodCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsPackageNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedEmptyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedPackageNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedPackageNameTypesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedPostExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedTwiceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PickBaseExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPostIntersectResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedWrapsIntersectResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Discriminated\IntersectedPostResource;

/**
 * The file a resource publishes.
 *
 * @param  class-string<JsonResource>  $class
 */
function intersectedResourceFile(string $class): string
{
    config()->set('ts-publish.output_to_files', false);

    return new ResourceWriter(new Filesystem)->write(new ResourceTransformer($class));
}

it('publishes a resource with an intersection as a type of its keys and the type it is intersected with', function () {
    expect(intersectedResourceFile(IntersectedPostResource::class))->toBe(<<<'TS'
        import type { PostPlacementVariants } from '@/types/placement';
        import type { FeedPlacementResource, PinnedPlacementResource } from '.';

        /**
         * GH-157's escape hatch: the keys publish flat, and a union written by hand is intersected with them, so `is_pinned`
         * still narrows `placement`.
         *
         * @see Workbench\App\Http\Resources\Discriminated\IntersectedPostResource
         */
        export type IntersectedPostResource = {
            id: number;
            is_pinned: boolean;
            placement: PinnedPlacementResource | FeedPlacementResource;
        } & (PostPlacementVariants);

        TS);
});

it('keeps a transformed resource\'s intersections beside its extends clauses, and hands them to its data', function () {
    $transformer = new ResourceTransformer(IntersectedPostResource::class);

    expect($transformer->tsIntersect)->toBe(['PostPlacementVariants'])
        ->and($transformer->tsExtends)->toBe([])
        ->and($transformer->data()->intersections)->toBe(['PostPlacementVariants'])
        ->and($transformer->data()->toArray()['intersections'])->toBe(['PostPlacementVariants']);
});

it('publishes an intersection after the extends clause, the keys and each group of arms', function (string $resource, string $declaration) {
    expect(intersectedResourceFile($resource))->toEndWith($declaration);
})->with([
    'an extends clause, and an intersection the parent declares' => [IntersectedPostExtendsResource::class, <<<'TS'
        export type IntersectedPostExtendsResource = HasTimestamps & {
            id: number;
            is_pinned: boolean;
            placement: PinnedPlacementResource | FeedPlacementResource;
        } & (PostPlacementVariants);

        TS],
    'a group of arms' => [PinnedPostIntersectResource::class, <<<'TS'
        export type PinnedPostIntersectResource = {
            id: number;
        } & (
            | { is_pinned: true; placement: PinnedPlacementResource }
            | { is_pinned: false; placement: FeedPlacementResource }
        ) & (PostPlacementVariants);

        TS],
    'a group of arms and no shared key' => [PinnedWrapsIntersectResource::class, <<<'TS'
        export type PinnedWrapsIntersectResource = (
            | { is_pinned: true; state: AsEnum<typeof WorkbenchStatus>; stage: WorkbenchStatusType; author: UserResource }
            | { is_pinned: false; state: AsEnum<typeof Visibility> | null; stage: CrmStatusType; author?: never }
        ) & (PostPlacementVariants);

        TS],
    'no key at all' => [IntersectedEmptyResource::class, <<<'TS'
        export type IntersectedEmptyResource = (PostPlacementVariants);

        TS],
]);

it('imports each intersection\'s names beside the extends clause\'s', function () {
    expect(intersectedResourceFile(IntersectedPostExtendsResource::class))
        ->toContain("import type { HasTimestamps } from '@/types/common';")
        ->toContain("import type { PostPlacementVariants } from '@/types/placement';");
});

it('publishes an intersection once, and aliases a name two paths bring as an extends clause does', function () {
    $transformer = new ResourceTransformer(IntersectedTwiceResource::class);

    expect($transformer->tsIntersect)->toBe(['FeedPlacement', 'PinnedPlacement'])
        ->and($transformer->typeImports)->toMatchArray([
            '@/types/feed' => ['Placement as FeedPlacement'],
            '@/types/pinned' => ['Placement as PinnedPlacement'],
        ])
        ->and(intersectedResourceFile(IntersectedTwiceResource::class))
        ->toContain("    placement: PinnedPlacementResource | FeedPlacementResource;\n} & (FeedPlacement) & (PinnedPlacement);\n");
});

// #104: a generic clause with an import and no `types` imports the name it spells, where it once imported nothing.
it('imports the name a generic extends clause with a union inside spells', function () {
    $transformer = new ResourceTransformer(PickBaseExtendsResource::class);

    expect($transformer->tsExtends)->toBe(['Pick<Base, "a" | "b">'])
        ->and($transformer->typeImports)->toBe(['@/types/base' => ['Base']])
        ->and(intersectedResourceFile(PickBaseExtendsResource::class))
        ->toContain("import type { Base } from '@/types/base';")
        ->toContain('export interface PickBaseExtendsResource extends Pick<Base, "a" | "b">');
});

// A name a clause builds on keeps the import of the class the package publishes under it; one `types` lists stays the
// clause's, so the package's class is aliased apart.
it('keeps the package\'s import of a class a clause builds on, and imports from the clause\'s path only what it lists', function (string $resource, array $imports, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe($imports)
        ->and($content)->toEndWith($declaration);
})->with([
    'an intersection with no `types`' => [IntersectedPackageNameResource::class, [
        "import type { UserResource } from '../../../../../../workbench/app/http/resources';",
    ], <<<'TS'
        export type IntersectedPackageNameResource = {
            owner: UserResource;
        } & (Pick<UserResource, "id">);

        TS],
    'an extends clause with no `types`' => [ExtendsPackageNameResource::class, [
        "import type { UserResource } from '../../../../../../workbench/app/http/resources';",
    ], <<<'TS'
        export interface ExtendsPackageNameResource extends Pick<UserResource, "id">
        {
            owner: UserResource;
        }

        TS],
    'an intersection that lists the name in its `types`' => [IntersectedPackageNameTypesResource::class, [
        "import type { UserResource } from '@/types/user';",
        "import type { UserResource as WorkbenchUserResource } from '../../../../../../workbench/app/http/resources';",
    ], <<<'TS'
        export type IntersectedPackageNameTypesResource = {
            owner: WorkbenchUserResource;
        } & (Pick<UserResource, "id">);

        TS],
]);

// A name a clause only builds on comes from its path only where no other import binds it, as a cast's import does.
it('imports no name a clause only builds on that a cast already imports from another path', function (string $resource, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe(["import type { Meta } from '@/types/meta';"])
        ->and($content)->toContain($declaration);
})->with([
    'a class-level cast' => [ExtendsBesideCastNameResource::class, 'export interface ExtendsBesideCastNameResource extends Pick<Meta, "a">'],
    'a method-level cast' => [ExtendsBesideMethodCastNameResource::class, 'export interface ExtendsBesideMethodCastNameResource extends Partial<Meta>'],
]);

// One pass reads both attributes, so a name each imports from its own path is aliased apart, as two clauses are.
it('aliases a name an extends clause and an intersection import from two paths', function (string $resource, array $imports, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe($imports)
        ->and($content)->toEndWith($declaration);
})->with([
    'names read from each' => [ExtendsAndIntersectTwoPathsResource::class, [
        "import type { Base as ABase } from '@/a';",
        "import type { Base as BBase } from '@/b';",
    ], <<<'TS'
        export type ExtendsAndIntersectTwoPathsResource = ABase & {
            id: number;
        } & (BBase);

        TS],
    'names each lists in its `types`' => [ExtendsAndIntersectTypesTwoPathsResource::class, [
        "import type { Base as ABase } from '@/a';",
        "import type { Base as BBase } from '@/b';",
    ], <<<'TS'
        export type ExtendsAndIntersectTypesTwoPathsResource = ABase & {
            id: number;
        } & (Partial<BBase>);

        TS],
]);
