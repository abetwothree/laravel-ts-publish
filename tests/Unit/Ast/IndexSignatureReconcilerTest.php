<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use Workbench\App\Http\Resources\PostResource;

/**
 * @param  list<array{0: string, 1: string, 2?: string}>  $entries  name, type, and the body type a fill replaced
 */
function reconciled(array $entries, ?Closure $prepare = null): MethodAnalysis
{
    $analysis = new MethodAnalysis(properties: array_map(
        fn (array $e): array => [
            'name' => $e[0],
            'type' => $e[1],
            'optional' => false,
            'description' => '',
            ...(isset($e[2]) ? ['bodyType' => $e[2]] : []),
        ],
        $entries,
    ));

    if ($prepare !== null) {
        $prepare($analysis);
    }

    resolve(IndexSignatureReconciler::class)->reconcile($analysis);

    return $analysis;
}

/**
 * @return array<string, string>
 */
function reconciledTypes(MethodAnalysis $analysis): array
{
    return array_column($analysis->properties, 'type', 'name');
}

const TAG_SIGNATURE = '[key: `${string}_tag`]';
const UNTYPED_SIGNATURE_VALUE = 'unknown | undefined';

test('same-pattern signatures fold into the first, whose value unions both and remembers the last body value', function () {
    $analysis = reconciled([[TAG_SIGNATURE, 'string | undefined'], ['id', 'number'], [TAG_SIGNATURE, 'number | undefined', UNTYPED_SIGNATURE_VALUE]]);

    expect($analysis->properties)->toHaveCount(2)
        ->and($analysis->properties[0])->toMatchArray(['name' => TAG_SIGNATURE, 'type' => 'string | number | undefined', 'bodyType' => UNTYPED_SIGNATURE_VALUE]);
});

test('only the last entry of a named key joins the union', function () {
    $types = reconciledTypes(reconciled([[TAG_SIGNATURE, 'string | undefined'], ['main_tag', 'PostResource'], ['main_tag', 'number']]));

    expect($types[TAG_SIGNATURE])->toBe('string | number | undefined');
});

test('a key covered only through an empty placeholder still joins', function () {
    $types = reconciledTypes(reconciled([[TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE], ['_tag', 'boolean'], ['tag', 'number']]));

    expect($types[TAG_SIGNATURE])->toBe('string | boolean | undefined');
});

test('a filled signature goes back to its body value beside a key that cannot join', function (string $named, ?Closure $prepare) {
    $types = reconciledTypes(reconciled([[TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE], ['main_tag', $named]], $prepare));

    expect($types[TAG_SIGNATURE])->toBe(UNTYPED_SIGNATURE_VALUE);
})->with([
    'unknown' => ['unknown', null],
    'a class token' => ['Carbon', null],
    'an FQCN channel' => ['number', fn (MethodAnalysis $a) => $a->nestedResources['main_tag'] = PostResource::class],
]);

test('a body-typed signature beside a key that cannot join is left as it is', function () {
    $analysis = reconciled([[TAG_SIGNATURE, 'string | undefined'], ['main_tag', 'unknown']]);

    expect(reconciledTypes($analysis)[TAG_SIGNATURE])->toBe('string | undefined')
        ->and($analysis->properties[0])->not->toHaveKey('bodyType');
});

test('a fill goes back to its body value where another signature may overlap', function (array $other, string $expected) {
    $types = reconciledTypes(reconciled([[TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE], $other]));

    expect($types[TAG_SIGNATURE])->toBe(UNTYPED_SIGNATURE_VALUE)
        ->and($types[$other[0]])->toBe($expected);
})->with([
    'a narrower suffix, also filled' => [['[key: `${string}_a_tag`]', 'number | undefined', UNTYPED_SIGNATURE_VALUE], UNTYPED_SIGNATURE_VALUE],
    'a prefix pattern' => [['[key: `x${string}`]', 'number | undefined'], 'number | undefined'],
    'a number signature, never reconciled itself' => [['[key: number]', 'PostResource'], 'PostResource'],
]);

test('a fill is kept beside a signature whose pattern is proven disjoint', function () {
    $types = reconciledTypes(reconciled([[TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE], ['[key: `${string}_label`]', 'number | undefined']]));

    expect($types[TAG_SIGNATURE])->toBe('string | undefined');
});

test('an escaped placeholder is literal text, not a wildcard', function () {
    $types = reconciledTypes(reconciled([
        ['[key: `${string}\${x}`]', 'string | undefined', UNTYPED_SIGNATURE_VALUE],
        ['q${x}', 'number'],
        ['q_x', 'boolean'],
    ]));

    expect($types['[key: `${string}\${x}`]'])->toBe('string | number | undefined');
});

test('a folded union goes back to the last body value when an outer conflict appears', function () {
    $inner = reconciled([[TAG_SIGNATURE, 'string | undefined'], [TAG_SIGNATURE, 'number | undefined', UNTYPED_SIGNATURE_VALUE]]);
    $outer = new MethodAnalysis(properties: [
        ...$inner->properties,
        ['name' => 'main_tag', 'type' => 'unknown', 'optional' => false, 'description' => ''],
    ]);

    resolve(IndexSignatureReconciler::class)->reconcile($outer);

    expect(reconciledTypes($outer)[TAG_SIGNATURE])->toBe(UNTYPED_SIGNATURE_VALUE);
});
