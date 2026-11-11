<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * The analysis a literal's recorded writes fold into.
 */
function foldedInto(LiteralWrites $writes, bool $liftsMerges = true): MethodAnalysis
{
    $analysis = new MethodAnalysis;
    $writes->applyTo($analysis, $liftsMerges);

    return $analysis;
}

/**
 * Each key an analysis holds, as `name` (`?` where it may not be sent) => its type.
 *
 * @return array<string, string>
 */
function foldedKeys(MethodAnalysis $analysis): array
{
    $keys = [];

    foreach ($analysis->properties as $property) {
        $keys[$property['name'].($property['optional'] ? '?' : '')] = $property['type'];
    }

    return $keys;
}

/**
 * A helper's analysis holding one key.
 *
 * @param  array<string, mixed>  $value
 */
function sourceOf(string $name, array $value, bool $optional = false): MethodAnalysis
{
    $source = new MethodAnalysis;
    $source->addProperty($name, ['optional' => false, ...$value], $optional);

    return $source;
}

it('writes a key a later write always sets as that write alone, with none of the earlier value\'s classes', function () {
    $writes = new LiteralWrites;
    $writes->recordKey('by', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class], 0);
    $writes->record(sourceOf('by', ['type' => 'string']), 1);
    $analysis = foldedInto($writes);

    expect(foldedKeys($analysis))->toBe(['by' => 'string'])
        ->and($analysis->allChannelFqcns())->toBe([]);
});

// No held class stays: a join keeps the class of every value PHP may send, and two one name spells stay two arms.
it('joins a key with a later write that may leave it unset, every class kept apart and queued', function () {
    $writes = new LiteralWrites;
    $writes->recordKey('u', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class], 0);
    $writes->record(sourceOf('u', ['type' => 'User', 'modelFqcn' => CrmUser::class], optional: true), 1);
    $analysis = foldedInto($writes);

    expect(foldedKeys($analysis))->toBe(['u' => 'User | User'])
        ->and($analysis->inlineModelFqcns['u'] ?? null)->toBe([User::class, CrmUser::class]);
});

it('keeps a held untyped value untyped, and leaves out a later untyped value that may not land', function () {
    $held = new LiteralWrites;
    $held->recordKey('by', ['type' => 'unknown', 'optional' => false], 0);
    $held->record(sourceOf('by', ['type' => 'string'], optional: true), 1);
    $later = new LiteralWrites;
    $later->recordKey('by', ['type' => 'string', 'optional' => false], 0);
    $later->record(sourceOf('by', ['type' => 'unknown'], optional: true), 1);

    expect(foldedKeys(foldedInto($held)))->toBe(['by' => 'unknown'])
        ->and(foldedKeys(foldedInto($later)))->toBe(['by' => 'string']);
});

it('keeps what both sides of a joined key leave open', function () {
    $writes = new LiteralWrites;
    $writes->recordKey('by', ['type' => '{ a: string }', 'optional' => false, 'openReadings' => [".a\0string"]], 0);
    $writes->recordKey('by', ['type' => '{ b: number }', 'optional' => true, 'absent' => true, 'openReadings' => [".b\0number"]], 1);

    expect(foldedInto($writes)->openReadings)->toBe(['by' => [".a\0string", ".b\0number"]]);
});

it('marks a key whose later value may be removed optional and removed, never sending the value before it', function () {
    $writes = new LiteralWrites;
    $writes->recordKey('by', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class], 0);
    $writes->recordKey('by', ['type' => 'string', 'optional' => true], 1);

    expect(foldedInto($writes)->properties)->toBe([
        ['name' => 'by', 'type' => 'string', 'optional' => true, 'description' => '', 'removed' => true],
    ]);
});

// A cast decides a key is always sent, whatever the body says of its value, so its write is a certain one.
it('reads a row a cast keeps required as a write that sets the key on every path', function () {
    $source = new MethodAnalysis(properties: [
        ['name' => 'by', 'type' => 'string', 'optional' => false, 'description' => '', 'removed' => true],
    ]);
    $writes = new LiteralWrites;
    $writes->recordKey('by', ['type' => 'number', 'optional' => false], 0);
    $writes->record($source, 1);

    expect(foldedKeys(foldedInto($writes)))->toBe(['by' => 'string']);
});

it('keeps a signature entry where it stood among the keys, folding none', function () {
    $writes = new LiteralWrites;
    $writes->recordKey('a', ['type' => 'number', 'optional' => false], 0);
    $writes->recordSignature('[key: string]', ['type' => 'string', 'optional' => false]);
    $writes->recordKey('b', ['type' => 'number', 'optional' => false], 2);
    $writes->recordKey('a', ['type' => 'string', 'optional' => false], 3);

    expect(array_column(foldedInto($writes)->properties, 'name'))->toBe(['a', '[key: string]', 'b']);
});

// A printed arm sends the keys it always writes over the ones before it; one it may write joins them.
it('leaves a key only a printed arm sends to the arm, and joins one the arm may write', function () {
    $writes = new LiteralWrites;
    $writes->recordKey('name', ['type' => 'number', 'optional' => false], 0);
    $writes->recordKey('nick', ['type' => 'number', 'optional' => false], 1);
    $writes->recordKeys([
        ['name', ['type' => 'string', 'optional' => false]],
        ['nick', ['type' => 'string', 'optional' => true, 'absent' => true]],
        ['id', ['type' => 'number', 'optional' => false]],
    ], ['type' => '{ name: string; nick?: string; id: number }', 'optional' => false], 2, printedArm: true);

    expect(foldedKeys(foldedInto($writes)))->toBe(['nick' => 'number | string']);
});

it('joins each key written before a map spread with the map\'s value, and no later one', function () {
    $writes = new LiteralWrites;
    $writes->recordKey('a', ['type' => 'number', 'optional' => false], 0);
    $writes->recordMapSpread(['type' => 'boolean', 'optional' => false], ['type' => 'Record<string, boolean>', 'optional' => false], 1);
    $writes->recordKey('b', ['type' => 'number', 'optional' => false], 2);

    expect(foldedKeys(foldedInto($writes)))->toBe(['a' => 'number | boolean', 'b' => 'number']);
});

// TypeScript cannot drop a key from a map's signature, so a map's arm whose value a key does not fit is left out.
it('hands the print each arm it keeps, in item order, a map\'s only where every key the fold writes fits its value', function () {
    $resource = ['fqcn' => User::class, 'isModel' => true, 'isCollection' => false];
    $strings = ['type' => 'Record<string, string>', 'result' => ['type' => 'Record<string, string>', 'optional' => false]];
    $numbers = ['type' => 'Record<string, number>', 'result' => ['type' => 'Record<string, number>', 'optional' => false]];
    $writes = new LiteralWrites;
    $writes->recordArm(['arm' => $strings, 'mapValue' => null]);
    $writes->recordKey('a', ['type' => '1 | 2', 'optional' => false], 1);
    $writes->recordArm(['arm' => $resource, 'mapValue' => null]);
    $writes->recordArm(['arm' => $strings, 'mapValue' => 'string']);
    $writes->recordArm(['arm' => $numbers, 'mapValue' => 'number']);

    expect(foldedInto($writes)->spreadArms())->toBe([$strings, $resource, $numbers]);
});

// Outside filter() PHP appends a merge call under an int key, so it sets no named key.
it('writes no key a merge call sets where nothing lifts it', function () {
    $writes = new LiteralWrites;
    $writes->recordKey('a', ['type' => 'number', 'optional' => false], 0);
    $writes->record(sourceOf('a', ['type' => 'string']), 1, fromMerge: true);
    $writes->record(sourceOf('m', ['type' => 'string']), 2, fromMerge: true);

    expect(foldedKeys(foldedInto($writes, liftsMerges: false)))->toBe(['a' => 'number'])
        ->and(foldedKeys(foldedInto($writes, liftsMerges: true)))->toBe(['a' => 'number', 'm' => 'string']);
});

it('carries every source\'s custom imports, carried classes and read flags, the losing ones\' too', function () {
    $source = sourceOf('by', ['type' => 'Money', 'customImports' => ['@/money' => ['Money']]]);
    $source->readsCompletely = false;
    $source->holdsUnnamedKeys = true;
    $writes = new LiteralWrites;
    $writes->record($source, 0);
    $writes->recordKey('by', ['type' => 'string', 'optional' => false], 1);
    $analysis = foldedInto($writes);

    expect(foldedKeys($analysis))->toBe(['by' => 'string'])
        ->and($analysis->customImports)->toBe(['@/money' => ['Money']])
        ->and($analysis->readsCompletely)->toBeFalse()
        ->and($analysis->holdsUnnamedKeys)->toBeTrue();
});

it('writes into an empty analysis only', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('held', ['type' => 'number', 'optional' => false]);

    expect(fn () => new LiteralWrites()->applyTo($analysis, true))->toThrow(LogicException::class);
});

// A memoised helper's analysis is a shallow clone the next caller reads again, so a fold never writes into a source.
it('leaves every recorded source as it was', function () {
    $source = sourceOf('u', ['type' => 'User', 'modelFqcn' => CrmUser::class], optional: true);
    $source->addProperty('n', ['type' => 'number', 'optional' => false]);
    $before = serialize($source);
    $writes = new LiteralWrites;
    $writes->recordKey('u', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class], 0);
    $writes->record($source, 1);
    foldedInto($writes);

    expect(serialize($source))->toBe($before);
});
