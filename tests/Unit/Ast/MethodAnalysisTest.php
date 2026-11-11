<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Chart as ChartEnum;

it('is the base class ResourceAnalysis now extends, as an empty subclass', function () {
    expect(new ResourceAnalysis)->toBeInstanceOf(MethodAnalysis::class)
        ->and(new ReflectionClass(ResourceAnalysis::class)->getParentClass()->getName())->toBe(MethodAnalysis::class);
});

it('appends the keys the source adds, and writes one it always sets over the held one where that stood', function () {
    $target = new MethodAnalysis(properties: [
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
        ['name' => 'at', 'type' => 'string', 'optional' => false, 'description' => ''],
    ]);
    $source = new MethodAnalysis(properties: [
        ['name' => 'by', 'type' => 'string', 'optional' => true, 'description' => ''],
        ['name' => 'id', 'type' => 'string', 'optional' => false, 'description' => ''],
    ]);

    $target->merge($source);

    expect($target->properties)->toBe([
        ['name' => 'id', 'type' => 'string', 'optional' => false, 'description' => ''],
        ['name' => 'at', 'type' => 'string', 'optional' => false, 'description' => ''],
        ['name' => 'by', 'type' => 'string', 'optional' => true, 'description' => ''],
    ]);
});

// PHP still sends the held value where the source leaves its key out, so the key holds either.
it('joins a key the source may leave out with the held one, which stays where both name classes or it is untyped', function () {
    $target = new MethodAnalysis;
    $target->addProperty('by', ['type' => 'User', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\User']);
    $target->addProperty('at', ['type' => 'Post', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\Post']);
    $target->addProperty('raw', ['type' => 'unknown', 'optional' => false]);
    $target->addProperty('n', ['type' => 'number', 'optional' => false]);
    // Keys a helper sets on some paths only: optional, though no value of theirs is left out by itself.
    $source = new MethodAnalysis(properties: [
        ['name' => 'by', 'type' => 'string', 'optional' => true, 'description' => ''],
        ['name' => 'at', 'type' => 'User', 'optional' => true, 'description' => ''],
        ['name' => 'raw', 'type' => 'string', 'optional' => true, 'description' => ''],
        ['name' => 'n', 'type' => 'string', 'optional' => true, 'description' => ''],
    ], modelFqcns: ['at' => 'Workbench\App\Models\User']);

    $target->merge($source);

    expect(array_map(static fn (array $property): string => $property['name'].($property['optional'] ? '?' : '').': '.$property['type'], $target->properties))
        ->toBe(['by: User | string', 'at: Post', 'raw: unknown', 'n: number | string'])
        ->and($target->modelFqcns)->toBe(['by' => 'Workbench\App\Models\User', 'at' => 'Workbench\App\Models\Post']);
});

// A when() value the source writes is a MissingValue PHP filters out, taking the key with it.
it('joins a key the source writes as a value it may leave out, which may leave the key out too', function () {
    $target = new MethodAnalysis;
    $target->addProperty('by', ['type' => 'number', 'optional' => false]);
    $source = new MethodAnalysis;
    $source->addProperty('by', ['type' => 'string', 'optional' => true]);

    $target->merge($source);

    expect($target->properties)->toBe([[
        'name' => 'by', 'type' => 'number | string', 'optional' => true, 'description' => '', 'removed' => true,
    ]]);
});

it('keeps a key a merge call wrote where a later key writes it, which wins only where the merge did not run', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('by', ['type' => 'string', 'optional' => true]);
    $analysis->addProperty('id', ['type' => 'number', 'optional' => false]);

    $analysis->writeUnderMerged('by', ['type' => 'number', 'optional' => false]);
    $analysis->writeUnderMerged('id', ['type' => 'number', 'optional' => true]);

    expect($analysis->properties)->toBe([
        ['name' => 'by', 'type' => 'string | number', 'optional' => false, 'description' => ''],
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
    ]);
});

it('array-spread-merges the single-value class maps, later source winning on key collision', function () {
    $target = new MethodAnalysis(
        enumResources: ['a' => 'Old\\A', 'shared' => 'Old\\Shared'],
        nestedResources: ['a' => 'Old\\A'],
        directEnumFqcns: ['a' => 'Old\\A'],
        modelFqcns: ['a' => 'Old\\A'],
    );
    $source = new MethodAnalysis(
        enumResources: ['b' => 'New\\B', 'shared' => 'New\\Shared'],
        nestedResources: ['b' => 'New\\B'],
        directEnumFqcns: ['b' => 'New\\B'],
        modelFqcns: ['b' => 'New\\B'],
    );

    $target->merge($source);

    expect($target->enumResources)->toBe(['a' => 'Old\\A', 'shared' => 'New\\Shared', 'b' => 'New\\B'])
        ->and($target->nestedResources)->toBe(['a' => 'Old\\A', 'b' => 'New\\B'])
        ->and($target->directEnumFqcns)->toBe(['a' => 'Old\\A', 'b' => 'New\\B'])
        ->and($target->modelFqcns)->toBe(['a' => 'Old\\A', 'b' => 'New\\B']);
});

it('array-spread-merges multiEnumResourceFqcns, later source winning on key collision', function () {
    $target = new MethodAnalysis(multiEnumResourceFqcns: ['a' => ['A1', 'A2'], 'shared' => ['Old']]);
    $source = new MethodAnalysis(multiEnumResourceFqcns: ['b' => ['B1'], 'shared' => ['New']]);

    $target->merge($source);

    expect($target->multiEnumResourceFqcns)->toBe([
        'a' => ['A1', 'A2'],
        'shared' => ['New'],
        'b' => ['B1'],
    ]);
});

it('appends customImports per import path rather than overwriting the path', function () {
    $target = new MethodAnalysis(customImports: ['@/types' => ['Foo']]);
    $source = new MethodAnalysis(customImports: ['@/types' => ['Bar'], '@/other' => ['Baz']]);

    $target->merge($source);

    expect($target->customImports)->toBe([
        '@/types' => ['Foo', 'Bar'],
        '@/other' => ['Baz'],
    ]);
});

it('appends inlineEnumFqcns per property WITHOUT deduping, same as inlineModelFqcns', function () {
    $target = new MethodAnalysis(inlineEnumFqcns: ['status' => ['App\\Enum1', 'App\\Enum2']]);
    $source = new MethodAnalysis(inlineEnumFqcns: ['status' => ['App\\Enum2', 'App\\Enum3']]);

    $target->merge($source);

    // Deliberately not deduped: aliasPropertyType() walks this as a positional queue against
    // left-to-right basename occurrences in the rendered type string.
    expect($target->inlineEnumFqcns)->toBe([
        'status' => ['App\\Enum1', 'App\\Enum2', 'App\\Enum2', 'App\\Enum3'],
    ]);
});

// A key the target holds is replaced by the source's value, whose own tokens alone the queue then describes; a target
// holding no such key is a join's accumulator, whose queue still reads every branch.
it('replaces the inline enum queue of a key the target holds and the source sets again', function (bool $holds, array $queue) {
    $target = new MethodAnalysis(
        properties: $holds ? [['name' => 'row', 'type' => '{ s: StatusType }', 'optional' => false, 'description' => '']] : [],
        inlineEnumFqcns: ['row' => ['App\\Status'], 'kept' => ['App\\Kept']],
    );
    $target->merge(new MethodAnalysis(
        properties: [['name' => 'row', 'type' => '{ c: StatusType }', 'optional' => false, 'description' => '']],
        inlineEnumFqcns: ['row' => ['Crm\\Status']],
    ));

    expect($target->inlineEnumFqcns['row'])->toBe($queue)
        ->and($target->inlineEnumFqcns['kept'])->toBe(['App\\Kept']);
})->with([
    'held by the target' => [true, ['Crm\\Status']],
    'held by no property of the target' => [false, ['App\\Status', 'Crm\\Status']],
]);

it('appends inlineEnumResourceFqcns per property WITHOUT deduping, same as inlineModelFqcns', function () {
    $target = new MethodAnalysis(inlineEnumResourceFqcns: ['status' => ['App\\Enum1', 'App\\Enum2']]);
    $source = new MethodAnalysis(inlineEnumResourceFqcns: ['status' => ['App\\Enum2', 'App\\Enum3']]);

    $target->merge($source);

    // Deliberately not deduped: same positional reasoning as inlineEnumFqcns above.
    expect($target->inlineEnumResourceFqcns)->toBe([
        'status' => ['App\\Enum1', 'App\\Enum2', 'App\\Enum2', 'App\\Enum3'],
    ]);
});

it('appends inlineResourceFqcns per property WITHOUT deduping, same as inlineModelFqcns', function () {
    $crm = 'Workbench\Crm\Http\Resources\UserResource';
    $app = 'Workbench\App\Http\Resources\UserResource';

    $target = new MethodAnalysis(inlineResourceFqcns: ['reviewable' => [$crm, $app]]);
    $target->merge(new MethodAnalysis(inlineResourceFqcns: ['reviewable' => [$crm], 'owner' => [$app]]));

    expect($target->inlineResourceFqcns)->toBe(['reviewable' => [$crm, $app, $crm], 'owner' => [$app]]);
});

// A later spread's cast is the one the key publishes, so its entry wins, with or without an import.
it('merges casts with the source winning for a key both cast', function () {
    $target = new MethodAnalysis(casts: ['k' => ['type' => 'A', 'import' => '@/a'], 'kept' => ['type' => 'K', 'import' => '@/k']]);
    $target->merge(new MethodAnalysis(casts: ['k' => ['type' => 'B', 'import' => null], 'owner' => ['type' => 'D', 'import' => '@/d']]));

    expect($target->casts)->toBe([
        'k' => ['type' => 'B', 'import' => null],
        'kept' => ['type' => 'K', 'import' => '@/k'],
        'owner' => ['type' => 'D', 'import' => '@/d'],
    ]);
});

// The source's value takes the key, and a cast entry describes only the value it was written for.
it('clears the cast entry of a key the source sets without one', function () {
    $target = new MethodAnalysis(casts: ['owner' => ['type' => 'string', 'import' => null], 'kept' => ['type' => 'K', 'import' => null]]);
    $target->merge(new MethodAnalysis(properties: [['name' => 'owner', 'type' => 'User', 'optional' => false, 'description' => '']]));

    expect($target->casts)->toBe(['kept' => ['type' => 'K', 'import' => null]]);
});

// A declaration's token slots describe the text it wrote, which the source's value replaces.
it('merges declared token slots as it merges casts', function () {
    $user = 'Workbench\\App\\Models\\User';
    $target = new MethodAnalysis(properties: [
        ['name' => 'owner', 'type' => 'User', 'optional' => false, 'description' => ''],
        ['name' => 'kept', 'type' => 'User', 'optional' => false, 'description' => ''],
    ]);
    $target->declaredTokenFqcns = ['owner' => [$user], 'kept' => [$user]];
    $source = new MethodAnalysis(properties: [
        ['name' => 'owner', 'type' => 'string', 'optional' => false, 'description' => ''],
        ['name' => 'either', 'type' => 'User | User', 'optional' => false, 'description' => ''],
    ]);
    $source->declaredTokenFqcns = ['either' => [null, $user]];

    $target->merge($source);

    expect($target->declaredTokenFqcns)->toBe(['kept' => [$user], 'either' => [null, $user]]);
});

it('reads a key as untyped: unknown, with no channel, and no custom import only its text spelled', function () {
    $analysis = new MethodAnalysis(
        properties: [
            [
                'name' => 'either', 'type' => 'User | User', 'optional' => true, 'description' => '',
                'fillType' => 'User | User', 'removed' => true,
            ],
            ['name' => 'chart', 'type' => 'UiNode', 'optional' => false, 'description' => ''],
        ],
        customImports: ['@js/types/ui' => ['User', 'UiNode'], '@js/types/other' => ['User as Owner']],
        inlineModelFqcns: ['either' => ['Workbench\\App\\Models\\User']],
    );
    $analysis->declaredTokenFqcns = ['either' => [null, 'Workbench\\App\\Models\\User']];

    $analysis->untype('either');

    expect($analysis->properties[0])->toBe([
        'name' => 'either', 'type' => 'unknown', 'optional' => true, 'description' => '', 'removed' => true,
    ])
        ->and($analysis->inlineModelFqcns)->toBe([])
        ->and($analysis->declaredTokenFqcns)->toBe([])
        ->and($analysis->customImports)->toBe(['@js/types/ui' => ['UiNode'], '@js/types/other' => ['User as Owner']]);
});

it('retypes a key class-less, keeping a custom import its new text still spells', function () {
    $analysis = new MethodAnalysis(
        properties: [
            ['name' => 'pair', 'type' => '{ a: User; b: User; n: UiNode }', 'optional' => false, 'description' => ''],
            ['name' => 'either', 'type' => 'User | User', 'optional' => false, 'description' => ''],
        ],
        customImports: ['@js/types/ui' => ['User', 'UiNode']],
        inlineModelFqcns: ['pair' => ['Workbench\\App\\Models\\User'], 'either' => ['Workbench\\App\\Models\\User']],
    );
    $analysis->declaredTokenFqcns = ['pair' => [null, 'Workbench\\App\\Models\\User']];

    $analysis->retypeClassLess('pair', '{ a: User; b: unknown; n: unknown }');

    expect($analysis->properties[0]['type'])->toBe('{ a: User; b: unknown; n: unknown }')
        ->and($analysis->inlineModelFqcns)->toBe(['either' => ['Workbench\\App\\Models\\User']])
        ->and($analysis->declaredTokenFqcns)->toBe([])
        ->and($analysis->customImports)->toBe(['@js/types/ui' => ['User']]);
});

it('retypes a key class-less, dropping a custom import another key spells only in a literal', function () {
    $analysis = new MethodAnalysis(
        properties: [
            ['name' => 'k', 'type' => 'User | User', 'optional' => false, 'description' => ''],
            ['name' => 'kind', 'type' => "'User'", 'optional' => false, 'description' => ''],
        ],
        customImports: ['@js/types/ui' => ['User']],
    );

    $analysis->retypeClassLess('k', 'unknown');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['k' => 'unknown', 'kind' => "'User'"])
        ->and($analysis->customImports)->toBe([]);
});

it('merges carried classes once per kind', function () {
    $target = new MethodAnalysis(carried: ['enums' => ['Workbench\App\Enums\Status']]);
    $target->merge(new MethodAnalysis(carried: [
        'enums' => ['Workbench\App\Enums\Status', 'Workbench\Crm\Enums\Status'],
        'models' => ['Workbench\App\Models\User'],
    ]));

    expect($target->carried)->toBe([
        'enums' => ['Workbench\App\Enums\Status', 'Workbench\Crm\Enums\Status'],
        'models' => ['Workbench\App\Models\User'],
    ]);
});

it('forgets the cast entry of a key another value took over, and no other key\'s', function () {
    $analysis = new MethodAnalysis(casts: ['taken_over' => ['type' => 'A', 'import' => '@/a'], 'kept' => ['type' => 'B', 'import' => null]]);

    $analysis->forgetChannels('taken_over');

    expect($analysis->casts)->toBe(['kept' => ['type' => 'B', 'import' => null]]);
});

it('forgets every channel keyed by a property another value took over, and nothing keyed by another', function () {
    $analysis = new MethodAnalysis;
    $channels = [
        'type' => 'UserResource | UserResource',
        'optional' => false,
        'enumFqcn' => 'Workbench\App\Enums\Status',
        'directEnumFqcn' => 'Workbench\App\Enums\Status',
        'resourceFqcn' => 'Workbench\App\Http\Resources\PostResource',
        'modelFqcn' => 'Workbench\App\Models\Post',
        'multiEnumResourceFqcns' => ['Workbench\App\Enums\Status', 'Workbench\App\Enums\Role'],
        'wrapIsCollection' => true,
        'directIsArray' => false,
        'embeddedEnumFqcns' => ['Workbench\App\Enums\Role'],
        'embeddedModelFqcns' => ['Workbench\App\Models\User'],
        'embeddedEnumResourceFqcns' => ['Workbench\App\Enums\Season'],
        'embeddedResourceFqcns' => ['Workbench\App\Http\Resources\TagResource'],
    ];

    $analysis->addProperty('taken_over', $channels);
    $analysis->addProperty('kept', $channels);

    $analysis->forgetChannels('taken_over');

    expect($analysis->hasFqcnChannel('taken_over'))->toBeFalse()
        ->and($analysis->hasFqcnChannel('kept'))->toBeTrue()
        ->and($analysis->inlineResourceFqcns)->toBe(['kept' => ['Workbench\App\Http\Resources\TagResource']])
        // An embedded class also rides a key of its own, which is how its import survives the property's.
        ->and($analysis->nestedResources)->toBe([
            'Workbench\App\Http\Resources\TagResource' => 'Workbench\App\Http\Resources\TagResource',
            'kept' => 'Workbench\App\Http\Resources\PostResource',
        ]);
});

it('publishes unknown for a member naming an unpublished model and forgets its channels, its siblings kept', function (?array $published, string $unpublished) {
    if ($published !== null) {
        PublishedModelRegistry::register($published);
    }

    $analysis = new MethodAnalysis;
    $analysis->addProperty('kept', ['type' => 'Post', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\Post']);
    $analysis->addProperty('nested', ['type' => '{ owner: Post }', 'optional' => false, 'embeddedModelFqcns' => ['Workbench\App\Models\Post']]);
    $analysis->properties[] = ['name' => 'lost', 'type' => '{ m: User }', 'optional' => false, 'description' => ''];
    $analysis->inlineModelFqcns['lost'] = [$unpublished];

    $analysis->forgetUnpublishableMembers();

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['kept' => 'Post', 'nested' => '{ owner: Post }', 'lost' => 'unknown'])
        ->and($analysis->hasFqcnChannel('lost'))->toBeFalse()
        ->and($analysis->channelFqcns('kept'))->toBe(['Workbench\App\Models\Post'])
        ->and($analysis->channelFqcns('nested'))->toBe(['Workbench\App\Models\Post']);
})->with([
    'a model the published set leaves out' => [['Workbench\App\Models\Post'], 'Workbench\App\Models\User'],
    'a framework model, with no published set' => [null, 'Illuminate\Foundation\Auth\User'],
]);

// The key holding these members aliases every token of a name alike, so the enum would take the composed class's.
it('publishes unknown for a member whose class publishes under a name a custom import brings, its siblings kept', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('kind', ['type' => 'ChartType', 'optional' => false, 'directEnumFqcn' => ChartEnum::class]);
    $analysis->addProperty('chart', ['type' => 'ChartType', 'optional' => false, 'customImports' => ['@ui' => ['ChartType']]]);

    $analysis->forgetUnpublishableMembers();

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['kind' => 'unknown', 'chart' => 'ChartType'])
        ->and($analysis->hasFqcnChannel('kind'))->toBeFalse()
        ->and($analysis->customImports)->toBe(['@ui' => ['ChartType']]);
});

it('appends inlineModelFqcns per property WITHOUT deduping, unlike its sibling inline maps', function () {
    $target = new MethodAnalysis(inlineModelFqcns: ['author' => ['App\\Models\\User', 'App\\Models\\Post']]);
    $source = new MethodAnalysis(inlineModelFqcns: ['author' => ['App\\Models\\Post', 'App\\Models\\User']]);

    $target->merge($source);

    // Deliberately not deduped: aliasPropertyType() walks this as a positional queue against
    // left-to-right basename occurrences in the rendered type string.
    expect($target->inlineModelFqcns)->toBe([
        'author' => ['App\\Models\\User', 'App\\Models\\Post', 'App\\Models\\Post', 'App\\Models\\User'],
    ]);
});

it('merges a ResourceAnalysis source into a ResourceAnalysis target, since it inherits merge()', function () {
    $target = new ResourceAnalysis(properties: [
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
    ], inlineModelFqcns: ['author' => ['App\\Models\\User']]);
    $source = new ResourceAnalysis(properties: [
        ['name' => 'name', 'type' => 'string', 'optional' => false, 'description' => ''],
    ], inlineModelFqcns: ['author' => ['App\\Models\\User']]);

    $target->merge($source);

    expect($target->properties)->toBe([
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
        ['name' => 'name', 'type' => 'string', 'optional' => false, 'description' => ''],
    ])->and($target->inlineModelFqcns)->toBe(['author' => ['App\\Models\\User', 'App\\Models\\User']]);
});

// The flat-type alias names the ONE resource a collection flattens to, so it is deliberately
// outside merge()'s reach: a spread source carrying its own alias must not rename the target.
it('merges properties without letting the source flat-type alias overwrite the target', function () {
    $target = new MethodAnalysis(properties: [
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
    ], flatTypeAlias: 'Foo', flatTypeAliasFqcn: 'App\\Foo');
    $source = new MethodAnalysis(properties: [
        ['name' => 'name', 'type' => 'string', 'optional' => false, 'description' => ''],
    ], flatTypeAlias: 'Bar', flatTypeAliasFqcn: 'App\\Bar');

    $target->merge($source);

    expect($target->properties)->toBe([
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
        ['name' => 'name', 'type' => 'string', 'optional' => false, 'description' => ''],
    ])
        ->and($target->flatTypeAlias)->toBe('Foo')
        ->and($target->flatTypeAliasFqcn)->toBe('App\\Foo');
});

it('rewrites each property\'s type, body type and fill, except a key a cast holds and a value that keeps its keys', function () {
    $analysis = new MethodAnalysis(
        properties: [
            ['name' => 'a', 'type' => 'A', 'optional' => false, 'description' => '', 'bodyType' => 'B', 'fillType' => 'F'],
            ['name' => '42', 'type' => 'A', 'optional' => false, 'description' => ''],
            ['name' => 'kept', 'type' => 'A', 'optional' => false, 'description' => '', 'keepsKeys' => true],
        ],
        casts: [42 => ['type' => 'A', 'import' => null]],
    );

    $analysis->rewritePropertyTypes(strtolower(...));

    expect($analysis->properties)->toBe([
        ['name' => 'a', 'type' => 'a', 'optional' => false, 'description' => '', 'bodyType' => 'b', 'fillType' => 'f'],
        ['name' => '42', 'type' => 'A', 'optional' => false, 'description' => ''],
        ['name' => 'kept', 'type' => 'A', 'optional' => false, 'description' => '', 'keepsKeys' => true],
    ]);
});

it('holds unread keys after a merge where either side holds them', function (bool $target, bool $source, bool $merged) {
    $into = new MethodAnalysis;
    $into->holdsUnreadKeys = $target;
    $from = new MethodAnalysis;
    $from->holdsUnreadKeys = $source;

    $into->merge($from);

    expect($into->holdsUnreadKeys)->toBe($merged);
})->with([
    'neither' => [false, false, false],
    'the target' => [true, false, true],
    'the source' => [false, true, true],
]);

it('takes over every field of another analysis, both flags included', function () {
    $source = new MethodAnalysis(
        properties: [['name' => 'a', 'type' => 'A', 'optional' => true, 'description' => 'd']],
        enumResources: ['a' => 'App\\E'],
        nestedResources: ['a' => 'App\\R'],
        customImports: ['@/x' => ['X']],
        directEnumFqcns: ['a' => 'App\\E'],
        modelFqcns: ['a' => 'App\\M'],
        inlineEnumFqcns: ['a' => ['App\\E']],
        inlineModelFqcns: ['a' => ['App\\M']],
        inlineResourceFqcns: ['a' => ['App\\R']],
        multiEnumResourceFqcns: ['a' => ['App\\E']],
        inlineEnumResourceFqcns: ['a' => ['App\\E']],
        enumResourceArmShapes: ['a' => ['wrapIsCollection' => true, 'directIsArray' => false]],
        casts: ['a' => ['type' => 'A', 'import' => null]],
        carried: ['models' => ['App\\M']],
        flatTypeAlias: 'A[]',
        flatTypeAliasFqcn: 'App\\M',
    );
    $source->readsCompletely = false;
    $source->holdsUnreadKeys = true;
    $source->holdsUnnamedKeys = true;
    $source->declaredTokenFqcns = ['a' => [null, 'App\\M']];
    $source->heldObjectKeys = ['a' => true];
    $source->closedToDottedKeys = ['a' => true];
    $source->openReadings = ['a' => ['{ b: number }']];
    $target = new MethodAnalysis(properties: [['name' => 'b', 'type' => 'B', 'optional' => false, 'description' => '']]);

    $target->replaceWith($source);

    // A field added later fails the first assertion until this source sets it, then the second until it is copied.
    expect(array_filter(
        get_object_vars(new MethodAnalysis),
        fn (mixed $default, string $field): bool => $source->{$field} === $default,
        ARRAY_FILTER_USE_BOTH,
    ))->toBe([])
        ->and(get_object_vars($target))->toBe(get_object_vars($source));
});

/**
 * Each key's presence facts, by name.
 *
 * @return array<string, array{optional?: bool, absent?: bool, removed?: bool}>
 */
function presenceFactsOf(MethodAnalysis $analysis): array
{
    return array_map(
        static fn (array $property): array => array_intersect_key($property, ['optional' => true, 'absent' => true, 'removed' => true]),
        array_column($analysis->writtenProperties(), null, 'name'),
    );
}

// No held value survives a removed value, as one survives a key left unset.
it('tells a key whose value may be removed from one a write or a spread member may leave unset, through merges', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('deferred', ['type' => 'string', 'optional' => true]);
    $analysis->addProperty('skipped', ['type' => 'string', 'optional' => false], optional: true);
    $analysis->addProperty('member', ['type' => 'string', 'optional' => true, 'absent' => true]);
    $analysis->addProperty('reset', ['type' => 'string', 'optional' => true]);
    $analysis->overwriteProperty('reset', ['type' => 'number', 'optional' => false]);
    $other = new MethodAnalysis;
    $other->addProperty('when', ['type' => 'string', 'optional' => true]);
    $analysis->merge($other);
    $analysis->forgetChannels('when');
    $analysis->renameKeys(['deferred' => 'later']);

    expect(presenceFactsOf($analysis))->toBe([
        'later' => ['optional' => true, 'removed' => true],
        'skipped' => ['optional' => true, 'absent' => true],
        'member' => ['optional' => true, 'absent' => true],
        'reset' => ['optional' => false],
        'when' => ['optional' => true, 'removed' => true],
    ]);
});

// The source's removed value removes the key wherever it runs; one it may leave unset keeps the held one there.
it('joins a held key with the presence of the value a merge may leave out', function (array $held, array $written, array $facts) {
    $target = new MethodAnalysis;
    $target->addProperty('k', ['type' => 'number', ...$held]);
    $source = new MethodAnalysis;
    $source->addProperty('k', ['type' => 'string', ...$written]);

    $target->merge($source);

    expect(presenceFactsOf($target)['k'])->toBe($facts);
})->with([
    'removed over required' => [['optional' => false], ['optional' => true], ['optional' => true, 'removed' => true]],
    'unset over required' => [['optional' => false], ['optional' => true, 'absent' => true], ['optional' => false]],
    'unset over removed' => [
        ['optional' => true], ['optional' => true, 'absent' => true], ['optional' => true, 'removed' => true],
    ],
    'unset over unset' => [
        ['optional' => true, 'absent' => true], ['optional' => true, 'absent' => true],
        ['optional' => true, 'absent' => true],
    ],
]);

// A merge call's value wins where it ran; a later key's removed value leaves the key out where it did not.
it('keeps a merge call\'s key removed only where a later key\'s value may be removed too', function (bool $merged, bool $later, array $facts) {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('k', ['type' => 'number', 'optional' => $merged]);

    $analysis->writeUnderMerged('k', ['type' => 'string', 'optional' => $later]);

    expect(presenceFactsOf($analysis)['k'])->toBe($facts);
})->with([
    'both may be removed' => [true, true, ['optional' => true, 'removed' => true]],
    'only the merged one' => [true, false, ['optional' => false]],
    'only the later one' => [false, true, ['optional' => false]],
]);

it('drops the queued classes and custom imports a branch of a join only repeats from another', function () {
    $entry = fn (string $name, string $type): array => ['name' => $name, 'type' => $type, 'optional' => false, 'description' => ''];
    $first = new MethodAnalysis(
        properties: [$entry('kept', '{ s: S }'), $entry('aliased', 'User'), $entry('rewritten', '{ s: S }')],
        customImports: ['@/x' => ['X']],
        inlineEnumFqcns: ['kept' => ['App\\S'], 'rewritten' => ['App\\S']],
        inlineModelFqcns: ['aliased' => ['App\\User']],
        inlineResourceFqcns: ['kept' => ['App\\R']],
        inlineEnumResourceFqcns: ['kept' => ['App\\S']],
    );
    $branch = new MethodAnalysis(
        properties: [$entry('kept', '{ s: S }'), $entry('aliased', 'User'), $entry('rewritten', '{ s: S } | null')],
        customImports: ['@/x' => ['X', 'Y']],
        inlineEnumFqcns: ['kept' => ['App\\S'], 'rewritten' => ['App\\S']],
        inlineModelFqcns: ['aliased' => ['Crm\\User']],
        inlineResourceFqcns: ['kept' => ['App\\R']],
        inlineEnumResourceFqcns: ['kept' => ['App\\S']],
    );

    $branch->dropRepeatsOf($first);

    // A same-named class with another FQCN is a different occurrence, and a rewritten key spells its type once more.
    expect($branch->inlineEnumFqcns)->toBe(['rewritten' => ['App\\S']])
        ->and($branch->inlineModelFqcns)->toBe(['aliased' => ['Crm\\User']])
        ->and($branch->inlineResourceFqcns)->toBe([])
        ->and($branch->inlineEnumResourceFqcns)->toBe([])
        ->and($branch->customImports)->toBe(['@/x' => ['Y']]);
});

it('queues in branch order each class the branches of a merge type a key with, where they type it apart', function () {
    $first = new MethodAnalysis(
        nestedResources: ['x' => 'App\\UserResource', 42 => 'App\\TagResource', 'App\\R' => 'App\\R'],
        directEnumFqcns: ['s' => 'App\\Status'],
        modelFqcns: ['who' => 'App\\User'],
        inlineResourceFqcns: ['x' => ['App\\R']],
    );
    $second = new MethodAnalysis(
        nestedResources: ['x' => 'App\\CategoryResource', 42 => 'App\\TagResource'],
        directEnumFqcns: ['s' => 'App\\Status'],
        modelFqcns: ['who' => 'App\\Category'],
    );
    $merged = new MethodAnalysis;
    $merged->merge($first);
    $merged->merge($second);

    $merged->queueSplitClasses([$first, $second]);

    expect($merged->nestedResources)->toBe([
        42 => 'App\\TagResource',
        'App\\R' => 'App\\R',
        'App\\UserResource' => 'App\\UserResource',
        'App\\CategoryResource' => 'App\\CategoryResource',
    ])
        ->and($merged->inlineResourceFqcns)->toBe(['x' => ['App\\UserResource', 'App\\R', 'App\\CategoryResource']])
        ->and($merged->directEnumFqcns)->toBe(['s' => 'App\\Status'])
        ->and($merged->inlineEnumFqcns)->toBe([])
        ->and($merged->modelFqcns)->toBe(['App\\User' => 'App\\User', 'App\\Category' => 'App\\Category'])
        ->and($merged->inlineModelFqcns)->toBe(['who' => ['App\\User', 'App\\Category']]);
});

it('lists the enums the branches wrap a key in only where each wraps it, none reads it bare, and each has its token', function (array $second, string $type, ?array $listed) {
    $entry = fn (string $type): array => [['name' => 's', 'type' => $type, 'optional' => false, 'description' => '']];
    $first = new MethodAnalysis(properties: $entry('StatusType'), enumResources: ['s' => 'App\\Status']);
    $merged = new MethodAnalysis(properties: $entry($type), enumResources: ['s' => 'App\\Status']);

    $merged->queueSplitClasses([$first, new MethodAnalysis(...['properties' => $entry('PriorityType'), ...$second])]);

    expect($merged->multiEnumResourceFqcns['s'] ?? null)->toBe($listed)
        ->and(isset($merged->enumResources['s']))->toBe($listed === null);
})->with([
    'another enum' => [['enumResources' => ['s' => 'App\\Priority']], 'StatusType | PriorityType | null', ['App\\Status', 'App\\Priority']],
    'another enum beside the empty list a wrap around null sends' => [
        ['enumResources' => ['s' => 'App\\Priority']],
        'StatusType | never[] | PriorityType',
        ['App\\Status', 'App\\Priority'],
    ],
    'another enum also read bare' => [
        ['enumResources' => ['s' => 'App\\Priority'], 'directEnumFqcns' => ['s' => 'App\\Priority']],
        'StatusType | PriorityType',
        null,
    ],
    'a type with another arm' => [['enumResources' => ['s' => 'App\\Priority']], 'StatusType | PriorityType | string', null],
    'the same enum' => [['enumResources' => ['s' => 'App\\Status']], 'StatusType', null],
]);

it('queues nothing for a merge of no branches', function () {
    $analysis = new MethodAnalysis(enumResources: ['s' => 'App\\Status']);

    $analysis->queueSplitClasses([]);

    expect($analysis->enumResources)->toBe(['s' => 'App\\Status']);
});

describe('MethodAnalysis::addProperty()', function () {
    test('routes every channel a resolved value can carry', function () {
        $analysis = new MethodAnalysis;

        $analysis->addProperty('status', [
            'type' => 'StatusType | null',
            'optional' => false,
            'enumFqcn' => 'Workbench\App\Enums\Status',
            'directEnumFqcn' => 'Workbench\App\Enums\Status',
            'resourceFqcn' => 'Workbench\App\Http\Resources\PostResource',
            'modelFqcn' => 'Workbench\App\Models\Post',
            'multiEnumResourceFqcns' => ['Workbench\App\Enums\Status', 'Workbench\App\Enums\Role'],
            'wrapIsCollection' => true,
            'directIsArray' => false,
            'embeddedEnumFqcns' => ['Workbench\App\Enums\Role', 'Workbench\App\Enums\Role'],
            'embeddedModelFqcns' => ['Workbench\App\Models\User', 'Workbench\Crm\Models\User', 'Workbench\App\Models\User'],
            'embeddedEnumResourceFqcns' => ['Workbench\App\Enums\Season'],
            'embeddedResourceFqcns' => ['Workbench\App\Http\Resources\TagResource'],
            'customImports' => ['@/types/x' => ['XType']],
        ], optional: true, description: 'doc');

        expect($analysis->properties)->toBe([[
            'name' => 'status', 'type' => 'StatusType | null', 'optional' => true, 'description' => 'doc', 'absent' => true,
        ]])
            ->and($analysis->enumResources)->toBe(['status' => 'Workbench\App\Enums\Status'])
            ->and($analysis->directEnumFqcns)->toBe([
                'status' => 'Workbench\App\Enums\Status',
                'Workbench\App\Enums\Role' => 'Workbench\App\Enums\Role',
            ])
            ->and($analysis->nestedResources)->toBe([
                'status' => 'Workbench\App\Http\Resources\PostResource',
                'Workbench\App\Http\Resources\TagResource' => 'Workbench\App\Http\Resources\TagResource',
            ])
            ->and($analysis->modelFqcns)->toBe([
                'status' => 'Workbench\App\Models\Post',
                'Workbench\App\Models\User' => 'Workbench\App\Models\User',
                'Workbench\Crm\Models\User' => 'Workbench\Crm\Models\User',
            ])
            ->and($analysis->multiEnumResourceFqcns)->toBe(['status' => ['Workbench\App\Enums\Status', 'Workbench\App\Enums\Role']])
            ->and($analysis->enumResourceArmShapes)->toBe(['status' => ['wrapIsCollection' => true, 'directIsArray' => false]])
            // The four inline queues keep repeats: aliasPropertyType() consumes them positionally.
            ->and($analysis->inlineEnumFqcns)->toBe(['status' => ['Workbench\App\Enums\Role', 'Workbench\App\Enums\Role']])
            ->and($analysis->inlineModelFqcns)->toBe(['status' => ['Workbench\App\Models\User', 'Workbench\Crm\Models\User', 'Workbench\App\Models\User']])
            ->and($analysis->inlineEnumResourceFqcns)->toBe(['status' => ['Workbench\App\Enums\Season']])
            // The fourth is keyed by property as well as by FQCN, so same-named resources are told apart by position.
            ->and($analysis->inlineResourceFqcns)->toBe(['status' => ['Workbench\App\Http\Resources\TagResource']])
            ->and($analysis->customImports)->toBe(['@/types/x' => ['XType']]);
    });

    test('a value naming a class no generated file exports is declined to unknown, keeping its optional flag', function () {
        PublishedModelRegistry::register(['Workbench\App\Models\User']);

        $analysis = new MethodAnalysis;
        $analysis->addProperty('post', ['type' => 'Post | null', 'optional' => true, 'modelFqcn' => 'Workbench\App\Models\Post']);
        $analysis->addProperty('user', ['type' => 'User', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\User']);

        expect($analysis->properties)->toBe([
            ['name' => 'post', 'type' => 'unknown', 'optional' => true, 'description' => '', 'removed' => true],
            ['name' => 'user', 'type' => 'User', 'optional' => false, 'description' => ''],
        ])
            ->and($analysis->modelFqcns)->toBe(['user' => 'Workbench\App\Models\User']);
    });

    test('optional is the union of the caller flag and the value result', function () {
        $analysis = new MethodAnalysis;
        $analysis->addProperty('a', ['type' => 'string', 'optional' => true]);
        $analysis->addProperty('b', ['type' => 'string', 'optional' => false], optional: true);
        $analysis->addProperty('c', ['type' => 'string', 'optional' => false]);

        expect(array_column($analysis->properties, 'optional', 'name'))->toBe(['a' => true, 'b' => true, 'c' => false]);
    });
});

it("lists the names each class on a property's channels publishes under, and none for a retyped enum it holds", function () {
    $analysis = new MethodAnalysis(
        nestedResources: ['post' => 'Workbench\App\Http\Resources\PostResource'],
        directEnumFqcns: ['status' => 'Workbench\App\Enums\Status', 'tone' => 'Workbench\App\Enums\UiTone'],
        modelFqcns: ['user' => 'Workbench\App\Models\User'],
        inlineEnumFqcns: ['tones' => ['Workbench\App\Enums\UiTone']],
        multiEnumResourceFqcns: ['wrapped' => ['Workbench\App\Enums\UiTone']],
    );

    expect($analysis->channelNames('status'))->toBe(['Status', 'StatusType', 'StatusKind'])
        ->and($analysis->channelNames('post'))->toBe(['PostResource'])
        ->and($analysis->channelNames('user'))->toBe(['User'])
        ->and($analysis->channelNames('tone'))->toBe([])
        ->and($analysis->channelNames('tones'))->toBe([])
        // A wrap spells the enum's own names, whatever a value of the enum publishes.
        ->and($analysis->channelNames('wrapped'))->toBe(['UiTone', 'UiToneType', 'UiToneKind'])
        ->and($analysis->channelNames('none'))->toBe([]);
});

it('names the inline channel each class is queued on, and none for a class of no such kind', function (string $fqcn, ?string $kind) {
    expect(MethodAnalysis::channelKind($fqcn))->toBe($kind)
        ->and(MethodAnalysis::queuesEmbedded($fqcn))->toBe($kind !== null);
})->with([
    'an enum' => ['Workbench\App\Enums\Status', 'enums'],
    'a model' => ['Workbench\App\Models\User', 'models'],
    'an API resource' => ['Workbench\App\Http\Resources\PostResource', 'resources'],
    'a resource collection' => ['Workbench\App\Http\Resources\SupplierCollection', 'resources'],
    'any other class' => ['Carbon\Carbon', null],
]);

it('lists a key\'s classes outside its inline queues, and the enums it wraps at its top', function () {
    $analysis = new MethodAnalysis(
        enumResources: ['k' => 'Workbench\App\Enums\Status'],
        nestedResources: ['k' => 'Workbench\App\Http\Resources\PostResource'],
        directEnumFqcns: ['k' => 'Workbench\App\Enums\Role'],
        modelFqcns: ['k' => 'Workbench\App\Models\User'],
        inlineEnumFqcns: ['k' => ['Workbench\App\Enums\Season']],
        inlineModelFqcns: ['k' => ['Workbench\App\Models\Post']],
        multiEnumResourceFqcns: ['k' => ['Workbench\App\Enums\Priority']],
        inlineEnumResourceFqcns: ['k' => ['Workbench\App\Enums\UiTone']],
    );

    expect($analysis->singleChannelFqcns('k'))->toBe([
        'Workbench\App\Enums\Status',
        'Workbench\App\Enums\Role',
        'Workbench\App\Enums\Priority',
        'Workbench\App\Models\User',
        'Workbench\App\Http\Resources\PostResource',
    ])->and($analysis->topWrappedEnumFqcns('k'))->toBe(['Workbench\App\Enums\Status', 'Workbench\App\Enums\Priority'])
        ->and($analysis->wrappedEnumFqcns('k'))->toBe([
            'Workbench\App\Enums\Status', 'Workbench\App\Enums\Priority', 'Workbench\App\Enums\UiTone',
        ])
        ->and($analysis->singleChannelFqcns('none'))->toBe([]);
});

it('replaces a key\'s inline queues with the classes requeued, each keyed by itself too', function () {
    $analysis = new MethodAnalysis(
        inlineEnumFqcns: ['k' => ['Workbench\App\Enums\Season'], 'other' => ['Workbench\App\Enums\Season']],
        inlineModelFqcns: ['k' => ['Workbench\App\Models\Post']],
        inlineResourceFqcns: ['k' => ['Workbench\App\Http\Resources\PostResource']],
    );

    $analysis->requeueInline('k', ['Workbench\App\Enums\Role', 'Workbench\App\Models\User']);

    expect($analysis->inlineEnumFqcns)->toBe([
        'other' => ['Workbench\App\Enums\Season'],
        'k' => ['Workbench\App\Enums\Role'],
    ])->and($analysis->inlineModelFqcns)->toBe(['k' => ['Workbench\App\Models\User']])
        ->and($analysis->inlineResourceFqcns)->toBe([])
        ->and($analysis->directEnumFqcns)->toBe(['Workbench\App\Enums\Role' => 'Workbench\App\Enums\Role'])
        ->and($analysis->modelFqcns)->toBe(['Workbench\App\Models\User' => 'Workbench\App\Models\User']);
});

it('writes a spread key over the held one where the spread always writes it, and joins one it may leave out', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('id', ['type' => 'number', 'optional' => false]);
    $analysis->addProperty('by', ['type' => 'User', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\User']);
    $analysis->addProperty('at', ['type' => 'Post', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\Post']);

    $analysis->writeSpreadKeys([
        ['id', ['type' => 'string', 'optional' => false]],
        ['by', ['type' => 'string', 'optional' => true]],
        ['at', ['type' => 'string', 'optional' => false]],
        ['note', ['type' => 'string', 'optional' => true]],
    ], ['type' => '{ id: string; by?: string; at: string; note?: string }', 'optional' => false, 'customImports' => ['@/x' => ['X']]]);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe([
        'id' => 'string', 'by' => 'User | string', 'at' => 'string', 'note' => 'string',
    ])->and(array_column($analysis->properties, 'optional', 'name'))->toBe([
        'id' => false, 'by' => false, 'at' => false, 'note' => true,
    ])->and($analysis->modelFqcns)->toBe(['by' => 'Workbench\App\Models\User'])
        ->and($analysis->customImports)->toBe(['@/x' => ['X']])
        ->and(array_column($analysis->properties, 'name'))->toBe(['id', 'by', 'at', 'note']);
});

// PHP's later write replaces the value where the first stood, so a join counts the key once per branch.
it('writes a key over every entry held under its name, where the first stood, with none of their channels', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('by', ['type' => 'User', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\User']);
    $analysis->addProperty('id', ['type' => 'number', 'optional' => false]);
    $analysis->addProperty('by', ['type' => 'Post', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\Post']);

    $analysis->overwriteProperty('by', ['type' => 'string', 'optional' => false], optional: true);
    $analysis->overwriteProperty('at', ['type' => 'number', 'optional' => false]);

    expect($analysis->properties)->toBe([
        ['name' => 'by', 'type' => 'string', 'optional' => true, 'description' => '', 'absent' => true],
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
        ['name' => 'at', 'type' => 'number', 'optional' => false, 'description' => ''],
    ])->and($analysis->modelFqcns)->toBe([]);
});

it('reads each key once, as its last write where its first stood, and every entry of a signature', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('by', ['type' => 'User', 'optional' => false]);
    $analysis->addSignatureEntry('[key: string]', ['type' => 'number', 'optional' => false]);
    $analysis->addProperty('id', ['type' => 'number', 'optional' => false]);
    $analysis->addProperty('by', ['type' => 'string', 'optional' => true]);
    $analysis->addSignatureEntry('[key: string]', ['type' => 'boolean', 'optional' => false]);

    expect(array_map(static fn (array $property): string => $property['name'].': '.$property['type'], $analysis->writtenProperties()))
        ->toBe(['by: string', '[key: string]: number | undefined', 'id: number', '[key: string]: boolean | undefined']);
});

it('writes a spread key over the held one with none of the held value\'s inline classes left', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('row', ['type' => '{ u: User }', 'optional' => false, 'embeddedModelFqcns' => ['Workbench\App\Models\User']]);

    $analysis->writeSpreadKeys(
        [['row', ['type' => '{ u: User }', 'optional' => false, 'embeddedModelFqcns' => ['Workbench\Crm\Models\User']]]],
        ['type' => '{ row: { u: User } }', 'optional' => false],
    );

    expect($analysis->inlineModelFqcns)->toBe(['row' => ['Workbench\Crm\Models\User']]);
});

it('keeps the held value of a key a spread may leave out where both name classes, or either is untyped', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('by', ['type' => 'User', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\User']);
    $analysis->addProperty('n', ['type' => 'number', 'optional' => false]);
    $analysis->addProperty('raw', ['type' => 'unknown', 'optional' => false]);

    $analysis->writeSpreadKeys([
        ['by', ['type' => 'Post', 'optional' => true, 'modelFqcn' => 'Workbench\App\Models\Post']],
        ['n', ['type' => 'unknown', 'optional' => true]],
        ['raw', ['type' => 'string', 'optional' => true]],
    ], ['type' => '{ by?: Post; n?: unknown; raw?: string }', 'optional' => false]);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['by' => 'User', 'n' => 'number', 'raw' => 'unknown'])
        ->and($analysis->modelFqcns)->toBe(['by' => 'Workbench\App\Models\User']);
});

it('keeps what both sides of a joined key leave open', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('by', ['type' => 'string | null', 'optional' => false, 'openReadings' => ["\0string | null"]]);

    $analysis->writeSpreadKeys(
        [['by', ['type' => '{ a: number }', 'optional' => true, 'openReadings' => ["\0{ a: number }"]]]],
        ['type' => '{ by?: { a: number } }', 'optional' => false],
    );

    expect($analysis->openReadings)->toBe(['by' => ["\0string | null", "\0{ a: number }"]])
        ->and(array_column($analysis->properties, 'name'))->toBe(['by']);
});

it('joins each named key held with the value a spread of a map may write over it', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('id', ['type' => 'number', 'optional' => false]);
    $analysis->addProperty('by', ['type' => 'User', 'optional' => true, 'modelFqcn' => 'Workbench\App\Models\User']);
    $analysis->addSignatureEntry('[key: `x_${string}`]', ['type' => 'boolean', 'optional' => false]);

    $analysis->joinHeldKeys(['type' => 'string', 'optional' => false], ['type' => 'Record<string, string>', 'optional' => false]);
    $untouched = clone $analysis;
    $analysis->joinHeldKeys(['type' => 'unknown', 'optional' => false], ['type' => 'Record<string, unknown>', 'optional' => false]);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe([
        'id' => 'number | string', 'by' => 'User | string', '[key: `x_${string}`]' => 'boolean | undefined',
    ])->and(array_column($analysis->properties, 'optional', 'name'))->toBe([
        'id' => false, 'by' => true, '[key: `x_${string}`]' => false,
    ])->and($analysis->modelFqcns)->toBe(['by' => 'Workbench\App\Models\User'])
        ->and($analysis->properties)->toBe($untouched->properties);
});

// The arm prints over the keys before it, so a key it always writes goes, and one it may write joins the held value.
it('gives up each held key a later arm always writes, and joins one it may write', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('id', ['type' => 'number', 'optional' => false]);
    $analysis->addProperty('by', ['type' => 'User', 'optional' => false, 'modelFqcn' => 'Workbench\App\Models\User']);

    $analysis->yieldToSpreadArm([
        ['by', ['type' => 'string', 'optional' => false]],
        ['id', ['type' => 'string', 'optional' => true]],
        ['extra', ['type' => 'string', 'optional' => false]],
    ], ['type' => '{ by: string; id?: string; extra: string }', 'optional' => false]);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['id' => 'number | string'])
        ->and(array_column($analysis->properties, 'name'))->toBe(['id'])
        ->and($analysis->modelFqcns)->toBe([]);
});
