<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ObjectMembers;
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
})->with(['{}', '{ a }', "{ 'a-b': number }", '{ [key: `${string}_tag`]: string | undefined }']);

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
