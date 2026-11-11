<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\KeyWrite;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralFold;

/**
 * One key write at an item's position, its source the item's own index unless given.
 */
function keyWrite(
    string $name,
    int $position,
    bool $merge = false,
    bool $absent = false,
    bool $removed = false,
    ?int $source = null,
): KeyWrite {
    return new KeyWrite($name, $position, $merge, $absent, $removed, $source ?? $position);
}

/**
 * Each key a fold sends, `?` marking one it may not send, as its sources and why it may not: `absent`, `removed`.
 *
 * @param  list<KeyWrite>  $writes
 * @return array<string, array{list<int>, string}>
 */
function folded(array $writes, bool $liftsMerges = true): array
{
    $keys = [];

    foreach (LiteralFold::fold($writes, $liftsMerges) as $name => $key) {
        $why = trim(($key->absent ? 'absent ' : '').($key->removed ? 'removed' : ''));
        $keys[$name.($key->optional ? '?' : '')] = [$key->sources, $why];
    }

    return $keys;
}

/**
 * The write kinds the oracle replay folds, each as its key writes (name, from a merge, absent, removed) and the
 * fixture letters its paths take, null for a path that writes nothing.
 *
 * @return array<string, array{list<array{string, bool, bool, bool}>, list<string|null>}>
 */
function foldOracleKinds(): array
{
    return [
        'k => A' => [[['k', false, false, false]], ['a']],
        'k => when($c, T)' => [[['k', false, false, true]], ['f', 't']],
        'a helper that may leave k unset' => [[['k', false, true, false]], [null, 'a']],
        'a printed k?, either of the two' => [[['k', false, true, true]], [null, 'f', 't']],
        'j => J' => [[['j', false, false, false]], ['j']],
        'merge(k)' => [[['k', true, false, false]], ['m']],
        'merge(k => when($c), j)' => [[['k', true, false, true], ['j', true, false, false]], ['r', 'b']],
        'mergeWhen($c, k)' => [[['k', true, true, false]], ['n', 'm']],
        'merge(j)' => [[['j', true, false, false]], ['g']],
        'mergeWhen($c, k, j)' => [[['k', true, true, false], ['j', true, true, false]], ['n', 'b']],
    ];
}

/**
 * What PHP sends for a sequence of kinds over every path its kinds can take, from the recorded fixture: each key
 * sent, `?` where some path leaves it out, with the kinds whose value it sent on some path.
 *
 * @param  list<string>  $sequence  kind names
 * @param  array<string, string>  $sent  the fixture: letters => each key and the index of the write it sent
 * @param  array<string, true>  $seen  each fixture sequence a path reads
 * @return array<string, list<int>>
 */
function foldOracleExpected(array $sequence, array $sent, array &$seen): array
{
    $kinds = foldOracleKinds();
    $paths = [[[], []]];

    foreach ($sequence as $index => $kind) {
        $next = [];

        foreach ($paths as [$letters, $owners]) {
            foreach ($kinds[$kind][1] as $letter) {
                $next[] = $letter === null ? [$letters, $owners] : [[...$letters, $letter], [...$owners, $index]];
            }
        }

        $paths = $next;
    }

    $sources = [];
    $missing = [];

    foreach ($paths as [$letters, $owners]) {
        $code = implode('', $letters);
        $seen[$code] = true;
        preg_match_all('/([kj])(\d)/', $code === '' ? '' : $sent[$code], $matches, PREG_SET_ORDER);
        $keys = [];

        foreach ($matches as [, $key, $at]) {
            $keys[$key] = $owners[(int) $at];
        }

        foreach (['k', 'j'] as $key) {
            if (isset($keys[$key])) {
                $sources[$key][$keys[$key]] = true;
            } else {
                $missing[$key] = true;
            }
        }
    }

    $expected = [];

    foreach (['k', 'j'] as $key) {
        if (isset($sources[$key])) {
            $sourceList = array_keys($sources[$key]);
            sort($sourceList);
            $expected[$key.(isset($missing[$key]) ? '?' : '')] = $sourceList;
        }
    }

    return $expected;
}

/**
 * The fold of a sequence of kinds, each key as its sources, `?` where it may not be sent.
 *
 * @param  list<string>  $sequence  kind names
 * @return array<string, list<int>>
 */
function foldOracleFolded(array $sequence): array
{
    $kinds = foldOracleKinds();
    $writes = [];

    foreach ($sequence as $index => $kind) {
        foreach ($kinds[$kind][0] as [$name, $merge, $absent, $removed]) {
            $writes[] = new KeyWrite($name, $index, $merge, $absent, $removed, $index);
        }
    }

    $keys = [];

    foreach (LiteralFold::fold($writes, true) as $name => $key) {
        $keys[$name.($key->optional ? '?' : '')] = $key->sources;
    }

    return $keys;
}

// The fixture holds what the real resolve() sent for every sequence of one to four of nine writes, one path each; a
// sequence of kinds stands for every path its kinds can take, so its fold must name exactly what those paths sent.
it('folds every sequence the fold oracle recorded as Laravel\'s resolve() sent it, over every path', function () {
    /** @var array<string, string> $sent */
    $sent = require __DIR__.'/Fixtures/fold-oracle.php';
    $seen = [];
    $mismatches = [];
    $sequences = [[]];

    for ($length = 1; $length <= 4; $length++) {
        $next = [];

        foreach ($sequences as $sequence) {
            foreach (array_keys(foldOracleKinds()) as $kind) {
                $next[] = [...$sequence, $kind];
            }
        }

        $sequences = $next;

        foreach ($sequences as $sequence) {
            $expected = foldOracleExpected($sequence, $sent, $seen);
            $actual = foldOracleFolded($sequence);
            ksort($expected);
            ksort($actual);

            if ($actual !== $expected && count($mismatches) < 10) {
                $mismatches[] = implode(', ', $sequence).': sent '.json_encode($expected).', folded '.json_encode($actual);
            }
        }
    }

    expect($mismatches)->toBe([])
        ->and(array_values(array_diff(array_keys($sent), array_keys($seen))))->toBe([]);
});

// An inner literal folds on its own, then its key is one write of the literal spreading it: whether a held key survives
// is the fold's absent-or-removed split, and PHP spreading the raw inner array sends what the joined sequence sends.
it('folds a spread literal\'s key into the literal holding it as PHP sends the two spread into one', function () {
    /** @var array<string, string> $sent */
    $sent = require __DIR__.'/Fixtures/fold-oracle.php';
    $seen = [];
    $mismatches = [];
    $inner = [[]];
    $kinds = ['k => A', 'k => when($c, T)', 'a helper that may leave k unset', 'a printed k?, either of the two', 'j => J'];

    for ($length = 1; $length <= 3; $length++) {
        $next = [];

        foreach ($inner as $sequence) {
            foreach ($kinds as $kind) {
                $next[] = [...$sequence, $kind];
            }
        }

        $inner = $next;

        foreach ($inner as $sequence) {
            $innerKey = LiteralFold::fold(array_merge(...array_map(
                static fn (string $kind, int $index): array => array_map(
                    static fn (array $write): KeyWrite => new KeyWrite($write[0], $index, $write[1], $write[2], $write[3], $index),
                    foldOracleKinds()[$kind][0],
                ),
                $sequence,
                array_keys($sequence),
            )), true)['k'] ?? null;

            if ($innerKey === null) {
                continue;
            }

            $outer = LiteralFold::fold([keyWrite('k', 0), keyWrite('k', 1, absent: $innerKey->absent, removed: $innerKey->removed)], true)['k'];
            $sources = [];

            foreach ($outer->sources as $source) {
                array_push($sources, ...($source === 0 ? [0] : array_map(static fn (int $at): int => $at + 1, $innerKey->sources)));
            }

            sort($sources);
            $expected = foldOracleExpected(['k => A', ...$sequence], $sent, $seen);
            $actual = ['k'.($outer->optional ? '?' : '') => $sources];
            $expectedKey = array_intersect_key($expected, ['k' => true, 'k?' => true]);

            if ($actual !== $expectedKey) {
                $mismatches[] = implode(', ', $sequence).': sent '.json_encode($expectedKey).', folded '.json_encode($actual);
            }
        }
    }

    expect($mismatches)->toBe([]);
});

it('D1: sends the later of two writes that set the key on every path, where the first stood', function () {
    $fold = LiteralFold::fold([keyWrite('k', 0), keyWrite('j', 1), keyWrite('k', 2)], true);

    expect(folded([keyWrite('k', 0), keyWrite('k', 1)]))->toBe(['k' => [[1], '']])
        ->and(folded([keyWrite('k', 0), keyWrite('j', 1), keyWrite('k', 2)]))->toBe(['k' => [[2], ''], 'j' => [[1], '']])
        ->and($fold['k']->position)->toBe(0)
        ->and($fold['j']->position)->toBe(1);
});

it('D2: joins a key with a later write that may leave it unset, required where the first write is certain', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1, absent: true)]))->toBe(['k' => [[0, 1], '']])
        ->and(folded([keyWrite('k', 0), keyWrite('k', 1, absent: true), keyWrite('k', 2, absent: true)]))
        ->toBe(['k' => [[0, 1, 2], '']]);
});

it('D3: sends a later removable value or nothing, never the value before it', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1, removed: true)]))->toBe(['k?' => [[1], 'removed']])
        ->and(folded([keyWrite('k', 0, absent: true), keyWrite('k', 1, removed: true)]))->toBe(['k?' => [[1], 'removed']]);
});

// A when() with a default always sets the key to one of its two values, so its write is a certain one.
it('D4: sends a later when() with a default as a write that sets the key on every path', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1)]))->toBe(['k' => [[1], '']]);
});

it('D5: sends a certain write over a removable value before it', function () {
    expect(folded([keyWrite('k', 0, removed: true), keyWrite('k', 1)]))->toBe(['k' => [[1], '']]);
});

it('D6: leaves a key two writes may each leave unset optional, as either value', function () {
    expect(folded([keyWrite('k', 0, absent: true), keyWrite('k', 1, absent: true)]))->toBe(['k?' => [[0, 1], 'absent']]);
});

// A first write that may not land leaves the slot to the next write on that path, behind a merge between the two.
it('D7: keeps a key written before a merge over the merged value, which wins only where the key is unset', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1, merge: true)]))->toBe(['k' => [[0], '']])
        ->and(folded([keyWrite('k', 0, absent: true), keyWrite('k', 1, merge: true)]))->toBe(['k' => [[0, 1], '']])
        ->and(folded([keyWrite('k', 0, absent: true), keyWrite('k', 1, merge: true), keyWrite('k', 2)]))
        ->toBe(['k' => [[1, 2], '']]);
});

it('D8: lets a removable value before a merge stop the merged value', function () {
    expect(folded([keyWrite('k', 0, removed: true), keyWrite('k', 1, merge: true)]))->toBe(['k?' => [[0], 'removed']]);
});

it('D9: sends a merged value over any later write of its key', function () {
    expect(folded([keyWrite('k', 0, merge: true), keyWrite('k', 1)]))->toBe(['k' => [[0], '']])
        ->and(folded([keyWrite('k', 0, merge: true), keyWrite('k', 1, removed: true)]))->toBe(['k' => [[0], '']])
        ->and(folded([keyWrite('k', 0, merge: true), keyWrite('k', 1, absent: true)]))->toBe(['k' => [[0], '']]);
});

it('D10: sends a mergeWhen() value where it runs, and the later write where it does not', function () {
    expect(folded([keyWrite('k', 0, merge: true, absent: true), keyWrite('k', 1)]))->toBe(['k' => [[0, 1], '']])
        ->and(folded([keyWrite('k', 0, merge: true, absent: true), keyWrite('k', 1, removed: true)]))
        ->toBe(['k?' => [[0, 1], 'removed']])
        ->and(folded([keyWrite('k', 0, merge: true, absent: true), keyWrite('k', 1, absent: true)]))
        ->toBe(['k?' => [[0, 1], 'absent']]);
});

// The table's `k: B` is a when(false) merged value; one that may be sent wins where it is.
it('D11: lets the later write through where a merge\'s own value is removed, and the merged value win elsewhere', function () {
    expect(folded([keyWrite('k', 0, merge: true, removed: true), keyWrite('k', 1)]))->toBe(['k' => [[0, 1], '']])
        ->and(folded([keyWrite('k', 0), keyWrite('k', 1, merge: true, removed: true)]))->toBe(['k' => [[0], '']]);
});

it('D12: sends the first of two merges that runs', function () {
    expect(folded([keyWrite('k', 0, merge: true), keyWrite('k', 1, merge: true)]))->toBe(['k' => [[0], '']])
        ->and(folded([keyWrite('k', 0, merge: true, absent: true), keyWrite('k', 1, merge: true)]))->toBe(['k' => [[0, 1], '']])
        ->and(folded([keyWrite('k', 0, merge: true, absent: true), keyWrite('k', 1, merge: true, absent: true)]))
        ->toBe(['k?' => [[0, 1], 'absent']]);
});

// An arm's keys are writes like any other: one it always writes wins, one it may write joins.
it('D13: sends a later arm\'s key over the one before it, joined where the arm may leave it unset', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1)]))->toBe(['k' => [[1], '']])
        ->and(folded([keyWrite('k', 0), keyWrite('k', 1, absent: true)]))->toBe(['k' => [[0, 1], '']]);
});

// An open value's named member is written on every path, as any closed value's is; its unnamed keys write nothing.
it('D14: sends an open value\'s named member over the key before it', function () {
    expect(folded([keyWrite('k', 0), keyWrite('j', 1), keyWrite('k', 2)]))->toBe(['k' => [[2], ''], 'j' => [[1], '']]);
});

// A union of closed values is one write per key: certain where every arm holds it, else one it may leave unset.
it('D15: sends a union of closed values\' keys as one write each', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1), keyWrite('x', 1, absent: true)]))
        ->toBe(['k' => [[1], ''], 'x?' => [[1], 'absent']]);
});

// The branch join, which leaves a key one branch lacks optional, stays outside the fold.
it('D16: folds each branch on its own', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1, absent: true)]))->toBe(['k' => [[0, 1], '']])
        ->and(folded([keyWrite('n', 0)]))->toBe(['n' => [[0], '']]);
});

it('D17: folds an Inertia literal in PHP\'s order alone, an optional() prop removing the value before it', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1, removed: true)], liftsMerges: false))->toBe(['k?' => [[1], 'removed']])
        ->and(folded([keyWrite('k', 0, removed: true), keyWrite('k', 1)], liftsMerges: false))->toBe(['k' => [[1], '']]);
});

// Outside filter() PHP appends a merge call under an int key, so it sets no named key.
it('D18: folds a broadcast literal in PHP\'s order alone, a merge call setting no named key', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1)], liftsMerges: false))->toBe(['k' => [[1], '']])
        ->and(folded([keyWrite('k', 0, merge: true), keyWrite('k', 1)], liftsMerges: false))->toBe(['k' => [[1], '']])
        ->and(folded([keyWrite('k', 0), keyWrite('k', 1, merge: true)], liftsMerges: false))->toBe(['k' => [[0], '']])
        ->and(folded([keyWrite('k', 0, merge: true)], liftsMerges: false))->toBe([]);
});

it('keeps the value before a write that may be unset or removed, as a printed `?` may be either', function () {
    expect(folded([keyWrite('k', 0), keyWrite('k', 1, absent: true, removed: true)]))->toBe(['k?' => [[0, 1], 'removed']]);
});

it('folds a write whose source is another item\'s analysis, as a helper\'s keys share the helper\'s index', function () {
    expect(folded([keyWrite('k', 0, source: 0), keyWrite('j', 1, source: 1), keyWrite('k', 1, source: 1)]))
        ->toBe(['k' => [[1], ''], 'j' => [[1], '']]);
});
