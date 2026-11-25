<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Build\ArrayWalk;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaqueKind;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaquePart;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;

// Each row's expectation is what ReadsReturnedVariables left for the same writes before the walk moved here.

/**
 * A walk of `$d` whose host joins as the returned-variable walk does and sends each value as it is.
 */
function arrayWalk(?MethodAnalysis $seed = null): ArrayWalk
{
    return ArrayWalk::from(
        $seed === null ? null : ArrayShapes::ofAnalysis($seed),
        'd',
        static fn (): MethodAnalysis => new MethodAnalysis,
        static function (array $branches): MethodAnalysis {
            $joined = new MethodAnalysis;
            BranchJoin::joinInto($joined, $branches, dropsUntypedBranches: true, keepsLoneNull: true);

            return $joined;
        },
        static fn (array $result): array => $result,
        static fn (Expr $key): ?string => $key instanceof String_ ? $key->value : null,
    );
}

/**
 * The walk's keys as `name => type`, a `?` before an optional one's name.
 *
 * @return array<string, string>
 */
function walkedKeys(ArrayWalk $walk): array
{
    $keys = [];

    foreach ($walk->state()->properties as $row) {
        $keys[($row['optional'] ? '?' : '').$row['name']] = $row['type'];
    }

    return $keys;
}

/**
 * An analysis of the given keys, each `name => type`.
 *
 * @param  array<string, string>  $keys
 */
function walkAnalysis(array $keys): MethodAnalysis
{
    $analysis = new MethodAnalysis;

    foreach ($keys as $name => $type) {
        $analysis->addProperty($name, ['type' => $type, 'optional' => false]);
    }

    return $analysis;
}

/**
 * A part bringing keys the walk cannot read, at a site of its own.
 */
function walkPart(OpaqueKind $kind = OpaqueKind::UnreadWholeWrite): OpaquePart
{
    return new OpaquePart(new Variable('rest'), $kind);
}

it('starts from a seed\'s keys', function () {
    expect(walkedKeys(arrayWalk(walkAnalysis(['a' => 'number']))))->toBe(['a' => 'number']);
});

it('lands a whole write, replacing every key when it always runs', function () {
    $walk = arrayWalk(walkAnalysis(['old' => 'string']));
    $walk->assign(ArrayShapes::ofAnalysis(walkAnalysis(['a' => 'number'])), false, new Array_);

    expect(walkedKeys($walk))->toBe(['a' => 'number']);
});

it('keeps the keys held before a whole write that may not run, the new ones optional', function () {
    $walk = arrayWalk(walkAnalysis(['old' => 'string']));
    $walk->assign(ArrayShapes::ofAnalysis(walkAnalysis(['a' => 'number'])), true, new Array_);

    expect(walkedKeys($walk))->toBe(['old' => 'string', '?a' => 'number']);
});

it('keeps the keys held before a whole write that spreads the variable into itself', function () {
    $walk = arrayWalk(walkAnalysis(['old' => 'string']));
    $spread = new Array_([new ArrayItem(new Variable('d'), unpack: true)]);
    $walk->assign(ArrayShapes::ofAnalysis(walkAnalysis(['a' => 'number'])), false, $spread);

    expect(walkedKeys($walk))->toBe(['old' => 'string', 'a' => 'number'])
        ->and(ArrayWalk::spreadsVariable($spread, 'd'))->toBeTrue()
        ->and(ArrayWalk::spreadsVariable($spread, 'other'))->toBeFalse();
});

it('lands a += past the keys it holds', function () {
    $walk = arrayWalk(walkAnalysis(['a' => 'string']));
    $walk->plus(ArrayShapes::ofAnalysis(walkAnalysis(['a' => 'number', 'b' => 'boolean'])), false, new Array_);

    expect(walkedKeys($walk))->toBe(['a' => 'string', 'b' => 'boolean']);
});

it('writes a key in its first position, optional where the write may not run, and a signature beside it', function () {
    $walk = arrayWalk(walkAnalysis(['a' => 'string', 'b' => 'number']));
    $walk->write('a', ['type' => 'boolean', 'optional' => false], false);
    $walk->write('c', ['type' => 'number', 'optional' => false], true);
    $walk->write('[key: string]', ['type' => 'number', 'optional' => false], false);

    expect(walkedKeys($walk))->toBe(['a' => 'boolean', 'b' => 'number', '?c' => 'number', '[key: string]' => 'number | undefined'])
        ->and($walk->holds('c'))->toBeTrue()
        ->and($walk->holds('z'))->toBeFalse();
});

it('writes a name shaped like a signature as the one key PHP stores under it, as an append names one', function () {
    $walk = arrayWalk();
    $value = new Variable('x');
    $walk->writeName('[key: string]', ['type' => 'unknown', 'optional' => false], false, $value);

    expect(walkedKeys($walk))->toBe(['[key: string]' => 'unknown'])
        ->and($walk->untypedWrites())->toBe([['[key: string]', $value]]);
});

it('notes a written value the engine cannot type', function () {
    $walk = arrayWalk();
    $value = new Variable('x');
    $walk->write('a', ['type' => 'unknown', 'optional' => false], false, $value);

    expect($walk->untypedWrites())->toBe([['a', $value]]);
});

it('fills a ??= default where the key may be unset or null', function (string $held, bool $optional, array $keys) {
    $walk = arrayWalk();
    $walk->write('a', ['type' => $held, 'optional' => false], $optional);
    $walk->coalesce('a', static fn (): array => ['type' => 'string', 'optional' => false], false);

    expect(walkedKeys($walk))->toBe($keys);
})->with([
    'a key that may be null' => ['number | null', false, ['a' => 'number | string']],
    'a key only some path sets' => ['number', true, ['a' => 'number | string']],
    'a key always set' => ['number', false, ['a' => 'number']],
]);

it('keeps a ??= default off a key whose value may be removed, as when()\'s may', function () {
    $walk = arrayWalk();
    $walk->write('a', ['type' => 'number', 'optional' => true], false);
    $walk->coalesce('a', static fn (): array => ['type' => 'string', 'optional' => false], false);

    expect(walkedKeys($walk))->toBe(['?a' => 'number']);
});

it('fills a ??= default where a body the walk joined leaves the key unset', function () {
    $walk = arrayWalk(walkAnalysis(['a' => 'string']));
    $body = clone $walk;
    $body->write('b', ['type' => 'number', 'optional' => false], false);
    $walk->branches([$body], true, static function (): void {});
    $walk->coalesce('b', static fn (): array => ['type' => 'string', 'optional' => false], false);

    expect(walkedKeys($walk))->toBe(['a' => 'string', 'b' => 'number | string']);
});

it('fills a ??= default on a key a loosened walk may have lost', function () {
    $walk = arrayWalk(walkAnalysis(['a' => 'number']));
    $walk->loosen(walkPart());
    $walk->coalesce('a', static fn (): array => ['type' => 'string', 'optional' => false], false);

    expect(walkedKeys($walk))->toBe(['a' => 'number | string']);
});

it('reads a ??= default against the keys the walk holds there', function () {
    $walk = arrayWalk();
    $walk->write('a', ['type' => 'number | null', 'optional' => false], false);
    $read = [];
    $walk->coalesce('a', static function (MethodAnalysis $state) use (&$read): array {
        $read = array_column($state->properties, 'type', 'name');

        return ['type' => 'string', 'optional' => false];
    }, false);

    expect($read)->toBe(['a' => 'number | null']);
});

it('takes an unset key, or makes it optional where the unset() may not run', function () {
    $walk = arrayWalk(walkAnalysis(['a' => 'string', 'b' => 'number']));
    $walk->unset('a', false);
    $walk->unset('b', true);

    expect(walkedKeys($walk))->toBe(['?b' => 'number']);
});

it('reads an unset() that names no key as one it cannot read, holding no part, as it brings none', function () {
    $walk = arrayWalk(walkAnalysis(['a' => 'string']));
    $walk->unset(null, false);

    expect($walk->state()->readsCompletely)->toBeFalse()
        ->and($walk->state()->holdsUnreadKeys)->toBeFalse()
        ->and($walk->shape()->opaque)->toBe([])
        ->and(walkedKeys($walk))->toBe(['a' => 'string']);
});

it('loosens every held key before a whole write it cannot read, holding the part that brings keys', function (bool $brings) {
    $walk = arrayWalk(walkAnalysis(['a' => 'string']));
    $part = $brings ? walkPart() : null;
    $walk->loosen($part);

    expect(walkedKeys($walk))->toBe(['?a' => 'string'])
        ->and($walk->state()->properties[0])->toHaveKey('absent', true)
        ->and($walk->state()->holdsUnreadKeys)->toBe($brings)
        ->and($walk->state()->readsCompletely)->toBe(! $brings)
        ->and($walk->shape()->opaque)->toBe($brings ? [$part] : []);
})->with(['a write that brings keys' => true, 'an unset() of the variable' => false]);

it('holds keys an opaque part brings, the shape naming its site', function (OpaqueKind $kind) {
    $walk = arrayWalk(walkAnalysis(['a' => 'string']));
    $part = walkPart($kind);
    $walk->holdOpaque($part);

    expect($walk->state()->holdsUnreadKeys)->toBeTrue()
        ->and($walk->state()->readsCompletely)->toBeFalse()
        ->and($walk->shape()->opaque)->toBe([$part])
        ->and($walk->shape()->isSealed())->toBeFalse();
})->with([OpaqueKind::UnreadWholeWrite, OpaqueKind::UnnamedKeyWrite, OpaqueKind::ByReferenceAppend]);

it('drops the parts held before a whole write that always runs, and keeps them past one that may not', function (bool $conditional, int $parts) {
    $walk = arrayWalk();
    $walk->holdOpaque(walkPart());
    $walk->assign(ArrayShapes::ofAnalysis(walkAnalysis(['a' => 'number'])), $conditional, new Array_);

    expect($walk->shape()->opaque)->toHaveCount($parts)
        ->and($walk->state()->holdsUnreadKeys)->toBe($parts === 1);
})->with(['always' => [false, 0], 'conditionally' => [true, 1]]);

it('lowers its shape into the analysis it holds', function () {
    $walk = arrayWalk(walkAnalysis(['a' => 'string']));
    $walk->write('b', ['type' => 'number', 'optional' => false], true);
    $walk->holdOpaque(walkPart(OpaqueKind::ByReferenceAppend));
    $lowered = new MethodAnalysis;
    ShapeLowering::into($lowered, $walk->shape());

    expect($lowered)->toEqual($walk->state());
});

it('joins exclusive bodies, the keys held before them one more branch where none may run', function () {
    $walk = arrayWalk(walkAnalysis(['a' => 'string']));
    $body = clone $walk;
    $body->write('b', ['type' => 'number', 'optional' => false], false);
    $recorded = [];
    $walk->branches([$body], true, function (MethodAnalysis $joined, array $branches, array $notes) use (&$recorded): void {
        $recorded = [array_column($joined->properties, 'name'), count($branches), count($notes)];
    });

    expect(walkedKeys($walk))->toBe(['a' => 'string', '?b' => 'number'])
        ->and($recorded)->toBe([['a', 'b'], 2, 2]);
});

it('records the value a join leaves out, naming the body\'s untyped write', function () {
    $walk = arrayWalk();
    $typed = clone $walk;
    $typed->write('a', ['type' => 'string', 'optional' => false], false);
    $untyped = clone $walk;
    $value = new Variable('x');
    $untyped->write('a', ['type' => 'unknown', 'optional' => false], false, $value);
    $notes = [];
    $walk->branches([$typed, $untyped], false, function (MethodAnalysis $joined, array $branches, array $branchNotes) use (&$notes): void {
        $notes = $branchNotes;
    });

    expect(walkedKeys($walk))->toBe(['a' => 'string'])
        ->and($notes)->toBe([[], [['a', $value]]])
        ->and($walk->untypedWrites())->toBe([['a', $value]]);
});

it('joins the bodies\' opaque parts, a part they copied once, and none from a body that replaced the keys', function () {
    $before = walkPart();
    $walk = arrayWalk();
    $walk->holdOpaque($before);
    $replaced = clone $walk;
    $replaced->assign(ArrayShapes::ofAnalysis(walkAnalysis(['a' => 'number'])), false, new Array_);
    $kept = clone $walk;
    $own = walkPart(OpaqueKind::UnnamedKeyWrite);
    $kept->holdOpaque($own);
    $joined = static function (array $bodies, bool $mayRunNone) use ($walk): array {
        $join = clone $walk;
        $join->branches(array_map(static fn (ArrayWalk $body): ArrayWalk => clone $body, $bodies), $mayRunNone, static function (): void {});

        return $join->shape()->opaque;
    };

    expect($joined([$replaced, $kept], false))->toBe([$before, $own])
        ->and($joined([$replaced, clone $replaced], false))->toBe([])
        ->and($joined([$replaced], true))->toBe([$before])
        ->and($joined([$kept, clone $kept], false))->toBe([$before, $own]);
});
