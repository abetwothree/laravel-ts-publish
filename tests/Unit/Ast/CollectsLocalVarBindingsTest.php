<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UnseenWritesResource;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * The names collectVariableWrites() reports for a parsed body, in order, with UnseenWritesResource as the subject.
 *
 * @return list<string>
 */
function localWriteNames(string $body): array
{
    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * The names the write scan reports.
         *
         * @param  array<Node>  $stmts
         * @return list<string>
         */
        public function names(array $stmts): array
        {
            return array_column($this->collectVariableWrites($stmts, new ReflectionClass(UnseenWritesResource::class)), 0);
        }
    };

    return $host->names(new AstParser()->parseSource('<?php '.$body));
}

// UnseenWritesResource declares fillByReference() and fillInstance(), each taking its argument by reference.
it('reports each write kind', function (string $body, array $names) {
    expect(localWriteNames($body))->toBe($names);
})->with([
    'catch' => ['try {} catch (Exception $e) {}', ['e']],
    'non-capturing catch' => ['try {} catch (Exception) {}', []],
    'unset' => ['unset($a, $b[1]);', ['a', 'b']],
    'unset of a key read by a variable' => ['unset($a[$k]);', ['a']],
    'unset of a key read through a property' => ['unset($c[$p->id]);', ['c']],
    'unset of a property' => ['unset($o->p);', []],
    'static' => ['static $s = 1, $t;', ['s', 't']],
    'global' => ['global $g;', ['g']],
    'by-ref function argument' => ['preg_match("/x/", $s, $m);', ['m']],
    'by-ref key argument' => ['sort($a["k"]);', ['a']],
    'by-ref key argument read by a variable' => ['sort($a[$k]);', ['a']],
    'by-ref property argument' => ['sort($o->list);', []],
    'by-ref parameter given a call result' => ['sort(array_values($a));', []],
    'by-value function argument' => ['strlen($s);', []],
    'pointer function' => ['end($a); reset($a);', []],
    'by-ref static method' => ['self::fillByReference($x);', ['x']],
    'by-ref instance method' => ['$this->fillInstance($x);', ['x']],
    'unnamed receiver' => ['$other->fill($x);', []],
    'named by-ref argument' => ['preg_match(subject: $s, pattern: "/x/", matches: $m);', ['m']],
    'foreach by reference' => ['foreach ($list as &$v) {}', ['v', 'list']],
    'foreach by reference over a key read by a variable' => ['foreach ($g[$n] as &$v) {}', ['v', 'g']],
    'foreach by reference over a call result' => ['foreach (array_values($l) as &$v) {}', ['v']],
    'closure parameter write' => ['$f = function ($x) { $x = 1; };', ['f']],
    'closure local write' => ['$f = function () { $y = 1; };', ['f']],
    'closure by-value use write' => ['$f = function () use ($y) { $y = 1; };', ['f', 'y']],
    'closure by-ref use' => ['$f = function () use (&$y) {};', ['f', 'y']],
    'closure $this write' => ['$f = function () { $this->x = 1; };', ['f', 'this']],
    'arrow parameter write' => ['$f = fn ($x) => $x = 1;', ['f']],
    'arrow capture write' => ['$f = fn () => $y = 1;', ['f', 'y']],
    'nested class' => ['$o = new class { public function m() { $z = 1; } };', ['o']],
]);

/**
 * Whether each read of `$p` in a parsed body, in order, still holds the binding a scope seeded for it, once the body's
 * binding pass and endBindingsAtWrites() have run.
 *
 * @param  Closure(AnalysisScope): void  $seed
 * @return list<bool>
 */
function parameterReadsHolding(string $body, Closure $seed): array
{
    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * Run the binding pass, then end the bindings the body's writes replace.
         *
         * @param  array<Node\Stmt>  $stmts
         */
        public function end(array $stmts, AnalysisScope $scope): void
        {
            $this->collectLocalVarBindings($stmts, $scope);
            $this->endBindingsAtWrites($stmts, $scope);
        }
    };

    /** @var array<Node\Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $seed($scope);
    $host->end($stmts, $scope);

    $reads = new NodeFinder()->find($stmts, fn (Node $node): bool => $node instanceof Variable && $node->name === 'p');

    return array_map(fn (Node $read): bool => $read instanceof Variable && $scope->holdsBinding($read), $reads);
}

it('ends a parameter\'s model binding at the first write that replaces it with another class', function (string $body, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope): void {
        $scope->varModelBindings['p'] = User::class;
    }))->toBe($holding);
})->with([
    'a top-level write to another model, past its own statement' => ['$p = $p->profile; return $p;', [true, true, false]],
    'a write naming the same model' => ['$p = $p->fresh() ?? $p; return $p;', [true, true, true, true]],
    'a write whose value names no class' => ['$p = $p->comments->first(); return $p;', [true, true, true]],
    'a write an inline @var names another class for' => ['/** @var \\Workbench\\App\\Models\\Comment $p */ $p = $p->comments->first(); return $p;', [true, true, false]],
    'a nested write, from where its statement starts' => ['if ($p->id) { $p = $p->profile; } return $p;', [false, false, false, false]],
    'a write through a property or a key' => ['$p->name = "x"; $p["k"] = 1; return $p;', [true, true, true]],
    'a destructuring write' => ['[$p] = [$p->profile]; return $p;', [true, true, false]],
    'a foreach target' => ['foreach ([] as $p) {} return $p;', [false, false]],
    'a later write past the first end' => ['$p = $p->profile; $p = $p->user; return $p;', [true, true, false, false, false]],
    'a write to a nested closure\'s own parameter' => ['$f = function ($p) { $p = 1; }; return $p;', [true, true, true]],
    'a write inside an arrow function, which captures by value' => ['$f = fn () => $p = $p->profile; return $p;', [true, true, true]],
    'a ??= naming the same model' => ['$p ??= new \\Workbench\\App\\Models\\User; return $p;', [true, true]],
    'a ??= naming another model' => ['$p ??= new \\Workbench\\App\\Models\\Profile; return $p;', [true, false]],
    'an operator such as .= or ++' => ['$p .= "x"; $p++; return $p;', [true, true, true]],
]);

it('keeps a loaded collection\'s binding across a write naming no other element class', function (string $body, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope): void {
        $scope->varCollectionBindings['p'] = ['type' => 'Comment[]', 'modelFqcn' => Comment::class];
    }))->toBe($holding);
})->with([
    'a re-sort' => ['$p = $p->sortBy("id"); return $p;', [true, true, true]],
    'a filter' => ['$p = $p->where("content", "!=", ""); return $p;', [true, true, true]],
    'a re-index' => ['$p = $p->values(); return $p;', [true, true, true]],
    'a value naming no class' => ['$p = $p->first(); return $p;', [true, true, true]],
    'a collection of another model' => ['$p = $this->resource->tags; return $p;', [true, false]],
    'a single model' => ['$p = new \\Workbench\\App\\Models\\Comment; return $p;', [true, false]],
]);

// A declared binding stands for every class the parameter declares: a write holding fewer, or others, ends it, so the
// local reads the narrower value the body wrote.
it('ends a declared binding at a write holding other classes, or fewer than it declares', function (string $body, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope): void {
        $scope->varDeclaredBindings['p'] = [User::class, Post::class];
    }))->toBe($holding);
})->with([
    'a write holding every declared class' => ['$p = $p; return $p;', [true, true, true]],
    'a write holding one of them' => ['$p = new \\Workbench\\App\\Models\\User; return $p;', [true, false]],
    'a write holding another class' => ['$p = new \\Workbench\\App\\Models\\Profile; return $p;', [true, false]],
    'a write whose value names no class' => ['$p = $p->comments->first(); return $p;', [true, true, true]],
]);

// A value binding names no class, so only a value that names one ends it; the local then reads what was assigned.
it('ends a value binding only at a write whose value names a class', function (string $body, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope): void {
        $scope->varValueBindings['p'] = ['type' => 'string', 'optional' => false];
    }))->toBe($holding);
})->with([
    'a concatenation' => ['$p .= "!"; return $p;', [true, true]],
    'a literal in a branch' => ['if ($p === "") { $p = "x"; } return $p;', [true, true, true]],
    'a value naming no class' => ['$p = trim($p); return $p;', [true, true, true]],
    'a value naming a class' => ['$p = \\Workbench\\App\\Enums\\Status::from($p); return $p;', [true, true, false]],
]);
