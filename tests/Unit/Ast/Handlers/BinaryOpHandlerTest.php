<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SelfReadingParameterResource;
use Illuminate\Http\Request;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/**
 * The type the `+` a body assigns to `$r` resolves to, the body seeded as PostResource's over a Post.
 *
 * @param  array<string, string>  $parameters  each parameter's value type, bound before the body is seeded
 */
function seededArrayUnionType(string $body, array $parameters = []): string
{
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $scope->varValueBindings = array_map(fn (string $type): array => ['type' => $type, 'optional' => false], $parameters);
    /** @var list<Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);

    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * Seed the scope from the body, as every body reader does.
         *
         * @param  list<Stmt>  $stmts
         */
        public function run(array $stmts, AnalysisScope $scope): void
        {
            $this->seedBody($stmts, $scope);
        }
    };

    $host->run($stmts, $scope);
    $assign = new NodeFinder()->findFirst($stmts, fn (Node $node): bool => $node instanceof Assign
        && $node->var instanceof Variable
        && $node->var->name === 'r'
        && $node->expr instanceof Plus);
    assert($assign instanceof Assign);

    return new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class, 'toArray', null, $scope)
        ->resolve($assign->expr)['type'];
}

/**
 * What PHP leaves in `$r` once a body runs with `$c` and the given variables set.
 *
 * @param  array<string, mixed>  $variables
 * @return array<array-key, mixed>
 */
function arrayUnionSent(string $body, bool $c, array $variables = []): array
{
    $r = (static function (bool $c, array $variables) use ($body): mixed {
        extract($variables);
        eval($body);

        return $r ?? null;
    })($c, $variables);

    return is_array($r) ? $r : [];
}

// Each oracle is what `$r` holds when `$c` is true, then false.
it('reads a local operand of + through the writes its read sees', function (string $body, string $type, array $sent) {
    expect(seededArrayUnionType($body))->toBe($type)
        ->and([arrayUnionSent($body, true), arrayUnionSent($body, false)])->toBe($sent);
})->with([
    'a single write' => [
        '$base = ["a" => 1]; $r = $base + ["k" => 1];',
        '{ a: number; k: number }',
        [['a' => 1, 'k' => 1], ['a' => 1, 'k' => 1]],
    ],
    'a block write, read in its block' => [
        'if ($c) { $base = ["a" => 1]; $r = $base + ["k" => 1]; }',
        '{ a: number; k: number }',
        [['a' => 1, 'k' => 1], []],
    ],
    'a two-arm join' => [
        'if ($c) { $base = ["a" => 1]; } else { $base = ["a" => 2]; } $r = $base + ["k" => 1];',
        '{ a: number; k: number }',
        [['a' => 1, 'k' => 1], ['a' => 2, 'k' => 1]],
    ],
    'a two-arm join of different shapes, on the right' => [
        'if ($c) { $base = ["a" => 1]; } else { $base = ["b" => "x"]; } $r = ["k" => true] + $base;',
        '{ k: boolean; a: number } | { k: boolean; b: string }',
        [['k' => true, 'a' => 1], ['k' => true, 'b' => 'x']],
    ],
    'a join over an earlier write' => [
        '$base = ["a" => 1]; if ($c) { $base = ["a" => "x"]; } $r = $base + ["k" => 1];',
        '{ a: number; k: number } | { a: string; k: number }',
        [['a' => 'x', 'k' => 1], ['a' => 1, 'k' => 1]],
    ],
]);

// sort() makes the local a list, so its literal's keys are not the ones PHP adds.
it('reads a local operand a by-reference call reshapes by its value, not its syntax', function () {
    $body = '$base = ["b" => 2, "a" => 1]; sort($base); $r = $base + ["k" => 1];';

    expect(seededArrayUnionType($body))->toBe("Omit<Record<string, unknown>, 'k'> & { k: number }")
        ->and(arrayUnionSent($body, true))->toBe([1, 2, 'k' => 1]);
});

// The write reads the parameter it replaces, whose binding still holds there, so its literal spells the value.
it('reads a parameter\'s write that reads the parameter itself as its literal', function () {
    $body = '$o = ["a" => $o["a"] ?? 1, "b" => 2]; $r = $o + ["k" => 1];';

    expect(seededArrayUnionType($body, ['o' => '{ a: number }']))->toBe('{ a: number; b: number; k: number }')
        ->and(arrayUnionSent($body, true, ['o' => ['a' => 5]]))->toBe(['a' => 5, 'b' => 2, 'k' => 1]);
});

/**
 * A body whose `$r` adds a local each of $left branches assigns to one each of $right branches assigns.
 */
function branchedUnionBody(int $left, int $right): string
{
    $branches = fn (string $name, string $key, int $count): string => implode(' else', array_map(
        fn (int $n): string => $n === $count
            ? ' { $'.$name.' = ["'.$key.'" => '.$n.']; }'
            : 'if ($n === '.$n.') { $'.$name.' = ["'.$key.'" => '.$n.']; }',
        range(1, $count),
    ));

    return ltrim($branches('l', 'a', $left), ' ').' '.ltrim($branches('m', 'b', $right), ' ').' $r = $l + $m;';
}

// Up to the cap a `+` reads one literal per pair of writes; past it, each side as the one value it may hold.
it('reads a + of joined locals as its literals up to the cap, and past it as their values', function (int $left, string $type) {
    $body = branchedUnionBody($left, 4);

    expect(seededArrayUnionType($body))->toBe($type)
        ->and(arrayUnionSent($body, true, ['n' => 2]))->toBe(['a' => 2, 'b' => 2])
        ->and(arrayUnionSent($body, true, ['n' => 9]))->toBe(['a' => $left, 'b' => 4]);
})->with([
    'four by four, at the cap' => [4, '{ a: number; b: number }'],
    'five by four, past the cap' => [5, 'Omit<{ b: number }, keyof { a: number }> & { a: number }'],
]);

// A helper read as a value reads its parameter's binding where its write reads the parameter.
it('reads a + over a helper parameter\'s write that reads the parameter, as a value and returned whole', function () {
    $post = new Post;
    $post->forceFill(['id' => 1]);

    expect(resourceProps(SelfReadingParameterResource::class))->toBe([
        'shape' => '{ o: { a: number; b: number }; m: { a: number; b: number; k: number } }',
        'declared' => '{ m: { a: number; b: number; k: number } }',
        'untyped' => '{ m: { a: number; b: number; k: number } }',
        'whole' => '{ a: number; b: number; k: number }',
    ])
        ->and(sentAsJson(new SelfReadingParameterResource($post)->resolve(Request::create('/'))))->toEqual((object) [
            'shape' => (object) ['o' => (object) ['a' => 5, 'b' => 2], 'm' => (object) ['a' => 5, 'b' => 2, 'k' => 1]],
            'declared' => (object) ['m' => (object) ['a' => 5, 'b' => 2, 'k' => 1]],
            'untyped' => (object) ['m' => (object) ['a' => 5, 'b' => 2, 'k' => 1]],
            'whole' => (object) ['a' => 5, 'b' => 2, 'k' => 1],
        ]);
});
