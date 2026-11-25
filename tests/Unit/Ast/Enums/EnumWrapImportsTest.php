<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumWrapImports;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\Crm\Enums\Status as CrmStatus;

describe('written', function () {
    test('keeps the entries whose const the type writes after typeof', function () {
        expect(EnumWrapImports::written('{ a: AsEnum<typeof Status> }', [Status::class, Visibility::class]))
            ->toBe([Status::class]);
    });

    test('keeps two same-named entries for two tokens, in order', function () {
        expect(EnumWrapImports::written('AsEnum<typeof Status> | AsEnum<typeof Status>', [Status::class, CrmStatus::class]))
            ->toBe([Status::class, CrmStatus::class]);
    });

    // A name a union merged into one token keeps its first class; the other's const would be imported for nothing.
    test('takes one entry per token, never more', function () {
        expect(EnumWrapImports::written('AsEnum<typeof Status>', [Status::class, CrmStatus::class]))->toBe([Status::class]);
    });

    test('lists the entries in the order the type writes their consts', function () {
        expect(EnumWrapImports::written(
            '{ v: AsEnum<typeof Visibility>; s: AsEnum<typeof Status> }',
            [Status::class, Visibility::class],
        ))->toBe([Visibility::class, Status::class]);
    });

    test('reads a token spelling the alias the file gives an entry\'s const as that entry', function () {
        expect(EnumWrapImports::written(
            'AsEnum<typeof CrmStatus> | AsEnum<typeof Status>',
            [Status::class, CrmStatus::class],
            [CrmStatus::class => 'CrmStatus'],
        ))->toBe([CrmStatus::class, Status::class]);
    });

    test('reads neither a bare type name nor a longer name as a const', function () {
        expect(EnumWrapImports::written('Status | StatusType | AsEnum<typeof StatusKind>', [Status::class]))->toBe([]);
    });
});

describe('writtenIn', function () {
    test('reads each key\'s queue against its own type, keeping each enum once', function () {
        expect(EnumWrapImports::writtenIn(
            ['a' => 'AsEnum<typeof Status>', 'b' => 'string', 'c' => 'AsEnum<typeof CrmStatus> | AsEnum<typeof Status>'],
            ['a' => [Status::class], 'b' => [Visibility::class], 'c' => [Status::class, CrmStatus::class], 'gone' => [Visibility::class]],
            [CrmStatus::class => 'CrmStatus'],
        ))->toBe([Status::class, CrmStatus::class]);
    });
});

describe('consts', function () {
    test('lists every const the types spell after typeof, once each, in order', function () {
        expect(EnumWrapImports::consts('AsEnum<typeof A> | { b: AsEnum<typeof B>[] }'))->toBe(['A', 'B'])
            ->and(EnumWrapImports::consts('(typeof B)[keyof typeof B]', 'AsEnum<typeof A> | AsEnum<typeof B>'))
            ->toBe(['B', 'A']);
    });

    test('reads no name a type spells bare or after a member access', function () {
        expect(EnumWrapImports::consts('Status | StatusType | ns.typeof | xtypeof Status'))->toBe([]);
    });
});

describe('needsAsEnum', function () {
    test('answers whether a type spells AsEnum<', function (array $types, bool $needs) {
        expect(EnumWrapImports::needsAsEnum(...$types))->toBe($needs);
    })->with([
        'an enum type name' => [['StatusType'], false],
        'a wrap in a member' => [['number', '{ a: AsEnum<typeof Status> }'], true],
        'a longer name' => [['MyAsEnum<typeof Status>'], false],
        'a member access' => [['ns.AsEnum<typeof Status>'], false],
    ]);
});
