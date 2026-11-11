<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ObjectMembers;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * An analysis holding each value under its key, as a walk records it.
 *
 * @param  array<string, array<string, mixed>>  $members
 */
function analysisOf(array $members): MethodAnalysis
{
    $analysis = new MethodAnalysis;

    foreach ($members as $name => $result) {
        $analysis->addProperty($name, $result);
    }

    return $analysis;
}

it('reads an inline object back into keys that carry their own classes', function () {
    $value = new InlineObjectResult()->of(analysisOf([
        'author' => ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
        'status' => ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
        'note' => ['type' => 'string', 'optional' => true],
    ]));
    $analysis = ObjectMembers::analysis($value);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['author' => 'User', 'status' => 'StatusType', 'note' => 'string'])
        ->and($analysis->modelFqcns)->toBe(['author' => User::class])
        ->and($analysis->directEnumFqcns)->toBe(['status' => Status::class])
        ->and(array_column($analysis->properties, 'optional', 'name')['note'])->toBeTrue();
});

it('round-trips an inline object through InlineObjectResult unchanged', function () {
    $value = new InlineObjectResult()->of(analysisOf([
        'a' => ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
        'b' => ['type' => 'User | Post', 'optional' => false, 'embeddedModelFqcns' => [User::class, Post::class]],
    ]));

    expect(new InlineObjectResult()->of(ObjectMembers::analysis($value)))->toEqual($value);
});

it('round-trips every channel it reads, a quoted key and a nested object that keeps its keys', function () {
    $value = new InlineObjectResult()->of(analysisOf([
        'can-edit' => ['type' => 'boolean', 'optional' => true],
        'é' => ['type' => 'UserResource | null', 'optional' => false, 'resourceFqcn' => UserResource::class],
        'name' => ['type' => 'StatusKind', 'optional' => false, 'directEnumFqcn' => Status::class],
        'pair' => ['type' => 'StatusType | GradeType', 'optional' => false, 'embeddedEnumFqcns' => [Status::class, Grade::class]],
        'nested' => new InlineObjectResult()->of(analysisOf([
            'by' => ['type' => 'User[]', 'optional' => false, 'modelFqcn' => User::class],
        ])),
        'price' => ['type' => 'Money', 'optional' => false, 'customImports' => ['@/types/money' => ['Money']]],
        'kept' => ['type' => 'string', 'optional' => false, 'carriedFqcns' => ['models' => [Post::class]]],
    ]));

    expect($value['type'])->toContain('"can-edit"?: boolean', '"\\u00e9": UserResource | null', 'nested: { by: User[] }')
        ->and($value['keepsKeys'] ?? false)->toBeTrue()
        ->and(new InlineObjectResult()->of(ObjectMembers::analysis($value)))->toEqual($value);
});

it('round-trips a member that spells only its enum\'s Kind, with the enum behind it', function () {
    $value = new InlineObjectResult()->of(analysisOf([
        'name' => ['type' => 'StatusKind', 'optional' => false, 'directEnumFqcn' => Status::class],
    ]));

    expect($value['embeddedEnumFqcns'] ?? [])->toBe([Status::class])
        ->and(ObjectMembers::analysis($value)->directEnumFqcns)->toBe(['name' => Status::class])
        ->and(new InlineObjectResult()->of(ObjectMembers::analysis($value)))->toEqual($value);
});

it('hands two same-named classes out member by member, a nested object\'s on its embedded queue', function () {
    $analysis = ObjectMembers::analysis([
        'type' => '{ a: User; b: { c: User | null }; d: UserResource[]; e: UserResource }',
        'optional' => false,
        'embeddedModelFqcns' => [CrmUser::class, User::class],
        'embeddedResourceFqcns' => [CrmUserResource::class, UserResource::class],
    ]);

    expect($analysis->modelFqcns)->toBe(['a' => CrmUser::class, User::class => User::class])
        ->and($analysis->inlineModelFqcns)->toBe(['b' => [User::class]])
        ->and($analysis->nestedResources)->toBe(['d' => CrmUserResource::class, 'e' => UserResource::class]);
});

it('reads an enum behind its Kind token from the queue its Type token reads', function () {
    $analysis = ObjectMembers::analysis([
        'type' => '{ value: StatusType; name: StatusKind | null }',
        'optional' => false,
        'embeddedEnumFqcns' => [Status::class, CrmStatus::class],
    ]);

    expect($analysis->directEnumFqcns)->toBe(['value' => Status::class, 'name' => CrmStatus::class]);
});

it('queues a member that spells two tokens on its embedded channel, and the value\'s own class first', function () {
    $analysis = ObjectMembers::analysis([
        'type' => '{ a: User; b: Post | User[] }',
        'optional' => false,
        'modelFqcn' => User::class,
        'embeddedModelFqcns' => [Post::class, User::class],
    ]);

    expect($analysis->modelFqcns)->toBe(['a' => User::class, Post::class => Post::class, User::class => User::class])
        ->and($analysis->inlineModelFqcns)->toBe(['b' => [Post::class, User::class]]);
});

it('reads a bare or quoted number key as its string', function () {
    $analysis = ObjectMembers::analysis(['type' => '{ 0: string; "1"?: number }', 'optional' => false]);

    expect(array_column($analysis->properties, 'optional', 'name'))->toBe(['0' => false, '1' => true]);
});

it('reads a single-quoted or escaped key back as the key it names', function () {
    $analysis = ObjectMembers::analysis(['type' => "{ 'a-b': number; \"a\\/b\"?: string; 'it\\'s': boolean }", 'optional' => false]);

    expect(array_column($analysis->properties, 'optional', 'name'))->toBe(['a-b' => false, 'a/b' => true, "it's" => false])
        ->and(new InlineObjectResult()->of($analysis)['type'])->toBe('{ "a-b": number; "a\\/b"?: string; "it\'s": boolean }');
});

it('carries the custom imports and the carried classes whole', function () {
    $analysis = ObjectMembers::analysis([
        'type' => '{ a: Money; b: string }',
        'optional' => false,
        'customImports' => ['@/types/money' => ['Money']],
        'carriedFqcns' => ['models' => [User::class]],
    ]);

    expect($analysis->customImports)->toBe(['@/types/money' => ['Money']])
        ->and($analysis->carried)->toBe(['models' => [User::class]]);
});

it('declines what is not one object literal', function (string $type) {
    expect(ObjectMembers::analysis(['type' => $type, 'optional' => false]))->toBeNull();
})->with(['{ a: number } | null', 'string[]', 'Record<string, number>', '{ a: number; [key: string]: unknown }', 'unknown']);

it('declines an object with no key or a key it cannot read back', function (string $type) {
    expect(ObjectMembers::analysis(['type' => $type, 'optional' => false]))->toBeNull();
})->with(['{}', '{ a }', '{ -1: number }', '{ "\\x41": number }', '{ [key: `${string}_tag`]: string | undefined }']);

it('declines a value carrying an EnumResource wrap', function () {
    expect(ObjectMembers::analysis(['type' => '{ s: AsEnum<typeof Status> }', 'optional' => false, 'enumFqcn' => Status::class]))
        ->toBeNull();
});

it('declines a value carrying any EnumResource channel', function (array $channel) {
    expect(ObjectMembers::analysis(['type' => '{ s: AsEnum<typeof Status> }', 'optional' => false, ...$channel]))
        ->toBeNull();
})->with([
    'embedded' => [['embeddedEnumResourceFqcns' => [Status::class]]],
    'per arm' => [['multiEnumResourceFqcns' => [Status::class, CrmStatus::class]]],
]);

it('declines a value whose queue holds two same-named classes for fewer tokens', function () {
    expect(ObjectMembers::analysis([
        'type' => '{ a: User }',
        'optional' => false,
        'embeddedModelFqcns' => [CrmUser::class, User::class],
    ]))->toBeNull();
});

it('seeds an analysis with the members analysis() reads where each keeps its text', function () {
    $value = ['type' => '{ author: User; status: StatusType }', 'optional' => false, 'modelFqcn' => User::class, 'embeddedEnumFqcns' => [Status::class]];

    expect(ObjectMembers::seed($value, carriesImports: true))->toEqual(ObjectMembers::analysis($value))
        ->and(ObjectMembers::seed(['type' => '{ id: number }', 'optional' => false], carriesImports: false))
        ->toEqual(ObjectMembers::analysis(['type' => '{ id: number }', 'optional' => false]));
});

it('seeds nothing where a member would lose its text', function (array $value, bool $carriesImports, array $published) {
    PublishedModelRegistry::register($published);

    expect(ObjectMembers::seed($value, $carriesImports))->toBeNull();
})->with([
    'a model no generated file exports' => [
        ['type' => '{ author: User }', 'optional' => false, 'modelFqcn' => User::class], true, [Post::class],
    ],
    'a class in an analysis with no imports' => [
        ['type' => '{ status: StatusType }', 'optional' => false, 'directEnumFqcn' => Status::class], false, [],
    ],
]);

it('hands out each member as the value it holds, with the classes behind its own tokens', function () {
    $value = new InlineObjectResult()->of(analysisOf([
        'author' => ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
        'note' => ['type' => 'string', 'optional' => true],
    ]));

    expect(ObjectMembers::members($value))->toBe([
        ['author', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]],
        ['note', ['type' => 'string', 'optional' => true]],
    ])->and(ObjectMembers::members(['type' => '{ [key: string]: string }', 'optional' => false]))->toBeNull();
});

// A printed `?` cannot tell an unset key from a removed value, so it keeps the removable reading the old map gave it.
it('reads a printed ? back as a value that may be removed', function () {
    $analysis = ObjectMembers::analysis(['type' => '{ a: number; b?: string }', 'optional' => false]);

    expect(array_map(
        static fn (array $property): array => array_intersect_key($property, ['optional' => true, 'absent' => true, 'removed' => true]),
        $analysis?->properties ?? [],
    ))->toBe([['optional' => false], ['optional' => true, 'removed' => true]]);
});

// A writer that may leave a key unset keeps the value held before it there, unlike a removed value.
it('marks each key one its writer may leave unset', function () {
    expect(ObjectMembers::eachOptional([['a', ['type' => 'number', 'optional' => false]]]))
        ->toBe([['a', ['type' => 'number', 'optional' => true, 'absent' => true]]]);
});

// A member written where its object stood keeps what its own value leaves open, under its own path.
it('hands each member the readings its value leaves open, and leaves the object\'s own to it', function () {
    $value = ['type' => '{ "a-b": { x: number | null }; c: string }', 'optional' => false, 'openReadings' => [
        "\0{ \"a-b\": { x: number | null }; c: string }",
        ".\"a-b\"\0{ x: number | null }",
        ".\"a-b\".x\0number | null",
        ".c2\0string",
    ]];

    expect(ObjectMembers::members($value))->toBe([
        ['a-b', ['type' => '{ x: number | null }', 'optional' => false, 'openReadings' => ["\0{ x: number | null }", ".x\0number | null"]]],
        ['c', ['type' => 'string', 'optional' => false]],
    ])->and(ObjectMembers::analysis($value)?->openReadings)->toBe([]);
});

it('hands out the members a seed would start with, and none where a member would lose its text', function () {
    $value = ['type' => '{ a: User }', 'optional' => false, 'modelFqcn' => User::class];

    expect(ObjectMembers::seededMembers($value, carriesImports: true))->toBe([['a', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]]])
        ->and(ObjectMembers::seededMembers($value, carriesImports: false))->toBeNull()
        ->and(ObjectMembers::seededMembers(['type' => '{ a: string }', 'optional' => false], carriesImports: false))
        ->toBe([['a', ['type' => 'string', 'optional' => false]]]);
});

// Openness is a fact of the value, never of a mark: a marked object still names every key it holds.
it('reads a value as open where it may hold a key its type does not name', function (array $value, bool $open) {
    expect(ObjectMembers::isOpen($value))->toBe($open);
})->with([
    'a closed object' => [['type' => '{ a: string }', 'optional' => false], false],
    'a literal holding a key no name names' => [['type' => '{ a: string }', 'optional' => false, 'unnamedKeys' => true], true],
    'a signature beside a named key' => [['type' => '{ [key: string]: string; a: string }', 'optional' => false], true],
    'a lone signature, which is a map' => [['type' => '{ [key: string]: string }', 'optional' => false], false],
    'a Record' => [['type' => 'Record<string, string>', 'optional' => false], false],
    'an object a mark says is open' => [['type' => '{ a: string }', 'optional' => false, 'openReadings' => ["\0{ a: string }"]], false],
]);

// A printed `?` cannot say whether its key may be unset or its value removed, which a spread over a held key must.
it('reads a `?` member as InlineObjectResult::of() recorded it, else as either, for a spread', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('unset', ['type' => 'string', 'optional' => false], optional: true);
    $analysis->addProperty('removed', ['type' => 'string', 'optional' => true]);
    $analysis->addProperty('kept', ['type' => 'string', 'optional' => false]);
    $printed = new InlineObjectResult()->of($analysis);
    $bare = ['type' => '{ unset?: string; removed?: string; open?: string }', 'optional' => false];
    $facts = static fn (?array $members): array => array_map(
        static fn (array $member): string => $member[0].':'.(($member[1]['optional'] ? 'o' : '').(($member[1]['absent'] ?? false) ? 'a' : '').(($member[1]['removed'] ?? false) ? 'r' : '')),
        $members ?? [],
    );

    expect($printed['keyPresence'] ?? null)->toBe([$printed['type'] => ['unset' => ['absent' => true], 'removed' => ['removed' => true]]])
        ->and($facts(ObjectMembers::writtenMembers($printed)))->toBe(['unset:oa', 'removed:o', 'kept:'])
        ->and($facts(ObjectMembers::writtenMembers($bare)))->toBe(['unset:oar', 'removed:oar', 'open:oar'])
        ->and($facts(ObjectMembers::writtenMembers([...$bare, 'absentKeys' => true])))->toBe(['unset:oa', 'removed:oa', 'open:oa'])
        ->and($facts(ObjectMembers::members($bare)))->toBe(['unset:o', 'removed:o', 'open:o']);
});

it('marks every `?` member of the keys resolve() returns, or a declared shape holds, as one it may leave unset', function () {
    $value = ObjectMembers::withOptionalKeysAbsent(['type' => '{ a: string; b?: number } | null', 'optional' => false]);
    $union = ObjectMembers::withOptionalKeysAbsent(['type' => '{ a?: string } | { b?: number } | never[]', 'optional' => false]);

    expect($value['keyPresence'] ?? null)->toBe(['{ a: string; b?: number }' => ['b' => ['absent' => true]]])
        ->and($union['keyPresence'] ?? null)->toBe([
            '{ a?: string }' => ['a' => ['absent' => true]],
            '{ b?: number }' => ['b' => ['absent' => true]],
        ]);
});
