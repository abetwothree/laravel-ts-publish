<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsInstanceofGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\DeclaredTotalsTraitResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use Workbench\App\Http\Resources\CartTotalsResource;
use Workbench\App\Http\Resources\PostPinnedCommentsResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\CartTotals;

/**
 * Parse a method body and seed a Post-backed resource scope from it, as the analyzer seeds `toArray()`.
 *
 * @return array{AnalysisScope, list<Stmt>}
 */
function inlineVarSeededBody(string $body): array
{
    $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
    /** @var list<Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);

    $host = new class
    {
        use CollectsInstanceofGuards;
        use CollectsLocalVarBindings;

        /** @param  list<Stmt>  $stmts */
        public function run(array $stmts, AnalysisScope $scope): void
        {
            $this->collectLocalVarBindings($stmts, $scope);
            $this->collectInstanceofGuards($stmts, $scope);
        }
    };

    $host->run($stmts, $scope);

    return [$scope, $stmts];
}

/**
 * The classes each `$x->member` read in a body resolves its receiver to, in source order; null where none is named.
 *
 * @return list<list<class-string>|null>
 */
function inlineVarReadClasses(string $body): array
{
    [$scope, $stmts] = inlineVarSeededBody($body);
    $reads = new NodeFinder()->find($stmts, fn (Node $node): bool => $node instanceof PropertyFetch
        && $node->var instanceof Variable
        && $node->var->name === 'x');

    return array_map(
        fn (Node $read): ?array => $read instanceof PropertyFetch
            ? resolve(ReceiverClassResolver::class)->resolve($read->var, $scope)?->classes
            : null,
        $reads,
    );
}

/** The expression a seeded body's final `return` hands back, with the scope that body seeded. */
function inlineVarReturned(string $body): array
{
    [$scope, $stmts] = inlineVarSeededBody($body);
    $last = $stmts[array_key_last($stmts)];

    return [$scope, $last instanceof Return_ && $last->expr instanceof Expr ? $last->expr : throw new LogicException('No return.')];
}

describe('an inline @var on a local assignment', function () {
    test('types every read through the local after it, and leaves the controls to the assignment', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => CartTotalsResource::class])->content)->toContain(<<<'TS'
            export interface CartTotalsResource
            {
                subtotal: number;
                chargeable: boolean;
                count: number;
                note: string | null;
                totals: { subtotal: number; chargeable: boolean; count: number; hasExtras: boolean };
                unnamed_count: number;
                label: string;
                count_before: number;
                count_after: unknown;
                missing_count: unknown;
            }
            TS);
    });

    test('names the collection a relation loaded under a name the model does not declare holds', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => PostPinnedCommentsResource::class])->content)
            ->toContain("import type { Comment } from '../../models';")
            ->toContain(<<<'TS'
                export interface PostPinnedCommentsResource
                {
                    id: number;
                    pinned_count: number;
                    pinned: Comment[];
                }
                TS);
    });

    test('resolves its names against the file of the body it sits in, a trait\'s included', function () {
        $props = collect(resolve(AstEngine::class)->analyze(DeclaredTotalsTraitResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toBe(['trait_count' => 'number']);
    });

    test('holds for the reads after its statement and before the statement that next writes the variable', function (string $body, array $classes) {
        expect(inlineVarReadClasses($body))->toBe($classes);
    })->with([
        'a named tag, read before and after' => [
            '$x->a; /** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->b; $x->c;',
            [null, [CartTotals::class], [CartTotals::class]],
        ],
        'a name-less tag' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals */ $x = json_decode(""); $x->a;',
            [[CartTotals::class]],
        ],
        'a tag naming another variable, then one naming this one' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $y */ $x = json_decode(""); $x->a; /** @var \Workbench\App\Models\User $x */ $x = json_decode(""); $x->b;',
            [null, [User::class]],
        ],
        'a later write' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a; $x = null; $x->b;',
            [[CartTotals::class], null],
        ],
        'a later write inside a loop, which ends the span where the loop starts' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a; foreach ([1] as $i) { $x->b; $x = null; } $x->c;',
            [[CartTotals::class], null, null],
        ],
        'a by-reference closure use after it' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a; $f = function () use (&$x) {}; $x->b;',
            [[CartTotals::class], null],
        ],
        'a write before it' => [
            '$x = null; /** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a;',
            [[CartTotals::class]],
        ],
        'a second annotated assignment, which starts a span of its own' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a; /** @var \Workbench\App\Models\User $x */ $x = json_decode(""); $x->b;',
            [[CartTotals::class], [User::class]],
        ],
        'a second write inside the assigning statement, then a plain annotated assignment' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = ($x = null) ?? json_decode(""); $x->a; /** @var \Workbench\App\Models\User $x */ $x = json_decode(""); $x->b;',
            [null, [User::class]],
        ],
    ]);

    test('outranks what the assignment reads unless that agrees and says more, and a union or ?T names each class', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        [$modelScope, $model] = inlineVarReturned('/** @var \Illuminate\Database\Eloquent\Model $a */ $a = $this->author; return $a;');
        [$collectionScope, $collection] = inlineVarReturned('/** @var \Illuminate\Database\Eloquent\Collection<int, \Workbench\App\Models\Comment> $c */ $c = $this->comments; return $c;');
        [$narrowerScope, $narrower] = inlineVarReturned('/** @var \Workbench\App\Models\SubscribedTeam $t */ $t = new \Workbench\App\Models\Team; return $t;');
        [$unionScope, $union] = inlineVarReturned('/** @var \Workbench\App\Models\Post|\Workbench\App\Models\User|null $u */ $u = json_decode(""); return $u;');
        [$nullableScope, $nullable] = inlineVarReturned('/** @var ?\Workbench\App\Models\Post $p */ $p = json_decode(""); return $p;');

        expect($resolver->resolve($model, $modelScope)?->classes)->toBe([User::class])
            ->and($resolver->resolve($collection, $collectionScope)?->classes)->toBe([EloquentCollection::class])
            ->and($resolver->resolve($collection, $collectionScope)?->elementModel)->toBe(Comment::class)
            ->and($resolver->resolve($narrower, $narrowerScope)?->classes)->toBe([SubscribedTeam::class])
            ->and($resolver->resolve($union, $unionScope)?->classes)->toBe([Post::class, User::class])
            ->and($resolver->resolve($nullable, $nullableScope)?->classes)->toBe([Post::class]);
    });

    test('leaves the receiver to the assignment when the type names no loadable class', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        [$declaredScope, $declared] = inlineVarReturned('/** @var \Workbench\App\ValueObjects\CartTotals $n */ $n = $this->author; return $n;');
        [$scalarScope, $scalar] = inlineVarReturned('/** @var int $n */ $n = $this->author; return $n;');
        [$missingScope, $missing] = inlineVarReturned('/** @var \No\Such\Totals $n */ $n = $this->author; return $n;');

        expect($resolver->resolve($declared, $declaredScope)?->classes)->toBe([CartTotals::class])
            ->and($resolver->resolve($scalar, $scalarScope)?->classes)->toBe([User::class])
            ->and($resolver->resolve($missing, $missingScope)?->classes)->toBe([User::class]);
    });

    test('types a bare read, unless the type is too vague to publish or names a model with no file', function () {
        $resolved = function (string $body): string {
            [$scope, $returned] = inlineVarReturned($body);

            return new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class, 'toArray', null, $scope)
                ->resolve($returned)['type'];
        };

        expect($resolved('/** @var list<string> $tags */ $tags = json_decode(""); return $tags;'))->toBe('string[]')
            ->and($resolved('/** @var string $s */ $s = json_decode(""); return $s;'))->toBe('string')
            ->and($resolved('/** @var \Illuminate\Database\Eloquent\Collection<int, \Workbench\App\Models\Comment> $c */ $c = json_decode(""); return $c;'))
            ->toBe('Comment[]')
            ->and($resolved('/** @var array<string, mixed> $row */ $row = ["a" => 1]; return $row;'))->toBe('{ a: number }')
            ->and($resolved('/** @var \Illuminate\Database\Eloquent\Model $m */ $m = $this->author; return $m;'))->toBe('User');
    });

    test('types a member read inside a whenLoaded closure from the declaration, not the loaded relation', function () {
        $closure = new AstParser()->parseSource('<?php $this->whenLoaded("author", function () {
            /** @var \Workbench\App\Models\Comment $c */
            $c = json_decode("");

            return $c->content;
        });')[0]->expr;

        expect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class)->resolve($closure)['type'])
            ->toBe('string');
    });
});
