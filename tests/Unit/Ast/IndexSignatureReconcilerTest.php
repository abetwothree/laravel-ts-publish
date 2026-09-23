<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\PostResource;

/**
 * Reconcile an analysis built from the given entries, after an optional closure edits its channels.
 *
 * @param  list<array{0: string, 1: string, 2?: string}>  $entries  name, type, and the body type a fill replaced
 * @param  array<string, string>  $castKeys
 */
function reconciled(array $entries, ?Closure $prepare = null, array $castKeys = [], bool $unseen = false): MethodAnalysis
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

    resolve(IndexSignatureReconciler::class)->reconcile($analysis, $castKeys, $unseen);

    return $analysis;
}

/**
 * Each property's published type, the last entry of a name winning.
 *
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

test('an escaped backslash is one literal backslash, as TypeScript reads it', function (string $signature, string $covered, string $uncovered) {
    $types = reconciledTypes(reconciled([[$signature, 'string | undefined', UNTYPED_SIGNATURE_VALUE], [$covered, 'number'], [$uncovered, 'boolean']]));

    expect($types[$signature])->toBe('string | number | undefined');
})->with([
    'after a placeholder' => ['[key: `${string}\\\\_tag`]', 'main\\_tag', 'main_tag'],
    'before a placeholder, which stays one' => ['[key: `a\\\\${string}`]', 'a\\x', 'ax'],
]);

test('a folded union goes back to the last body value when an outer conflict appears', function () {
    $inner = reconciled([[TAG_SIGNATURE, 'string | undefined'], [TAG_SIGNATURE, 'number | undefined', UNTYPED_SIGNATURE_VALUE]]);
    $outer = new MethodAnalysis(properties: [
        ...$inner->properties,
        ['name' => 'main_tag', 'type' => 'unknown', 'optional' => false, 'description' => ''],
    ]);

    resolve(IndexSignatureReconciler::class)->reconcile($outer);

    expect(reconciledTypes($outer)[TAG_SIGNATURE])->toBe(UNTYPED_SIGNATURE_VALUE);
});

test('a fill goes back beside an overlapping pattern, whichever literal text is longer', function (string $own, string $other) {
    $types = reconciledTypes(reconciled([
        [$own, 'string | undefined', UNTYPED_SIGNATURE_VALUE],
        [$other, 'number | undefined'],
    ]));

    expect($types[$own])->toBe(UNTYPED_SIGNATURE_VALUE);
})->with([
    'a longer own head' => ['[key: `ab${string}`]', '[key: `a${string}`]'],
    'a longer other head' => ['[key: `a${string}`]', '[key: `ab${string}`]'],
    'a longer own tail' => ['[key: `${string}_a_tag`]', '[key: `${string}_tag`]'],
    'a longer other tail' => ['[key: `${string}_tag`]', '[key: `${string}_a_tag`]'],
]);

test('a fill is kept beside a pattern whose leading text cannot hold for the same key', function () {
    $types = reconciledTypes(reconciled([
        ['[key: `a${string}_tag`]', 'string | undefined', UNTYPED_SIGNATURE_VALUE],
        ['[key: `b${string}_tag`]', 'number | undefined'],
    ]));

    expect($types['[key: `a${string}_tag`]'])->toBe('string | undefined');
});

test('an FQCN channel on the signature\'s own name puts the fill back', function () {
    $analysis = reconciled(
        [[TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE], [TAG_SIGNATURE, "'red' | undefined"]],
        fn (MethodAnalysis $a) => $a->directEnumFqcns[TAG_SIGNATURE] = Status::class,
    );

    expect($analysis->properties)->toHaveCount(2)
        ->and($analysis->properties[0]['type'])->toBe(UNTYPED_SIGNATURE_VALUE);
});

test('string and number literal types join the union', function () {
    $types = reconciledTypes(reconciled([
        [TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE],
        ['a_tag', "'x' | 'y'"],
        ['b_tag', '1 | -2.5'],
    ]));

    expect($types[TAG_SIGNATURE])->toBe("string | 'x' | 'y' | 1 | -2.5 | undefined");
});

test('a union keeps its undefined arm when another arm names undefined only inside it', function (string $named, string $expected) {
    $types = reconciledTypes(reconciled([[TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE], ['main_tag', $named]]));

    expect($types[TAG_SIGNATURE])->toBe($expected);
})->with([
    'a string literal' => ["'undefined' | 'defined'", "string | 'undefined' | 'defined' | undefined"],
    'a Record value' => ['Record<string, number | undefined>', 'string | Record<string, number | undefined> | undefined'],
    'a nested signature' => [
        '{ "[key: `${string}_tag`]": string | undefined }',
        'string | { "[key: `${string}_tag`]": string | undefined } | undefined',
    ],
]);

test('a string literal holding a backslash cannot join, so the fill goes back', function (string $cast) {
    $types = reconciledTypes(reconciled([[TAG_SIGNATURE, 'number | undefined', UNTYPED_SIGNATURE_VALUE]], castKeys: ['state_tag' => $cast]));

    expect($types[TAG_SIGNATURE])->toBe(UNTYPED_SIGNATURE_VALUE);
})->with([
    'an undefined in single quotes' => ["'it\\'s | undefined | x'"],
    'a null in single quotes' => ["'it\\'s | null | x'"],
    'an undefined in double quotes' => ['"q\\" | undefined | r"'],
    'an escaped backslash' => ["'a\\\\' | 'b'"],
]);

test('a lone fill is never put back, whatever its type', function () {
    $analysis = reconciled([[TAG_SIGNATURE, 'Carbon | undefined', UNTYPED_SIGNATURE_VALUE]]);

    expect($analysis->properties[0])->toMatchArray(['type' => 'Carbon | undefined', 'bodyType' => UNTYPED_SIGNATURE_VALUE]);
});

test('a cast key a publisher lays over the analysis joins the union in place of the analysis\'s own type', function () {
    $types = reconciledTypes(reconciled(
        [[TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE], ['main_tag', 'PostResource']],
        fn (MethodAnalysis $a) => $a->nestedResources['main_tag'] = PostResource::class,
        ['main_tag' => 'number', 'extra_tag' => 'boolean'],
    ));

    expect($types[TAG_SIGNATURE])->toBe('string | number | boolean | undefined');
});

test('a cast key naming a class puts the fill back', function () {
    $analysis = reconciled([[TAG_SIGNATURE, 'string | undefined', UNTYPED_SIGNATURE_VALUE]], castKeys: ['extra_tag' => 'Money']);
    $types = reconciledTypes($analysis);

    expect($types[TAG_SIGNATURE])->toBe(UNTYPED_SIGNATURE_VALUE);
});

test('unseen inherited keys put back every changed value and leave body values alone', function () {
    $analysis = reconciled([
        [TAG_SIGNATURE, 'string | number | undefined', UNTYPED_SIGNATURE_VALUE],
        ['[key: `${string}_label`]', 'string | undefined'],
        ['price_tag', 'number'],
    ], unseen: true);

    expect(reconciledTypes($analysis))->toMatchArray([
        TAG_SIGNATURE => UNTYPED_SIGNATURE_VALUE,
        '[key: `${string}_label`]' => 'string | undefined',
    ]);
});
