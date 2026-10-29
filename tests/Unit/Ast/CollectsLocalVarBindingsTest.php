<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\ModelFills;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\VariableWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MemberWrittenLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UnseenWritesResource;
use Illuminate\Http\Request;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeFinder;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Profile;
use Workbench\App\Models\User;

/**
 * The names VariableWrites::of() reports for a parsed body, in order, read in a scope over UnseenWritesResource.
 *
 * @return list<string>
 */
function localWriteNames(string $body): array
{
    return array_column(
        VariableWrites::of(new AstParser()->parseSource('<?php '.$body), new AnalysisScope(new ReflectionClass(UnseenWritesResource::class))),
        0,
    );
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
    'closure by-value use write' => ['$f = function () use ($y) { $y = 1; };', ['f']],
    'closure by-ref use' => ['$f = function () use (&$y) {};', ['f', 'y']],
    'closure by-ref use write' => ['$f = function () use (&$y) { $y = 1; };', ['f', 'y', 'y']],
    'arrow write inside a by-ref use' => ['$f = function () use (&$y) { $g = fn () => $y = 1; };', ['f', 'y']],
    'closure $this write' => ['$f = function () { $this->x = 1; };', ['f', 'this']],
    'arrow parameter write' => ['$f = fn ($x) => $x = 1;', ['f']],
    'arrow capture write' => ['$f = fn () => $y = 1;', ['f']],
    'arrow $this write' => ['$f = fn () => $this->x = 1;', ['f', 'this']],
    'nested class' => ['$o = new class { public function m() { $z = 1; } };', ['o']],
    'a write through a key a variable reads' => ['$a[$k] = 1;', ['a']],
    'a write through a member a variable names' => ['$o->{$n} = 1; $o->list[$i] = 1;', ['o', 'o']],
    'a write through a relation' => ['$p->author->name = "x";', ['p']],
    'a destructured key a variable reads' => ['[$a[$i], $b] = [1, 2];', ['a', 'b']],
    'a variable variable' => ['$$k = 1; ${"x"} = 1;', []],
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
    'a write naming the element model find() of one key returns' => ['$p = $p->comments->find(1); return $p;', [true, true, false]],
    'a find() of a key a variable holds, which may be a list' => ['$p = $p->comments->find($k); return $p;', [true, true, true]],
    'a top-level write whose value names no class' => ['$p = json_decode($p->name); return $p;', [true, true, true]],
    'a nested write whose value names no class' => ['if ($p->id) { $p = json_decode("x"); } return $p;', [true, true, true]],
    'a write naming the element model first() returns' => ['$p = $p->comments->first(); return $p;', [true, true, false]],
    'a write an inline @var names another class for' => ['/** @var \\Workbench\\App\\Models\\Comment $p */ $p = $p->comments->first(); return $p;', [true, true, false]],
    'a nested write, from where its statement starts' => ['if ($p->id) { $p = $p->profile; } return $p;', [false, false, false, false]],
    'a write in a block that always exits, ended only inside it' => [
        'if ($p->id) { $p = $p->profile; return $p; } return $p;',
        [true, true, true, false, true],
    ],
    'a write in an else that always exits' => ['if ($p->id) { } else { $p = $p->profile; throw new \\Exception($p); } return $p;', [true, true, true, false, true]],
    'a write in an elseif that always exits' => ['if ($p->id) { } elseif ($p->x) { $p = $p->profile; return $p; } return $p;', [true, true, true, true, false, true]],
    'a write in a switch case that always exits' => ['switch ($p->id) { case 1: $p = $p->profile; return $p; } return $p;', [true, true, true, false, true]],
    'a nested write in a block that always exits' => ['if ($p->id) { if ($p->x) { $p = $p->profile; } return $p; } return $p;', [true, false, false, false, false, true]],
    'a write in a block a continue leaves before it returns' => [
        'foreach ($xs as $i) { if ($p->id) { $p = $p->profile; if ($i) { continue; } return $p; } } return $p;',
        [false, false, false, false, false],
    ],
    'a write in a block a break leaves before it returns' => [
        'foreach ($xs as $i) { if ($p->id) { $p = $p->profile; if ($i) { break; } return $p; } } return $p;',
        [false, false, false, false, false],
    ],
    'a write in a block a break inside a loop of its own does not leave' => [
        'if ($p->id) { $p = $p->profile; foreach ($xs as $x) { break; } return $p; } return $p;',
        [true, true, true, false, true],
    ],
    'a write in a block whose throw a catch around it resumes from' => [
        'try { if ($p->id) { $p = $p->profile; throw new \\Exception(); } } catch (\\Exception) { return $p; } return $p;',
        [false, false, false, false, false],
    ],
    'a write in a block that exits' => ['if ($p->id) { $p = $p->profile; exit(); } return $p;', [true, true, true, true]],
    'a write in a closure inside a block that falls through' => [
        'if ($p->id) { $f = function () use (&$p) { $p = $p->profile; return 1; }; } return $p;',
        [false, false, false, false, false],
    ],
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

// A collection naming no element still holds the models, but past a step such as pluck() or map() that replaces them.
it('keeps a loaded collection\'s binding across a write that keeps its models', function (string $body, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope): void {
        $scope->varCollectionBindings['p'] = ['type' => 'Comment[]', 'modelFqcn' => Comment::class];
    }))->toBe($holding);
})->with([
    'a re-sort' => ['$p = $p->sortBy("id"); return $p;', [true, true, true]],
    'a filter' => ['$p = $p->where("content", "!=", ""); return $p;', [true, true, true]],
    'a re-key' => ['$p = $p->keyBy("content"); return $p;', [true, true, true]],
    'a re-index' => ['$p = $p->values(); return $p;', [true, true, true]],
    'an eager load' => ['$p = $p->load("user"); return $p;', [true, true, true]],
    'a merge()' => ['$p = $p->merge([]); return $p;', [true, true, true]],
    'a concat()' => ['$p = $p->concat([]); return $p;', [true, true, true]],
    'a push()' => ['$p = $p->push(new \\Workbench\\App\\Models\\Comment); return $p;', [true, true, true]],
    'collect()' => ['$p = collect($p); return $p;', [true, true, true]],
    'a new base collection' => ['$p = new \\Illuminate\\Support\\Collection($p); return $p;', [true, true, true]],
    'Collection::make()' => ['$p = \\Illuminate\\Support\\Collection::make($p); return $p;', [true, true, true]],
    'a ternary falling back to an empty collection' => ['$p = $p->isEmpty() ? collect() : $p; return $p;', [true, true, true, true]],
    'a base collection\'s merge()' => ['$p = $p->toBase()->merge([]); return $p;', [true, true, true]],
    'a lazy collection' => ['$p = $p->lazy(); return $p;', [true, true, true]],
    'a top-level value naming no class' => ['$p = $p->toJson(); return $p;', [true, true, true]],
    'a nested value naming no class' => ['if ($p->isEmpty()) { $p = $p->toJson(); } return $p;', [true, true, true, true]],
    'a pluck() of plain values' => ['$p = $p->pluck("id"); return $p;', [true, true, false]],
    'a map() to plain values' => ['$p = $p->map(fn ($c) => $c->content); return $p;', [true, true, false]],
    'a pluck() an item-keeping step follows' => ['$p = $p->pluck("id")->values(); return $p;', [true, true, false]],
    'a groupBy()' => ['$p = $p->groupBy("user_id"); return $p;', [true, true, false]],
    'the model find() of one key returns' => ['$p = $p->find(1); return $p;', [true, true, false]],
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
    'a write naming the element model find() of one key returns' => ['$p = $p->comments->find(1); return $p;', [true, true, false]],
    'a top-level write whose value names no class' => ['$p = $p->comments->toJson(); return $p;', [true, true, true]],
    'a nested write whose value names no class' => ['if ($p->id) { $p = $p->comments->toJson(); } return $p;', [true, true, true, true]],
]);

// A value binding ends past its own statement, whose value reads the old binding, and at a nested write only where that
// value names a class; the local then reads what was assigned.
it('ends a value binding past a top-level write, and at a nested write whose value names a class', function (string $body, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope): void {
        $scope->varValueBindings['p'] = ['type' => 'string', 'optional' => false];
    }))->toBe($holding);
})->with([
    'a concatenation' => ['$p .= "!"; return $p;', [true, true]],
    'a literal in a branch' => ['if ($p === "") { $p = "x"; } return $p;', [true, true, true]],
    'a top-level value naming no class' => ['$p = trim($p); return $p;', [true, true, false]],
    'a nested value naming no class' => ['if ($p === "") { $p = trim($p); } return $p;', [true, true, true, true]],
    'a ??= naming no class, which keeps a value it finds set' => ['$p ??= trim("x"); return $p;', [true, true]],
    'a value naming a class' => ['$p = \\Workbench\\App\\Enums\\Status::from($p); return $p;', [true, true, false]],
]);

// An expression binding the receiver resolver names no class for is a value binding; one it names a class for keeps it.
it('ends an expression binding naming no class past a top-level write, and keeps one naming a class', function (string $body, Expr $bound, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope) use ($bound): void {
        $scope->closureParamExprBindings['p'] = $bound;
    }))->toBe($holding);
})->with([
    'a column read' => ['$p = trim($p); return $p;', new PropertyFetch(new Variable('this'), 'title'), [true, true, false]],
    'a relation read' => ['$p = json_decode($p); return $p;', new PropertyFetch(new Variable('this'), 'author'), [true, true, true]],
    'a passed model no row backs yet' => ['$p = json_decode($p); return $p;', new New_(new FullyQualified(User::class)), [true, true, true]],
    'a nested write naming no class' => ['if ($p) { $p = json_decode($p); } return $p;', new PropertyFetch(new Variable('this'), 'author'), [true, true, true, true]],
]);

/**
 * The classes the receiver of each `$p->x` read of a parsed body resolves to once the body is seeded, null for none.
 *
 * @param  Closure(AnalysisScope): void  $seed
 * @return list<list<class-string>|null>
 */
function seededReceiverClasses(string $body, Closure $seed): array
{
    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * Seed the body.
         *
         * @param  array<Node\Stmt>  $stmts
         */
        public function seed(array $stmts, AnalysisScope $scope): void
        {
            $this->seedBody($stmts, $scope);
        }
    };

    /** @var array<Node\Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $seed($scope);
    $host->seed($stmts, $scope);

    $reads = new NodeFinder()->find($stmts, fn (Node $node): bool => $node instanceof PropertyFetch
        && $node->var instanceof Variable
        && $node->var->name === 'p'
        && $node->name instanceof Identifier
        && $node->name->toString() === 'x');

    return array_map(
        fn (Node $read): ?array => $read instanceof PropertyFetch
            ? resolve(ReceiverClassResolver::class)->resolve($read->var, $scope)?->classes
            : null,
        $reads,
    );
}

// Past a nested write the receiver keeps what the parameter came in with, as when the write never ran, beside a class
// the written value names: the write's own read of the parameter, inside its block, still sees the old binding.
it('keeps a parameter\'s receiver past a nested write naming no class', function (string $body, Closure $seed, array $classes) {
    expect(seededReceiverClasses($body, $seed))->toBe($classes);
})->with([
    'a model binding, a value naming no class' => [
        'if ($c) { $p = json_decode("x"); } $p->x;',
        function (AnalysisScope $scope): void {
            $scope->varModelBindings['p'] = User::class;
        },
        [[User::class]],
    ],
    'a model binding, a value naming another class' => [
        'if ($c) { $p = $p->profile; } $p->x;',
        function (AnalysisScope $scope): void {
            $scope->varModelBindings['p'] = User::class;
        },
        [[User::class, Profile::class]],
    ],
    'an expression binding, a value naming no class' => [
        'foreach ($cs as $c) { if ($c) { $p = $c; continue; } } $p->x;',
        function (AnalysisScope $scope): void {
            $scope->closureParamExprBindings['p'] = new PropertyFetch(new Variable('this'), 'author');
        },
        [[User::class]],
    ],
    // The `try` ends the binding where it starts, an offset an unformatted body shares with the `if`'s end.
    'a model binding read in a try that ends it later' => [
        'if ($c) { $p = json_decode("x"); } try { $p->x; $p = $p->profile; } catch (\Exception $e) {}',
        function (AnalysisScope $scope): void {
            $scope->varModelBindings['p'] = User::class;
        },
        [[User::class]],
    ],
    'the same body unformatted' => [
        'if($c){$p=json_decode("x");}try{$p->x;$p=$p->profile;}catch(\Exception $e){}',
        function (AnalysisScope $scope): void {
            $scope->varModelBindings['p'] = User::class;
        },
        [[User::class]],
    ],
]);

// Laravel stores a member or offset write on a model as an attribute of that same model.
it('keeps a local\'s model across a write through its member or offset', function (string $body, array $classes) {
    expect(seededReceiverClasses($body, function (AnalysisScope $scope): void {}))->toBe($classes);
})->with([
    'a member write' => ['$p = new \\Workbench\\App\\Models\\Post; $p->title = "x"; $p->x;', [[Post::class]]],
    'an offset write' => ['$p = new \\Workbench\\App\\Models\\Post; $p["title"] = "x"; $p->x;', [[Post::class]]],
    'a write through a relation' => ['$p = $this->resource; $p->author->name = "x"; $p->x;', [[Post::class]]],
    'a member write in a block' => ['if ($c) { $p = $this->author; $p->name = "x"; $p->x; }', [[User::class]]],
    'a member write between a block\'s write and its read' => [
        'if ($c) { $p = $this->author; if ($d) { $p->name = "x"; } $p->x; }',
        [[User::class]],
    ],
    'a local a list destructures, which the scan cannot read' => ['[$p] = [$this->author]; $p->name = "x"; $p->x;', [null]],
    'a member written null, which its column cannot hold' => ['$p = new \\Workbench\\App\\Models\\Post; $p->title = null; $p->x;', [null]],
    'a member written a value the scan cannot type' => ['$p = new \\Workbench\\App\\Models\\Post; $p->title = $t; $p->x;', [null]],
    'a relation\'s member written null' => ['$p = $this->resource; $p->author->name = null; $p->x;', [null]],
    'an array, which an offset write extends' => ['$p = []; $p["k"] = $this->author; $p->x;', [null]],
    'a model or null, which an offset write makes an array' => ['$p = $c ? $this->author : null; $p["name"] = "x"; $p->x;', [null]],
    'a model or null, whose member write throws on null' => ['$p = $c ? $this->author : null; $p->name = "x"; $p->x;', [[User::class]]],
]);

/**
 * Whether the old binding may hold, and whether a receiver keeps it, in the gap holding a parsed body's last read of
 * `$p`, once the body is seeded; null where no gap holds it.
 *
 * @param  Closure(AnalysisScope): void  $seed
 * @return array{bool, bool}|null
 */
function lastReadGapFlags(string $body, Closure $seed): ?array
{
    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * Seed the body.
         *
         * @param  array<Node\Stmt>  $stmts
         */
        public function seed(array $stmts, AnalysisScope $scope): void
        {
            $this->seedBody($stmts, $scope);
        }
    };

    /** @var array<Node\Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $seed($scope);
    $host->seed($stmts, $scope);
    $reads = new NodeFinder()->find($stmts, fn (Node $node): bool => $node instanceof Variable && $node->name === 'p');
    $last = end($reads);
    $gap = $last instanceof Variable ? $scope->gapAt($last) : null;

    return $gap === null ? null : [$gap[3], $gap[4]];
}

// A value binding's own write ends it, so no receiver keeps it past; a class kept beside a value still is.
it('marks a receiver keeping a binding past the parameter\'s own write only where the write kept it', function (string $body, Closure $seed, array $flags) {
    expect(lastReadGapFlags($body, $seed))->toBe($flags);
})->with([
    'a value binding' => [
        '$p = trim($p); return $p;',
        function (AnalysisScope $scope): void {
            $scope->varValueBindings['p'] = ['type' => 'string', 'optional' => false];
        },
        [false, false],
    ],
    'a declared class beside a value, written a value naming no class' => [
        '$p = $p->title; return $p;',
        function (AnalysisScope $scope): void {
            $scope->varDeclaredBindings['p'] = [Post::class];
            $scope->varValueBindings['p'] = ['type' => 'Post | null', 'optional' => false];
        },
        [false, true],
    ],
]);

/**
 * The locals seedBody() binds in a parsed body, with UnseenWritesResource as the subject, each with the effect of every
 * by-reference form recorded beside it, followed by the key path the form keeps, as `KeepsList[k]`.
 *
 * @return array<string, list<string>>
 */
function byReferenceSeededLocals(string $body): array
{
    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * Seed the body.
         *
         * @param  array<Node\Stmt>  $stmts
         */
        public function seed(array $stmts, AnalysisScope $scope): void
        {
            $this->seedBody($stmts, $scope);
        }
    };

    /** @var array<Node\Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);
    $scope = new AnalysisScope(new ReflectionClass(UnseenWritesResource::class), Post::class);
    $host->seed($stmts, $scope);

    $bound = [];

    foreach (array_keys($scope->localVarBindings) as $name) {
        $bound[$name] = array_map(
            fn (array $form): string => $form['effect']->name.implode('', array_map(fn (string $key): string => "[{$key}]", $form['path'])),
            $scope->byReferenceForms[$name] ?? [],
        );
    }

    return $bound;
}

/**
 * The names seedBody() records as built by key writes in a parsed body, with UnseenWritesResource as the subject, once
 * the seed step binds what a caller would.
 *
 * @param  (Closure(AnalysisScope): void)|null  $seed
 * @return list<string>
 */
function keyBuiltLocalNames(string $body, ?Closure $seed = null): array
{
    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * Seed the body.
         *
         * @param  array<Node\Stmt>  $stmts
         */
        public function seed(array $stmts, AnalysisScope $scope): void
        {
            $this->seedBody($stmts, $scope);
        }
    };

    /** @var array<Node\Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);
    $scope = new AnalysisScope(new ReflectionClass(UnseenWritesResource::class), Post::class);

    if ($seed !== null) {
        $seed($scope);
    }

    $host->seed($stmts, $scope);

    return array_keys($scope->keyBuiltLocals);
}

it('records a local assigned whole and built by keys, but one a binding holds or a reference can write', function (string $body, array $names, ?Closure $seed = null) {
    expect(keyBuiltLocalNames($body, $seed))->toBe($names);
})->with([
    'a key write' => ['$a = []; $a["k"] = 1;', ['a']],
    '+=' => ['$a = []; $a += ["k" => 1];', ['a']],
    'an unset() of a key' => ['$a = ["k" => 1]; unset($a["k"]);', ['a']],
    'a local written once' => ['$a = ["k" => 1];', []],
    'no whole write' => ['$a["k"] = 1;', []],
    'a reference' => ['$a = []; $r = &$a; $a["k"] = 1;', []],
    'a closure importing it by reference' => ['$a = []; $f = function () use (&$a) {}; $a["k"] = 1;', []],
    'a parameter' => ['$a = []; $a["k"] = 1;', [], function (AnalysisScope $scope): void {
        $scope->varValueBindings['a'] = ['type' => 'string[]', 'optional' => false];
    }],
]);

it('binds a local across a by-reference built-in that keeps its value in some form, and records that form', function (string $body, array $bound) {
    expect(byReferenceSeededLocals($body))->toBe($bound);
})->with([
    'ksort(), any form' => ['$x = []; ksort($x);', ['x' => []]],
    'uksort(), an object' => ['$x = []; uksort($x, fn ($a, $b) => 0);', ['x' => ['KeepsObject']]],
    'ksort() comparing keys as strings, an object' => ['$x = []; ksort($x, SORT_STRING);', ['x' => ['KeepsObject']]],
    'array_pop(), a list' => ['$x = []; array_pop($x);', ['x' => ['KeepsList']]],
    'array_shift(), a list' => ['$x = []; array_shift($x);', ['x' => ['KeepsList']]],
    'array_splice(), a list of its replacement' => ['$x = []; array_splice($x, 1, 1, ["z"]);', ['x' => ['Appends']]],
    'array_splice() with an unpacked replacement' => ['$x = []; array_splice($x, 1, 1, [...$y]);', []],
    'sort(), a list' => ['$x = []; sort($x);', ['x' => ['KeepsList']]],
    'rsort(), a list' => ['$x = []; rsort($x);', ['x' => ['KeepsList']]],
    'usort(), a list' => ['$x = []; usort($x, fn ($a, $b) => 0);', ['x' => ['KeepsList']]],
    'shuffle(), a list' => ['$x = []; shuffle($x);', ['x' => ['KeepsList']]],
    'krsort(), an object' => ['$x = []; krsort($x);', ['x' => ['KeepsObject']]],
    'asort(), an object' => ['$x = []; asort($x);', ['x' => ['KeepsObject']]],
    'arsort(), an object' => ['$x = []; arsort($x);', ['x' => ['KeepsObject']]],
    'uasort(), an object' => ['$x = []; uasort($x, fn ($a, $b) => 0);', ['x' => ['KeepsObject']]],
    'natsort(), an object' => ['$x = []; natsort($x);', ['x' => ['KeepsObject']]],
    'natcasesort(), an object' => ['$x = []; natcasesort($x);', ['x' => ['KeepsObject']]],
    'str_replace()\'s count, a number' => ['$n = 0; str_replace("a", "b", "c", $n);', ['n' => ['KeepsNumber']]],
    'str_ireplace()\'s count, a number' => ['$n = 0; str_ireplace("a", "b", "c", $n);', ['n' => ['KeepsNumber']]],
    'preg_replace()\'s count, a number' => ['$n = 0; preg_replace("/a/", "b", "c", -1, $n);', ['n' => ['KeepsNumber']]],
    'preg_replace_callback()\'s count, named' => ['$n = 0; preg_replace_callback("/a/", fn ($m) => "b", "c", count: $n);', ['n' => ['KeepsNumber']]],
    'array_push(), a list of what it adds' => ['$x = []; array_push($x, 1, 2);', ['x' => ['Appends']]],
    'array_unshift(), a list of what it adds' => ['$x = []; array_unshift($x, 1);', ['x' => ['Appends']]],
    'array_walk() by reference, the elements it writes' => ['$x = []; array_walk($x, function (&$v) { $v = 1; });', ['x' => ['WritesElements']]],
    'array_walk_recursive() by reference, the leaves it writes' => ['$x = []; array_walk_recursive($x, fn (&$v) => $v++);', ['x' => ['WritesLeaves']]],
    'array_walk() by value, any form' => ['$x = []; array_walk($x, fn ($v) => $v);', ['x' => []]],
    'a by-reference foreach, the elements it writes' => ['$x = []; foreach ($x as &$v) { $v = 1; } unset($v);', ['x' => ['WritesElements']]],
    'preg_match()\'s matches, a list of strings' => ['$m = []; preg_match("/x/", "s", $m);', ['m' => ['WritesMatches']]],
    'preg_match()\'s matches with a flag' => ['$m = []; preg_match("/x/", "s", $m, PREG_OFFSET_CAPTURE);', []],
    'settype()' => ['$x = 1; settype($x, "string");', []],
    'parse_str()' => ['$x = []; parse_str("a=1", $x);', []],
    'a user method' => ['$x = 1; $this->fillInstance($x);', []],
    'sort() of a key of the variable, that member a list' => ['$x = []; sort($x["k"]);', ['x' => ['KeepsList[k]']]],
    'sort() of a nested int key' => ['$x = []; sort($x["k"][0]);', ['x' => ['KeepsList[k][0]']]],
    'ksort() of a key, any form' => ['$x = []; ksort($x["k"]);', ['x' => []]],
    'array_push() into a key' => ['$x = []; array_push($x["k"], 1);', ['x' => ['Appends[k]']]],
    'a by-reference foreach over a key' => ['$x = []; foreach ($x["k"] as &$v) { $v = 1; } unset($v);', ['x' => ['WritesElements[k]']]],
    'a key read by a variable' => ['$x = []; sort($x[$k]);', []],
    'an appended key' => ['$x = []; sort($x[]);', []],
    'preg_match() into a key' => ['$x = []; preg_match("/x/", "s", $x["k"]);', []],
    'array_push() of an unpacked list' => ['$x = []; array_push($x, ...$y);', []],
    'array_walk() with a callback it cannot read' => ['$x = []; array_walk($x, "trim");', []],
    'array_walk() writing a key of the element' => ['$x = []; array_walk($x, function (&$v) { $v["k"] = 1; });', []],
    'a foreach writing a key of the element' => ['$x = []; foreach ($x as &$v) { $v["k"] = 1; }', []],
    'a foreach whose element is written past the loop' => ['$x = []; foreach ($x as &$v) {} $v = 1;', ['x' => ['WritesElements']]],
    'a foreach whose element a by-reference call writes' => ['$x = []; foreach ($x as &$v) { sort($v); }', []],
]);

it('keeps a parameter\'s value binding across a by-reference built-in that keeps it in some form', function (string $body, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope): void {
        $scope->varValueBindings['p'] = ['type' => 'string[]', 'optional' => false];
    }))->toBe($holding);
})->with([
    'sort()' => ['sort($p); return $p;', [true, true]],
    'ksort()' => ['ksort($p); return $p;', [true, true]],
    'array_push()' => ['array_push($p, "x"); return $p;', [true, true]],
    'preg_match()\'s matches' => ['preg_match("/x/", "s", $p); return $p;', [false, false]],
    'preg_match()\'s matches in a call that may not run' => ['if ($c) { preg_match("/x/", "s", $p); } return $p;', [true, true]],
]);

// No form a by-reference built-in keeps is an object's, but ksort(), which keeps any value it can take.
it('ends a class binding at a by-reference built-in that keeps a value in another form', function (string $body, array $holding) {
    expect(parameterReadsHolding($body, function (AnalysisScope $scope): void {
        $scope->varModelBindings['p'] = User::class;
    }))->toBe($holding);
})->with([
    'sort()' => ['sort($p); return $p;', [false, false]],
    'a count out-parameter' => ['str_replace("a", "b", "c", $p); return $p;', [false, false]],
    'ksort()' => ['ksort($p); return $p;', [true, true]],
    'preg_match()\'s matches' => ['preg_match("/x/", "s", $p); return $p;', [false, false]],
    'preg_match()\'s matches in a call that may not run' => ['$c && preg_match("/x/", "s", $p); return $p;', [true, true]],
]);

// An offset write extends an array, so the literal assigned no longer describes it and the walk reads its keys; a model
// takes the write itself.
it('keeps a local holding a model across a write through it, and reads an array an offset write extends by its keys', function () {
    $user = new User;
    $user->forceFill(['id' => 3, 'name' => 'Ann', 'email' => 'ann@example.com']);
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);
    $post->setRelation('author', $user);

    expect(publishedResourceKeyTypes(MemberWrittenLocalsResource::class, Post::class))->toBe([
        'draft_title' => 'string',
        'draft_content' => 'string',
        'post_title' => 'string',
        'author_name' => 'string',
        'captured_title?' => 'string',
        'options' => '{ a: number; b: string }',
        'key' => 'string',
        'maybe' => 'unknown',
    ])
        ->and(json_decode((string) json_encode(new MemberWrittenLocalsResource($post)->resolve(Request::create('/'))), true))
        ->toBe([
            'draft_title' => 'Draft',
            'draft_content' => 'Body',
            'post_title' => 'Banana',
            'author_name' => 'Bob',
            'captured_title' => 'Again',
            'options' => ['a' => 1, 'b' => 'x'],
            'key' => 'a',
            'maybe' => ['title' => 'm'],
        ]);
});

/**
 * What ModelFills::mark() marks the `new` a parsed body's `$p = new Post;` assigns, past $before, as holding: every
 * attribute (true), the members a write fills, or none (false).
 *
 * @return list<string>|bool
 */
function marksFilled(string $body, string $before = ''): array|bool
{
    /** @var array<Node\Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$before.'$p = new \\Workbench\\App\\Models\\Post; '.$body);
    $new = new NodeFinder()->findFirstInstanceOf($stmts, New_::class);
    assert($new instanceof New_);
    ModelFills::mark($new, 'p', $stmts, VariableWrites::of($stmts), new AnalysisScope(new ReflectionClass(PostResource::class), Post::class));

    return $new->getAttribute(ReceiverClassResolver::HOLDS_ATTRIBUTES) === true
        ?: $new->getAttribute(ReceiverClassResolver::FILLED_MEMBERS, false);
}

// Laravel sends what a body wrote through the variable, before the read or after it, so its `new` holds attributes.
it('marks a variable\'s new as holding attributes where its body fills the model', function (string $body, array|bool $fills, string $before = '') {
    expect(marksFilled($body, $before))->toBe($fills);
})->with([
    'a member write' => ['$p->title = "x";', ['title']],
    'a coalescing member write' => ['$p->title ??= "x";', ['title']],
    'an offset write' => ['$p["title"] = "x";', ['title']],
    'an increment' => ['$p->word_count++;', ['word_count']],
    'two members written' => ['$p->title = "x"; $p->content .= "y";', ['title', 'content']],
    'a member written null, which its column cannot hold' => ['$p->title = null;', false],
    'a member written a value the scan cannot type' => ['$p->title = $t;', false],
    'a member no column names' => ['$p->views = 1;', false],
    'fill() as a statement' => ['$p->fill([]);', true],
    'forceFill() inside an expression' => ['$q = $p->forceFill([]);', true],
    'any method called as a statement' => ['$p->save();', true],
    'a relation method called as a statement' => ['$p->author()->associate($u);', true],
    'a closure a call runs' => ['collect([1])->each(function () use ($p) { $p->title = "x"; });', ['title']],
    'an arrow function a call runs' => ['collect([1])->each(fn () => $p->title = "x");', ['title']],
    'a closure the body calls' => ['$f = function () use (&$p) { $p->title = "x"; }; $f();', ['title']],
    'a closure the body never calls' => ['$f = function () use ($p) { $p->title = "x"; };', false],
    'a closure assigned inside a call that may run it' => ['$this->register($f = function () use ($p) { $p->title = "x"; });', ['title']],
    'a closure parameter of the name' => ['collect([1])->each(function ($p) { $p->save(); });', false],
    'an arrow function parameter of the name' => ['collect([1])->each(fn ($p) => $p->title = "x");', false],
    'a closure not importing the name' => ['collect([1])->each(function () { $p->title = "x"; });', false],
    'a nested class' => ['$o = new class { public function m($p) { $p->save(); } };', false],
    'a write through a relation' => ['$p->author->name = "x";', false],
    'an attribute writer on a relation' => ['$q = $p->author->fill([]);', false],
    'a method called on a relation as a statement' => ['$p->author->save();', false],
    'a write before the assignment' => ['', false, '$p->title = "x"; '],
    'a write past the next assignment' => ['$p = new \\Workbench\\App\\Models\\Post; $p->title = "x";', false],
    'a write past a block that assigns and leaves' => ['if ($c) { $p = new \\Workbench\\App\\Models\\Post; return; } $p->title = "x";', ['title']],
    'a write past an assignment in its own block' => ['if ($c) { $p = new \\Workbench\\App\\Models\\Post; $p->title = "x"; }', false],
    'a member read' => ['$t = $p->title;', false],
    'a method read as a value' => ['$k = $p->getKey();', false],
    'another variable written' => ['$q = new \\Workbench\\App\\Models\\Post; $q->title = "x";', false],
    'a reassignment' => ['$p = null;', false],
]);
