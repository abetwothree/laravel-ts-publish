<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\ArrayUnion;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\JoinOptions;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ObjectMembers;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * @phpstan-type UnionKeyReading array{list<string>, bool, bool, bool} a key's sorted type arms, `?`, absent, removed
 */

/**
 * An analysis of the given rows, each `[name, type, optional, value facts]`.
 *
 * @param  list<array{string, string, bool, array{absent?: bool, removed?: bool}}>  $rows
 */
function plusOperand(array $rows): ResourceAnalysis
{
    $analysis = new ResourceAnalysis;

    foreach ($rows as [$name, $type, $optional, $facts]) {
        $analysis->addProperty($name, ['type' => $type, 'optional' => $optional, ...$facts]);
    }

    return $analysis;
}

/**
 * A value `{ id: number; k?: number }` whose `k` holds the given presence facts.
 *
 * @return array<string, mixed>
 */
function unionBound(string ...$facts): array
{
    return [
        'type' => '{ id: number; k?: number }',
        'optional' => false,
        'keyPresence' => ['{ id: number; k?: number }' => ['k' => array_fill_keys($facts, true)]],
    ];
}

/**
 * The rows ArrayUnion::plus() lowers for two operands given as rows.
 *
 * @param  list<array{string, string, bool, array{absent?: bool, removed?: bool}}>  $left
 * @param  list<array{string, string, bool, array{absent?: bool, removed?: bool}}>  $right
 * @return list<array<string, mixed>>
 */
function plusRows(array $left, array $right): array
{
    $union = new ResourceAnalysis;
    ShapeLowering::into($union, ArrayUnion::plus(
        ArrayShapes::ofAnalysis(plusOperand($left)),
        ArrayShapes::ofAnalysis(plusOperand($right)),
    ));

    return $union->properties;
}

/**
 * Each key a `+` of PHP source sends, read two ways: the engine's read of the literal PHP's union equals
 * (arrayUnionLiteral()), and ArrayUnion::plus() over each operand's members. `$l` and `$r` stand for the given values.
 *
 * @param  array<string, array<string, mixed>>  $bindings
 * @return array{array<string, UnionKeyReading>, array<string, UnionKeyReading>}
 */
function unionReadings(string $union, array $bindings): array
{
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $scope->varValueBindings = $bindings;
    $statement = resolve(AstParser::class)->parseSource('<?php $u = '.$union.';')[0];
    assert($statement instanceof Expression && $statement->expr instanceof Assign);
    assert($statement->expr->expr instanceof Plus);
    $plus = $statement->expr->expr;
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class, 'toArray', null, $scope);
    $operand = fn (Expr $expr): ArrayShape => ArrayShapes::ofAnalysis(plusOperand(array_map(
        static fn (array $member): array => [$member[0], $member[1]['type'], $member[1]['optional'], $member[1]],
        ObjectMembers::writtenMembers($analyzer->resolve($expr)) ?? [],
    )));
    $keys = static function (array $rows, Closure $presence): array {
        $keys = [];

        foreach ($rows as [$name, $result]) {
            $arms = TsTypeString::splitTopLevelUnion($result['type']);
            sort($arms);
            $sent = $presence($result);
            $keys[$name] = [$arms, $sent->optional, $sent->absent, $sent->removed];
        }

        return $keys;
    };
    $lowered = new ResourceAnalysis;
    ShapeLowering::into($lowered, ArrayUnion::plus($operand($plus->left), $operand($plus->right)));
    $rows = array_map(static fn (array $row): array => [$row['name'], $row], $lowered->properties);

    return [
        $keys(ObjectMembers::writtenMembers($analyzer->resolve($plus)) ?? [], KeyPresence::fromValue(...)),
        $keys($rows, KeyPresence::ofRow(...)),
    ];
}

// A row named for a controller action holds the operands its `+` of props handed plusAnalyses(): every pair the suite's
// run reached.
it('lays the right side\'s keys where the left lacks them, as PHP\'s + does', function (array $left, array $right, array $rows) {
    $union = ArrayUnion::plusAnalyses(plusOperand($left), plusOperand($right), new ResourceAnalysis);

    expect($union->properties)->toBe($rows);
})->with([
    'a key the left always holds keeps its value' => [
        [['a', 'number', false, []]],
        [['a', 'string', false, []], ['k', 'number', false, []]],
        [
            ['name' => 'a', 'type' => 'number', 'optional' => false, 'description' => ''],
            ['name' => 'k', 'type' => 'number', 'optional' => false, 'description' => ''],
        ],
    ],
    'a key the left may lack joins the right\'s value' => [
        [['a', 'string', true, ['absent' => true]]],
        [['a', 'number', false, []], ['k', 'number', false, []]],
        [
            ['name' => 'a', 'type' => 'string | number', 'optional' => false, 'description' => ''],
            ['name' => 'k', 'type' => 'number', 'optional' => false, 'description' => ''],
        ],
    ],
    'a key the left may remove keeps its own value' => [
        [['a', 'string', true, []]],
        [['a', 'number', false, []]],
        [['name' => 'a', 'type' => 'string', 'optional' => true, 'description' => '', 'removed' => true]],
    ],
    'the left\'s keys lead, the right\'s new keys follow' => [
        [['a', 'number', false, []], ['b', 'string', true, ['absent' => true]]],
        [['c', 'number', false, []], ['b', 'boolean', false, []]],
        [
            ['name' => 'a', 'type' => 'number', 'optional' => false, 'description' => ''],
            ['name' => 'b', 'type' => 'string | boolean', 'optional' => false, 'description' => ''],
            ['name' => 'c', 'type' => 'number', 'optional' => false, 'description' => ''],
        ],
    ],
    'PropsLocalController@plus' => [
        [['a', 'number', false, []]],
        [['k', 'number', false, []]],
        [
            ['name' => 'a', 'type' => 'number', 'optional' => false, 'description' => ''],
            ['name' => 'k', 'type' => 'number', 'optional' => false, 'description' => ''],
        ],
    ],
    'PropsLocalController@plusOptional' => [
        [['a', 'number', true, ['absent' => true]]],
        [['a', 'string', false, []]],
        [['name' => 'a', 'type' => 'number | string', 'optional' => false, 'description' => '']],
    ],
    'InertiaPropsWalkController@plusOperator' => [
        [['heading', 'string', false, []], ['total', 'number', true, ['absent' => true]]],
        [['total', 'string', false, []]],
        [
            ['name' => 'heading', 'type' => 'string', 'optional' => false, 'description' => ''],
            ['name' => 'total', 'type' => 'number | string', 'optional' => false, 'description' => ''],
        ],
    ],
    'TopLevelKeysController@plusIntKeys' => [
        [['7', 'string', true, ['absent' => true]], ['9', 'string', true, ['absent' => true]]],
        [['8', 'string', false, []]],
        [
            ['name' => '7', 'type' => 'string', 'optional' => true, 'description' => '', 'absent' => true],
            ['name' => '9', 'type' => 'string', 'optional' => true, 'description' => '', 'absent' => true],
            ['name' => '8', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
    ],
    'TopLevelKeysController@plusDeferred' => [
        [['7', 'string', true, []]],
        [['8', 'string', false, []]],
        [
            ['name' => '7', 'type' => 'string', 'optional' => true, 'description' => '', 'removed' => true],
            ['name' => '8', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
    ],
    'TopLevelKeysController@plusOptionalInTernary' => [
        [['7', 'string', true, ['absent' => true, 'removed' => true]], ['y', 'number', true, ['absent' => true]]],
        [['8', 'string', false, []]],
        [
            ['name' => '7', 'type' => 'string', 'optional' => true, 'description' => '', 'absent' => true, 'removed' => true],
            ['name' => 'y', 'type' => 'number', 'optional' => true, 'description' => '', 'absent' => true],
            ['name' => '8', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
    ],
    'TopLevelKeysController@plusClosedHead' => [
        [['card', '{ data: UserCardResource }', false, []]],
        [['card.can', 'boolean', false, []]],
        [
            ['name' => 'card', 'type' => '{ data: UserCardResource }', 'optional' => false, 'description' => ''],
            ['name' => 'card.can', 'type' => 'boolean', 'optional' => false, 'description' => ''],
        ],
    ],
]);

// The path where the left lacks the key holds none of its classes, so the right's queue is not appended to the left's.
it('lays the right\'s classes alone on the path where the left lacks the key', function () {
    $left = plusOperand([['id', 'number', false, []], ['k', '{ a: Post }', true, ['absent' => true]]]);
    $left->inlineModelFqcns['k'] = [Post::class];
    $right = plusOperand([['k', '{ b: User }', false, []]]);
    $right->inlineModelFqcns['k'] = [User::class];

    $union = ArrayUnion::plusAnalyses($left, $right, new ResourceAnalysis);

    expect($union->properties)->toBe([
        ['name' => 'id', 'type' => 'number', 'optional' => false, 'description' => ''],
        ['name' => 'k', 'type' => '{ a: Post } | { b: User }', 'optional' => false, 'description' => ''],
    ])->and($union->inlineModelFqcns)->toBe(['k' => [Post::class, User::class]]);
});

it('keeps PHP\'s key order and lays the right\'s value only where the left may lack the key', function (array $left, array $right, array $rows) {
    expect(plusRows($left, $right))->toBe($rows);
})->with([
    '[0 => \'a\'] + [1 => \'b\'] keeps 0 before 1' => [
        [['0', 'string', false, []]],
        [['1', 'string', false, []]],
        [
            ['name' => '0', 'type' => 'string', 'optional' => false, 'description' => ''],
            ['name' => '1', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
    ],
    'a sure left key keeps its value' => [
        [['k', 'number', false, []]],
        [['k', 'string', false, []]],
        [['name' => 'k', 'type' => 'number', 'optional' => false, 'description' => '']],
    ],
    'a key absent on one path and removed on another' => [
        [['k', 'number', true, ['absent' => true, 'removed' => true]]],
        [['k', 'string', false, []]],
        [['name' => 'k', 'type' => 'number | string', 'optional' => true, 'description' => '', 'removed' => true]],
    ],
]);

it('tells of each untypable value the right lays beside a typed one the union keeps alone', function (array $left, array $right, array $told) {
    $heard = [];
    ArrayUnion::plus(
        ArrayShapes::ofAnalysis(plusOperand($left)),
        ArrayShapes::ofAnalysis(plusOperand($right)),
        JoinOptions::union(),
        function (string $key, string $type) use (&$heard): void {
            $heard[] = [$key, $type];
        },
    );

    expect($heard)->toBe($told);
})->with([
    'a key the left may lack' => [[['k', 'number', true, ['absent' => true]]], [['k', 'unknown', false, []]], [['k', 'number']]],
    'a key the left holds on every path' => [[['k', 'number', false, []]], [['k', 'unknown', false, []]], []],
    'a key the left may lack, holding a value no rule types' => [
        [['k', 'unknown', true, ['absent' => true]]],
        [['k', 'unknown', false, []]],
        [],
    ],
    'a typed value the right lays' => [[['k', 'number', true, ['absent' => true]]], [['k', 'string', false, []]], []],
    'a key only the right holds' => [[['a', 'number', false, []]], [['k', 'unknown', false, []]], []],
]);

// PHP's `+` keeps a key the left holds as one of its extras, and lays the right's extras where the left lacks them.
it('joins the left\'s extras into each key the right adds, and the right\'s extras into the result\'s', function () {
    $value = static fn (string $type): KeyValue => new KeyValue(['type' => $type, 'optional' => false, 'description' => '']);
    $key = static fn (string $name, string $type): ShapeKey => new ShapeKey($name, $value($type), new KeyPresence);
    $left = new ArrayShape([$key('a', 'number')], $value('string'));
    $right = new ArrayShape([$key('a', 'null'), $key('b', 'boolean')], $value('number'));

    $union = ArrayUnion::plus($left, $right);

    expect(array_map(static fn (ShapeKey $key): array => [$key->name, $key->value->type()], array_values(array_filter(
        $union->entries,
        static fn (object $entry): bool => $entry instanceof ShapeKey,
    ))))->toBe([['a', 'number'], ['b', 'boolean | string']])
        ->and($union->extras?->type())->toBe('string | number');
});

// The literal reads each key in PHP's order only where both operands spell their keys; a value's spread hides them.
it('reads a + as the literal PHP\'s union equals reads it, key order where both operands are literals', function (string $union, array $bindings, bool $ordered) {
    [$literal, $plus] = unionReadings($union, $bindings);

    if (! $ordered) {
        ksort($literal);
        ksort($plus);
    }

    expect($plus)->toBe($literal);
})->with([
    'a sure left key' => ['["k" => 1] + ["k" => "z"]', [], true],
    'a key the right adds' => ['["a" => 1] + ["b" => "x", "a" => true]', [], true],
    'a when() key over the right\'s' => ['["by" => $this->when($x, 1)] + ["by" => "z"]', [], true],
    'a key absent on some path' => ['$l + ["k" => "z"]', ['l' => unionBound('absent')], false],
    'a key removed on some path' => ['$l + ["k" => "z"]', ['l' => unionBound('removed')], false],
    'a key absent on one path and removed on another' => ['$l + ["k" => "z"]', ['l' => unionBound('absent', 'removed')], false],
    'a key absent on some path beside a removed right value' => [
        '$l + $r',
        [
            'l' => unionBound('absent'),
            'r' => ['type' => '{ k?: string }', 'optional' => false, 'keyPresence' => ['{ k?: string }' => ['k' => ['removed' => true]]]],
        ],
        false,
    ],
    'a key absent on some path the right does not name' => ['$l + ["z" => 1]', ['l' => unionBound('absent')], false],
    'a mixed-presence key the right does not name' => ['$l + ["z" => 1]', ['l' => unionBound('absent', 'removed')], false],
]);
