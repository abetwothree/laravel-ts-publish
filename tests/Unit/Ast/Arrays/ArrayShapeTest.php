<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\Certainty;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaqueKind;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaquePart;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeSignature;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeWide;
use PhpParser\Node\Expr\Variable;

/**
 * A presence as `[optional, absent, removed]`.
 *
 * @return array{bool, bool, bool}
 */
function presenceOf(KeyPresence $presence): array
{
    return [$presence->optional, $presence->absent, $presence->removed];
}

/**
 * A shape entry for one named key with the given presence.
 */
function shapeKey(string $name, KeyPresence $presence = new KeyPresence): ShapeKey
{
    return new ShapeKey($name, new KeyValue(['type' => 'number', 'optional' => $presence->optional, 'description' => '']), $presence);
}

/**
 * A `[key: string]: number` signature entry.
 */
function shapeSignature(): ShapeSignature
{
    return new ShapeSignature('[key: string]', new KeyValue(['type' => 'number | undefined', 'optional' => false, 'description' => '']));
}

describe('KeyPresence', function () {
    it('reads a fold row\'s write, a bare `?` as a key its writer may leave unset', function (array $row, array $presence) {
        expect(presenceOf(KeyPresence::fromRow(['name' => 'k', 'type' => 'number', 'description' => '', ...$row])))->toBe($presence);
    })->with([
        'required' => [['optional' => false], [false, false, false]],
        'required by a cast, whatever the body says' => [['optional' => false, 'absent' => true, 'removed' => true], [false, false, false]],
        'a bare `?`' => [['optional' => true], [true, true, false]],
        'absent' => [['optional' => true, 'absent' => true], [true, true, false]],
        'removed' => [['optional' => true, 'removed' => true], [true, false, true]],
        'absent and removed' => [['optional' => true, 'absent' => true, 'removed' => true], [true, true, true]],
    ]);

    it('reads a written value as addProperty() does, a bare `?` as a value removed', function (array $value, array $presence) {
        expect(presenceOf(KeyPresence::fromValue(['type' => 'number', ...$value])))->toBe($presence);
    })->with([
        'required' => [['optional' => false], [false, false, false]],
        'required, whatever its facts say' => [['optional' => false, 'absent' => true, 'removed' => true], [false, false, false]],
        'a bare `?`' => [['optional' => true], [true, false, true]],
        'absent' => [['optional' => true, 'absent' => true], [true, true, false]],
        'removed' => [['optional' => true, 'removed' => true], [true, false, true]],
        'absent and removed' => [['optional' => true, 'absent' => true, 'removed' => true], [true, true, true]],
    ]);

    it('lays a key write over what the key held, as the walk writes one key', function (bool $valueOptional, bool $sent, ?KeyPresence $held, bool $conditional, array $presence) {
        expect(presenceOf(KeyPresence::written($valueOptional, $sent, $held, $conditional)))->toBe($presence);
    })->with([
        'an unconditional write of a new key' => [false, false, null, false, [false, false, false]],
        'a conditional write of a new key' => [false, false, null, true, [true, true, false]],
        'a conditional write over a sure key' => [false, false, new KeyPresence, true, [false, false, false]],
        'a conditional write over an absent key' => [false, false, new KeyPresence(true, true, false), true, [true, true, false]],
        'a conditional write over a removed key' => [false, false, new KeyPresence(true, false, true), true, [true, false, true]],
        'a conditional write over a key a cast leaves optional' => [false, false, new KeyPresence(true, false, false), true, [true, false, false]],
        'an unconditional write over a removed key' => [false, false, new KeyPresence(true, false, true), false, [false, false, false]],
        'a value the array sends as removed' => [true, true, new KeyPresence, false, [true, false, true]],
        'a value left out that the array sends' => [true, false, new KeyPresence, false, [true, false, false]],
    ]);

    it('lays a whole write over a held key, as the walk merges one', function (KeyPresence $written, KeyPresence $held, bool $conditional, array $presence) {
        expect(presenceOf($written->after($held, $conditional)))->toBe($presence);
    })->with([
        'an unconditional write takes its own' => [new KeyPresence, new KeyPresence(true, true, true), false, [false, false, false]],
        'a conditional write keeps an absent held key\'s' => [new KeyPresence, new KeyPresence(true, true, false), true, [true, true, false]],
        'a conditional write keeps a removed held key\'s' => [new KeyPresence, new KeyPresence(true, false, true), true, [true, false, true]],
        'a removed value stays removed' => [new KeyPresence(true, false, true), new KeyPresence, true, [true, false, true]],
        'an absent value stays absent' => [new KeyPresence(true, true, false), new KeyPresence, false, [true, true, false]],
        'a conditional write of a key no write set' => [new KeyPresence, KeyPresence::missing(), true, [true, true, false]],
        'an unconditional write of a key no write set' => [new KeyPresence(true, false, true), KeyPresence::missing(), false, [true, false, true]],
    ]);

    it('lays itself on a row, replacing the presence the row held', function () {
        $row = ['name' => 'k', 'type' => 'number', 'optional' => true, 'description' => '', 'absent' => true];

        expect(new KeyPresence(true, false, true)->onRow($row))
            ->toBe(['name' => 'k', 'type' => 'number', 'optional' => true, 'description' => '', 'removed' => true])
            ->and(new KeyPresence()->onRow($row))->toBe(['name' => 'k', 'type' => 'number', 'optional' => false, 'description' => '']);
    });

    it('holds a key on the path a `+` reads it held, keeping its removal', function (KeyPresence $left, array $presence) {
        expect(presenceOf($left->held()))->toBe($presence);
    })->with([
        'sure' => [new KeyPresence, [false, false, false]],
        'absent' => [new KeyPresence(true, true, false), [false, false, false]],
        'removed' => [new KeyPresence(true, false, true), [false, false, true]],
        'absent and removed' => [new KeyPresence(true, true, true), [false, false, true]],
    ]);

    it('joins a key\'s presence across branches, as a branch join does', function (array $held, bool $inEvery, array $presence) {
        expect(presenceOf(KeyPresence::join($held, $inEvery)))->toBe($presence);
    })->with([
        'sure in every branch' => [[new KeyPresence, new KeyPresence], true, [false, false, false]],
        'missing from one' => [[new KeyPresence], false, [true, true, false]],
        'absent in one' => [[new KeyPresence, new KeyPresence(true, true, false)], true, [true, true, false]],
        'removed in one' => [[new KeyPresence, new KeyPresence(true, false, true)], true, [true, false, true]],
        'optional by a cast in one' => [[new KeyPresence, new KeyPresence(true, false, false)], true, [true, false, false]],
    ]);

    it('says a key is absent only where no path removes it', function (KeyPresence $presence, bool $absentOnly) {
        expect($presence->isAbsentOnly())->toBe($absentOnly);
    })->with([
        'absent' => [new KeyPresence(true, true, false), true],
        'absent and removed' => [new KeyPresence(true, true, true), false],
        'removed' => [new KeyPresence(true, false, true), false],
        'sure' => [new KeyPresence, false],
    ]);
});

describe('ArrayShape', function () {
    it('holds a key yes where it is sure, maybe where it may be missing, and no only where it is sealed', function () {
        $sealed = new ArrayShape([shapeKey('a'), shapeKey('b', new KeyPresence(true, true, false))]);
        $open = new ArrayShape([shapeKey('a')], wide: new ShapeWide(holdsUnreadKeys: true));

        expect($sealed->certainty('a'))->toBe(Certainty::Yes)
            ->and($sealed->certainty('b'))->toBe(Certainty::Maybe)
            ->and($sealed->certainty('c'))->toBe(Certainty::No)
            ->and($open->certainty('a'))->toBe(Certainty::Yes)
            ->and($open->certainty('c'))->toBe(Certainty::Maybe);
    });

    it('reads a key from the last entry of its name, as a read of the key sees it', function () {
        $sure = shapeKey('a');
        $missing = shapeKey('a', new KeyPresence(true, true, false));

        expect(new ArrayShape([$sure, $missing])->certainty('a'))->toBe(Certainty::Maybe)
            ->and(new ArrayShape([$missing, $sure])->certainty('a'))->toBe(Certainty::Yes);
    });

    it('is sealed with no extras, opaque part, signature, or unread or unnamed key', function (ArrayShape $shape, bool $sealed) {
        expect($shape->isSealed())->toBe($sealed);
    })->with([
        'named keys alone' => [new ArrayShape([shapeKey('a')]), true],
        'a signature' => [new ArrayShape([shapeKey('a'), shapeSignature()]), false],
        'extras' => [new ArrayShape([shapeKey('a')], new KeyValue(['type' => 'string', 'optional' => false, 'description' => ''])), false],
        'an opaque part' => [new ArrayShape([shapeKey('a')], opaque: [new OpaquePart(new Variable('rest'), OpaqueKind::UnreadSpread)]), false],
        'unread keys' => [new ArrayShape([shapeKey('a')], wide: new ShapeWide(holdsUnreadKeys: true)), false],
        'unnamed keys' => [new ArrayShape([shapeKey('a')], wide: new ShapeWide(holdsUnnamedKeys: true)), false],
    ]);

    it('derives whether it holds a key from its entries and its stored proof', function (ArrayShape $shape, Certainty $nonEmpty) {
        expect($shape->nonEmpty())->toBe($nonEmpty);
    })->with([
        'a required entry' => [new ArrayShape([shapeKey('a')]), Certainty::Yes],
        'an empty sealed shape' => [new ArrayShape, Certainty::No],
        'its only key removed, as when() may' => [new ArrayShape([shapeKey('a', new KeyPresence(true, false, true))]), Certainty::Maybe],
        'a map a guard proves holds a key' => [new ArrayShape([shapeSignature()], nonEmptyProof: true), Certainty::Yes],
        'that map once an unset() drops the proof' => [new ArrayShape([shapeSignature()]), Certainty::Maybe],
        'no entry beside unread keys' => [new ArrayShape(wide: new ShapeWide(holdsUnreadKeys: true)), Certainty::Maybe],
    ]);

    it('is no list where a key PHP stores as a string is sent on every path', function (ArrayShape $shape, Certainty $isList) {
        expect($shape->isList())->toBe($isList);
    })->with([
        'a string key' => [new ArrayShape([shapeKey('a')]), Certainty::No],
        'a numeric string PHP keeps a string' => [new ArrayShape([shapeKey('1.5')]), Certainty::No],
        'a string key that may be missing' => [new ArrayShape([shapeKey('a', new KeyPresence(true, true, false))]), Certainty::Maybe],
        'an int key' => [new ArrayShape([shapeKey('0')]), Certainty::Maybe],
    ]);
});

it('answers each certainty by its own predicate', function (Certainty $certainty, array $answers) {
    expect([$certainty->yes(), $certainty->maybe(), $certainty->no()])->toBe($answers);
})->with([
    'yes' => [Certainty::Yes, [true, false, false]],
    'maybe' => [Certainty::Maybe, [false, true, false]],
    'no' => [Certainty::No, [false, false, true]],
]);
