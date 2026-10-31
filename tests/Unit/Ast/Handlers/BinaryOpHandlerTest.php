<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
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
 */
function seededArrayUnionType(string $body): string
{
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
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
 * What PHP leaves in `$r` once a body runs with `$c` set as given.
 *
 * @return array<array-key, mixed>
 */
function arrayUnionSent(string $body, bool $c): array
{
    $r = (static function (bool $c) use ($body): mixed {
        eval($body);

        return $r ?? null;
    })($c);

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
