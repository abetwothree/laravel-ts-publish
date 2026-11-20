<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AliasBesideLiteralResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AliasBesideMemberKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AliasShadowedByTypeParameterResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltOnTwoNamesTwoPathsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltOnUnsentResourceTwoPathsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsAndIntersectTwoPathsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsAndIntersectTypesTwoPathsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsBesideCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsBesideMethodCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsBuiltOnTwoEnumsNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsDomGlobalResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsIntersectTwoPathsBuiltOnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsLibGlobalResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsNestedArmBesideCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsPackageNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsTwoPathsBuiltOnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsTypesBesideCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsWholeBesideCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsWholeBesideIntersectNestedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsWholeBesideMethodCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsWholeBesideNestedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsWholeLibNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ExtendsWholePackageNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectBuiltOnEnumNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectBuiltOnTwoEnumsNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectBuiltOnWrappedEnumNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectDomGlobalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedEmptyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedPackageNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedPackageNameTypesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedPostExtendsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectedTwiceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectLibGlobalResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectNestedArmBesideCastNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectNestedArmGlobalResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectOptionalMethodResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectTwoPathsBuiltOnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectTypeofValueGlobalResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\IntersectValueGlobalsResource;
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

    expect($transformer->intersections)->toBe(['PostPlacementVariants'])
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

    expect($transformer->intersections)->toBe(['FeedPlacement', 'PinnedPlacement'])
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

// A name a clause builds on keeps the import of the class the package publishes under it; one the clause is whole or
// lists in `types` stays the clause's, as before, so the package's class is aliased apart.
it('keeps the package\'s import of a class a clause builds on, and imports from the clause\'s path what it lists', function (string $resource, array $imports, string $declaration) {
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
    'an extends clause that is the name' => [ExtendsWholePackageNameResource::class, [
        "import type { UserResource } from '@/types/user';",
        "import type { UserResource as WorkbenchUserResource } from '../../../../../../workbench/app/http/resources';",
    ], <<<'TS'
        export interface ExtendsWholePackageNameResource extends UserResource
        {
            owner: WorkbenchUserResource;
        }

        TS],
]);

// A name a clause only builds on reads a cast's import of it, so the clause adds no second import from its own path.
it('imports no name a clause builds on that a cast already imports from another path', function (string $resource, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe(["import type { Meta } from '@/types/meta';"])
        ->and($content)->toContain($declaration);
})->with([
    'a class-level cast' => [ExtendsBesideCastNameResource::class, 'export interface ExtendsBesideCastNameResource extends Pick<Meta, "a">'],
    'a method-level cast' => [ExtendsBesideMethodCastNameResource::class, 'export interface ExtendsBesideMethodCastNameResource extends Partial<Meta>'],
]);

// A name inside a union inside a clause is no arm the clause lists, so a cast's import of it binds it there as well.
it('imports no name a clause builds on inside a nested union that a cast already imports from another path', function (string $resource, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe([
        "import type { Meta } from '@/types/meta';",
        "import type { Other } from '@/types/o';",
    ])->and($content)->toContain($declaration)->toContain('    m: Other;');
})->with([
    'an intersection' => [IntersectNestedArmBesideCastNameResource::class, '} & ({ at: Meta | Other | null });'],
    'an extends clause' => [
        ExtendsNestedArmBesideCastNameResource::class,
        'export interface ExtendsNestedArmBesideCastNameResource extends Record<string, Meta | Other | null>',
    ],
]);

// A name a clause is or lists stays the clause's beside a cast's import of it, aliased apart; the cast is as before.
it('aliases a name a clause is or lists that a cast already imports from another path', function (string $resource, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe([
        "import type { Meta } from '@/types/meta';",
        "import type { Meta as OtherMeta } from '@/types/other';",
    ])->and($content)->toContain($declaration)->toContain('    m: Meta;');
})->with([
    'a class-level cast' => [ExtendsWholeBesideCastNameResource::class, 'export interface ExtendsWholeBesideCastNameResource extends OtherMeta'],
    'a method-level cast' => [ExtendsWholeBesideMethodCastNameResource::class, 'export interface ExtendsWholeBesideMethodCastNameResource extends OtherMeta'],
    'a name listed in `types`' => [ExtendsTypesBesideCastNameResource::class, 'export interface ExtendsTypesBesideCastNameResource extends Pick<OtherMeta, "a">'],
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

// A name a clause only builds on aliases as one it is whole does, across both attributes, so no name imports twice.
it('aliases a name clauses build on or are from two paths', function (string $resource, array $imports, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe($imports)
        ->and($content)->toContain($declaration);
})->with([
    'two extends clauses' => [ExtendsTwoPathsBuiltOnResource::class, [
        "import type { Meta as AMeta } from '@/types/a';",
        "import type { Meta as BMeta } from '@/types/b';",
    ], 'export interface ExtendsTwoPathsBuiltOnResource extends Partial<AMeta>, Pick<BMeta, "a">'],
    'an extends clause and an intersection' => [ExtendsIntersectTwoPathsBuiltOnResource::class, [
        "import type { Meta as AMeta } from '@/types/a';",
        "import type { Meta as BMeta } from '@/types/b';",
    ], "export type ExtendsIntersectTwoPathsBuiltOnResource = Partial<AMeta> & {\n    id: number;\n} & (Pick<BMeta, \"a\">);"],
    'two intersections' => [IntersectTwoPathsBuiltOnResource::class, [
        "import type { Meta as AMeta } from '@/types/a';",
        "import type { Meta as BMeta } from '@/types/b';",
    ], '} & (Partial<AMeta>) & (Pick<BMeta, "a">);'],
    'two names each' => [BuiltOnTwoNamesTwoPathsResource::class, [
        "import type { Meta as AMeta, Paged as APaged } from '@/types/a';",
        "import type { Meta as BMeta, Paged as BPaged } from '@/types/b';",
    ], "export type BuiltOnTwoNamesTwoPathsResource = APaged<AMeta> & {\n    id: number;\n} & (BPaged<BMeta>);"],
    'a resource\'s name no key sends' => [BuiltOnUnsentResourceTwoPathsResource::class, [
        "import type { UserResource as AUserResource } from '@/types/a';",
        "import type { UserResource as BUserResource } from '@/types/b';",
    ], "= Pick<AUserResource, \"id\"> & {\n    id: number;\n} & (Partial<BUserResource>);"],
    'a name one extends clause is whole' => [ExtendsWholeBesideNestedResource::class, [
        "import type { Meta as MetaMeta } from '@/types/meta';",
        "import type { Meta as OtherMeta } from '@/types/other';",
    ], 'export interface ExtendsWholeBesideNestedResource extends MetaMeta, Pick<OtherMeta, "a">'],
    'a name one clause also spells as a string literal' => [AliasBesideLiteralResource::class, [
        "import type { Meta as AMeta } from '@/types/a';",
        "import type { Meta as BMeta } from '@/types/b';",
    ], 'export interface AliasBesideLiteralResource extends Pick<AMeta, "Meta">, Partial<BMeta>'],
    'a name one clause also declares as a type parameter' => [AliasShadowedByTypeParameterResource::class, [
        "import type { Meta as AMeta } from '@/types/a';",
        "import type { Meta as BMeta } from '@/types/b';",
    ], 'extends Record<string, { f: <AMeta>(x: AMeta) => AMeta; g: AMeta }>, BMeta'],
    'a name one clause also spells as a member\'s key' => [AliasBesideMemberKeyResource::class, [
        "import type { Meta as AMeta } from '@/types/a';",
        "import type { Meta as BMeta } from '@/types/b';",
    ], "= Partial<BMeta> & {\n    id: number;\n} & ({ Meta: AMeta });"],
    'a name the extends clause is whole beside an intersection' => [ExtendsWholeBesideIntersectNestedResource::class, [
        "import type { Meta as MetaMeta } from '@/types/meta';",
        "import type { Meta as OtherMeta } from '@/types/other';",
    ], "= MetaMeta & {\n    id: number;\n} & (Pick<OtherMeta, \"a\">);"],
]);

// An enum's const binds its name only where the file imports the const; otherwise the clause imports the name.
it('imports a name a clause builds on that an enum\'s const takes only where the file imports no such const', function (bool $tolki, string $resource, array $imports) {
    config()->set('ts-publish.enums.use_tolki_package', $tolki);

    expect(array_values(preg_grep('/^import /', explode("\n", intersectedResourceFile($resource))) ?: []))->toBe($imports);
})->with([
    'a key reading the enum\'s type' => [false, IntersectBuiltOnEnumNameResource::class, [
        "import type { Status } from '@/types/foo';",
        "import type { StatusType } from '../../../../../../workbench/app/enums';",
    ]],
    'a key wrapping the enum, whose const the file imports' => [true, IntersectBuiltOnWrappedEnumNameResource::class, [
        "import { type AsEnum } from '@tolki/ts';",
        "import { Status } from '../../../../../../workbench/app/enums';",
    ]],
]);

it('imports no global type TypeScript declares that a clause spells', function (string $resource, array $imports, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe($imports)
        ->and($content)->toContain($declaration);
})->with([
    'beside a name of its own path' => [IntersectLibGlobalResource::class, [
        "import type { Meta } from '@/types/meta';",
    ], '} & ({ meta: Meta; at: Date });'],
    'inside a generic' => [ExtendsLibGlobalResource::class, [], 'export interface ExtendsLibGlobalResource extends Record<string, Date>'],
    'the whole clause, which imports it as before' => [ExtendsWholeLibNameResource::class, [
        "import type { Date } from '@/types/date';",
    ], 'export interface ExtendsWholeLibNameResource extends Date'],
    'an arm of a union inside an object' => [IntersectNestedArmGlobalResource::class, [
        "import type { Meta } from '@/types/meta';",
    ], '} & ({ at: Meta | Date | null });'],
    'the DOM\'s, inside generics' => [ExtendsDomGlobalResource::class, [], 'extends Partial<Response>, Pick<Event, "type">'],
    'the DOM\'s, in an object and a generic' => [IntersectDomGlobalsResource::class, [], '} & ({ n: Node; d: Document; q: Request; e: HTMLElement })'],
    'a DOM value\'s typeof, beside a DOM type' => [IntersectTypeofValueGlobalResource::class, [], '} & ({ ctor: typeof Image; el: HTMLElement })'],
]);

// A name before `?(` or `?<T>(` in an object is an optional method's own, like one before `(`, never a type.
it('imports no optional method\'s name a clause declares', function () {
    $content = intersectedResourceFile(IntersectOptionalMethodResource::class);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe([
        "import type { Meta } from '@/types/meta';",
    ])->and($content)->toContain('} & ({ load?(): Meta; each?<T>(value: T): Meta });');
});

// DOM's `Image` is a value with no type, so `{ image: Image }` names the clause's own type and imports it.
it('imports a name a clause spells as a type that the libs declare only as a value', function () {
    $content = intersectedResourceFile(IntersectValueGlobalsResource::class);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe([
        "import type { Audio, Image, Option } from '@/types/media';",
    ])->and($content)->toContain('} & ({ image: Image; audio: Audio; option: Option });');
});

// Two enums the file reads print one type name, both aliased, so the name binds neither and the clause imports it.
it('imports a name a clause builds on that two enums the file reads both print', function (string $resource, string $declaration) {
    $content = intersectedResourceFile($resource);

    expect(array_values(preg_grep('/^import /', explode("\n", $content)) ?: []))->toBe([
        "import type { StatusType } from '@/types/s';",
        "import type { StatusType as WorkbenchStatusType } from '../../../../../../workbench/app/enums';",
        "import type { StatusType as CrmStatusType } from '../../../../../../workbench/crm/enums';",
    ])->and($content)->toContain($declaration);
})->with([
    'an intersection' => [IntersectBuiltOnTwoEnumsNameResource::class, '} & ({ s: StatusType });'],
    'an extends clause' => [ExtendsBuiltOnTwoEnumsNameResource::class, 'extends Partial<Record<StatusType, string>>'],
]);
