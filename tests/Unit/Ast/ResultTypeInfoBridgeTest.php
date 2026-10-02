<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ResultTypeInfoBridge;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

it('lists each class once, and adds no token queue while every class has a name of its own', function () {
    $info = (new ResultTypeInfoBridge)->toTypeInfo([
        'type' => '{ a: User; b: Comment; c: User }',
        'optional' => false,
        'embeddedModelFqcns' => [User::class, Comment::class, User::class],
    ]);

    expect($info['type'])->toBe('{ a: User; b: Comment; c: User }')
        ->and($info['classFqcns'])->toBe([User::class, Comment::class])
        ->and($info)->not->toHaveKey('classTokenFqcns');
});

it('adds the class behind each token when the type spells one name for two classes', function () {
    $info = (new ResultTypeInfoBridge)->toTypeInfo([
        'type' => '{ first: User | null; either: User | User | null }',
        'optional' => false,
        'embeddedModelFqcns' => [User::class, User::class, CrmUser::class],
    ]);

    expect($info['classFqcns'])->toBe([User::class, CrmUser::class])
        ->and($info['classes'])->toBe(['User', 'User'])
        ->and($info['classTokenFqcns'])->toBe([User::class, User::class, CrmUser::class]);
});

it('adds no token queue when the queue outruns the tokens, as a merge by text leaves it', function () {
    $info = (new ResultTypeInfoBridge)->toTypeInfo([
        'type' => '{ lead: User | string; author: User }',
        'optional' => false,
        'embeddedModelFqcns' => [CrmUser::class, CrmUser::class, User::class],
    ]);

    expect($info['classFqcns'])->toBe([CrmUser::class, User::class])
        ->and($info)->not->toHaveKey('classTokenFqcns');
});

it('reads the first class from modelFqcn when the result carries it there, ahead of the embedded ones', function () {
    $info = (new ResultTypeInfoBridge)->toTypeInfo([
        'type' => 'User | { a: User }',
        'optional' => false,
        'modelFqcn' => User::class,
        'embeddedModelFqcns' => [CrmUser::class],
    ]);

    expect($info['classFqcns'])->toBe([User::class, CrmUser::class])
        ->and($info['classTokenFqcns'])->toBe([User::class, CrmUser::class]);
});
