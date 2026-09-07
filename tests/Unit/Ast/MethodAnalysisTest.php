<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;

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
            // The three inline queues keep repeats: aliasPropertyType() consumes them positionally.
            ->and($analysis->inlineEnumFqcns)->toBe(['status' => ['Workbench\App\Enums\Role', 'Workbench\App\Enums\Role']])
            ->and($analysis->inlineModelFqcns)->toBe(['status' => ['Workbench\App\Models\User', 'Workbench\Crm\Models\User', 'Workbench\App\Models\User']])
            ->and($analysis->inlineEnumResourceFqcns)->toBe(['status' => ['Workbench\App\Enums\Season']])
            ->and($analysis->customImports)->toBe(['@/types/x' => ['XType']]);
    });

    test('optional is the union of the caller flag and the value result', function () {
        $analysis = new MethodAnalysis;
        $analysis->addProperty('a', ['type' => 'string', 'optional' => true]);
        $analysis->addProperty('b', ['type' => 'string', 'optional' => false], optional: true);
        $analysis->addProperty('c', ['type' => 'string', 'optional' => false]);

        expect(array_column($analysis->properties, 'optional', 'name'))->toBe(['a' => true, 'b' => true, 'c' => false]);
    });
});
