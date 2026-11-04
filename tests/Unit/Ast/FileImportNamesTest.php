<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Composition\FileImportNames;
use Workbench\App\Models\User;

test('declaredTokens() gives each own name\'s token the next slot, an unread one\'s too, and another name\'s none', function () {
    $tokens = FileImportNames::declaredTokens("{ kind: 'User'; r: User | User; p: Post }", [null, null, User::class], ['Post']);

    expect(array_map(fn (array $token): array => [$token['token'], $token['slot'], $token['own'], $token['read']], $tokens))->toBe([
        ['User', null, true, false],
        ['User', null, true, true],
        ['User', User::class, true, true],
        ['Post', null, false, true],
    ]);
});

test('declaredTokens() reads no token where the slots hold no class and no other name is asked for', function () {
    expect(FileImportNames::declaredTokens('User | Post', []))->toBe([]);
});

test('declaredArms() gives each top-level arm the slots its own names\' tokens take, in order', function () {
    expect(FileImportNames::declaredArms('User | User[] | string | null', [null, User::class]))->toBe([
        ['User', [null]],
        ['User[]', [User::class]],
        ['string', []],
        ['null', []],
    ])->and(FileImportNames::declaredArms('string | number', []))->toBe([['string', []], ['number', []]]);
});
