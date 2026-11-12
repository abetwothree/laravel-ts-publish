<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;

/**
 * The scanner corpus' golden types whose top-level arms join as printed, so a gap appended to one leaves them as
 * they are; `unknown` takes no gap.
 *
 * @return list<string>
 */
function visibleGapsGoldenTypes(): array
{
    /** @var list<array{t: string, src: string}> $rows */
    $rows = json_decode((string) file_get_contents(__DIR__.'/Fixtures/scanner-corpus.json'), true, flags: JSON_THROW_ON_ERROR);
    $types = array_column(array_filter($rows, static fn (array $row): bool => $row['src'] === 'a'), 't');

    return array_values(array_filter($types, static function (string $type): bool {
        $arms = TsTypeShape::splitTopLevel($type, ['|']);

        return $type !== 'unknown' && implode(' | ', array_map(TsTypeShape::unionArm(...), $arms)) === $type;
    }));
}

describe('spelling', function () {
    test('a gap arm is `$gap` and a positive id', function (string $arm, bool $expected) {
        expect(VisibleGaps::isGap($arm))->toBe($expected);
    })->with([
        'one' => ['$gap1', true],
        'two digits' => ['$gap12', true],
        'zero' => ['$gap0', false],
        'a leading zero' => ['$gap01', false],
        'a trailing newline' => ["\$gap1\n", false],
        'a trailing space' => ['$gap1 ', false],
        'no dollar' => ['gap1', false],
        'a string literal' => ["'\$gap1'", false],
        'a list of one' => ['$gap1[]', false],
    ]);

    test('spells a gap arm and the open marker', function () {
        expect(VisibleGaps::arm(3))->toBe('$gap3')
            ->and(VisibleGaps::isGap(VisibleGaps::arm(3)))->toBeTrue()
            ->and(VisibleGaps::MARKER)->toBe('#$open: unknown')
            ->and(VisibleGaps::isMarker(VisibleGaps::MARKER_KEY))->toBeTrue()
            ->and(VisibleGaps::isMarker('open'))->toBeFalse()
            ->and(VisibleGaps::isMarker('[key: string]'))->toBeFalse();
    });

    test('no signature reader takes the marker for a signature', function () {
        expect(IndexSignatureKey::is(VisibleGaps::MARKER_KEY))->toBeFalse()
            ->and(IndexSignatureKey::covers(VisibleGaps::MARKER_KEY, 'a'))->toBeFalse()
            ->and(TsTypeShape::members('{ a: number; #$open: unknown }'))->toBe([['a', 'number'], ['#$open', 'unknown']])
            ->and(TsTypeShape::memberType('{ a: number; #$open: unknown }', 'b'))->toBeNull();
    });

    test('holds tells whether a type may spell a gap or the marker', function (string $type, bool $expected) {
        expect(VisibleGaps::holds($type))->toBe($expected);
    })->with([
        'a gap' => ['string | $gap1', true],
        'the marker' => ['{ a: number; #$open: unknown }', true],
        'neither' => ['{ a: number } | null', false],
    ]);
});

describe('withArm and ids', function () {
    test('appends a gap arm once, grouping an arrow, and leaves unknown alone', function (string $type, string $expected) {
        expect(VisibleGaps::withArm($type, 2))->toBe($expected);
    })->with([
        'a scalar' => ['string', 'string | $gap2'],
        'a nullable type' => ['string | null', 'string | null | $gap2'],
        'a type holding it' => ['string | $gap2', 'string | $gap2'],
        'a type holding another' => ['string | $gap1', 'string | $gap1 | $gap2'],
        'an arrow' => ['() => void', '(() => void) | $gap2'],
        'unknown' => ['unknown', 'unknown'],
        'unknown beside null' => ['unknown | null', 'unknown | null | $gap2'],
    ]);

    test('reads the gap arms at every depth, in order, each once', function (string $type, array $ids) {
        expect(VisibleGaps::ids($type))->toBe($ids);
    })->with([
        'none' => ['string | null', []],
        'top level' => ['string | $gap3 | $gap1', [3, 1]],
        'nested' => ['{ a: X | $gap2 }[] | $gap1', [2, 1]],
        'in an Omit part' => ["Omit<{ by: string | \$gap4 }, 'id'> & { id: number }", [4]],
        'twice' => ['{ a: $gap5 } | $gap5', [5]],
        'a key spelled `$gap1`' => ['{ $gap1: string }', []],
        'a string literal' => ["'\$gap1' | string", []],
    ]);
});

describe('stripped', function () {
    test('removes a gap appended to a type, giving back the type', function (string $type) {
        expect(VisibleGaps::stripped(VisibleGaps::withArm($type, 3)))->toBe([$type, [3]]);
    })->with([
        'a scalar' => ['string'],
        'a nullable type' => ['string | null'],
        'a list of objects' => ['{ a: number }[]'],
        'an Omit beside an object' => ["Omit<{ by: string }, 'id'> & { id: number }"],
    ]);

    test('removes a gap appended to any golden type, giving back the type', function () {
        $types = visibleGapsGoldenTypes();
        $changed = array_filter(
            $types,
            static fn (string $type): bool => VisibleGaps::stripped(VisibleGaps::withArm($type, 7)) !== [$type, [7]],
        );

        expect(count($types))->toBeGreaterThan(250)
            ->and($changed)->toBe([]);
    });

    test('removes every gap and marker at any depth', function (string $type, string $expected, array $ids) {
        expect(VisibleGaps::stripped($type))->toBe([$expected, $ids]);
    })->with([
        'a gap inside an Omit part' => ["Omit<{ by: string | \$gap2 }, 'id'> & { id: number }", "Omit<{ by: string }, 'id'> & { id: number }", [2]],
        'a gap in a list\'s element' => ['(number[] | $gap1)[]', 'number[][]', [1]],
        'a gap left alone' => ['$gap1', 'unknown', [1]],
        'a gap in a member left alone' => ['{ a: $gap1; b: number }', '{ a: unknown; b: number }', [1]],
        'an object made alike another' => ['{ a: string | $gap1 } | { a: string }', '{ a: string }', [1]],
        'the marker' => ['{ a: number; #$open: unknown }', '{ a: number }', []],
        'a nested marker beside a gap' => ['{ a: { b: number; #$open: unknown } | $gap2 }', '{ a: { b: number } }', [2]],
        'the marker alone' => ['{ #$open: unknown }', '{}', []],
        'a marker part after an Omit and a class' => [
            'Omit<{ name: number }, keyof Recipient> & Recipient & { #$open: unknown }',
            'Omit<{ name: number }, keyof Recipient> & Recipient',
            [],
        ],
        'a marker part in a group' => ['{ a: (A & B & { #$open: unknown }) | null }', '{ a: (A & B) | null }', []],
        'a marker part in a list' => ['(A & B & { #$open: unknown })[]', '(A & B)[]', []],
        'an empty object part beside a gap' => ['A & {} | $gap1', 'A & {}', [1]],
        'a gap in a compact object' => ['{a:string; b: X | $gap1}', '{a:string; b: X}', [1]],
        'a gap in an unpadded object' => [
            'Record<string, {title: string | $gap2, content: string}>',
            'Record<string, {title: string, content: string}>',
            [2],
        ],
    ]);

    test('leaves a type that spells neither as it is', function (string $type) {
        expect(VisibleGaps::stripped($type))->toBe([$type, []])
            ->and(VisibleGaps::ids($type))->toBe([]);
    })->with([
        'a string signature' => ['{ [key: string]: unknown }'],
        'unknown beside null' => ['unknown | null'],
        'a key spelled `$gap1`' => ['{ $gap1: string }'],
        'a string literal spelled `$gap1`' => ["'\$gap1'"],
        'a key spelled like the marker' => ['{ "#$open": unknown }'],
        'a template literal spelling `$gap1`' => ['`a$gap1`'],
    ]);
});

describe('aside, restored and carried', function () {
    test('sets a type\'s own top-level gap arms aside', function (string $type, string $rest, array $gaps) {
        expect(VisibleGaps::aside($type))->toBe([$rest, $gaps]);
    })->with([
        'beside null' => ['null | $gap1', 'null', ['$gap1']],
        'alone' => ['$gap1', 'unknown', ['$gap1']],
        'two, among arms' => ['A | $gap2 | B | $gap1', 'A | B', ['$gap2', '$gap1']],
        'a nested gap stays' => ['{ a: $gap1 } | null', '{ a: $gap1 } | null', []],
        'none' => ['string | null', 'string | null', []],
    ]);

    test('appends each gap a type lacks, and unknown absorbs them', function (string $type, array $gaps, string $expected) {
        expect(VisibleGaps::restored($type, $gaps))->toBe($expected);
    })->with([
        'onto a type' => ['string', ['$gap1', '$gap2'], 'string | $gap1 | $gap2'],
        'one it has' => ['string | $gap1', ['$gap1', '$gap2'], 'string | $gap1 | $gap2'],
        'onto an arrow' => ['() => void', ['$gap1'], '(() => void) | $gap1'],
        'onto unknown' => ['unknown', ['$gap1'], 'unknown'],
        'none' => ['string', [], 'string'],
        'one twice' => ['string', ['$gap1', '$gap1'], 'string | $gap1'],
    ]);

    test('carries a type\'s gaps over a read', function (string $type, ?string $expected) {
        expect(VisibleGaps::carried($type, TsTypeShape::elementType(...)))->toBe($expected);
    })->with([
        'onto the read\'s result' => ['X[] | $gap1', 'X | $gap1'],
        'a read that declines' => ['string | $gap1', null],
        'a type with none' => ['X[]', 'X'],
        'a type of only gaps' => ['$gap1', null],
    ]);

    test('reads a type of only gaps as unknown', function () {
        $read = [];

        $carried = VisibleGaps::carried('$gap1 | $gap2', static function (string $type) use (&$read): string {
            $read[] = $type;

            return $type;
        });

        expect($read)->toBe(['unknown'])
            ->and($carried)->toBe('unknown');
    });
});

describe('marker', function () {
    test('marks an object open and reads it back', function (string $object, string $marked, array $members) {
        expect(VisibleGaps::withMarker($object))->toBe($marked)
            ->and(VisibleGaps::withMarker($marked))->toBe($marked)
            ->and(VisibleGaps::opened($object, true))->toBe($marked)
            ->and(VisibleGaps::opened($object, false))->toBe($object)
            ->and(VisibleGaps::members($marked))->toBe([$members, true])
            ->and(VisibleGaps::members($object))->toBe([$members, false])
            ->and(VisibleGaps::stripped($marked))->toBe([$object, []]);
    })->with([
        'an object' => ['{ a: number }', '{ a: number; #$open: unknown }', [['a', 'number']]],
        'the empty object' => ['{}', '{ #$open: unknown }', []],
        'a nested object' => ['{ a: { b: number } }', '{ a: { b: number }; #$open: unknown }', [['a', '{ b: number }']]],
        'an object with commas' => ['{ a: number, b: string }', '{ a: number, b: string, #$open: unknown }', [['a', 'number'], ['b', 'string']]],
        'an unpadded object' => ['{title: string, content: string}', '{title: string, content: string, #$open: unknown}', [['title', 'string'], ['content', 'string']]],
        'a compact object' => ['{a:string}', '{a:string; #$open: unknown}', [['a', 'string']]],
        'a trailing separator' => ['{ a: number; }', '{ a: number; #$open: unknown; }', [['a', 'number']]],
    ]);

    test('marks an object inside a type, and strips it back out', function () {
        $part = VisibleGaps::withMarker('{ id: number }');
        $nested = '{ a: '.VisibleGaps::withMarker('{ b: number }').' }';

        expect(VisibleGaps::stripped("Omit<{ by: string }, 'id'> & ".$part))->toBe(["Omit<{ by: string }, 'id'> & { id: number }", []])
            ->and(VisibleGaps::stripped($nested.'[]'))->toBe(['{ a: { b: number } }[]', []])
            ->and(VisibleGaps::members($nested))->toBe([[['a', '{ b: number; #$open: unknown }']], false]);
    });

    test('leaves a type that is not one object literal as it is', function (string $type) {
        expect(VisibleGaps::withMarker($type))->toBe($type)
            ->and(VisibleGaps::members($type))->toBeNull();
    })->with([
        'a Record' => ['Record<string, unknown>'],
        'an intersection' => ["Omit<A, 'a'> & { a: number }"],
        'a nullable object' => ['{ a: number } | null'],
    ]);
});

// A union groups an arrow only beside another arm, so the arrow a gap left alone reads as it did before the gap came.
test('gives back a lone arrow without the parentheses its gap arm gave it', function () {
    $gapped = VisibleGaps::withArm('() => string', 3);

    expect($gapped)->toBe('(() => string) | $gap3')
        ->and(VisibleGaps::stripped($gapped)[0])->toBe('() => string')
        ->and(VisibleGaps::aside($gapped)[0])->toBe('() => string')
        ->and(VisibleGaps::stripped('{ cb: (() => string) | $gap3 }')[0])->toBe('{ cb: () => string }')
        ->and(VisibleGaps::stripped('(() => string) | null | $gap3')[0])->toBe('(() => string) | null');
});
