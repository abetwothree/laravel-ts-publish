<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedEmptyResource;
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
