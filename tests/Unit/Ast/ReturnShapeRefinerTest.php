<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShapeRefiner;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AuthorEditorRowsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AuthoredRowsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ClashHelperRowsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ClashingAuthorsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredHelperRowsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LiteralKindRowsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NestedHelperSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReversedClashAuthorsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StatusOrRowsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReturnShapeFixture;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Http\Request;
use Workbench\App\Http\Resources\RecipientSettingsResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\ValueObjects\MailRecipient;
use Workbench\App\ValueObjects\RecipientList;
use Workbench\Crm\Models\User as CrmUser;

/**
 * @param  list<array{name: string, type: string, optional?: bool}>  $properties
 */
function refinedAnalysis(array $properties, string $method, bool $keepsUnresolvedNames = true): MethodAnalysis
{
    $analysis = new MethodAnalysis(properties: array_map(
        fn (array $p): array => [
            'name' => $p['name'],
            'type' => $p['type'],
            'optional' => $p['optional'] ?? false,
            'description' => '',
        ],
        $properties,
    ));

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, $method), $keepsUnresolvedNames);

    return $analysis;
}

test('a property the body already typed is never overwritten by the shape', function () {
    $props = collect(refinedAnalysis([['name' => 'known', 'type' => 'number']], 'shaped')->properties)->keyBy('name');

    expect($props['known']['type'])->toBe('number');
});

test('a shape value naming a bare class is not applied', function () {
    $props = collect(refinedAnalysis([['name' => 'handle', 'type' => 'unknown']], 'shaped')->properties)->keyBy('name');

    expect($props['handle']['type'])->toBe('unknown');
});

test('a shape value naming no PHP type at all keeps the name the app declares', function () {
    $props = collect(refinedAnalysis([['name' => 'custom', 'type' => 'unknown']], 'shaped')->properties)->keyBy('name');

    expect($props['custom'])->toMatchArray(['type' => 'CustomThing', 'optional' => true]);
});

test('a name no class answers to is not kept from the analyzed method\'s own docblock', function () {
    $props = collect(refinedAnalysis([['name' => 'custom', 'type' => 'unknown']], 'shaped', keepsUnresolvedNames: false)->properties)->keyBy('name');

    expect($props['custom'])->toMatchArray(['type' => 'unknown', 'optional' => true]);
});

test('a bare name a class answers to is not kept, since the shape cannot import it', function () {
    $props = collect(refinedAnalysis([['name' => 'text', 'type' => 'unknown']], 'spelled')->properties)->keyBy('name');

    expect($props['text']['type'])->toBe('unknown');
});

test('a shape value lists each arm once, and one left unknown fills nothing', function (string $name, string $expected) {
    $props = collect(refinedAnalysis([['name' => $name, 'type' => 'unknown']], 'spelled')->properties)->keyBy('name');

    expect($props[$name]['type'])->toBe($expected);
})->with([
    'int|float' => ['amount', 'number'],
    'two unknown arms' => ['spare', 'unknown'],
    'string literals' => ['mode', "'draft' | 'live'"],
]);

test('a shape value naming an overridden class fills with the override and brings its import', function () {
    $analysis = refinedAnalysis([
        ['name' => 'chart', 'type' => 'unknown'],
        ['name' => 'nested', 'type' => 'unknown'],
        ['name' => 'owner', 'type' => 'unknown'],
    ], 'overridden');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe([
        'chart' => 'ChartType',
        'nested' => '{ panel: UiNode; n: number }',
        'owner' => 'User',
    ])->and($analysis->customImports)->toBe([
        '@js/types/ui' => ['UiNode'],
        ComposedClassTypes::marker(Chart::class) => ['ChartType'],
    ]);
});

test('a filled key queues the model its member names, as a cast naming it does', function () {
    $analysis = refinedAnalysis([['name' => 'owner', 'type' => 'unknown']], 'overridden');

    expect($analysis->inlineModelFqcns)->toBe(['owner' => [User::class]])
        ->and($analysis->declaredTokenFqcns)->toBe(['owner' => [User::class]]);
});

test('a fill keeps a key whose value may be left out so, and a fill naming no class declares no slots', function () {
    $analysis = new MethodAnalysis(properties: [
        ['name' => 'owner', 'type' => 'unknown', 'optional' => true, 'description' => ''],
        ['name' => 'chart', 'type' => 'unknown', 'optional' => true, 'description' => ''],
    ]);
    $analysis->leftOutValues = ['owner' => true, 'chart' => true];

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, 'overridden'));

    expect($analysis->leftOutValues)->toEqual(['owner' => true, 'chart' => true])
        ->and($analysis->declaredTokenFqcns)->toBe(['owner' => [User::class]]);
});

test('an override spelled like the model beside it keeps both arms, each token with its own class', function () {
    $analysis = refinedAnalysis([['name' => 'either', 'type' => 'unknown']], 'spelledLikeModel');

    expect($analysis->properties[0]['type'])->toBe('User | User')
        ->and($analysis->declaredTokenFqcns)->toBe(['either' => [null, User::class]])
        ->and($analysis->inlineModelFqcns)->toBe(['either' => [User::class]])
        ->and($analysis->customImports)->toBe(['@js/types/ui' => ['User']]);
});

test('a vague-only key takes its member in place of the vague arms, keeping the null arm', function (string $name, string $inferred, string $expected) {
    $props = collect(refinedAnalysis([['name' => $name, 'type' => $inferred]], 'vague')->properties)->keyBy('name');

    expect($props[$name]['type'])->toBe($expected);
})->with([
    'a nullable list' => ['list', 'unknown[] | null', 'string[] | null'],
    'a list' => ['list', 'unknown[]', 'string[]'],
    'an empty list' => ['list', 'never[]', 'string[]'],
    'a null before the list' => ['list', 'null | unknown[]', 'string[] | null'],
    'unknown with a null arm' => ['list', 'unknown | null', 'string[] | null'],
    'a record' => ['record', 'Record<string, unknown>', 'Record<string, number>'],
    'a nullable record' => ['record', 'Record<string, unknown> | null', 'Record<string, number> | null'],
]);

test('a vague member fills only an exact unknown, never a vague key it would make less specific', function (string $inferred, string $expected) {
    $props = collect(refinedAnalysis([['name' => 'vague', 'type' => $inferred]], 'vague')->properties)->keyBy('name');

    expect($props['vague']['type'])->toBe($expected);
})->with([
    'a list' => ['unknown[]', 'unknown[]'],
    'a nullable record' => ['Record<string, unknown> | null', 'Record<string, unknown> | null'],
    'unknown with a null arm' => ['unknown | null', 'unknown | null'],
    'unknown' => ['unknown', 'unknown'],
]);

test('a precise inferred key keeps its type over the docblock', function (string $inferred) {
    $props = collect(refinedAnalysis([['name' => 'precise', 'type' => $inferred]], 'vague')->properties)->keyBy('name');

    expect($props['precise']['type'])->toBe($inferred);
})->with([
    'a string' => ['string'],
    'a list of strings' => ['string[]'],
    'a vague arm beside a precise one' => ['unknown[] | string'],
    'a lone null' => ['null'],
    'an unknown inside a shape' => ['{ a: unknown }'],
]);

test('a union of two Records gives no record value', function () {
    $analysis = refinedAnalysis([['name' => 'a', 'type' => 'unknown']], 'records');

    expect($analysis->properties[0]['type'])->toBe('unknown')
        ->and($analysis->customImports)->toBe([]);
});

test('an array<string, V> return naming an overridden class fills with the override and brings its import', function () {
    $analysis = refinedAnalysis([
        ['name' => 'a', 'type' => 'unknown'],
        ['name' => '[key: `${string}_tag`]', 'type' => 'unknown | undefined'],
    ], 'overriddenRecord');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe([
        'a' => 'UiNode',
        '[key: `${string}_tag`]' => 'UiNode | undefined',
    ])->and($analysis->customImports)->toBe(['@js/types/ui' => ['UiNode']]);
});

test('a key the shape declares optional becomes optional, keeping its own type', function () {
    $props = collect(refinedAnalysis([['name' => 'maybe', 'type' => 'unknown']], 'shaped')->properties)->keyBy('name');

    expect($props['maybe'])->toMatchArray(['type' => 'number', 'optional' => true]);
});

test('an array<string, V> return fills every unknown property with V', function () {
    $props = collect(refinedAnalysis([
        ['name' => 'a', 'type' => 'unknown'],
        ['name' => 'b', 'type' => 'boolean'],
    ], 'record')->properties)->keyBy('name');

    expect($props['a']['type'])->toBe('string')
        ->and($props['b']['type'])->toBe('boolean');
});

test('an array<string, V> return fills an untyped index signature with V, keeping its | undefined', function () {
    $props = collect(refinedAnalysis([
        ['name' => '[key: `${string}_tag`]', 'type' => 'unknown | undefined'],
        ['name' => 'named', 'type' => 'unknown | undefined'],
    ], 'record')->properties)->keyBy('name');

    expect($props['[key: `${string}_tag`]'])->toMatchArray(['type' => 'string | undefined', 'optional' => false])
        ->and($props['named']['type'])->toBe('unknown | undefined');
});

test('an index signature with any other value, or under an array{...} shape, is left alone', function () {
    $merged = collect(refinedAnalysis([['name' => '[key: `${string}_tag`]', 'type' => 'string | undefined | unknown']], 'record')->properties)->keyBy('name');
    $shaped = collect(refinedAnalysis([['name' => '[key: `${string}_tag`]', 'type' => 'unknown | undefined']], 'shaped')->properties)->keyBy('name');

    expect($merged['[key: `${string}_tag`]']['type'])->toBe('string | undefined | unknown')
        ->and($shaped['[key: `${string}_tag`]'])->toMatchArray(['type' => 'unknown | undefined', 'optional' => false]);
});

test('a method with no return docblock leaves every property alone', function () {
    $props = collect(refinedAnalysis([['name' => 'a', 'type' => 'unknown']], 'undocumented')->properties)->keyBy('name');

    expect($props['a'])->toMatchArray(['type' => 'unknown', 'optional' => false]);
});

test('an already-optional property stays optional when the shape does not say so', function () {
    $props = collect(refinedAnalysis([['name' => 'known', 'type' => 'unknown', 'optional' => true]], 'shaped')->properties)->keyBy('name');

    expect($props['known'])->toMatchArray(['type' => 'string', 'optional' => true]);
});

// Where the body types a key but leaves a member, an element or a record value unknown, the docblock's member at that
// position fills it; every position the body types keeps the body's type, as the whole key does (GH-154).
test('a key the body types fills each unknown position inside it from the docblock', function (string $method, string $name, string $inferred, string $expected) {
    $analysis = refinedAnalysis([['name' => $name, 'type' => $inferred]], $method);

    expect($analysis->properties[0]['type'])->toBe($expected)
        ->and($analysis->declaredTokenFqcns)->toBe([]);
})->with([
    'list elements\' members' => ['nestedLists', 'rows', '{ name: unknown; email: unknown }[]', '{ name: string | null; email: string | null }[]'],
    'one member the body types' => ['nestedLists', 'rows', '{ name: string; email: unknown }[]', '{ name: string; email: string | null }[]'],
    'an optional member' => ['nestedLists', 'rows', '{ name?: unknown; email: string }[]', '{ name?: string | null; email: string }[]'],
    'a member the docblock lacks' => ['nestedLists', 'rows', '{ name: unknown; extra: unknown }[]', '{ name: string | null; extra: unknown }[]'],
    'a nullable list' => ['nestedLists', 'rows', '{ name: unknown; email: string }[] | null', '{ name: string | null; email: string }[] | null'],
    'a grouped element' => ['nestedLists', 'rows', '({ name: unknown; email: string } | null)[]', '({ name: string | null; email: string } | null)[]'],
    'a record value' => ['nestedLists', 'byKey', 'Record<string, { n: unknown }>', 'Record<string, { n: number }>'],
    'a nested shape' => ['nestedShapes', 'nested', '{ inner: { a: unknown; b: number } }', '{ inner: { a: number; b: number } }'],
    'a nullable shape' => ['nestedArms', 'maybe', '{ a: unknown } | null', '{ a: number } | null'],
    'an unknown list element' => ['nestedLists', 'rows', 'unknown[] | string', '{ name: string | null; email: string | null }[] | string'],
    'the arm of the docblock\'s kind' => ['nestedArms', 'either', '{ a: unknown } | unknown[]', '{ a: number } | number[]'],
]);

test('an unknown position whose docblock member is another kind of value stays unknown', function (string $method, string $name, string $inferred, string $expected) {
    $analysis = refinedAnalysis([['name' => $name, 'type' => $inferred]], $method);

    expect($analysis->properties[0]['type'])->toBe($expected)
        ->and($analysis->customImports)->toBe([])
        ->and($analysis->inlineModelFqcns)->toBe([]);
})->with([
    'a shape where the docblock lists' => ['nestedLists', 'rows', '{ name: unknown }', '{ name: unknown }'],
    'a list where the docblock has a shape' => ['nestedShapes', 'nested', 'string | unknown[]', 'string | unknown[]'],
    'a shape where the docblock has a scalar' => ['nestedShapes', 'scalar', '{ a: unknown }', '{ a: unknown }'],
    'a key the docblock lacks' => ['nestedLists', 'missing', '{ a: unknown }[]', '{ a: unknown }[]'],
]);

// A class a fill names joins the key's channels as a class the body names inside its value does, so the key stays a
// value the body typed, beside the classes the body already queued (GH-154).
test('an unknown position whose member names a class fills with its import', function () {
    $analysis = refinedAnalysis([['name' => 'owned', 'type' => '{ owner: unknown; n: unknown }[]']], 'nestedClasses');
    $overridden = refinedAnalysis([['name' => 'nested', 'type' => '{ panel: unknown; n: string }']], 'overridden');
    $reordered = refinedAnalysis([['name' => 'pair', 'type' => '{ second: unknown; first: unknown }']], 'nestedClasses');

    expect($analysis->properties[0]['type'])->toBe('{ owner: User; n: number }[]')
        ->and($analysis->inlineModelFqcns)->toBe(['owned' => [User::class]])
        ->and($analysis->modelFqcns)->toBe([User::class => User::class])
        ->and($analysis->declaredTokenFqcns)->toBe([])
        ->and($reordered->properties[0]['type'])->toBe('{ second: Post | null; first: User }')
        ->and($reordered->inlineModelFqcns['pair'])->toEqualCanonicalizing([Post::class, User::class])
        ->and($overridden->properties[0]['type'])->toBe('{ panel: UiNode; n: string }')
        ->and($overridden->customImports)->toBe(['@js/types/ui' => ['UiNode']])
        ->and($overridden->inlineModelFqcns)->toBe([]);
});

test('an unknown position whose member names a class fills beside the classes the body queued', function (string $name, string $inferred, string $expected, array $queued) {
    $analysis = new MethodAnalysis(properties: [['name' => $name, 'type' => $inferred, 'optional' => false, 'description' => '']]);
    $analysis->inlineModelFqcns = [$name => [Post::class]];

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, 'nestedClasses'));

    expect($analysis->properties[0]['type'])->toBe($expected)
        ->and($analysis->inlineModelFqcns)->toBe([$name => $queued])
        ->and($analysis->declaredTokenFqcns)->toBe([]);
})->with([
    'another class' => ['owned', '{ owner: unknown; n: unknown; post: Post }[]', '{ owner: User; n: number; post: Post }[]', [Post::class, User::class]],
    'the class the body names' => ['reviewers', '{ by: Post; other: unknown }[]', '{ by: Post; other: User | null }[]', [Post::class, User::class]],
]);

test('an unknown position whose member names the class the body already queued fills with no new channel', function () {
    $analysis = new MethodAnalysis(properties: [
        ['name' => 'reviewers', 'type' => '{ by: User; other: unknown }[]', 'optional' => false, 'description' => ''],
    ]);
    $analysis->inlineModelFqcns = ['reviewers' => [User::class]];

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, 'nestedClasses'));

    expect($analysis->properties[0]['type'])->toBe('{ by: User; other: User | null }[]')
        ->and($analysis->inlineModelFqcns)->toBe(['reviewers' => [User::class]]);
});

// A key spelling two classes by one name takes the slot of each token, as a whole key's fill does, so the publisher
// aliases each class apart (GH-154).
test('a fill naming a class another class of the key is spelled like declares the class behind each token', function () {
    $analysis = new MethodAnalysis(properties: [
        ['name' => 'owned', 'type' => '{ by: User; owner: unknown; n: unknown }[]', 'optional' => false, 'description' => ''],
    ]);
    $analysis->inlineModelFqcns = ['owned' => [CrmUser::class]];

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, 'nestedClasses'));

    expect($analysis->properties[0]['type'])->toBe('{ by: User; owner: User; n: number }[]')
        ->and($analysis->declaredTokenFqcns)->toBe(['owned' => [CrmUser::class, User::class]])
        ->and($analysis->inlineModelFqcns)->toBe(['owned' => [CrmUser::class, User::class]]);
});

test('a key the body types beside a fill naming a same-named class publishes each class under its own alias', function (string $resource, array $expected) {
    $data = new ResourceTransformer($resource)->data();

    expect(array_map(fn (array $property): string => $property['type'], $data->properties))->toBe($expected)
        ->and(array_merge(...array_values($data->typeImports)))->toEqualCanonicalizing(['User as CrmUser', 'User as WorkbenchUser']);
})->with([
    'a CRM author beside the app user' => [ClashingAuthorsResource::class, [
        'clash' => '{ by: CrmUser; other: WorkbenchUser | null }[]',
        'typed' => '{ by: CrmUser; other: WorkbenchUser | null }[]',
        'unread' => '{ by: CrmUser; other: WorkbenchUser | null }[]',
    ]],
    'the app author beside a CRM user' => [ReversedClashAuthorsResource::class, [
        'clash' => '{ by: WorkbenchUser; other: CrmUser | null }[]',
    ]],
]);

// Where a position cannot fill, as beside two classes the body spells by one name or an enum it wraps, the key takes
// its whole @return type, as a key no rule types does; a key a helper's @return already declared stays (GH-154).
test('a key whose positions cannot all fill publishes the whole @return type, or what a helper already declared', function (string $resource, array $expected, array $imports) {
    $data = new ResourceTransformer($resource)->data();

    expect(array_map(fn (array $property): string => $property['type'], $data->properties))->toBe($expected)
        ->and(array_merge(...array_values($data->typeImports)))->toEqualCanonicalizing($imports);
})->with([
    'two classes the body spells by one name' => [AuthorEditorRowsResource::class, [
        'rows' => '{ by: CrmUser; ed: WorkbenchUser; other: WorkbenchUser | null }[]',
    ], ['User as CrmUser', 'User as WorkbenchUser']],
    'a wrapped enum beside the list' => [StatusOrRowsResource::class, [
        'subject' => 'StatusType | { by: CrmUser; other: WorkbenchUser | null }[]',
    ], ['StatusType', 'User as CrmUser', 'User as WorkbenchUser']],
    'a helper\'s whole fill' => [DeclaredHelperRowsResource::class, [
        'rows' => '{ by: User; other: unknown }[]',
    ], ['User']],
    'a helper\'s position fill' => [ClashHelperRowsResource::class, [
        'rows' => '{ by: CrmUser; ed: WorkbenchUser; other: unknown }[]',
    ], ['User as CrmUser', 'User as WorkbenchUser']],
    'a helper\'s fill spread inside a literal' => [NestedHelperSpreadResource::class, [
        'payload' => '{ rows: { by: unknown; other: unknown }[] }',
    ], []],
]);

test('a key the body types with a model takes the @return member\'s enum or model at a position it leaves unknown', function () {
    expect(analyzedKeyTypes(AuthoredRowsResource::class))->toBe([
        'statuses' => '{ by: User; status: StatusType }[]',
        'typed' => '{ by: User; status: StatusType }[]',
        'reviewers' => '{ by: User; other: User | null }[]',
    ])->and(analyzedImportNames(AuthoredRowsResource::class))->toContain('StatusType', 'User');
});

// share() and a metadata provider's provide() lay their docblock over every key it types, so a position fill could only
// move a key's channels, as a model's would let a metadata companion import it.
test('a caller that lays the docblock over every key itself fills no position inside a key the body types, but a vague key', function () {
    $analysis = new MethodAnalysis(properties: [
        ['name' => 'owned', 'type' => '{ owner: unknown; n: unknown }[]', 'optional' => false, 'description' => ''],
        ['name' => 'pair', 'type' => 'unknown', 'optional' => false, 'description' => ''],
    ]);

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, 'nestedClasses'), fillsPositions: false);

    expect(array_column($analysis->properties, 'type', 'name'))
        ->toBe(['owned' => '{ owner: unknown; n: unknown }[]', 'pair' => '{ first: User; second: Post | null }'])
        ->and($analysis->inlineModelFqcns)->not->toHaveKey('owned');
});

// A bare scalar the body reads takes the docblock's literals of that scalar, which the body widened; a literal, a union
// or another scalar's literal keeps the body's reading (GH-154).
test('a bare scalar the body reads takes the @return docblock\'s literals of that scalar', function (string $name, string $inferred, string $expected) {
    $analysis = refinedAnalysis([['name' => $name, 'type' => $inferred]], 'literals');

    expect($analysis->properties[0]['type'])->toBe($expected);
})->with([
    'string, number and boolean' => [
        'kinds',
        '{ kind: string; n: number; flag: boolean; other: unknown }',
        "{ kind: 'User' | 'Post'; n: 1 | 2; flag: true; other: number }",
    ],
    'a whole key' => ['top', 'string', "'a' | 'b'"],
    'another scalar\'s literal' => ['mismatched', '{ n: number }', '{ n: number }'],
    'a literal the body reads' => ['kept', "{ kind: 'Admin' }", "{ kind: 'Admin' }"],
    'a union the body reads' => ['kept', '{ kind: string | null }', '{ kind: string | null }'],
    'a literal beside null' => ['nullable', 'string', 'string'],
]);

test('a callback\'s widened literal takes the @return docblock\'s literal beside the classes the body types', function () {
    $data = new ResourceTransformer(LiteralKindRowsResource::class)->data();

    expect(array_map(fn (array $property): string => $property['type'], $data->properties))
        ->toBe(['rows' => "{ kind: 'User'; by: User; other: unknown }[]"])
        ->and(array_merge(...array_values($data->typeImports)))->toBe(['User']);
});

test('an array<string, V> return fills each unknown position inside every key from V', function () {
    $analysis = refinedAnalysis([
        ['name' => 'a', 'type' => '{ n: unknown }'],
        ['name' => 'b', 'type' => '{ n: string }'],
    ], 'nestedRecord');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['a' => '{ n: number }', 'b' => '{ n: string }']);
});

test('a workbench resource\'s @return fills the member a cache read leaves unknown, and the body types the rest', function () {
    config()->set('cache.default', 'array');
    cache()->put('mail.settings.ann@example.com', ['digest' => true]);
    $list = new RecipientList([new MailRecipient('Ann', 'ann@example.com'), new MailRecipient('Bob')]);

    expect(analyzedKeyTypes(RecipientSettingsResource::class))->toBe([
        'recipients' => '{ name: string | null; settings: { digest: boolean } | null }[]',
    ])->and(json_encode(new RecipientSettingsResource($list)->resolve(Request::create('/'))))->toBe(
        '{"recipients":[{"name":"Ann","settings":{"digest":true}},{"name":"Bob","settings":null}]}',
    );
});
