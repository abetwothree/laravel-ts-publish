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
// A plain class sends a list where the keys are 0, 1, …; a re-indexing resource wherever only numeric keys remain.
it('applies each element write to every arm of an array type', function (string $type, array $writes, string $written, bool $reindexes = false) {
    expect(ArrayArm::written($type, $writes, $reindexes))->toBe($written);
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
    'a member named by 5 left as the one key, which a plain class sends as an object' => [
        '{ a: number }',
        [arrayArmWrite('5', 'string'), arrayArmWrite('a', null)],
        '{ a?: number; "5"?: string } | never[]',
    ],
    'a member named by 0 left as the one key, which admits the list it is sent as' => [
        '{ a: number }',
        [arrayArmWrite('0', 'string'), arrayArmWrite('a', null)],
        '{ a?: number; "0"?: string } | never[]',
    ],
    'a member named by 5 left as the one key in a re-indexing resource' => [
        '{ a: number }',
        [arrayArmWrite('5', 'string'), arrayArmWrite('a', null)],
        '{ a?: number; "5"?: string } | string[]',
        true,
    ],
    'a lone object keyed only by numbers, which keeps its members' => [
        '{ "1": string; "2": number }',
        [arrayArmWrite('k', 'boolean')],
        '{ "1": string; "2": number; k?: boolean }',
    ],
    'a lone object keyed only by numbers in a re-indexing resource' => [
        '{ "5": string }',
        [arrayArmWrite('k', 'number')],
        '{ "5": string; k?: number } | string[]',
        true,
    ],
    'two objects each always holding a named key, which no write leaves a list' => [
        '{ a: number } | { b: string }',
        [arrayArmWrite(ElementKey::Any, 'number')],
        '{ a?: number; b?: string | number; [key: string]: number | string | undefined }',
        true,
    ],
    'an object keyed only by numbers beside another, which a plain class keeps by name' => [
        '{ a: number } | { "5": string }',
        [arrayArmWrite('k', 'number')],
        '{ a?: number; "5"?: string; k?: number }',
    ],
    'an object keyed only by numbers beside another in a re-indexing resource' => [
        '{ a: number } | { "5": string }',
        [arrayArmWrite('k', 'number')],
        '{ a?: number; k?: number } & Record<number, string>',
        true,
    ],
    'unsets that may shift a later number down in a re-indexing resource' => [
        '{ a: number; "0": string; "1": string }',
        [arrayArmWrite('a', null), arrayArmWrite('0', null)],
        '{ a?: number; "0"?: string; "1": string } | string[]',
        true,
    ],
    'a number added after those always held in a re-indexing resource, which the members admit' => [
        '{ a: number; "0": string }',
        [arrayArmWrite('a', null), arrayArmWrite('1', 'number')],
        '{ a?: number; "0": string; "1"?: number }',
        true,
    ],
    'two optional numbers of different types in a re-indexing resource, either of which may land at 0' => [
        '{ a: number }',
        [arrayArmWrite('a', null), arrayArmWrite('0', 'string'), arrayArmWrite('1', 'number')],
        '{ a?: number; "0"?: string; "1"?: number } | (string | number)[]',
        true,
    ],
    'two objects holding numbers of different types in different orders, in a re-indexing resource' => [
        '{ a: number; "0": string; "1": number } | { a: number; "1": number; "0": string }',
        [arrayArmWrite('a', null)],
        '{ a?: number; "0": string; "1": number } | (string | number)[]',
        true,
    ],
    'a number one object holds before another\'s first, in a re-indexing resource' => [
        '{ a: number; "0": string } | { a: number; "1": number; "0": string }',
        [arrayArmWrite('a', null)],
        '{ a?: number; "0": string; "1"?: number } | (string | number)[]',
        true,
    ],
    'two objects in different orders, one holding a number more, in a re-indexing resource' => [
        '{ a: number; "0": string; "1": number } | { a: number; "1": number; "0": string; "2": boolean }',
        [arrayArmWrite('a', null)],
        '{ a?: number; "0": string; "1": number; "2"?: boolean } | (string | number | boolean)[]',
        true,
    ],
    'two objects holding numbers of one type in different orders, which the members admit in a re-indexing resource' => [
        '{ a: number; "0": string; "1": string } | { a: number; "1": string; "0": string; "2": string }',
        [arrayArmWrite('a', null)],
        '{ a?: number; "0": string; "1": string; "2"?: string }',
        true,
    ],
    'two objects holding numbers in different orders, which a plain class sends keyed as they are' => [
        '{ a: number; "0": string; "1": number } | { a: number; "1": number; "0": string }',
        [arrayArmWrite('a', null)],
        '{ a?: number; "0": string; "1": number }',
    ],
    'two members holding numbers in different orders, reached by an unset inside, in a re-indexing resource' => [
        '{ a: { k: number; "0": string; "1": number } } | { a: { k: number; "1": number; "0": string } }',
        [arrayArmWrite('k', null, ['a'])],
        '{ a: { k?: number; "0": string; "1": number } | (string | number)[] }',
        true,
    ],
    'two members two levels down holding numbers in different orders, in a re-indexing resource' => [
        '{ a: { b: { k: number; "0": string; "1": number } } } | { a: { b: { k: number; "1": number; "0": string } } }',
        [arrayArmWrite('k', null, ['a', 'b'])],
        '{ a: { b: { k?: number; "0": string; "1": number } | (string | number)[] } }',
        true,
    ],
    'two objects of one type each holding two numbers, which the members admit in a re-indexing resource' => [
        '{ a: number; "0": string; "1": string } | { a: number; "1": string; "2": string }',
        [arrayArmWrite('a', null)],
        '{ a?: number; "0"?: string; "1": string; "2"?: string }',
        true,
    ],
    'two objects of one type, one holding a single number that lands at 0, in a re-indexing resource' => [
        '{ a: number; "0": string; "1": string; "2": string } | { a: number; "1": string }',
        [arrayArmWrite('a', null)],
        '{ a?: number; "0"?: string; "1": string; "2"?: string } | string[]',
        true,
    ],
    'two objects of one type each holding two numbers, one of which an unset may remove, in a re-indexing resource' => [
        '{ a: number; "0": string; "1": string } | { a: number; "1": string; "2": string }',
        [arrayArmWrite('a', null), arrayArmWrite('0', null)],
        '{ a?: number; "0"?: string; "1": string; "2"?: string } | string[]',
        true,
    ],
    'any key set beside an object keyed from 5, which a plain class never sends as a list' => [
        '{ a: number } | { "5": string }',
        [arrayArmWrite(ElementKey::Any, 'number')],
        '{ a?: number; "5"?: string | number; [key: string]: number | string | undefined }',
    ],
    'any key set beside an object keyed only by numbers in a re-indexing resource' => [
        '{ a: number } | { "5": string }',
        [arrayArmWrite(ElementKey::Any, 'number')],
        '{ a?: number; [key: string]: number | string | undefined } | (string | number)[]',
        true,
    ],
    'a key set two levels into members named by numbers in a re-indexing resource' => [
        '{ a: number; b: { "3": { x: number } } }',
        [arrayArmWrite('y', 'number', ['b', '3'])],
        '{ a: number; b: { "3": { x: number; y?: number } } | { x: number; y?: number }[] }',
        true,
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

    expect(ArrayArm::written('{ a: number }', [arrayArmWrite(ElementKey::Any, 'number'), arrayArmWrite('a', null)], false))
        ->toBe('{ a?: number; [key: string]: number | undefined } | number[]')
        ->and(json_encode($sent))->toBe('[2]');
});

it('declines a write on an arm that is no array, or through a member an arm may not hold', function (string $type, array $writes) {
    expect(ArrayArm::written($type, $writes, false))->toBeNull();
})->with([
    'a nullable list' => ['string[] | null', [arrayArmWrite(ElementKey::Next, 'string')]],
    'a tuple' => ['[string, number]', [arrayArmWrite(ElementKey::Next, 'string')]],
    'a member the object lacks' => ['{ a: { b: number } }', [arrayArmWrite('c', 'number', ['z'])]],
    'an optional member' => ['{ a?: { b: number } }', [arrayArmWrite('c', 'number', ['a'])]],
    'a member of a list' => ['string[][]', [arrayArmWrite('c', 'number', ['0'])]],
    'a member holding no array' => ['{ a: string }', [arrayArmWrite('c', 'number', ['a'])]],
]);
