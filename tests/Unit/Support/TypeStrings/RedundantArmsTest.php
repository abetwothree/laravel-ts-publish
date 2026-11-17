<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\LiteralArm;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\RedundantArms;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The enum aliases the cases read, each with the case scalars it admits.
 *
 * @return array<string, list<int|string>>
 */
function redundantArmsAliases(): array
{
    return ['MediaTypeType' => ['image', 'video'], 'PriorityType' => [0, 1]];
}

/**
 * Seeded unions of one to four arms: never[], a list, a Record, an object, case literals, their aliases, null and
 * string, each arm maybe a union nested in `{ k: … }` or `(…)[]`, two levels deep at most.
 *
 * @return list<string>
 */
function redundantArmsCases(int $seed, int $cases): array
{
    $random = new Randomizer(new Xoshiro256StarStar($seed));
    $leaves = ['never[]', 'PriorityType[]', 'Record<string, number>', '{ a: number }', "'image'", "'video'", '0', 'MediaTypeType', 'PriorityType', 'null', 'string'];
    $union = static function (int $depth) use (&$union, $random, $leaves): string {
        $arms = array_map(static fn (): string => match ($depth === 0 ? 0 : $random->getInt(0, 3)) {
            1 => '{ k: '.$union($depth - 1).' }',
            2 => '('.$union($depth - 1).')[]',
            default => $leaves[$random->getInt(0, count($leaves) - 1)],
        }, range(1, $random->getInt(1, 4)));

        return implode(' | ', $arms);
    };

    return array_map(static fn (): string => $union(2), range(1, $cases));
}

/**
 * The arms $left leaves out of $arms, matched in order, or null when $left is not a subsequence of $arms.
 *
 * @param  list<string>  $arms
 * @param  list<string>  $left
 * @return list<string>|null
 */
function redundantArmsDropped(array $arms, array $left): ?array
{
    $dropped = [];

    foreach ($arms as $arm) {
        if ($left !== [] && $left[0] === $arm) {
            array_shift($left);
        } else {
            $dropped[] = $arm;
        }
    }

    return $left === [] ? $dropped : null;
}

/**
 * Whether an arm admits `never[]`, read apart from the rule: the empty tuple, or one `X[]`, `Array<X>` or
 * `ReadonlyArray<X>` with no top-level `&`.
 */
function redundantArmsAdmitsEmptyList(string $by): bool
{
    return $by === '[]' || ($by !== 'never[]'
        && count(TsTypeShape::splitTopLevel($by, ['&'])) === 1
        && preg_match('/\[\]$|^(?:Readonly)?Array<.+>$/D', $by) === 1);
}

/**
 * Every identifier outside literal text, `never` left out, sorted.
 *
 * @return list<string>
 */
function redundantArmsIdentifiers(string $type): array
{
    preg_match_all('/[\p{L}_$][\p{L}\p{N}_$]*/u', TypeScanner::maskLiterals($type), $matches);
    $identifiers = array_values(array_filter($matches[0], static fn (string $name): bool => $name !== 'never'));
    sort($identifiers);

    return $identifiers;
}

describe('never[]', function () {
    test('is absorbed by a lone list or the empty tuple beside it, at every depth', function (string $type, string $expected) {
        expect(RedundantArms::absorbed($type, redundantArmsAliases()))->toBe($expected);
    })->with([
        'beside a list of an alias' => ['PriorityType[] | never[]', 'PriorityType[]'],
        'beside a list and null' => ['never[] | PriorityType[] | null', 'PriorityType[] | null'],
        'beside a list of a union' => ['never[] | (string | number)[]', '(string | number)[]'],
        'beside unknown[]' => ['unknown[] | never[]', 'unknown[]'],
        'beside an Array<…>' => ['Array<string> | never[]', 'Array<string>'],
        'beside a ReadonlyArray<…>' => ['ReadonlyArray<string> | never[]', 'ReadonlyArray<string>'],
        'beside a readonly list' => ['readonly string[] | never[]', 'readonly string[]'],
        'beside a list of lists' => ['never[] | PriorityType[][]', 'PriorityType[][]'],
        'twice beside a list' => ['never[] | string[] | never[]', 'string[]'],
        'in a member of a listed object' => ['{ f: never[] | number[] }[]', '{ f: number[] }[]'],
        'in a Record\'s value' => ['Record<string, never[] | number[]>', 'Record<string, number[]>'],
        'in a list\'s element' => ['(never[] | number[])[]', 'number[][]'],
        'in a tuple element' => ['[never[] | number[], string]', '[number[], string]'],
        'beside the empty tuple' => ['[] | never[]', '[]'],
    ]);

    test('stays beside anything but a lone list or the empty tuple', function (string $type) {
        expect(RedundantArms::absorbed($type, redundantArmsAliases()))->toBe($type);
    })->with([
        'beside a Record' => ['Record<string, PriorityType> | never[]'],
        'beside an object' => ['{ a: number } | never[]'],
        'beside a tuple' => ['[number, string] | never[]'],
        'beside an AsEnum' => ['AsEnum<typeof Role> | never[]'],
        'beside a named collection' => ['UserCollection | never[]'],
        'beside an intersection holding a list' => ['(A & B[]) | never[]'],
        'beside null' => ['never[] | null'],
        'alone' => ['never[]'],
        'beside itself' => ['never[] | never[]'],
        'beside an arrow returning a list' => ['(() => string[]) | never[]'],
    ]);
});

describe('alike arms', function () {
    test('all stay, as two classes spelled alike may be', function (string $type, string $expected) {
        expect(RedundantArms::absorbed($type, redundantArmsAliases()))->toBe($expected);
    })->with([
        'in a list printed again' => ['(PriorityType[] | PriorityType[] | never[])[]', '(PriorityType[] | PriorityType[])[]'],
        'beside a literal left out' => ["(StatusType | StatusType | 'image' | MediaTypeType)[]", '(StatusType | StatusType | MediaTypeType)[]'],
        'objects made alike' => ['{ k: never[] | PriorityType[] } | { k: PriorityType[] }', '{ k: PriorityType[] } | { k: PriorityType[] }'],
    ]);
});

describe('literals', function () {
    test('are absorbed by the alias that admits them, at every depth', function (string $type, string $expected) {
        expect(RedundantArms::absorbed($type, redundantArmsAliases()))->toBe($expected);
    })->with([
        'before the alias' => ["'image' | MediaTypeType", 'MediaTypeType'],
        'after the alias, beside null' => ["MediaTypeType | 'image' | 'video' | null", 'MediaTypeType | null'],
        'double-quoted' => ['"image" | MediaTypeType', 'MediaTypeType'],
        'a number' => ['0 | PriorityType', 'PriorityType'],
        'in an object member' => ["{ inner: 'image' | MediaTypeType }", '{ inner: MediaTypeType }'],
        'in a list\'s element' => ["('image' | MediaTypeType)[]", 'MediaTypeType[]'],
    ]);

    test('stay beside any alias that does not admit them', function (string $type) {
        expect(RedundantArms::absorbed($type, redundantArmsAliases()))->toBe($type);
    })->with([
        'a value no case holds' => ["'other' | MediaTypeType"],
        'an alias with no cases given' => ["'image' | SeasonType"],
        'no alias' => ["'image' | 'video'"],
        'a numeric string beside int cases' => ["'1' | PriorityType"],
        'a fraction beside int cases' => ['1.5 | PriorityType'],
        'alone' => ["'image'"],
        'beside a list of the alias' => ["'image' | MediaTypeType[]"],
    ]);
});

describe('gaps', function () {
    test('are neither absorbed nor absorbing', function (string $type, string $expected) {
        expect(RedundantArms::absorbed($type, redundantArmsAliases()))->toBe($expected);
    })->with([
        'beside never[] and a list' => ['$gap1 | never[] | number[]', '$gap1 | number[]'],
        'beside never[]' => ['never[] | $gap1', 'never[] | $gap1'],
        'beside a literal' => ["'image' | \$gap1", "'image' | \$gap1"],
        'beside a literal and its alias' => ["'image' | \$gap1 | MediaTypeType", '$gap1 | MediaTypeType'],
    ]);
});

describe('kept', function () {
    test('drops each arm a sibling admits and keeps the others in order', function (array $arms, array $kept) {
        expect(RedundantArms::kept($arms, redundantArmsAliases()))->toBe($kept);
    })->with([
        'never[] and a literal among others' => [['null', 'never[]', 'PriorityType[]', "'image'", 'MediaTypeType', 'string'], ['null', 'PriorityType[]', 'MediaTypeType', 'string']],
        'a literal twice' => [["'image'", 'MediaTypeType', "'image'"], ['MediaTypeType']],
        'one arm' => [['never[]'], ['never[]']],
        'no arm' => [[], []],
    ]);

    test('keeps every arm when each is admitted by another', function () {
        $arms = ["'a'", "'b'"];

        expect(RedundantArms::kept($arms, ["'a'" => ['b'], "'b'" => ['a']]))->toBe($arms);
    });
});

describe('joined', function () {
    test('joins the arms as one union, each once and without each a sibling admits', function (array $arms, string $joined) {
        expect(RedundantArms::joined($arms))->toBe($joined);
    })->with([
        'a repeated arm' => [['string[]', 'number', 'string[]'], 'string[] | number'],
        'never[] beside a list' => [['never[]', 'PriorityType[]', 'never[]'], 'PriorityType[]'],
        'never[] alone, twice' => [['never[]', 'never[]'], 'never[]'],
        'one arm' => [['string'], 'string'],
    ]);
});

describe('absorbedReporting', function () {
    test('gives the type absorbed() gives, and whether a literal was among the arms it left out', function (string $type, string $expected, bool $tookLiteral) {
        expect(RedundantArms::absorbedReporting($type, redundantArmsAliases()))->toBe([$expected, $tookLiteral])
            ->and(RedundantArms::absorbed($type, redundantArmsAliases()))->toBe($expected);
    })->with([
        'a literal beside its alias' => ["'image' | MediaTypeType", 'MediaTypeType', true],
        'a literal inside a member' => ['{ k: 0 | PriorityType }', '{ k: PriorityType }', true],
        'never[] and a literal' => ['never[] | PriorityType[] | 1 | PriorityType', 'PriorityType[] | PriorityType', true],
        'never[] alone' => ['never[] | PriorityType[]', 'PriorityType[]', false],
        'a literal no alias admits' => ["'other' | MediaTypeType", "'other' | MediaTypeType", false],
        'nothing to absorb' => ['string', 'string', false],
    ]);
});

describe('admits', function () {
    test('answers whether one arm admits every value of another', function (string $by, string $arm, bool $admits) {
        expect(RedundantArms::admits($by, $arm, redundantArmsAliases()))->toBe($admits);
    })->with([
        'a list, never[]' => ['PriorityType[]', 'never[]', true],
        'an Array<…>, never[]' => ['Array<string>', 'never[]', true],
        'a list of tuples, never[]' => ['[number, string][]', 'never[]', true],
        'never[], never[]' => ['never[]', 'never[]', false],
        'a nullable list, never[]' => ['PriorityType[] | null', 'never[]', false],
        'an intersection ending in a list, never[]' => ['A & B[]', 'never[]', false],
        'a Record, never[]' => ['Record<string, number>', 'never[]', false],
        'an object of optional keys, never[]' => ['{ a?: number }', 'never[]', false],
        'the empty tuple, never[]' => ['[]', 'never[]', true],
        'an alias, never[]' => ['PriorityType', 'never[]', false],
        'an alias, its case literal' => ['MediaTypeType', "'video'", true],
        'an alias, another alias\'s literal' => ['PriorityType', "'video'", false],
        'an alias, itself' => ['MediaTypeType', 'MediaTypeType', false],
        'a list, a literal' => ['PriorityType[]', '0', false],
        'a literal, never[]' => ["'image'", 'never[]', false],
    ]);
});

test('keeps a subset of the arms, every token, and its own answer, each dropped arm admitted by a kept one', function (string $type) {
    $aliases = redundantArmsAliases();
    $absorbed = RedundantArms::absorbed($type, $aliases);
    $insides = array_map(
        static fn (string $arm): string => RedundantArms::absorbed($arm, $aliases),
        TsTypeShape::splitTopLevel($type, ['|']),
    );
    $left = TsTypeShape::splitTopLevel($absorbed, ['|']);
    $dropped = redundantArmsDropped($insides, $left);
    $unadmitted = array_filter((array) $dropped, static fn (string $arm): bool => ! array_any(
        $left,
        static fn (string $by): bool => match (true) {
            $arm === 'never[]' => redundantArmsAdmitsEmptyList($by),
            LiteralArm::value($arm) !== null => in_array(LiteralArm::value($arm), $aliases[$by] ?? [], true),
            default => false,
        },
    ));

    expect(RedundantArms::absorbed($absorbed, $aliases))->toBe($absorbed)
        ->and(RedundantArms::absorbedReporting($type, $aliases))->toBe([$absorbed, $absorbed !== RedundantArms::absorbed($type)])
        ->and($dropped)->not->toBeNull()
        ->and($unadmitted)->toBe([])
        ->and($left)->not->toBeEmpty()
        ->and(redundantArmsIdentifiers($absorbed))->toBe(redundantArmsIdentifiers($type));
})->with(fn (): array => redundantArmsCases(166, 400));

test('the seeded unions leave out never[] and literals at the top level and inside', function () {
    $aliases = redundantArmsAliases();
    $tally = ['never[]' => 0, 'literal' => 0, 'inside' => 0];

    foreach (redundantArmsCases(166, 400) as $type) {
        $absorbed = RedundantArms::absorbed($type, $aliases);
        $tally['never[]'] += (int) (substr_count($absorbed, 'never[]') < substr_count($type, 'never[]'));
        $tally['literal'] += (int) (count(TypeScanner::literalSpans($absorbed)) < count(TypeScanner::literalSpans($type)));
        $tally['inside'] += (int) array_any(
            TsTypeShape::splitTopLevel($type, ['|']),
            static fn (string $arm): bool => RedundantArms::absorbed($arm, $aliases) !== $arm,
        );
    }

    expect($tally['never[]'])->toBeGreaterThan(50)
        ->and($tally['literal'])->toBeGreaterThan(50)
        ->and($tally['inside'])->toBeGreaterThan(50);
});
