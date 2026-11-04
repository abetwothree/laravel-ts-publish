<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\CastHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumCaseHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers\PostHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NamesakeEdgeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NamesakeInvoiceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TitleLabelCategoryResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TitleLabelPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\EnumCaseHelperService;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\KeyStampingChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\UnreadBasePayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassNamingMemberModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ConstantKeyFixture;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ConstantKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilteringAccessorModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\InheritedFilterRelease;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostMetaService;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RecursiveVagueFixture;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Workbench\App\Http\Resources\DecoratedPayloadResource;
use Workbench\App\Http\Resources\ServiceReturnResource;
use Workbench\App\Models\Category;
use Workbench\App\Models\Post;
use Workbench\App\Models\Release;
use Workbench\App\Services\PayloadDecoratorService;
use Workbench\App\Services\PostStatsService;
use Workbench\App\ValueObjects\GuardedLinkPayload;

test('a vague array signature falls back to the literal body', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ServiceReturnResource::class), Post::class)->analyze()->properties)->keyBy('name');

    // The analysis keeps the keys the body writes; the resource's publisher re-indexes them into a list.
    expect($props['quote']['type'])->toBe('{ unit: string; minimum: number; discounted: { unit: string } }')
        ->and($props['tiers']['type'])->toBe('{ "1": string; "2": string }');
});

test('a self-recursive vague method declines instead of looping', function () {
    // The body contributes nothing either way, so both keep the vague reflected type rather than a
    // fabricated shape. The keyed cycle is the one that re-enters, and it terminates.
    expect(resolve(MethodReturnTypeResolver::class)->resolve(RecursiveVagueFixture::class, 'again'))
        ->toBe(['type' => 'unknown[]', 'optional' => false])
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(RecursiveVagueFixture::class, 'againKeyed'))
        ->toBe(['type' => '{ again: unknown[] }', 'optional' => false, 'keepsKeys' => true]);
});

test('a constant key resolves through parent, self and a foreign class', function () {
    // `parent::PARENT_TIER` is 7, kept as the quoted key JSON writes.
    expect(resolve(MethodReturnTypeResolver::class)->resolve(ConstantKeyFixture::class, 'shape'))
        ->toBe(['type' => '{ "7": string; label: string; external: string }', 'optional' => false, 'keepsKeys' => true]);
});

test('a numeric constant key is kept when the analyzed subject is a resource', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(ConstantKeyResource::class, 'shape'))
        ->toBe(['type' => '{ "8": string; external: string }', 'optional' => false, 'keepsKeys' => true]);
});

// The body's shape stands in for the declaration's array arm only: every other arm it names stays, and a return the
// shape does not describe leaves the method to the value read, which types each return as what it holds.
test('a body fallback keeps the declaration\'s other arms, or reads each return as a value where the shape cannot', function (string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(PostMetaService::class, $method)['type'] ?? null)
        ->toBe($expected);
})->with([
    'a nullable array returning null early' => ['nullableMeta', '{ id: number } | null'],
    'a nullable array never returning null' => ['declaredNullableMeta', '{ id: number } | null'],
    'an array or false' => ['metaOrFalse', '{ id: number } | false'],
    'an array or a string' => ['metaOrLabel', '{ id: number } | string'],
    'no declaration, with a string return' => ['untypedMeta', 'string | { id: number }'],
    'no declaration, with a bare return' => ['bareReturnMeta', '{ id: number } | null'],
    'a mixed declaration, with a false return' => ['mixedMeta', 'boolean | { id: number }'],
    'an array, with a return of another call' => ['delegatingMeta', 'number[] | { id: number }'],
    'an array, with literals inside a try' => ['guardedMeta', '{ id: number } | { code: string }'],
]);

test('an application method whose declaration is vague publishes what its returns hold', function (string $class, string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve($class, $method)['type'] ?? null)->toBe($expected);
})->with([
    'a mixed declaration over a number its model namesake formats' => [NamesakeInvoiceResource::class, 'total', 'number'],
    'a mixed declaration over a string or a number' => [NamesakeInvoiceResource::class, 'branchy', 'string | number'],
    'a vague array delegating to the model\'s shape' => [NamesakeInvoiceResource::class, 'summary', '{ a: number }'],
    'a short ternary' => [NamesakeEdgeResource::class, 'conditional', 'string'],
    'an untyped body that can end without a value' => [NamesakeEdgeResource::class, 'fallsOff', 'string | null'],
    'a sum over another untyped helper' => [NamesakeEdgeResource::class, 'outer', 'number'],
    'a callable string' => [NamesakeEdgeResource::class, 'callableString', 'string'],
    'a local holding a string' => [NamesakeEdgeResource::class, 'titleViaLocal', 'string'],
    'a local holding a pipeline whose callback is an argument' => [NamesakeEdgeResource::class, 'idsViaLocal', 'number[]'],
]);

// A declined read leaves the declaration's own answer, or none at all, for the next step to take.
test('the value read declines a returned closure, a generator, a vendor method and a return nothing types', function (string $class, string $method, ?string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve($class, $method)['type'] ?? null)->toBe($expected);
})->with([
    'a returned closure' => [NamesakeEdgeResource::class, 'formatter', null],
    'a returned arrow function' => [NamesakeEdgeResource::class, 'arrow', null],
    'a closure held in a local' => [NamesakeEdgeResource::class, 'formatterViaLocal', null],
    'a closure copied between locals' => [NamesakeEdgeResource::class, 'copiedFormatter', null],
    'a closure each branch of an if assigns a local' => [NamesakeEdgeResource::class, 'formatterViaBranches', null],
    'a first-class callable held in a local' => [NamesakeEdgeResource::class, 'callableViaLocal', null],
    'a closure as a ternary arm' => [NamesakeEdgeResource::class, 'formatterOrTitle', null],
    'a closure as a short ternary\'s fallback' => [NamesakeEdgeResource::class, 'titleOrFormatter', null],
    'a closure as a short ternary\'s checked value' => [NamesakeEdgeResource::class, 'checkedFormatter', null],
    'a closure as ??\'s fallback' => [NamesakeEdgeResource::class, 'coalescedFormatter', null],
    'a closure on the left of ??' => [NamesakeEdgeResource::class, 'coalescedLocal', null],
    'a closure as a match arm' => [NamesakeEdgeResource::class, 'matchedFormatter', null],
    'two locals bound to each other' => [NamesakeEdgeResource::class, 'cyclicLocals', null],
    'a generator' => [PostMetaService::class, 'generatedMeta', 'unknown[]'],
    'an iterable, with an iterator return nothing types' => [PostMetaService::class, 'iterableMeta', 'unknown[]'],
    'a vendor method, whose conditional @return types nothing' => [Str::class, 'contains', null],
    'a method of this package, which an application installs under vendor/' => [CastHandler::class, 'nodeClasses', 'unknown[]'],
    'a return nothing types' => [NamesakeInvoiceResource::class, 'opaque', null],
]);

// A parameter is not bound to an argument that may hold a callable, so a helper returning it publishes nothing for it.
test('a helper passed a callable through a local, a ternary or another helper publishes unknown', function (string $key, string $type) {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(NamesakeEdgeResource::class), Post::class)->analyze()->properties)->keyBy('name');

    expect($props[$key]['type'])->toBe($type);
})->with([
    'a closure passed through a local' => ['passed_local', 'unknown'],
    'a closure passed as a ternary arm' => ['passed_ternary', 'unknown'],
    'a closure passed from another helper\'s local' => ['via_helper', 'unknown'],
    'a string passed through a local' => ['passed_title', 'string'],
]);

// Unlike the literal shape, a value read keeps the class its returns name, so the caller imports it.
test('a helper read as a value carries the import channels its returns name', function () {
    $call = new AstParser()->parseSource('<?php $this->wrapped();')[0]->expr;

    expect(new ResourceAstAnalyzer(new ReflectionClass(NamesakeEdgeResource::class), Post::class)->resolve($call))
        ->toBe(['type' => 'Post', 'optional' => false, 'embeddedModelFqcns' => [Post::class]]);
});

// Laravel sends a returned Closure object as `{}`, never what calling it returns, however the return reaches it.
test('a helper that returns a closure publishes unknown', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(NamesakeEdgeResource::class), Post::class)->analyze()->properties)->keyBy('name');

    expect($props['closure']['type'])->toBe('unknown')
        ->and($props['arrow']['type'])->toBe('unknown')
        ->and($props['via_local']['type'])->toBe('unknown')
        ->and($props['via_ternary']['type'])->toBe('unknown');
});

test('a mutual recursion with no declaration terminates, each read declining', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(RecursiveVagueFixture::class, 'a'))->toBeNull()
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(RecursiveVagueFixture::class, 'b'))->toBeNull();
});

// The read runs over the using resource and its model, so one trait helper never answers for another resource.
test('a trait helper is read over each using resource\'s model', function () {
    $label = fn (string $resource, string $model): string => collect(new ResourceAstAnalyzer(new ReflectionClass($resource), $model)->analyze()->properties)
        ->keyBy('name')['label']['type'];

    // A category has no title, so its read declines instead of borrowing the post's.
    expect($label(TitleLabelPostResource::class, Post::class))->toBe('string')
        ->and($label(TitleLabelCategoryResource::class, Category::class))->toBe('unknown');
});

test('a precise declaration is kept, and an absent method declines', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(PostStatsService::class, 'summary'))
        ->toBe(['type' => '{ views: number; likes: number }', 'optional' => false])
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(PostStatsService::class, 'missingMethod'))
        ->toBeNull();
});

// The body fallback carries import channels as a literal does, so its filters keep their Pick<>, and it reads a plain
// enum case as the literal it sends; an analysis that reads the case as its enum is cached apart.
test('a body fallback reads its enum cases as literals, apart from an analysis that reads them as their enum', function () {
    $engine = resolve(AstEngine::class);
    $asEnum = $engine->analyzeMethod(PostHelperResource::class, 'withSingleCase');
    $asLiteral = $engine->analyzeMethod(PostHelperResource::class, 'withSingleCase', casesAsLiterals: true);
    $filtered = $engine->analyzeMethod(Release::class, 'columnSummary', Release::class, casesAsLiterals: true);

    expect(array_column($asEnum->properties, 'type', 'name'))->toBe(['v' => 'StatusType'])
        ->and(array_column($asLiteral->properties, 'type', 'name'))->toBe(['v' => '0'])
        ->and(collect($filtered->properties)->keyBy('name')['named']['type'])->toBe("Pick<Release, 'major' | 'minor'>");
});

// A value read takes the body fallback's literal as the shape does, while a wrap still needs the case's enum.
test('a helper\'s plain enum case reads as its literal, as its shape\'s does, and wraps as its enum', function () {
    expect(resourceProps(EnumCaseHelperResource::class))->toBe([
        'draft' => '0',
        'draft_resource' => 'AsEnum<typeof Status>',
        'draft_list' => 'AsEnum<typeof Status>[]',
    ])->and(resolve(MethodReturnTypeResolver::class)->resolve(EnumCaseHelperService::class, 'draft'))
        ->toBe(['type' => '0', 'optional' => false]);
});

test('a body the model inherits filters the model it runs on', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(InheritedFilterRelease::class, 'columnSummary')['type'] ?? null)
        ->toBe("{ named: Pick<InheritedFilterRelease, 'major' | 'minor'>; rest: Pick<InheritedFilterRelease, 'id' | 'major' | "
            ."'minor' | 'created_at' | 'updated_at'>; picked: Record<string, unknown>; left: Record<string, unknown> }");
});

// The getter a method body reads publishes there what the model file publishes for its accessor, filters included.
test('a method body reading an accessor publishes the type the model publishes for it', function (string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, $method)['type'] ?? null)
        ->toBe($expected);
})->with([
    'bare $this, literal keys' => ['readOwnPicks', "{ v: { v: Pick<FilteringAccessorModel, 'id' | 'title'>; id: number }; id: number }"],
    'bare $this, runtime keys' => ['readOwnRuntime', '{ v: { v: Record<string, unknown>; id: number }; id: number }'],
    'runtime keys returned whole, which the model publishes as unknown' => ['readRuntimeFields', '{ v: unknown; id: number }'],
    'a vague getter its @property-read tag types' => ['readTaggedFields', '{ v: { id: number }[]; id: number }'],
    'bare $this through ?->' => ['readOwnNullsafe', "{ v: { v: Pick<FilteringAccessorModel, 'id' | 'content'>; id: number }; id: number }"],
    'a single relation' => ['readAuthorPicks', "{ v: { v: Pick<User, 'id' | 'role'> | null; id: number }; id: number }"],
    'a to-many relation' => ['readCommentPicks', '{ v: { v: Comment[]; id: number }; id: number }'],
    'a to-many relation returned whole, beside a @property-read tag naming the model' => ['readCommentList', '{ v: Comment[]; id: number }'],
    'a relation to an untyped override' => ['readLoosePicks', "{ v: { v: Pick<UntypedFilterOverrideModel, 'id' | 'title'>; id: number }; id: number }"],
    'a multi-model accessor' => ['readCounterpartPicks', "{ v: { v: Pick<Comment, 'id'> | Pick<User, 'id'> | null; id: number }; id: number }"],
    'an old-style accessor' => ['readLegacyPicks', "{ v: { v: Pick<User, 'id' | 'name'>; id: number }; id: number }"],
    'a camelCase alias' => ['readCamelAlias', "{ v: { v: Pick<User, 'id' | 'role'> | null; id: number }; id: number }"],
    'through a relation' => ['readThroughRelation', "{ v: { v: Pick<User, 'id' | 'role'> | null; id: number } | null; id: number }"],
    'through a local variable' => ['readThroughLocal', "{ v: { v: Pick<User, 'id' | 'role'> | null; id: number }; id: number }"],
    'through a closure parameter' => ['readThroughVariable', '{ v: Comment[][]; id: number }'],
    'as a filtered key' => ['readAsFilteredKey', "{ v: { id: number; author_picks: { v: Pick<User, 'id' | 'role'> | null; id: number } }; id: number }"],
    'as a spread key' => ['readAsSpreadKey', "{ id: number; author_picks: { v: Pick<User, 'id' | 'role'> | null; id: number }; x: number }"],
    'as a spread camelCase key' => ['readAsSpreadAlias', "{ id: number; authorPicks: { v: Pick<User, 'id' | 'role'> | null; id: number }; x: number }"],
    'a literal getter' => ['readLiteral', '{ v: { a: number; b: string }; id: number }'],
    'a docblock-typed getter' => ['readOwner', '{ v: User; id: number }'],
    'a list docblock' => ['readDocRecords', '{ v: Comment[]; id: number }'],
    'a nullable list docblock through ?->' => ['readDocRecordsNullsafe', '{ v: Comment[] | null; id: number }'],
    'a list docblock over runtime keys' => ['readDocRecordsRuntime', '{ v: Comment[]; id: number }'],
    'a nested list docblock' => ['readDocNestedRecords', '{ v: Comment[][]; id: number }'],
    'a keyed docblock' => ['readDocKeyed', '{ v: Comment[]; id: number }'],
    'a docblock with an unknown arm' => ['readDocIntMixed', '{ v: Comment[]; id: number }'],
    'a keyed-or-list docblock' => ['readDocRecordOrList', '{ v: Comment[]; id: number }'],
    'a docblock naming a class' => ['readDocClassList', '{ v: Comment[]; id: number }'],
    'a token-free tag over a vague signature' => ['readSignedTagRows', '{ v: Comment[]; id: number }'],
    'a token-free tag over an old-style getter' => ['readLegacyTagRows', '{ v: Comment[]; id: number }'],
]);

test('a vague helper whose member names a class keeps that class', function (string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(ClassNamingMemberModel::class, $method)['type'] ?? null)
        ->toBe($expected);
})->with([
    'a toResource() member' => ['withResource', '{ r: UserResource; title: string }'],
    'an EnumResource::make() member' => ['withEnumResource', '{ s: AsEnum<typeof ArticleStatus>; title: string }'],
]);

// Without imports a to-many filter spells `unknown[]`. As when the body itself is vague, a fallback annotation or tag
// types the reader unless it is `unknown` or names a class, which would cost the reader its shape.
test('a vague spelling without imports gives way to a fallback that is not unknown and names no class', function (string $attribute, string $expected) {
    expect(resolve(ModelAttributeResolver::class)->resolveAttribute(FilteringAccessorModel::class, $attribute, carriesImports: false)['type'])
        ->toBe($expected);
})->with([
    'a list docblock' => ['doc_records', 'Record<string, unknown>[]'],
    'a nullable list docblock through ?->' => ['doc_records_nullsafe', 'Record<string, unknown>[] | null'],
    'a list docblock over runtime keys' => ['doc_records_runtime', 'Record<string, unknown>[]'],
    'a nested list docblock over a nested vague spelling' => ['doc_nested_records', 'Record<string, unknown>[][]'],
    // The value is a list, but the reader trusts the annotation's keyed object, as it did before the getter body typed
    // the accessor: the waterfall never checks an annotation against the body.
    'a keyed docblock' => ['doc_keyed', 'Record<string, unknown>'],
    'a docblock with an unknown arm' => ['doc_int_mixed', 'number | unknown'],
    'a keyed-or-list docblock' => ['doc_record_or_list', 'Record<string, unknown> | unknown[]'],
    'a docblock naming a class' => ['doc_class_list', 'unknown[]'],
    'no annotation' => ['comment_list', 'unknown[]'],
    'a token-free tag over a vague signature' => ['signed_tag_rows', '{ id: number }[]'],
    'a token-free tag over an old-style getter' => ['legacy_tag_rows', '{ id: number; content: string }[]'],
]);

test('a spread model reads its appended filtering accessor as the model publishes it', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(FilteringAccessorModel::class, 'readAsSpreadAppend')['type'] ?? null)
        ->toContain("; author_picks: { v: Pick<User, 'id' | 'role'> | null; id: number }; x: number }");
});

// The caller's keys reach the return unseen, so the written keys publish beside a signature that admits them.
test('a returned parameter the body only writes keys into publishes an open shape', function (string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(PayloadDecoratorService::class, $method)['type'] ?? null)
        ->toBe($expected);
})->with([
    'a key write' => ['decorate', '{ seen: boolean; [key: string]: unknown }'],
    'a key write inside an if' => ['decorateWhen', '{ flag?: string; [key: string]: unknown }'],
    'a ??= and an unset() beside a key write' => ['fillDefaults', '{ seen: boolean; [key: string]: unknown }'],
    'a static method' => ['decorateStatic', '{ seen: boolean; [key: string]: unknown }'],
    'an untyped parameter' => ['untyped', '{ seen: boolean; [key: string]: unknown }'],
    'a whole write replacing the parameter first' => ['rebuilt', '{ id: number; seen: boolean }'],
    'no key write' => ['passThrough', 'Record<string, unknown>'],
    'a += of the parameter' => ['merged', '{ seen: boolean }'],
    'a precise @return' => ['declared', '{ seen: boolean }'],
]);

test('every call path publishes a returned key-written parameter open', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(DecoratedPayloadResource::class), Post::class)->analyze()->properties)
        ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

    expect($props->all())->toBe([
        'decorated' => '{ seen: boolean; [key: string]: unknown }',
        'conditional' => '{ flag?: string; [key: string]: unknown }',
        'static' => '{ seen: boolean; [key: string]: unknown }',
        'helper' => '{ seen: boolean; [key: string]: unknown }',
        'passed_through' => 'Record<string, string> | never[]',
        'rebuilt' => '{ id: number; seen: boolean }',
    ]);
});

test('keeps the open signature only for a parameter bound to nothing it can read', function () {
    $values = analyzedKeyTypes(GuardedLinkPayload::class, 'values');

    expect(resolve(MethodReturnTypeResolver::class)->resolve(GuardedLinkPayload::class, 'guard')['type'] ?? null)
        ->toBe('{ url?: null; disabled?: boolean; [key: string]: unknown }')
        ->and($values['opaque'])->toBe('{ url?: null; disabled?: boolean; [key: string]: unknown }')
        ->and($values['nested'])->toBe('{ id: number; url: string | null; disabled: boolean }')
        ->and(json_encode(new GuardedLinkPayload()->values()['opaque']))->toBe('[]');
});

// The parent's body is read in an analyzer of its own, which binds nothing the child passes, so it seeds nothing.
test('keeps a parent:: helper that writes keys into its parameter open through the child', function () {
    $child = new KeyStampingChild;

    expect(resolve(MethodReturnTypeResolver::class)->resolve(KeyStampingChild::class, 'stamped')['type'] ?? null)
        ->toBe('{ x: number; [key: string]: unknown }')
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(KeyStampingChild::class, 'extended')['type'] ?? null)
        ->toBe('{ x: number; y: number; [key: string]: unknown }')
        ->and(json_encode($child->stamped(['id' => 1])))->toBe('{"id":1,"x":1}')
        ->and(json_encode($child->extended(['id' => 1])))->toBe('{"id":1,"x":1,"y":2}');
});

test('lets a seeded shape win over a vague @return array<string, mixed>', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(GuardedLinkPayload::class, 'declared'))
        ->toBe(['type' => '{ id: number; name: string; extra: boolean }', 'optional' => false, 'keepsKeys' => true])
        ->and(json_encode(new GuardedLinkPayload()->values()['declared']))->toBe('{"id":1,"name":"x","extra":true}');
});

it('publishes a value-read shape open when its base is unread', function () {
    $payload = new UnreadBasePayload(Request::create('/', 'GET', ['a' => 1]));

    expect(analyzedKeyTypes(UnreadBasePayload::class))->toBe([
        'unread_local' => '{ x: number; [key: string]: unknown }',
        'untouched' => 'Record<string, unknown>',
    ])->and(json_encode($payload->toArray()))->toBe('{"unread_local":{"a":1,"x":1},"untouched":{"k":1}}');
});

test('keeps a seeded parameter open where the body writes a key the walk cannot name', function () {
    $values = new UnreadBasePayload(Request::create('/'))->values();

    expect(analyzedKeyTypes(UnreadBasePayload::class, 'values'))->toMatchArray([
        'extended' => '{ id: number; seen: boolean; [key: string]: unknown }',
        'appended' => '{ id: number; [key: string]: unknown }',
        'nested_key' => '{ id: number; [key: string]: unknown }',
    ])->and(json_encode($values['extended']))->toBe('{"id":1,"k":true,"seen":true}')
        ->and(json_encode($values['appended']))->toBe('{"id":1,"0":5}')
        ->and(json_encode($values['nested_key']))->toBe('{"id":1,"a":{"b":1}}');
});

test('publishes a value-read shape open over a helper or a spread the walk cannot read', function () {
    $values = new UnreadBasePayload(Request::create('/', 'GET', ['a' => 1]))->values();

    expect(analyzedKeyTypes(UnreadBasePayload::class, 'values'))->toMatchArray([
        'raw_base' => '{ x: number; [key: string]: unknown }',
        'raw_spread' => '{ x: number; [key: string]: unknown }',
        'null_base' => '{ x: number }',
    ])->and(json_encode($values['raw_base']))->toBe('{"a":1,"x":1}')
        ->and(json_encode($values['raw_spread']))->toBe('{"a":1,"x":1}')
        ->and(json_encode($values['null_base']))->toBe('{"x":1}');
});

test('publishes a string-keyed parameter returned untouched as a record where its argument reads as a list', function () {
    $payload = new UnreadBasePayload(Request::create('/', 'GET', ['a' => 1]));
    $filled = new UnreadBasePayload(Request::create('/'));
    $filled->extra = ['b' => 2];
    $filled->either = ['a' => 1];

    expect(analyzedKeyTypes(UnreadBasePayload::class, 'values'))->toMatchArray([
        'request_untouched' => 'Record<string, unknown>',
        'maybe_untouched' => 'Record<string, unknown> | null',
        'either_untouched' => 'Record<string, unknown> | string',
    ])->and(json_encode($payload->values()['request_untouched']))->toBe('{"a":1}')
        ->and(json_encode($payload->values()['maybe_untouched']))->toBe('null')
        ->and(json_encode($filled->values()['maybe_untouched']))->toBe('{"b":2}')
        ->and(json_encode($payload->values()['either_untouched']))->toBe('"none"')
        ->and(json_encode($filled->values()['either_untouched']))->toBe('{"a":1}');
});

test('publishes a value-read shape open where a += adds an array the walk cannot read', function () {
    $payload = new UnreadBasePayload(Request::create('/', 'GET', ['a' => 1]));

    expect(analyzedKeyTypes(UnreadBasePayload::class, 'values')['topped_up'])
        ->toBe('{ x: number; [key: string]: unknown }')
        ->and(json_encode($payload->values()['topped_up']))->toBe('{"x":1,"a":1}');
});
