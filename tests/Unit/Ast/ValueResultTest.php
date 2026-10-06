<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\OpaqueHandle;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

describe('ValueResult::withAttributeChannels()', function () {
    test('carries each FQCN kind on the channel its count picks', function (array $attribute, array $channels) {
        $result = ['type' => 'T', 'optional' => false];

        expect(ValueResult::withAttributeChannels($result, $attribute))->toBe([...$result, ...$channels]);
    })->with([
        'nothing to carry' => [['enumFqcns' => [], 'classFqcns' => []], []],
        'one enum' => [['enumFqcns' => [Status::class], 'classFqcns' => []], ['directEnumFqcn' => Status::class]],
        'several enums' => [
            ['enumFqcns' => [Status::class, Priority::class], 'classFqcns' => []],
            ['embeddedEnumFqcns' => [Status::class, Priority::class]],
        ],
        'one class' => [['enumFqcns' => [], 'classFqcns' => [Comment::class]], ['modelFqcn' => Comment::class]],
        'several classes' => [
            ['enumFqcns' => [], 'classFqcns' => [Comment::class, User::class]],
            ['embeddedModelFqcns' => [Comment::class, User::class]],
        ],
        'an enum beside a class' => [
            ['enumFqcns' => [Status::class], 'classFqcns' => [User::class]],
            ['directEnumFqcn' => Status::class, 'modelFqcn' => User::class],
        ],
        'a #[TsType] import' => [
            ['enumFqcns' => [], 'classFqcns' => [], 'customImports' => ['@/types/geo' => ['GeoPoint']]],
            ['customImports' => ['@/types/geo' => ['GeoPoint']]],
        ],
        'an empty #[TsType] import map' => [['enumFqcns' => [], 'classFqcns' => [], 'customImports' => []], []],
    ]);
});

describe('ValueResult::namesOnlyExportedClasses()', function () {
    test('a result naming no class passes', function () {
        expect(ValueResult::namesOnlyExportedClasses(['type' => 'string', 'optional' => false]))->toBeTrue();
    });

    test('with no published set every model passes, and a class no file is generated for never does', function () {
        expect(ValueResult::namesOnlyExportedClasses(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]))->toBeTrue()
            ->and(ValueResult::namesOnlyExportedClasses([
                'type' => 'User | OpaqueHandle', 'optional' => false, 'embeddedModelFqcns' => [User::class, OpaqueHandle::class],
            ]))->toBeFalse();
    });

    test('with a published set a model outside it fails on either channel', function () {
        PublishedModelRegistry::register([User::class]);

        expect(ValueResult::namesOnlyExportedClasses(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]))->toBeTrue()
            ->and(ValueResult::namesOnlyExportedClasses(['type' => 'Comment', 'optional' => false, 'modelFqcn' => Comment::class]))->toBeFalse()
            ->and(ValueResult::namesOnlyExportedClasses([
                'type' => 'User | Comment', 'optional' => false, 'embeddedModelFqcns' => [User::class, Comment::class],
            ]))->toBeFalse();
    });
});

describe('ValueResult::namesOnlyPublishedModels()', function () {
    test('with no published set a framework or abstract model is still known to have no file', function () {
        expect(ValueResult::namesOnlyPublishedModels(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]))->toBeTrue()
            ->and(ValueResult::namesOnlyPublishedModels([
                'type' => 'DatabaseNotification', 'optional' => false, 'modelFqcn' => DatabaseNotification::class,
            ]))->toBeFalse()
            ->and(ValueResult::namesOnlyPublishedModels(['type' => 'Model', 'optional' => false, 'modelFqcn' => Model::class]))->toBeFalse();
    });

    test('with a published set the set alone decides, so a published framework model passes', function () {
        PublishedModelRegistry::register([DatabaseNotification::class]);

        expect(ValueResult::namesOnlyPublishedModels([
            'type' => 'DatabaseNotification', 'optional' => false, 'modelFqcn' => DatabaseNotification::class,
        ]))->toBeTrue()
            ->and(ValueResult::namesOnlyPublishedModels(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]))->toBeFalse()
            ->and(ValueResult::namesOnlyPublishedModels(['type' => 'Model', 'optional' => false, 'modelFqcn' => Model::class]))->toBeFalse();
    });
});

describe('ValueResult::unionResults() over arms that spell one name for two classes', function () {
    test('two models sharing a name are two members, each queued in the order the type spells them', function () {
        $result = ValueResult::unionResults([
            ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class],
            ['type' => 'User | null', 'optional' => false, 'modelFqcn' => CrmUser::class],
        ]);

        expect($result)->toBe([
            'type' => 'User | User | null',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, CrmUser::class],
        ]);
    });

    test('the same model in two arms is still one member', function () {
        $result = ValueResult::unionResults([
            ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class],
            ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
        ]);

        expect($result['type'])->toBe('User | User | null')
            ->and($result['embeddedModelFqcns'])->toBe([User::class, CrmUser::class]);
    });

    test('a member is told apart by the class behind its token, whatever wraps the token', function () {
        $result = ValueResult::unionResults([
            ['type' => 'User[]', 'optional' => false, 'modelFqcn' => User::class],
            ['type' => '{ owner: User; post: Comment }', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class, Comment::class]],
            ['type' => 'User[]', 'optional' => false, 'modelFqcn' => CrmUser::class],
        ]);

        expect($result['type'])->toBe('User[] | { owner: User; post: Comment } | User[]')
            ->and($result['embeddedModelFqcns'])->toBe([User::class, CrmUser::class, Comment::class, CrmUser::class]);
    });

    test('two resources sharing a name are two members as well', function () {
        $result = ValueResult::unionResults([
            ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class],
            ['type' => 'UserResource | null', 'optional' => false, 'resourceFqcn' => CrmUserResource::class],
        ]);

        expect($result)->toBe([
            'type' => 'UserResource | UserResource | null',
            'optional' => false,
            'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class],
        ]);
    });

    test('arms whose classes each have a name of their own are merged by their text, as before', function () {
        $result = ValueResult::unionResults([
            ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class],
            ['type' => 'Comment', 'optional' => false, 'modelFqcn' => Comment::class],
            ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
        ]);

        expect($result)->toBe([
            'type' => 'User | Comment | null',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, Comment::class],
        ]);
    });
});

// A union is one of enum resources when its enum resources are as many as the types it counts: its arms' types, or its
// members where the caller says so, as a conditional default does.
describe('ValueResult::unionResults() over enum-resource branches', function () {
    $nullableSecond = [
        ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
        ['type' => 'VisibilityType | null', 'optional' => false, 'enumFqcn' => Visibility::class],
    ];
    $sameNamedPair = [
        ['type' => 'StatusType | null', 'optional' => false, 'enumFqcn' => Status::class],
        ['type' => 'StatusType | null', 'optional' => false, 'enumFqcn' => CrmStatus::class],
    ];

    test('is a union of enum resources when it counts as many types as enum resources', function (array $branches, bool $countMembers, array $union) {
        expect(ValueResult::unionResults($branches, $countMembers))->toBe($union);
    })->with([
        'two arms, one of them nullable, counted by arm' => [
            $nullableSecond,
            false,
            [
                'type' => 'StatusType | VisibilityType | null',
                'optional' => false,
                'multiEnumResourceFqcns' => [Status::class, Visibility::class],
            ],
        ],
        'a same-named pair whose arms render alike, counted by member' => [
            $sameNamedPair,
            true,
            ['type' => 'StatusType | null', 'optional' => false, 'multiEnumResourceFqcns' => [Status::class, CrmStatus::class]],
        ],
    ]);

    test('is not one when the count differs, or a branch is no enum resource', function (array $branches, bool $countMembers, array $union) {
        expect(ValueResult::unionResults($branches, $countMembers))->toBe($union);
    })->with([
        'two arms, one of them nullable, counted by member' => [
            $nullableSecond,
            true,
            [
                'type' => 'StatusType | VisibilityType | null',
                'optional' => false,
                'embeddedEnumFqcns' => [Status::class, Visibility::class],
            ],
        ],
        'a same-named pair whose arms render alike, counted by arm' => [
            $sameNamedPair,
            false,
            ['type' => 'StatusType | null', 'optional' => false, 'embeddedEnumFqcns' => [Status::class, CrmStatus::class]],
        ],
        'two arms beside a branch typed null, counted by arm' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'VisibilityType', 'optional' => false, 'enumFqcn' => Visibility::class],
                ['type' => 'null', 'optional' => false],
            ],
            false,
            [
                'type' => 'StatusType | VisibilityType | null',
                'optional' => false,
                'embeddedEnumFqcns' => [Status::class, Visibility::class],
            ],
        ],
        'two arms beside a string' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'VisibilityType', 'optional' => false, 'enumFqcn' => Visibility::class],
                ['type' => 'string', 'optional' => false],
            ],
            false,
            [
                'type' => 'StatusType | VisibilityType | string',
                'optional' => false,
                'embeddedEnumFqcns' => [Status::class, Visibility::class],
            ],
        ],
        'an arm beside a direct read of another enum' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'VisibilityType', 'optional' => false, 'directEnumFqcn' => Visibility::class],
            ],
            false,
            ['type' => 'StatusType | VisibilityType', 'optional' => false, 'embeddedEnumFqcns' => [Status::class, Visibility::class]],
        ],
    ]);
});

describe('ValueResult::unionResults() over model, resource and class-less arms', function () {
    $app = ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class];
    $crm = ['type' => 'User', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]];
    $resource = ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class];
    $crmResource = ['type' => 'UserResource', 'optional' => false, 'embeddedResourceFqcns' => [CrmUserResource::class]];

    test('reads two models or two resources under one name by class, and any other arms by their text', function (array $arms, array $union) {
        expect(ValueResult::unionResults($arms))->toBe($union);
    })->with([
        'a model on the single channel and another on the embedded one' => [
            [$app, $crm],
            ['type' => 'User | User', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
        ],
        'a resource on the single channel and another on the embedded one' => [
            [$resource, $crmResource],
            [
                'type' => 'UserResource | UserResource',
                'optional' => false,
                'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class],
            ],
        ],
        'one model twice, beside a resource under another name' => [
            [$app, $app, $resource],
            [
                'type' => 'User | UserResource',
                'optional' => false,
                'embeddedModelFqcns' => [User::class],
                'embeddedResourceFqcns' => [UserResource::class],
            ],
        ],
        'an arm that names no class' => [
            [['type' => 'string', 'optional' => false]],
            ['type' => 'string', 'optional' => false],
        ],
    ]);
});

describe('ValueResult::modelQueueByToken()', function () {
    test('answers with the model queue when two models share a name and it lines up with the type', function (array $result, array $queue) {
        expect(ValueResult::modelQueueByToken($result))->toBe($queue);
    })->with([
        'one entry per token, two classes under one name' => [
            [
                'type' => '{ first: User | null; either: User | User | null }',
                'optional' => false,
                'embeddedModelFqcns' => [User::class, User::class, CrmUser::class],
            ],
            [User::class, User::class, CrmUser::class],
        ],
        'the single channel ahead of the embedded one' => [
            [
                'type' => 'User | { a: User }',
                'optional' => false,
                'modelFqcn' => User::class,
                'embeddedModelFqcns' => [CrmUser::class],
            ],
            [User::class, CrmUser::class],
        ],
    ]);

    test('answers with nothing otherwise', function (array $result) {
        expect(ValueResult::modelQueueByToken($result))->toBeNull();
    })->with([
        'every model has a name of its own' => [
            [
                'type' => '{ a: User; b: Comment; c: User }',
                'optional' => false,
                'embeddedModelFqcns' => [User::class, Comment::class, User::class],
            ],
        ],
        'a queue that outruns the tokens' => [
            [
                'type' => '{ lead: User | string; author: User }',
                'optional' => false,
                'embeddedModelFqcns' => [CrmUser::class, CrmUser::class, User::class],
            ],
        ],
        'two resources under one name, and no two models' => [
            [
                'type' => 'User | UserResource | UserResource',
                'optional' => false,
                'modelFqcn' => User::class,
                'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class],
            ],
        ],
        'no class at all' => [['type' => 'string', 'optional' => false]],
    ]);
});

describe('ValueResult::withAttributeChannels() and a per-token class queue', function () {
    test('carries the queue in place of the class list when the attribute has one', function () {
        $result = ValueResult::withAttributeChannels(['type' => 'T', 'optional' => false], [
            'enumFqcns' => [],
            'classFqcns' => [User::class, CrmUser::class],
            'classTokenFqcns' => [User::class, User::class, CrmUser::class],
        ]);

        expect($result['embeddedModelFqcns'])->toBe([User::class, User::class, CrmUser::class]);
    });
});

describe('ValueResult::withEnumArmShapes()', function () {
    $mixed = [
        'type' => 'StatusType[] | StatusType',
        'optional' => false,
        'enumFqcn' => Status::class,
        'directEnumFqcn' => Status::class,
    ];

    test('records which arm of a mixed union is a list, whatever the order of the arms', function (array $arms, bool $wrapIsCollection, bool $directIsArray) use ($mixed) {
        expect(ValueResult::withEnumArmShapes($mixed, $arms))
            ->toBe([...$mixed, 'wrapIsCollection' => $wrapIsCollection, 'directIsArray' => $directIsArray]);
    })->with([
        'a list wrap and a scalar direct read' => [
            [
                ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
            ],
            true,
            false,
        ],
        'a scalar direct read and a list wrap' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class],
            ],
            true,
            false,
        ],
        'a scalar wrap and a list direct read' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'StatusType[]', 'optional' => false, 'directEnumFqcn' => Status::class],
            ],
            false,
            true,
        ],
        'a nullable list on each side' => [
            [
                ['type' => 'StatusType[] | null', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'StatusType[] | null', 'optional' => false, 'directEnumFqcn' => Status::class],
            ],
            true,
            true,
        ],
        'wraps that agree, and an arm holding no enum' => [
            [
                ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => 'null', 'optional' => false],
            ],
            true,
            false,
        ],
    ]);

    test('leaves the union as it is when the arms cannot be attributed', function (array $arms) use ($mixed) {
        expect(ValueResult::withEnumArmShapes($mixed, $arms))->toBe($mixed);
    })->with([
        'an arm carrying both channels' => [[
            ['type' => 'StatusType[] | StatusType', 'optional' => false, 'enumFqcn' => Status::class, 'directEnumFqcn' => Status::class],
            ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
        ]],
        'wrap arms that disagree' => [[
            ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
            ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class],
            ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
        ]],
        'direct arms that disagree' => [[
            ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
            ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
            ['type' => 'StatusType[]', 'optional' => false, 'directEnumFqcn' => Status::class],
        ]],
        'no arm that reads the enum directly' => [[
            ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class],
        ]],
        'no arm that wraps the enum' => [[
            ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
        ]],
    ]);

    test('does not resolve the arms of a union that is not a mixed one of a single enum', function (array $union) {
        $arms = fn (): array => throw new LogicException('the arms must not be resolved');

        expect(ValueResult::withEnumArmShapes($union, $arms))->toBe($union);
    })->with([
        'a union with no enum' => [['type' => 'string | number', 'optional' => false]],
        'a union that only wraps' => [['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
        'a union that only reads directly' => [['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class]],
        'a wrap and a direct read of two enums' => [[
            'type' => 'StatusType | PriorityType',
            'optional' => false,
            'enumFqcn' => Status::class,
            'directEnumFqcn' => Priority::class,
        ]],
    ]);

    test('reads the arms of a mixed union from a closure as it does from a list', function () use ($mixed) {
        $arms = [
            ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class],
            ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
        ];

        expect(ValueResult::withEnumArmShapes($mixed, fn (): array => $arms))->toBe(ValueResult::withEnumArmShapes($mixed, $arms))
            ->and(ValueResult::withEnumArmShapes($mixed, fn (): array => $arms))->toHaveKeys(['wrapIsCollection', 'directIsArray']);
    });
});

describe('ValueResult::withNullArm() and hasNullArm()', function () {
    // Two classes can share a name, so the arm is appended to the text, never merged into it.
    test('append one top-level null arm and read only a top-level one', function (string $type, string $withArm, bool $hasArm) {
        expect(ValueResult::withNullArm($type))->toBe($withArm)
            ->and(ValueResult::hasNullArm($type))->toBe($hasArm);
    })->with([
        'two classes spelled alike, never de-duplicated' => ['User | User', 'User | User | null', false],
        'unknown, which already admits null' => ['unknown', 'unknown', false],
        'a leading null arm' => ['null | string', 'null | string', true],
        'a null inside an element type, which is not top-level' => ['(X | null)[]', '(X | null)[] | null', false],
        'a trailing null arm' => ['string | null', 'string | null', true],
    ]);
});
