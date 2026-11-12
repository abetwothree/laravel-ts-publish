<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use PhpParser\Node\Expr\Variable;
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
 * A map's printed arm: `Record<string, $value>`, the spread's $wide data on its result, and $value as the map's value;
 * with $mayBeEmpty, a spread that may be `[]`.
 *
 * @param  array<string, mixed>  $extra  more of the value's result
 * @param  array<string, mixed>  $wide  the spread's custom imports and carried classes
 * @return array{arm: array{type: string, result: array<string, mixed>}, map: array<string, mixed>}
 */
function literalMapArm(string $value, bool $checksKeys, array $extra = [], array $wide = [], bool $mayBeEmpty = false): array
{
    $type = 'Record<string, '.$value.'>';

    return [
        'arm' => ['type' => $type, 'result' => ['type' => $type, 'optional' => false, ...$extra, ...$wide]],
        'map' => ['value' => ['type' => $value, 'optional' => false, ...$extra], 'checksKeys' => $checksKeys, 'mayBeEmpty' => $mayBeEmpty],
    ];
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
it('hands the print each arm it keeps, in item order, a map\'s after another item only where every key fits its value', function (string $value, bool $kept) {
    $resource = ['fqcn' => User::class, 'isModel' => true, 'isCollection' => false];
    $first = new LiteralWrites;
    $first->recordArm(literalMapArm('string', checksKeys: false));
    $first->recordKey('a', ['type' => '1 | 2', 'optional' => false], 1);
    $after = new LiteralWrites;
    $after->recordKey('a', ['type' => '1 | 2', 'optional' => false], 0);
    $after->recordArm(['arm' => $resource, 'map' => null]);
    $after->recordArm(literalMapArm($value, checksKeys: true));

    expect(foldedInto($first)->spreadArms())->toBe([literalMapArm('string', checksKeys: false)['arm']])
        ->and(foldedInto($after)->spreadArms())->toBe($kept ? [$resource, literalMapArm($value, checksKeys: true)['arm']] : [$resource]);
})->with([
    'a value the key fits' => ['number', true],
    'a value the key does not fit' => ['string', false],
]);

// `keyof` a map is every key, so the print would keep the later map alone, though PHP keeps the earlier map's keys.
it('joins a map\'s arm with each earlier map\'s, in its place, one value per class', function () {
    $resource = ['fqcn' => User::class, 'isModel' => true, 'isCollection' => false];
    $writes = new LiteralWrites;
    $writes->recordArm(literalMapArm('string', checksKeys: false));
    $writes->recordArm(['arm' => $resource, 'map' => null]);
    $writes->recordArm(literalMapArm('number', checksKeys: true));
    $writes->recordArm(literalMapArm('User', checksKeys: true, extra: ['modelFqcn' => User::class]));
    $writes->recordArm(literalMapArm('User | null', checksKeys: true, extra: ['modelFqcn' => User::class]));

    expect(foldedInto($writes)->spreadArms())->toBe([$resource, [
        'type' => 'Record<string, string | number | User | null>',
        'result' => ['type' => 'Record<string, string | number | User | null>', 'optional' => false, 'embeddedModelFqcns' => [User::class]],
    ]]);
});

it('leaves out a joined map\'s arm where a key does not fit the joined value, as for one map', function () {
    $writes = new LiteralWrites;
    $writes->recordArm(literalMapArm('string', checksKeys: false));
    $writes->recordKey('a', ['type' => 'boolean', 'optional' => false], 1);
    $writes->recordArm(literalMapArm('number', checksKeys: true));

    expect(foldedInto($writes)->spreadArms())->toBe([]);
});

it('joins a map whose value is unknown into an unknown map', function () {
    $writes = new LiteralWrites;
    $writes->recordArm(literalMapArm('unknown', checksKeys: false));
    $writes->recordArm(literalMapArm('{ a: string }', checksKeys: true));

    expect(foldedInto($writes)->spreadArms())->toBe([[
        'type' => 'Record<string, unknown>',
        'result' => ['type' => 'Record<string, unknown>', 'optional' => false],
    ]]);
});

// Printed alone, a signature would also cover the keys of a resource's arm beside it.
it('prints a map that may be empty only joined into a map that may not', function () {
    $resource = ['fqcn' => User::class, 'isModel' => true, 'isCollection' => false];
    $alone = new LiteralWrites;
    $alone->recordArm(literalMapArm('number', checksKeys: false, mayBeEmpty: true));
    $alone->recordArm(['arm' => $resource, 'map' => null]);
    $joined = new LiteralWrites;
    $joined->recordArm(literalMapArm('number', checksKeys: false, mayBeEmpty: true));
    $joined->recordArm(literalMapArm('string', checksKeys: true));
    $both = new LiteralWrites;
    $both->recordArm(literalMapArm('number', checksKeys: false, mayBeEmpty: true));
    $both->recordArm(literalMapArm('string', checksKeys: true, mayBeEmpty: true));

    expect(foldedInto($alone)->spreadArms())->toBe([$resource])
        ->and(foldedInto($joined)->spreadArms())->toBe([[
            'type' => 'Record<string, number | string>',
            'result' => ['type' => 'Record<string, number | string>', 'optional' => false],
        ]])
        ->and(foldedInto($both)->spreadArms())->toBe([]);
});

it('carries each joined map\'s custom imports and carried classes on the joined arm', function () {
    $writes = new LiteralWrites;
    $writes->recordArm(literalMapArm('Money', checksKeys: false, wide: ['customImports' => ['@/money' => ['Money']]]));
    $writes->recordArm(literalMapArm('Rate', checksKeys: true, wide: ['carriedFqcns' => ['models' => [User::class]]]));

    expect(foldedInto($writes)->spreadArms())->toBe([[
        'type' => 'Record<string, Money | Rate>',
        'result' => [
            'type' => 'Record<string, Money | Rate>',
            'optional' => false,
            'customImports' => ['@/money' => ['Money']],
            'carriedFqcns' => ['models' => [User::class]],
        ],
    ]]);
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

it('keeps a map\'s arm after a key holding a gap where the key\'s gap-free value fits the map\'s', function () {
    $resource = ['fqcn' => User::class, 'isModel' => true, 'isCollection' => false];
    $writes = new LiteralWrites;
    $writes->recordKey('a', ['type' => 'number | $gap1', 'optional' => false], 0);
    $writes->recordArm(['arm' => $resource, 'map' => null]);
    $writes->recordArm(literalMapArm('number | $gap2', checksKeys: true));

    expect(foldedInto($writes)->spreadArms())->toBe([$resource, literalMapArm('number | $gap2', checksKeys: true)['arm']]);
});

it('keeps a gap in the gap mode where a key\'s later source may write a value no rule types, which the join drops (PJ10b)', function (bool $shows) {
    $writes = new LiteralWrites(new Variable('literal'), new AnalysisScope(new ReflectionClass(stdClass::class)));
    $writes->recordKey('k', ['type' => 'string', 'optional' => false], 0);
    $writes->recordKeys([['k', ['type' => 'unknown', 'optional' => true, 'absent' => true]]], ['type' => '{ k?: unknown }', 'optional' => false], 1);
    $fold = static fn (): array => array_column(foldedInto($writes)->properties, 'type', 'name');
    $type = ($shows ? resolve(AnalysisMemo::class)->showingGaps($fold) : $fold())['k'];

    expect(VisibleGaps::stripped($type)[0])->toBe('string')
        ->and(VisibleGaps::ids($type))->toHaveCount($shows ? 1 : 0);
})->with([false, true]);
