<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ConstantKeyFixture;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ConstantKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilteringAccessorModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\InheritedFilterRelease;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RecursiveVagueFixture;
use Workbench\App\Http\Resources\ServiceReturnResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Release;
use Workbench\App\Services\PostStatsService;

test('a vague array signature falls back to the literal body', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ServiceReturnResource::class), Post::class)->analyze()->properties)->keyBy('name');

    expect($props['quote']['type'])->toBe('{ unit: string; minimum: number; discounted: { unit: string } }')
        ->and($props['tiers']['type'])->toBe('{ "1": string; "2": string }');
});

test('a self-recursive vague method declines instead of looping', function () {
    // The body contributes nothing either way, so both keep the vague reflected type rather than a
    // fabricated shape. The keyed cycle is the one that re-enters, and it terminates.
    expect(resolve(MethodReturnTypeResolver::class)->resolve(RecursiveVagueFixture::class, 'again'))
        ->toBe(['type' => 'unknown[]', 'optional' => false])
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(RecursiveVagueFixture::class, 'againKeyed'))
        ->toBe(['type' => '{ again: unknown[] }', 'optional' => false]);
});

test('a constant key resolves through parent, self and a foreign class', function () {
    // `parent::PARENT_TIER` is 7 and survives as a quoted key, because the subject is not a resource.
    expect(resolve(MethodReturnTypeResolver::class)->resolve(ConstantKeyFixture::class, 'shape'))
        ->toBe(['type' => '{ "7": string; label: string; external: string }', 'optional' => false]);
});

test('a numeric constant key is dropped when the analyzed subject is a resource', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(ConstantKeyResource::class, 'shape'))
        ->toBe(['type' => '{ external: string }', 'optional' => false]);
});

test('a precise declaration is kept, and an absent method declines', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(PostStatsService::class, 'summary'))
        ->toBe(['type' => '{ views: number; likes: number }', 'optional' => false])
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(PostStatsService::class, 'missingMethod'))
        ->toBeNull();
});

// The flattened body keeps no FQCN channel, so its filters publish no token; an analysis whose caller keeps the
// channels is cached apart and keeps the Pick<>.
test('a body fallback analyzes its method without imports, apart from an analysis that keeps them', function () {
    $withImports = resolve(AstEngine::class)->analyzeMethod(Release::class, 'columnSummary', Release::class);
    $withoutImports = resolve(AstEngine::class)->analyzeMethod(Release::class, 'columnSummary', Release::class, carriesImports: false);

    expect(collect($withImports->properties)->keyBy('name')['named']['type'])->toBe("Pick<Release, 'major' | 'minor'>")
        ->and(collect($withoutImports->properties)->keyBy('name')['named']['type'])->toBe('{ major: number; minor: number }');
});

test('a body the model inherits is analyzed without imports too', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(InheritedFilterRelease::class, 'columnSummary')['type'] ?? null)
        ->toBe('{ named: { major: number; minor: number }; rest: { id: number; major: number; minor: number; '
            .'created_at: string | null; updated_at: string | null }; picked: Record<string, unknown>; left: Record<string, unknown> }');
});

// The getter a method body reads is analyzed without imports too, so its filters publish the answer the same call
// written in the method body would, and the method keeps its shape.
test('a method body reading an accessor whose getter filters keeps its shape', function (string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, $method)['type'] ?? null)
        ->toBe($expected);
})->with([
    'bare $this, literal keys' => ['readOwnPicks', '{ v: { v: { id: number; title: string }; id: number }; id: number }'],
    'bare $this, runtime keys' => ['readOwnRuntime', '{ v: { v: Record<string, unknown>; id: number }; id: number }'],
    'runtime keys returned whole, which the model publishes as unknown' => ['readRuntimeFields', '{ v: unknown; id: number }'],
    'a vague getter its @property-read tag types' => ['readTaggedFields', '{ v: { id: number }[]; id: number }'],
    'bare $this through ?->' => ['readOwnNullsafe', '{ v: { v: { id: number; content: string }; id: number }; id: number }'],
    'a single relation' => ['readAuthorPicks', '{ v: { v: { id: number; role: unknown } | null; id: number }; id: number }'],
    'a to-many relation' => ['readCommentPicks', '{ v: { v: unknown[]; id: number }; id: number }'],
    'a to-many relation returned whole, beside a @property-read tag naming the model' => ['readCommentList', '{ v: unknown[]; id: number }'],
    'a relation to an untyped override' => ['readLoosePicks', '{ v: { v: { id: number; title: string }; id: number }; id: number }'],
    'a multi-model accessor' => ['readCounterpartPicks', '{ v: { v: { id: number } | { id: number } | null; id: number }; id: number }'],
    'an old-style accessor' => ['readLegacyPicks', '{ v: { v: { id: number; name: string }; id: number }; id: number }'],
    'a camelCase alias' => ['readCamelAlias', '{ v: { v: { id: number; role: unknown } | null; id: number }; id: number }'],
    'through a relation' => ['readThroughRelation', '{ v: { v: { id: number; role: unknown } | null; id: number } | null; id: number }'],
    'through a local variable' => ['readThroughLocal', '{ v: { v: { id: number; role: unknown } | null; id: number }; id: number }'],
    'through a closure parameter' => ['readThroughVariable', '{ v: unknown[][]; id: number }'],
    'as a filtered key' => ['readAsFilteredKey', '{ v: { id: number; author_picks: { v: { id: number; role: unknown } | null; id: number } }; id: number }'],
    'as a spread key' => ['readAsSpreadKey', '{ id: number; author_picks: { v: { id: number; role: unknown } | null; id: number }; x: number }'],
    'as a spread camelCase key' => ['readAsSpreadAlias', '{ id: number; authorPicks: { v: { id: number; role: unknown } | null; id: number }; x: number }'],
    'a literal getter' => ['readLiteral', '{ v: { a: number; b: string }; id: number }'],
    'a docblock-typed getter still drops the shape' => ['readOwner', 'unknown[]'],
]);

// Without imports a to-many filter spells `unknown[]`. As when the body itself is vague, a fallback annotation or tag
// types the reader unless it is `unknown` or names a class, which would cost the reader its shape.
test('a vague spelling without imports gives way to a fallback that is not unknown and names no class', function (string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, $method)['type'] ?? null)
        ->toBe($expected);
})->with([
    'a list docblock' => ['readDocRecords', '{ v: Record<string, unknown>[]; id: number }'],
    'a nullable list docblock through ?->' => ['readDocRecordsNullsafe', '{ v: Record<string, unknown>[] | null; id: number }'],
    'a list docblock over runtime keys' => ['readDocRecordsRuntime', '{ v: Record<string, unknown>[]; id: number }'],
    'a nested list docblock over a nested vague spelling' => ['readDocNestedRecords', '{ v: Record<string, unknown>[][]; id: number }'],
    // The value is a list, but the reader trusts the annotation's keyed object, as it did before the getter body typed
    // the accessor: the waterfall never checks an annotation against the body.
    'a keyed docblock' => ['readDocKeyed', '{ v: Record<string, unknown>; id: number }'],
    'a docblock with an unknown arm' => ['readDocIntMixed', '{ v: number | unknown; id: number }'],
    'a keyed-or-list docblock' => ['readDocRecordOrList', '{ v: Record<string, unknown> | unknown[]; id: number }'],
    'a docblock naming a class' => ['readDocClassList', '{ v: unknown[]; id: number }'],
    'no annotation' => ['readCommentList', '{ v: unknown[]; id: number }'],
    'a token-free tag over a vague signature' => ['readSignedTagRows', '{ v: { id: number }[]; id: number }'],
    'a token-free tag over an old-style getter' => ['readLegacyTagRows', '{ v: { id: number; content: string }[]; id: number }'],
]);

test('a spread model reads its appended filtering accessor without imports', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'readAsSpreadAppend')['type'] ?? null)
        ->toContain('; author_picks: { v: { id: number; role: unknown } | null; id: number }; x: number }');
});
