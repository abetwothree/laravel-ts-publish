<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Values\ClassIdentity;
use AbeTwoThree\LaravelTsPublish\Ast\Values\TokenKind;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Support\ResourceReindexing;
use Workbench\App\Enums\Grade as GradeEnum;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Grade;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

describe('TokenKind', function () {
    test('names a class the way its kind spells its token, and only an enum by a sibling name too', function (TokenKind $kind, string $fqcn, string $name, array $siblings) {
        expect($kind->nameOf($fqcn))->toBe($name)
            ->and($kind->siblingNames($fqcn))->toBe($siblings);
    })->with([
        'a model, by its basename' => [TokenKind::Model, CrmUser::class, 'User', []],
        'a resource, by its #[TsResource] name' => [TokenKind::Resource, AddressResource::class, 'Address', []],
        'a backed enum, by its type and its `…Kind`' => [TokenKind::Enum, Status::class, 'StatusType', ['StatusKind']],
        'a pure enum, by its type alone' => [TokenKind::Enum, Visibility::class, 'VisibilityType', []],
        'a wrapped enum, by its const' => [TokenKind::WrapConst, Status::class, 'Status', []],
    ]);

    test('reads a const after `typeof` and every other kind where no `typeof` reads it', function () {
        expect(TokenKind::WrapConst->anchor())->toBe('typeof\s+\K')
            ->and(array_map(
                static fn (TokenKind $kind): string => $kind->anchor(),
                [TokenKind::Model, TokenKind::Resource, TokenKind::Enum],
            ))->toBe(['', '', '']);
    });

    test('reads each kind off its single channel, then its embedded one', function () {
        $result = [
            'type' => 'T',
            'optional' => false,
            'modelFqcn' => User::class,
            'embeddedModelFqcns' => [CrmUser::class],
            'resourceFqcn' => UserResource::class,
            'embeddedResourceFqcns' => [CrmUserResource::class],
            'directEnumFqcn' => Status::class,
            'embeddedEnumFqcns' => [CrmStatus::class],
            'embeddedEnumResourceFqcns' => [Visibility::class, Status::class],
        ];

        expect(TokenKind::Model->channelFqcns($result))->toBe([User::class, CrmUser::class])
            ->and(TokenKind::Resource->channelFqcns($result))->toBe([UserResource::class, CrmUserResource::class])
            ->and(TokenKind::Enum->channelFqcns($result))->toBe([Status::class, CrmStatus::class])
            ->and(TokenKind::WrapConst->channelFqcns($result))->toBe([Visibility::class, Status::class])
            ->and(TokenKind::Model->channelFqcns(['type' => 'string', 'optional' => false]))->toBe([]);
    });

    test('gives a class the kind of channel it is queued on, never the const one', function (string $fqcn, ?TokenKind $kind) {
        expect(TokenKind::ofClass($fqcn))->toBe($kind);
    })->with([
        'an enum' => [Status::class, TokenKind::Enum],
        'a model' => [Grade::class, TokenKind::Model],
        'an API resource' => [UserResource::class, TokenKind::Resource],
        'the enum resource' => [EnumResource::class, TokenKind::Resource],
        'any other class' => ['Carbon\Carbon', null],
    ]);
});

describe('ClassIdentity::queues()', function () {
    test('gives one queue per token kind, each reading its own channels', function () {
        $result = [
            'type' => '{ a: User; b: User; r: UserResource; s: StatusType; w: AsEnum<typeof Status>; v: AsEnum<typeof Visibility> }',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, CrmUser::class],
            'resourceFqcn' => UserResource::class,
            'directEnumFqcn' => Status::class,
            'embeddedEnumResourceFqcns' => [Status::class, Visibility::class],
        ];

        $queues = ClassIdentity::queues($result);

        expect(array_keys($queues))->toBe(['models', 'resources', 'enums', 'consts'])
            ->and($queues['models']->take($result['type']))->toBe([User::class, CrmUser::class])
            ->and($queues['resources']->take($result['type']))->toBe([UserResource::class])
            ->and($queues['enums']->take($result['type']))->toBe([Status::class])
            ->and($queues['consts']->take($result['type']))->toBe([Status::class, Visibility::class]);
    });

    test('reads a model and a resource that print one name in their record\'s order, each queue keeping its own', function () {
        $queues = ClassIdentity::queues([
            'type' => '{ address: Address; raw: Address }',
            'optional' => false,
            'embeddedModelFqcns' => [Address::class],
            'embeddedResourceFqcns' => [AddressResource::class],
            'alikeClassFqcns' => [AddressResource::class, Address::class],
        ]);
        $taken = static fn (string $piece): array => [$queues['models']->take($piece), $queues['resources']->take($piece)];

        expect($taken('{ address: Address'))->toBe([[], [AddressResource::class]])
            ->and($taken('; raw: Address }'))->toBe([[Address::class], []]);
    });

    test('reads a name after `typeof` as a const, and the same name bare as a model', function () {
        $type = 'AsEnum<typeof Grade> | Grade';
        $queues = ClassIdentity::queues([
            'type' => $type,
            'optional' => false,
            'modelFqcn' => Grade::class,
            'embeddedEnumResourceFqcns' => [GradeEnum::class],
        ]);
        $mark = static fn (string $mark): Closure => static fn (string $fqcn): string => $mark;

        expect($queues['consts']->rewrite($type, $mark('C')))->toBe('AsEnum<typeof C> | Grade')
            ->and($queues['models']->rewrite($type, $mark('M')))->toBe('AsEnum<typeof Grade> | M');
    });
});

describe('ClassIdentity::spellsTwoClassesAlike()', function () {
    test('tells whether one name spells two classes of a kind it reads', function (array $results, bool $alike) {
        expect(ClassIdentity::spellsTwoClassesAlike($results))->toBe($alike);
    })->with([
        'two same-named enums read directly' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => CrmStatus::class],
            ],
            true,
        ],
        'two same-named enums read directly, beside an arm that wraps an enum' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => CrmStatus::class],
                ['type' => 'AsEnum<typeof Visibility>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Visibility::class]],
            ],
            true,
        ],
        'two same-named enums read directly, beside an arm that wraps two' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => CrmStatus::class],
                [
                    'type' => 'AsEnum<typeof Status> | AsEnum<typeof Visibility>',
                    'optional' => false,
                    'embeddedEnumResourceFqcns' => [Status::class, Visibility::class],
                ],
            ],
            true,
        ],
        'two same-named models, beside an arm that wraps an enum' => [
            [
                ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class],
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class]],
            ],
            true,
        ],
        'two consts of one enum' => [
            [
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class]],
                ['type' => 'AsEnum<typeof Status>[]', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class]],
            ],
            false,
        ],
        'two same-named consts' => [
            [
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class]],
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [CrmStatus::class]],
            ],
            true,
        ],
        'a model and a resource published under its name' => [
            [
                ['type' => 'Address | null', 'optional' => false, 'resourceFqcn' => AddressResource::class],
                ['type' => 'Address | null', 'optional' => false, 'modelFqcn' => Address::class],
            ],
            true,
        ],
        'a model and a resource under a name of its own' => [
            [
                ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class],
            ],
            false,
        ],
    ]);
});

describe('ClassIdentity::queueByToken()', function () {
    test('reads a name a model and a resource both print in the order its record gives', function () {
        expect(ClassIdentity::queueByToken([Address::class, AddressResource::class], [AddressResource::class, Address::class]))
            ->toBe([AddressResource::class, Address::class])
            ->and(ClassIdentity::queueByToken([AddressResource::class, Address::class], [Address::class, AddressResource::class]))
            ->toBe([Address::class, AddressResource::class]);
    });

    test('keeps every other class where its own kind queued it', function () {
        expect(ClassIdentity::queueByToken(
            [Status::class, User::class, Address::class, CrmUser::class, UserResource::class, AddressResource::class],
            [AddressResource::class, Address::class],
        ))->toBe([Status::class, User::class, CrmUser::class, UserResource::class, AddressResource::class, Address::class]);
    });

    test('leaves the queue as it came where no record lines up with it', function (array $queue, array $alike) {
        expect(ClassIdentity::queueByToken($queue, $alike))->toBe($queue)
            ->and(ClassIdentity::ordersAlike($queue, $alike))->toBeFalse();
    })->with([
        'no record' => [[AddressResource::class, Address::class], []],
        'a record missing a model\'s token' => [[Address::class, Address::class, AddressResource::class], [AddressResource::class, Address::class]],
        'a record naming a class the queue does not hold' => [[Address::class, AddressResource::class], [AddressResource::class, Address::class, User::class]],
        'a record naming a class of a name only one kind prints' => [
            [Address::class, CrmUser::class, AddressResource::class],
            [AddressResource::class, CrmUser::class, Address::class],
        ],
    ]);

    test('leaves the queue as it came, and its order known, where no name is printed by both kinds', function () {
        $queue = [User::class, CrmUser::class, UserResource::class];

        expect(ClassIdentity::queueByToken($queue, [UserResource::class]))->toBe($queue)
            ->and(ClassIdentity::ordersAlike($queue, []))->toBeTrue()
            ->and(ClassIdentity::ordersAlike([Address::class, AddressResource::class], [AddressResource::class, Address::class]))->toBeTrue();
    });
});

describe('ClassIdentity::membersByClass()', function () {
    $app = ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class];
    $crm = ['type' => 'User', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]];
    $resource = ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class];
    $crmResource = ['type' => 'UserResource', 'optional' => false, 'embeddedResourceFqcns' => [CrmUserResource::class]];
    $users = [User::class, CrmUser::class];
    $userResources = [UserResource::class, CrmUserResource::class];

    // Each row: the arms, whether they spell two classes alike, the arms merged by their text, and the union by class.
    test('gives a union one member per class, as unionResults() publishes it', function (array $arms, bool $alike, array $byText, array $union) {
        expect(ClassIdentity::spellsTwoClassesAlike($arms))->toBe($alike)
            ->and(ClassIdentity::membersByClass($byText, $arms))->toBe($union)
            ->and(ValueResult::unionResults($arms))->toBe($union);
    })->with([
        'two models sharing a name are two members, each queued in the order the type spells them' => [
            [
                ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'User | null', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ],
            true,
            ['type' => 'User | null', 'optional' => false, 'embeddedModelFqcns' => $users],
            ['type' => 'User | User | null', 'optional' => false, 'embeddedModelFqcns' => $users],
        ],
        'the same model in two arms is still one member' => [
            [
                ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class],
                ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
            ],
            true,
            ['type' => 'User | null', 'optional' => false, 'embeddedModelFqcns' => $users],
            ['type' => 'User | User | null', 'optional' => false, 'embeddedModelFqcns' => $users],
        ],
        'a member is told apart by the class behind its token, whatever wraps the token' => [
            [
                ['type' => 'User[]', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => '{ owner: User; post: Comment }', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class, Comment::class]],
                ['type' => 'User[]', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ],
            true,
            [
                'type' => 'User[] | { owner: User; post: Comment }',
                'optional' => false,
                'embeddedModelFqcns' => [User::class, CrmUser::class, Comment::class, CrmUser::class],
            ],
            [
                'type' => 'User[] | { owner: User; post: Comment } | User[]',
                'optional' => false,
                'embeddedModelFqcns' => [User::class, CrmUser::class, Comment::class, CrmUser::class],
            ],
        ],
        'two resources sharing a name are two members as well' => [
            [
                ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class],
                ['type' => 'UserResource | null', 'optional' => false, 'resourceFqcn' => CrmUserResource::class],
            ],
            true,
            ['type' => 'UserResource | null', 'optional' => false, 'embeddedResourceFqcns' => $userResources],
            ['type' => 'UserResource | UserResource | null', 'optional' => false, 'embeddedResourceFqcns' => $userResources],
        ],
        'two enums read directly that share a name are two members, queued per token in place of the direct one' => [
            [
                ['type' => '{ s: StatusType | null }', 'optional' => false, 'embeddedEnumFqcns' => [Status::class]],
                ['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => CrmStatus::class],
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
            ],
            true,
            [
                'type' => '{ s: StatusType | null } | StatusType | null',
                'optional' => false,
                'embeddedEnumFqcns' => [CrmStatus::class, Status::class, Status::class],
            ],
            [
                'type' => '{ s: StatusType | null } | StatusType | StatusType | null',
                'optional' => false,
                'embeddedEnumFqcns' => [Status::class, CrmStatus::class, Status::class],
            ],
        ],
        // A wrap's token is its const, read on its own queue, so the reads beside it are read by class (#76, #108).
        'two enums read directly beside an enum resource arm are two members' => [
            [
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]],
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => CrmStatus::class],
            ],
            true,
            [
                'type' => 'AsEnum<typeof Status> | StatusType | null',
                'optional' => false,
                'embeddedEnumFqcns' => [Status::class, CrmStatus::class],
                'embeddedEnumResourceFqcns' => [Status::class],
                'wrappedEnumFqcns' => [Status::class],
            ],
            [
                'type' => 'AsEnum<typeof Status> | StatusType | StatusType | null',
                'optional' => false,
                'embeddedEnumFqcns' => [Status::class, CrmStatus::class],
                'embeddedEnumResourceFqcns' => [Status::class],
                'wrappedEnumFqcns' => [Status::class],
            ],
        ],
        'two enum resource arms that share a name are two members' => [
            [
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]],
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [CrmStatus::class], 'wrappedEnumFqcns' => [CrmStatus::class]],
            ],
            true,
            [
                'type' => 'AsEnum<typeof Status>',
                'optional' => false,
                'embeddedEnumResourceFqcns' => [Status::class, CrmStatus::class],
                'wrappedEnumFqcns' => [Status::class, CrmStatus::class],
            ],
            [
                'type' => 'AsEnum<typeof Status> | AsEnum<typeof Status>',
                'optional' => false,
                'embeddedEnumResourceFqcns' => [Status::class, CrmStatus::class],
                'wrappedEnumFqcns' => [Status::class, CrmStatus::class],
            ],
        ],
        'arms whose classes each have a name of their own are merged by their text, as before' => [
            [
                ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'Comment', 'optional' => false, 'modelFqcn' => Comment::class],
                ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
            ],
            false,
            ['type' => 'User | Comment | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, Comment::class]],
            ['type' => 'User | Comment | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, Comment::class]],
        ],
        'a model on the single channel and another on the embedded one' => [
            [$app, $crm],
            true,
            ['type' => 'User', 'optional' => false, 'embeddedModelFqcns' => $users],
            ['type' => 'User | User', 'optional' => false, 'embeddedModelFqcns' => $users],
        ],
        'a resource on the single channel and another on the embedded one' => [
            [$resource, $crmResource],
            true,
            ['type' => 'UserResource', 'optional' => false, 'embeddedResourceFqcns' => $userResources],
            ['type' => 'UserResource | UserResource', 'optional' => false, 'embeddedResourceFqcns' => $userResources],
        ],
        'one model twice, beside a resource under another name' => [
            [$app, $app, $resource],
            false,
            [
                'type' => 'User | UserResource',
                'optional' => false,
                'embeddedModelFqcns' => [User::class],
                'embeddedResourceFqcns' => [UserResource::class],
            ],
            [
                'type' => 'User | UserResource',
                'optional' => false,
                'embeddedModelFqcns' => [User::class],
                'embeddedResourceFqcns' => [UserResource::class],
            ],
        ],
        'an arm that names no class' => [
            [['type' => 'string', 'optional' => false]],
            false,
            ['type' => 'string', 'optional' => false],
            ['type' => 'string', 'optional' => false],
        ],
        'a resource and a model published under one name are two members, recorded in arm order' => [
            [
                ['type' => 'Address | null', 'optional' => false, 'resourceFqcn' => AddressResource::class],
                ['type' => 'Address | null', 'optional' => false, 'modelFqcn' => Address::class],
            ],
            true,
            [
                'type' => 'Address | null',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
            ],
            [
                'type' => 'Address | Address | null',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
                'alikeClassFqcns' => [AddressResource::class, Address::class],
            ],
        ],
        'a model and a resource published under one name, the model first' => [
            [
                ['type' => 'Address', 'optional' => false, 'modelFqcn' => Address::class],
                ['type' => 'Address', 'optional' => false, 'resourceFqcn' => AddressResource::class],
                ['type' => 'Address', 'optional' => false, 'modelFqcn' => Address::class],
            ],
            true,
            [
                'type' => 'Address',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
            ],
            [
                'type' => 'Address | Address',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
                'alikeClassFqcns' => [Address::class, AddressResource::class],
            ],
        ],
        'an arm holding both reads its own record' => [
            [
                [
                    'type' => '{ address: Address; raw: Address }',
                    'optional' => false,
                    'embeddedModelFqcns' => [Address::class],
                    'embeddedResourceFqcns' => [AddressResource::class],
                    'alikeClassFqcns' => [AddressResource::class, Address::class],
                ],
                ['type' => 'Address', 'optional' => false, 'modelFqcn' => Address::class],
            ],
            true,
            [
                'type' => '{ address: Address; raw: Address } | Address',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class, Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
            ],
            [
                'type' => '{ address: Address; raw: Address } | Address',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class, Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
                'alikeClassFqcns' => [AddressResource::class, Address::class, Address::class],
            ],
        ],
    ]);

    test('reads an arm\'s record past a member that repeats an earlier arm', function () {
        $alike = [Address::class, AddressResource::class];

        expect(ValueResult::unionResults([
            ['type' => 'Address', 'optional' => false, 'modelFqcn' => Address::class],
            [
                'type' => 'Address | Address',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
                'alikeClassFqcns' => $alike,
            ],
        ]))->toBe([
            'type' => 'Address | Address',
            'optional' => false,
            'embeddedModelFqcns' => [Address::class],
            'embeddedResourceFqcns' => [AddressResource::class],
            'alikeClassFqcns' => $alike,
        ]);
    });

    test('records no order where an arm holds a model and a resource of one name and does not say whose each token is', function () {
        $union = ValueResult::unionResults([
            ['type' => 'Address', 'optional' => false, 'resourceFqcn' => AddressResource::class],
            [
                'type' => '{ a: Address; b: Address }',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
            ],
        ]);

        expect($union['type'])->toBe('Address | { a: Address; b: Address }')
            ->and($union)->not->toHaveKey('alikeClassFqcns');
    });

    test('keeps the merge by text where an arm queues a name for two classes more often than it spells it', function () {
        $byText = ['type' => 'User[] | User', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class]];

        expect(ClassIdentity::membersByClass($byText, [
            ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
            ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class],
        ]))->toBe($byText);
    });
});

describe('ClassIdentity::modelQueueByToken()', function () {
    test('answers with the model queue when two models share a name and it lines up with the type', function (array $result, array $queue) {
        expect(ClassIdentity::modelQueueByToken($result))->toBe($queue);
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
        expect(ClassIdentity::modelQueueByToken($result))->toBeNull();
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

describe('ClassIdentity::retyped()', function () {
    test('keeps each queue to the classes behind the tokens the re-indexed text spells', function (array $result, array $retyped) {
        expect(ClassIdentity::retyped($result, ResourceReindexing::reindexedType(...)))->toBe($retyped);
    })->with([
        'a filtered list of two same-named enums beside a later one' => [
            [
                'type' => '{ list: (StatusType | StatusType)[] | Record<number, StatusType | StatusType>; one: StatusType }',
                'optional' => false,
                'embeddedEnumFqcns' => [CrmStatus::class, Status::class, CrmStatus::class, Status::class, Status::class],
            ],
            [
                'type' => '{ list: (StatusType | StatusType)[]; one: StatusType }',
                'optional' => false,
                'embeddedEnumFqcns' => [CrmStatus::class, Status::class, Status::class],
            ],
        ],
        'arms of one text but other classes stay two' => [
            [
                'type' => 'StatusType[] | Record<number, StatusType>',
                'optional' => false,
                'embeddedEnumFqcns' => [CrmStatus::class, Status::class],
            ],
            [
                'type' => 'StatusType[] | StatusType[]',
                'optional' => false,
                'embeddedEnumFqcns' => [CrmStatus::class, Status::class],
            ],
        ],
        'a model and a resource of one name, its record kept to the tokens left' => [
            [
                'type' => '(Address | Address)[] | Record<number, Address | Address> | Address',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class, Address::class, Address::class],
                'embeddedResourceFqcns' => [AddressResource::class, AddressResource::class],
                'alikeClassFqcns' => [AddressResource::class, Address::class, AddressResource::class, Address::class, Address::class],
            ],
            [
                'type' => '(Address | Address)[] | Address',
                'optional' => false,
                'embeddedModelFqcns' => [Address::class, Address::class],
                'embeddedResourceFqcns' => [AddressResource::class],
                'alikeClassFqcns' => [AddressResource::class, Address::class, Address::class],
            ],
        ],
        'wraps\' consts, a single channel first and a class no token spells last' => [
            [
                'type' => 'AsEnum<typeof Status>[] | Record<number, AsEnum<typeof Status>> | VisibilityType',
                'optional' => false,
                'directEnumFqcn' => Visibility::class,
                'embeddedEnumFqcns' => [CrmStatus::class],
                'embeddedEnumResourceFqcns' => [CrmStatus::class, CrmStatus::class],
            ],
            [
                'type' => 'AsEnum<typeof Status>[] | VisibilityType',
                'optional' => false,
                'directEnumFqcn' => Visibility::class,
                'embeddedEnumFqcns' => [CrmStatus::class],
                'embeddedEnumResourceFqcns' => [CrmStatus::class],
            ],
        ],
    ]);

    test('cannot tell a token\'s class where two kinds read it and no record says whose it is', function () {
        expect(ClassIdentity::retyped([
            'type' => 'Address[] | Record<number, Address>',
            'optional' => false,
            'embeddedModelFqcns' => [Address::class],
            'embeddedResourceFqcns' => [AddressResource::class],
        ], ResourceReindexing::reindexedType(...)))->toBeNull();
    });
});
