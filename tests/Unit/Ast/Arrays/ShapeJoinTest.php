<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\JoinOptions;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\ShapeJoin;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\Certainty;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaqueKind;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaquePart;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeSignature;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeWide;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use PhpParser\Node\Expr\Variable;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A value of the given type, with the given presence in its row.
 */
function joinTypeValue(string $type, KeyPresence $presence = new KeyPresence): KeyValue
{
    return new KeyValue(['type' => $type, 'optional' => $presence->optional, 'description' => '', ...$presence->facts()]);
}

/**
 * A shape of named keys, each `name => type`, a `?` before the name marking it absent on some path.
 *
 * @param  array<string, string>  $keys
 */
function joinShape(array $keys, ?string $extras = null, ShapeWide $wide = new ShapeWide): ArrayShape
{
    $entries = [];

    foreach ($keys as $name => $type) {
        $absent = str_starts_with($name, '?');
        $presence = $absent ? new KeyPresence(true, true, false) : new KeyPresence;
        $entries[] = new ShapeKey(ltrim($name, '?'), joinTypeValue($type, $presence), $presence);
    }

    return new ArrayShape($entries, $extras === null ? null : joinTypeValue($extras), wide: $wide);
}

/**
 * A joined shape's keys as `name => type`, a `?` before an optional one's name.
 *
 * @return array<string, string>
 */
function joinedKeys(ArrayShape $shape): array
{
    $keys = [];

    foreach ($shape->entries as $entry) {
        $keys[$entry instanceof ShapeKey ? ($entry->presence->optional ? '?' : '').$entry->name : $entry->pattern] = $entry->value->type();
    }

    return $keys;
}

it('joins a key every branch holds by its values', function () {
    $joined = ShapeJoin::exclusive([joinShape(['a' => 'string']), joinShape(['a' => 'number'])], new JoinOptions);

    expect(joinedKeys($joined))->toBe(['a' => 'string | number']);
});

it('joins a key\'s presence as its entry states it, over the presence its value was read with', function () {
    $branch = new ArrayShape([new ShapeKey('a', joinTypeValue('string'), KeyPresence::missing())]);
    $joined = ShapeJoin::exclusive([$branch, $branch], new JoinOptions);

    expect($joined->entries[0])->toBeInstanceOf(ShapeKey::class)
        ->and($joined->entries[0]->presence ?? null)->toEqual(KeyPresence::missing())
        ->and(joinedKeys($joined))->toBe(['?a' => 'string']);
});

it('makes a key a sealed branch lacks optional and absent there', function () {
    $joined = ShapeJoin::exclusive([joinShape(['a' => 'string', 'b' => 'number']), joinShape(['a' => 'string'])], new JoinOptions);
    $b = $joined->entries[1];

    expect(joinedKeys($joined))->toBe(['a' => 'string', '?b' => 'number'])
        ->and($b instanceof ShapeKey ? [$b->presence->absent, $b->presence->removed] : null)->toBe([true, false]);
});

it('joins a key a branch with extras lacks with those extras, optional and absent (X31-shaped)', function () {
    $joined = ShapeJoin::exclusive([joinShape(['by' => 'string']), joinShape([], 'number')], new JoinOptions);

    expect(joinedKeys($joined))->toBe(['?by' => 'string | number'])
        ->and($joined->extras?->type())->toBe('number');
});

it('keeps a key both branches send required beside one branch\'s extras (X22-shaped)', function () {
    $joined = ShapeJoin::exclusive([joinShape(['by' => 'string'], 'string'), joinShape(['by' => 'string'])], new JoinOptions);

    expect(joinedKeys($joined))->toBe(['by' => 'string'])
        ->and($joined->extras?->type())->toBe('string');
});

it('keeps a seed\'s sure key required and joins a literal\'s own key with the seed\'s extras (Y33-shaped)', function () {
    $joined = ShapeJoin::exclusive([joinShape(['by' => 'string'], 'string'), joinShape(['by' => "'x'", 'z' => 'number'])], new JoinOptions);

    expect(joinedKeys($joined))->toBe(['by' => "string | 'x'", '?z' => 'string | number']);
});

it('never absorbs a named key into the extras (AR-5)', function (ArrayShape $literal, array $keys) {
    $x = joinShape(['a' => 'number'], 'string');
    $joined = ShapeJoin::exclusive([$literal, $x], new JoinOptions);

    expect(joinedKeys($joined))->toBe($keys)
        ->and($joined->extras?->type())->toBe('string');
})->with([
    'a key only the literal sends joins the extras' => [joinShape(['k' => 'number', 'a' => 'number']), ['?k' => 'number | string', 'a' => 'number']],
    'a key the extras may hold keeps its own presence' => [joinShape(['k' => 'string', 'a' => 'number']), ['?k' => 'string', 'a' => 'number']],
]);

it('joins signatures by pattern and never makes one optional', function () {
    $signature = new ShapeSignature('[key: string]', joinTypeValue('number', new KeyPresence(true, true, false)));
    $joined = ShapeJoin::exclusive([new ArrayShape([$signature]), new ArrayShape([])], new JoinOptions);

    expect(joinedKeys($joined))->toBe(['[key: string]' => 'number'])
        ->and($joined->entries[0]->value->row)->toBe(['type' => 'number', 'optional' => false, 'description' => '']);
});

it('unions the branches\' opaque parts and combines their read flags', function () {
    $first = new OpaquePart(new Variable('x'), OpaqueKind::UnreadSpread);
    $second = new OpaquePart(new Variable('y'), OpaqueKind::UnreadSpread);
    $joined = ShapeJoin::exclusive([
        new ArrayShape([], opaque: [$first], wide: new ShapeWide(readsCompletely: false)),
        new ArrayShape([], opaque: [$second], wide: new ShapeWide(holdsUnnamedKeys: true)),
    ], new JoinOptions);

    expect($joined->opaque)->toBe([$first, $second])
        ->and([$joined->wide->readsCompletely, $joined->wide->holdsUnreadKeys, $joined->wide->holdsUnnamedKeys])
        ->toBe([false, false, true]);
});

it('reads each branch\'s derived non-emptiness, proving it only where every branch is non-empty', function (array $branches, Certainty $nonEmpty) {
    expect(ShapeJoin::exclusive($branches, new JoinOptions)->nonEmpty())->toBe($nonEmpty);
})->with([
    'every branch sends a key' => [fn () => [joinShape(['a' => 'number']), joinShape(['b' => 'string'])], Certainty::Yes],
    'one branch may be empty' => [fn () => [joinShape(['a' => 'number']), joinShape([])], Certainty::Maybe],
    'every branch is empty' => [fn () => [joinShape([]), joinShape([])], Certainty::No],
]);

it('carries no arm and keeps a lone branch\'s wide data', function () {
    $wide = new ShapeWide(
        customImports: ['@/x' => ['X']],
        flatTypeAlias: 'Xs',
        spreadArms: [['fqcn' => User::class, 'isModel' => true, 'isCollection' => false]],
    );
    $joined = ShapeJoin::exclusive([joinShape(['a' => 'number'], wide: $wide)], new JoinOptions);

    expect($joined->wide->spreadArms)->toBe([])
        ->and($joined->wide->customImports)->toBe(['@/x' => ['X']])
        ->and($joined->wide->flatTypeAlias)->toBe('Xs');
});

it('keeps a key the first branch leaves untyped untyped only where told to', function (array $types, JoinOptions $options, string $type) {
    $branches = array_map(static fn (string $type): ArrayShape => joinShape(['k' => $type]), $types);

    expect(joinedKeys(ShapeJoin::exclusive($branches, $options)))->toBe(['k' => $type]);
})->with([
    'told to' => [['unknown', 'string'], new JoinOptions(dropsUntypedBranches: true, keepsFirstUntyped: true), 'unknown'],
    'not told to' => [['unknown', 'string'], new JoinOptions(dropsUntypedBranches: true), 'string'],
    'a later branch untyped' => [['string', 'unknown'], new JoinOptions(dropsUntypedBranches: true, keepsFirstUntyped: true), 'string'],
]);

it('joins one key\'s sources, a dropping join dropping a fill another source spells unalike', function () {
    $fill = new KeyValue(
        ['type' => 'User | User', 'optional' => false, 'description' => ''],
        ['inlineModelFqcns' => [User::class], 'declaredTokenFqcns' => [null, User::class]],
    );
    $model = new KeyValue(['type' => 'User', 'optional' => false, 'description' => ''], ['modelFqcns' => User::class]);

    expect(ShapeJoin::value([$fill, $model], new JoinOptions(dropsUntypedBranches: true))->type())->toBe('User')
        ->and(ShapeJoin::value([$fill, $model], new JoinOptions)->type())->toBe('unknown');
});

it('leaves a gap for each method return a branch spells unalike in the gap mode, under a provisional read', function () {
    $branches = array_map(static function (array $branch): ArrayShape {
        $analysis = new ResourceAnalysis(properties: [['name' => 'k', 'type' => $branch[0], 'optional' => false, 'description' => '']]);
        $analysis->declaredTokenFqcns = ['k' => $branch[1]];

        return ArrayShapes::ofAnalysis($analysis);
    }, [['User | User', [null, User::class]], ['User', [User::class]]]);
    $options = new JoinOptions(
        dropsUntypedBranches: true,
        returns: [new Variable('a'), new Variable('b')],
        scope: new AnalysisScope(new ReflectionClass(PostResource::class)),
    );
    $join = static fn (): string => ShapeJoin::exclusive($branches, $options)->entries[0]->value->type();

    $type = resolve(AnalysisMemo::class)->showingGaps(
        fn (): string => DroppedUnionArms::provisional($join, static fn (string $type): bool => true),
    );
    $join();
    DroppedUnionArms::stripped(VisibleGaps::ids($type));

    // Neither fill stands beside a typed value, so the key is left `unknown` and nothing is left out.
    expect(VisibleGaps::ids($type))->toHaveCount(2)
        ->and(VisibleGaps::stripped($type)[0])->toBe('unknown')
        ->and(AnalysisWarnings::all())->toBe([]);
});

it('warns that a fill a method return spells unalike is left out beside a typed branch, at once or on its gap', function (bool $shows) {
    $fill = new ResourceAnalysis(properties: [['name' => 'k', 'type' => 'User | User', 'optional' => false, 'description' => '']]);
    $fill->declaredTokenFqcns = ['k' => [null, User::class]];
    $model = new ResourceAnalysis(properties: [['name' => 'k', 'type' => 'User', 'optional' => false, 'description' => '']]);
    $model->modelFqcns = ['k' => User::class];
    $options = new JoinOptions(
        dropsUntypedBranches: true,
        returns: [new Variable('fill', ['startLine' => 4]), new Variable('model')],
        scope: new AnalysisScope(new ReflectionClass(PostResource::class)),
    );
    $join = static fn (): string => ShapeJoin::exclusive(array_map(ArrayShapes::ofAnalysis(...), [$fill, $model]), $options)
        ->entries[0]->value->type();

    $type = $shows ? resolve(AnalysisMemo::class)->showingGaps($join) : $join();
    $held = AnalysisWarnings::all();
    DroppedUnionArms::stripped(VisibleGaps::ids($type));

    expect(VisibleGaps::stripped($type)[0])->toBe('User')
        ->and(VisibleGaps::ids($type))->toHaveCount($shows ? 1 : 0)
        ->and($held)->toHaveCount($shows ? 0 : 1)
        ->and(array_column(AnalysisWarnings::all(), 'message'))->toBe([
            'Key `k` leaves out what `$fill` on line 4 holds there, which could not be typed beside the typed values it '
            .'joins, so its published type may not admit every value it sends. Give what it reads a return type or a '
            .'`@return` docblock.',
        ]);
})->with(['gaps hidden' => [false], 'gaps shown' => [true]]);

it('orders each channel map as its branches first hold a key, and keeps an entry no key owns where no joined key names it', function () {
    $branch = static fn (array $keys, array $order, array $loose): ArrayShape => joinShape($keys, wide: new ShapeWide(
        order: ['modelFqcns' => $order],
        loose: ['modelFqcns' => $loose],
    ));
    $joined = ShapeJoin::exclusive([
        $branch(['a' => 'string', 'b' => 'string'], ['b', 'a'], []),
        $branch(['a' => 'string'], ['x', 'b', 'a'], ['x' => Post::class, 'b' => User::class]),
    ], new JoinOptions);

    expect($joined->wide->order)->toBe(['modelFqcns' => ['b', 'a', 'x']])
        ->and($joined->wide->loose)->toBe(['modelFqcns' => ['x' => Post::class]]);
});
