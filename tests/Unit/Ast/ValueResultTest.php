<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\OpaqueHandle;
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

describe('ValueResult::spellsTwoClassesAlike()', function () {
    test('is true only when one name spells two different models or two different resources', function () {
        $app = ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class];
        $crm = ['type' => 'User', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]];
        $resource = ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class];
        $crmResource = ['type' => 'UserResource', 'optional' => false, 'embeddedResourceFqcns' => [CrmUserResource::class]];

        expect(ValueResult::spellsTwoClassesAlike([$app, $crm]))->toBeTrue()
            ->and(ValueResult::spellsTwoClassesAlike([$resource, $crmResource]))->toBeTrue()
            ->and(ValueResult::spellsTwoClassesAlike([$app, $app, $resource]))->toBeFalse()
            ->and(ValueResult::spellsTwoClassesAlike([['type' => 'string', 'optional' => false]]))->toBeFalse();
    });
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
