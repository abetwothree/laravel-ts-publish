<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReplacedParameter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SeededEnumMembers;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\SeededRows;
use Workbench\App\ValueObjects\GuardedLinkPayload;

it('joins a helper\'s writes onto the shape of the array it is passed', function () {
    $flagged = new GuardedLinkPayload;
    $flagged->flag = true;

    expect(analyzedKeyTypes(GuardedLinkPayload::class))->toBe([
        'id' => 'number', 'name' => 'string', 'url' => 'string | null', 'disabled' => 'boolean',
    ])->and(json_encode(new GuardedLinkPayload()->toArray()))
        ->toBe('{"id":1,"name":"x","url":"https:\/\/example.com","disabled":false}')
        ->and(json_encode($flagged->toArray()))->toBe('{"id":1,"name":"x","url":null,"disabled":true}');
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

it('seeds nothing past a top-level unset() of the parameter', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'values')['cleared'])->toBe('{ x: number }')
        ->and(json_encode(new ReplacedParameter()->values()['cleared']))->toBe('{"x":1}');
});

// Each leaves keys the walk cannot read, a known gap, but never the argument's `id`.
it('seeds nothing past a top-level whole write the walk does not read as an assignment', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'values'))->toMatchArray([
        'destructured' => '{ x: number }',
        'sorted' => '{ x: number }',
        'matched' => '{ x: number }',
        'looped' => '{ x: number }',
    ])->and(json_encode(array_intersect_key(
        new ReplacedParameter()->values(),
        array_flip(['destructured', 'sorted', 'matched', 'looped']),
    )))->toBe(
        '{"destructured":{"k":1,"x":1},"sorted":{"0":1,"x":1},"matched":{"0":"a","k":"a","1":"a","x":1},'
        .'"looped":{"k":1,"x":1}}',
    );
});

// The walk does not read an assignment inside a condition, so `k` stays unread there, but `id` never comes back.
it('seeds nothing past an assignment inside another expression', function () {
    $values = new ReplacedParameter()->values();

    expect(analyzedKeyTypes(ReplacedParameter::class, 'values'))->toMatchArray([
        'chained' => '{ k: number; x: number }',
        'inCondition' => 'never[] | { x: number }',
    ])->and(json_encode($values['chained']))->toBe('{"k":1,"x":1}')
        ->and(json_encode($values['inCondition']))->toBe('{"k":1,"x":1}');
});

it('seeds nothing past an array_splice(), whose keys publish open', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'values')['spliced'])->toBe('{ x: number; [key: string]: unknown }')
        ->and(json_encode(new ReplacedParameter()->values()['spliced']))->toBe('{"0":5,"id":1,"x":1}');
});

// The walk still keeps `id` required past the unset() that may not run, though it removes the key where it runs.
it('keeps the seed past a key-keeping sort, an unset() of one key, an unset() that may not run, a ??= and an array_push()', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'kept'))->toBe([
        'ksorted' => '{ id: number; x: number }',
        'trimmed' => '{ id: number; x: number }',
        'maybeCleared' => '{ id: number; x: number }',
        'coalesced' => '{ id: number; x: number }',
        'pushed' => '{ id: number; x: number; [key: string]: unknown }',
    ])->and(json_encode(new ReplacedParameter()->kept()))->toBe(
        '{"ksorted":{"id":1,"x":1},"trimmed":{"id":1,"x":1},"maybeCleared":{"id":1,"x":1},"coalesced":{"id":1,"x":1},'
        .'"pushed":{"id":1,"0":5,"x":1}}',
    );
});

it('keeps the seed past a ??= in the method\'s own return', function () {
    expect(analyzedKeyTypes(ReplacedParameter::class, 'coalescedOwn'))->toBe(['id' => 'number', 'x' => 'number'])
        ->and(json_encode(new ReplacedParameter()->coalescedOwn()))->toBe('{"id":1,"x":1}');
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
