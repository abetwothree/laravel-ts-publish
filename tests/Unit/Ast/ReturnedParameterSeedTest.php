<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReplacedParameter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SeededEnumMembers;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SeededRows;
use Workbench\App\ValueObjects\GuardedLinkPayload;

it('joins a helper\'s writes onto the shape of the array it is passed', function () {
    expect(analyzedKeyTypes(GuardedLinkPayload::class))->toBe([
        'id' => 'number', 'name' => 'string', 'url' => 'string | null', 'disabled' => 'boolean',
    ])->and(json_encode(new GuardedLinkPayload()->toArray()))
        ->toBe('{"id":1,"name":"x","url":"https:\/\/example.com","disabled":false}');
});

it('seeds a parameter read as a value, a += and a declared shape', function () {
    expect(analyzedKeyTypes(GuardedLinkPayload::class, 'values'))->toBe([
        'nested' => '{ id: number; url: string | null; disabled: boolean }',
        'seen' => '{ id: number; seen: boolean }',
        'declared' => '{ id: number; name: string; extra: boolean }',
        'opaque' => '{ url?: null; disabled?: boolean; [key: string]: unknown }',
        'rebuilt' => '{ fresh: number; more: boolean }',
    ]);
});

it('starts from the helper\'s own whole write, not the argument', function () {
    expect(analyzedKeyTypes(GuardedLinkPayload::class, 'values')['rebuilt'])->toBe('{ fresh: number; more: boolean }')
        ->and(json_encode(new GuardedLinkPayload()->values()['rebuilt']))->toBe('{"fresh":1,"more":true}');
});

// The decoded array's own keys stay unread, but the argument's `id` never reaches the return.
it('seeds nothing past a top-level whole write the walk cannot read', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class))->toBe(['x' => 'number'])
        ->and(json_encode(new ReplacedParameter()->toArray()))->toBe('{"k":1,"x":1}');
});

it('seeds a map callback\'s parameter from its element', function () {
    expect(analyzedKeyTypes(SeededRows::class)['rows'])->toBe('{ id: number; seen: boolean }[]')
        ->and(json_encode(new SeededRows()->toArray()))->toBe('{"rows":[{"id":1,"seen":true}]}');
});

it('keeps two same-named enums apart through a seed', function () {
    expect(analyzedKeyTypes(SeededEnumMembers::class, 'pair'))->toBe([
        'a' => 'WorkbenchStatusType', 'b' => 'CrmStatusType', 'stamped' => 'boolean',
    ])->and(analyzedImportNames(SeededEnumMembers::class, 'pair'))
        ->toBe(['StatusType as CrmStatusType', 'StatusType as WorkbenchStatusType'])
        ->and(json_encode(new SeededEnumMembers()->pair()))->toBe('{"a":0,"b":"lead","stamped":true}');
});

// ObjectMembers cannot hand a same-named pair out member by member once one sits a level down.
it('keeps only the written keys where the seed cannot place a same-named pair', function () {
    expect(analyzedKeyTypes(SeededEnumMembers::class, 'nestedPair'))->toBe(['stamped' => 'boolean'])
        ->and(json_encode(new SeededEnumMembers()->nestedPair()))->toBe('{"a":0,"inner":{"b":"lead"},"stamped":true}');
});

it('keeps the open signature where an import-less read would type a seeded class unknown', function () {
    expect(analyzedKeyTypes(SeededEnumMembers::class, 'values'))->toBe([
        'pair' => '{ stamped: boolean; [key: string]: unknown }',
    ])->and(json_encode(new SeededEnumMembers()->values()))->toBe('{"pair":{"a":0,"b":"lead","stamped":true}}');
});

it('keeps only the written keys where the argument holds an EnumResource wrap', function () {
    expect(analyzedKeyTypes(SeededEnumMembers::class, 'seededWrapped'))->toBe(['stamped' => 'boolean']);
});

it('seeds a wrapped enum with Tolki off as the argument prints alone', function () {
    config()->set('ts-publish.enums.use_tolki_package', false);

    expect(analyzedKeyTypes(SeededEnumMembers::class, 'wrapped'))->toBe(['status' => 'StatusType'])
        ->and(analyzedKeyTypes(SeededEnumMembers::class, 'seededWrapped'))
        ->toBe(['status' => 'StatusType', 'stamped' => 'boolean'])
        ->and(analyzedImportNames(SeededEnumMembers::class, 'seededWrapped'))
        ->toBe(analyzedImportNames(SeededEnumMembers::class, 'wrapped'));
});
