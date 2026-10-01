<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\OpaqueHandle;

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
