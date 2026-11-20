<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Values\ClassIdentity;
use AbeTwoThree\LaravelTsPublish\Ast\Values\TokenKind;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Grade as GradeEnum;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\UserResource;
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
                ['type' => 'VisibilityType', 'optional' => false, 'enumFqcn' => Visibility::class],
            ],
            false,
        ],
        'two same-named enums read directly, beside an arm that wraps two' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => CrmStatus::class],
                ['type' => 'StatusType | VisibilityType', 'optional' => false, 'multiEnumResourceFqcns' => [Status::class, Visibility::class]],
            ],
            false,
        ],
        'two same-named models, beside an arm that wraps an enum' => [
            [
                ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class],
                ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
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
        'two same-named consts, a kind no reading reads yet' => [
            [
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class]],
                ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [CrmStatus::class]],
            ],
            false,
        ],
    ]);
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
        // An enum resource's token holds no queue entry, since the AsEnum rewrite spells it (#76, #108).
        'two enums read directly beside an enum resource arm stay merged by their text' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => CrmStatus::class],
            ],
            false,
            ['type' => 'StatusType | null', 'optional' => false, 'embeddedEnumFqcns' => [Status::class, CrmStatus::class]],
            ['type' => 'StatusType | null', 'optional' => false, 'embeddedEnumFqcns' => [Status::class, CrmStatus::class]],
        ],
        'two enum resource arms that share a name stay merged by their text' => [
            [
                ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class],
                ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => CrmStatus::class],
            ],
            false,
            ['type' => 'StatusType', 'optional' => false, 'embeddedEnumFqcns' => [Status::class, CrmStatus::class]],
            ['type' => 'StatusType', 'optional' => false, 'embeddedEnumFqcns' => [Status::class, CrmStatus::class]],
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
    ]);

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
