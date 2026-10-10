<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;

it('is the base class ResourceAnalysis now extends, as an empty subclass', function () {
    expect(new ResourceAnalysis)->toBeInstanceOf(MethodAnalysis::class)
        ->and(new ReflectionClass(ResourceAnalysis::class)->getParentClass()->getName())->toBe(MethodAnalysis::class);
});

it('appends properties from the source, never replacing a same-name earlier entry', function () {
    $target = new MethodAnalysis(properties: [
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
    ]);
    $source = new MethodAnalysis(properties: [
        ['name' => 'id', 'type' => 'string', 'optional' => true, 'description' => ''],
    ]);

    $target->merge($source);

    expect($target->properties)->toBe([
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
        ['name' => 'id', 'type' => 'string', 'optional' => true, 'description' => ''],
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
    $target = new MethodAnalysis(casts: ['k' => ['type' => 'A', 'import' => true], 'kept' => ['type' => 'K', 'import' => true]]);
    $target->merge(new MethodAnalysis(casts: ['k' => ['type' => 'B', 'import' => false], 'owner' => ['type' => 'D', 'import' => true]]));

    expect($target->casts)->toBe([
        'k' => ['type' => 'B', 'import' => false],
        'kept' => ['type' => 'K', 'import' => true],
        'owner' => ['type' => 'D', 'import' => true],
    ]);
});

it('forgets the cast entry of a key another value took over, and no other key\'s', function () {
    $analysis = new MethodAnalysis(casts: ['taken_over' => ['type' => 'A', 'import' => true], 'kept' => ['type' => 'B', 'import' => false]]);

    $analysis->forgetChannels('taken_over');

    expect($analysis->casts)->toBe(['kept' => ['type' => 'B', 'import' => false]]);
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
            'name' => 'status', 'type' => 'StatusType | null', 'optional' => true, 'description' => 'doc',
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
            ['name' => 'post', 'type' => 'unknown', 'optional' => true, 'description' => ''],
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
