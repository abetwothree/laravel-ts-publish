<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Members\ArrayArm;
use AbeTwoThree\LaravelTsPublish\Ast\Members\ElementKey;

/**
 * One write ArrayArm applies: the value set at the key the path reaches, or null for an unset().
 *
 * @phpstan-import-type ArrayWrite from ArrayArm
 *
 * @param  list<string>  $path
 * @return ArrayWrite
 */
function arrayArmWrite(string|ElementKey $key, ?string $value, array $path = []): array
{
    return ['path' => $path, 'key' => $key, 'value' => $value];
}

// A write may run in any order, any number of times or not at all, so each arm keeps what it held beside what it gains.
it('applies each element write to every arm of an array type', function (string $type, array $writes, string $written) {
    expect(ArrayArm::written($type, $writes))->toBe($written);
})->with([
    'a string key set on a list' => ['string[]', [arrayArmWrite('k', 'number')], 'string[] | ({ k?: number } & Record<number, string>)'],
    'a list element unset' => ['string[]', [arrayArmWrite(ElementKey::Any, null)], 'string[] | Record<number, string>'],
    'an int key set on a list, which may leave a gap' => [
        'string[]',
        [arrayArmWrite('3', 'number')],
        '(string | number)[] | Record<number, string | number>',
    ],
    'any key set on a list' => ['string[]', [arrayArmWrite(ElementKey::Any, 'number')], '(string | number)[] | Record<string, string | number>'],
    'an append onto an object' => ['{ a: string }', [arrayArmWrite(ElementKey::Next, 'string')], '{ a: string } & Record<number, string>'],
    'any key set twice on an object, which may overwrite a member' => [
        '{ a: boolean }',
        [arrayArmWrite(ElementKey::Any, 'number'), arrayArmWrite(ElementKey::Any, 'string')],
        '{ a: boolean | number | string; [key: string]: boolean | number | string }',
    ],
    'a literal key set after any key, which a later run of that write may overwrite' => [
        '{ a: boolean }',
        [arrayArmWrite(ElementKey::Any, 'number'), arrayArmWrite('k', 'string')],
        '{ a: boolean | number; k?: string | number; [key: string]: boolean | number | string | undefined }',
    ],
    'every member unset, which PHP sends as `[]`' => [
        '{ a: number; b: number }',
        [arrayArmWrite('a', null), arrayArmWrite('b', null)],
        '{ a?: number; b?: number } | never[]',
    ],
    'any key unset on an object' => ['{ a: number }', [arrayArmWrite(ElementKey::Any, null)], '{ a?: number } | never[]'],
    'an unset beside an append, whose number index admits `[]`' => [
        '{ a: number }',
        [arrayArmWrite(ElementKey::Next, 'string'), arrayArmWrite('a', null)],
        '{ a?: number } & Record<number, string>',
    ],
    'an unset of a key the object never holds' => ['{ a: number }', [arrayArmWrite('z', null)], '{ a: number }'],
    'every element rewritten by reference' => [
        '{ a: string; b: string }',
        [arrayArmWrite(ElementKey::Every, 'number')],
        '{ a: string | number; b: string | number }',
    ],
    'a key set inside a member' => ['{ a: { b: number } }', [arrayArmWrite('c', 'string', ['a'])], '{ a: { b: number; c?: string } }'],
    'every key unset inside a member' => ['{ a: { b: number } }', [arrayArmWrite('b', null, ['a'])], '{ a: { b?: number } | never[] }'],
    'a member keyed by a number beside an append' => [
        '{ a: number; "5": string }',
        [arrayArmWrite(ElementKey::Next, 'boolean')],
        '{ a: number } & Record<number, boolean | string>',
    ],
    'a member named by a number left as the one key' => [
        '{ a: number }',
        [arrayArmWrite('5', 'string'), arrayArmWrite('a', null)],
        '{ a?: number; "5"?: string } | string[]',
    ],
    'an object keyed only by numbers beside another' => [
        '{ a: number } | { 5: string }',
        [arrayArmWrite('k', 'number')],
        '{ a?: number; k?: number } & Record<number, string>',
    ],
    'any key set beside an object keyed only by numbers, which no unset need leave a list' => [
        '{ a: number } | { 5: string }',
        [arrayArmWrite(ElementKey::Any, 'number')],
        '{ a?: number; [key: string]: number | string | undefined } | (string | number)[]',
    ],
    'a key set on each of two objects' => [
        '{ a: number } | { b: string }',
        [arrayArmWrite('c', 'boolean')],
        '{ a?: number; b?: string; c?: boolean }',
    ],
]);

it('publishes the list PHP sends an object an unset leaves keyed only by numbers 0, 1, …', function () {
    $sent = ['a' => 1];
    $sent['0'] = 2;
    unset($sent['a']);

    expect(ArrayArm::written('{ a: number }', [arrayArmWrite(ElementKey::Any, 'number'), arrayArmWrite('a', null)]))
        ->toBe('{ a?: number; [key: string]: number | undefined } | number[]')
        ->and(json_encode($sent))->toBe('[2]');
});

it('declines a write on an arm that is no array, or through a member an arm may not hold', function (string $type, array $writes) {
    expect(ArrayArm::written($type, $writes))->toBeNull();
})->with([
    'a nullable list' => ['string[] | null', [arrayArmWrite(ElementKey::Next, 'string')]],
    'a tuple' => ['[string, number]', [arrayArmWrite(ElementKey::Next, 'string')]],
    'a member the object lacks' => ['{ a: { b: number } }', [arrayArmWrite('c', 'number', ['z'])]],
    'an optional member' => ['{ a?: { b: number } }', [arrayArmWrite('c', 'number', ['a'])]],
    'a member of a list' => ['string[][]', [arrayArmWrite('c', 'number', ['0'])]],
    'a member holding no array' => ['{ a: string }', [arrayArmWrite('c', 'number', ['a'])]],
]);
