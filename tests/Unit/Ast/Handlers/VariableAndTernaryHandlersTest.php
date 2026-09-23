<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ExpressionDispatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ScalarHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\TernaryHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\VariableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\HelperCallResource;
use Workbench\App\Http\Resources\NarrowedImageableResource;
use Workbench\App\Http\Resources\PostCommentAuthorsResource;
use Workbench\App\Http\Resources\TeamSubscriberResource;
use Workbench\App\Http\Resources\UserFeaturedPostsResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * An engine that fails the test if a handler calls back into it.
 */
function variableHandlersThrowingEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called in this case');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

/**
 * Resolve one expression through the full resource profile, with `$rows` bound to the subject's own comments.
 *
 * @param  class-string<Model>  $model
 * @return array<string, mixed>
 */
function variableHandlersResolveOn(string $php, string $model = Post::class, ?AnalysisScope $scope = null): array
{
    $parse = fn (string $source): Expr => new AstParser()->parseSource('<?php '.$source.';')[0]->expr;
    $scope ??= new AnalysisScope(new ReflectionClass(PostCommentAuthorsResource::class), $model);
    $scope->localVarBindings['rows'] = $parse('$this->resource->getRelation("comments")');

    return new ResourceAstAnalyzer(new ReflectionClass(PostCommentAuthorsResource::class), $model, 'toArray', null, $scope)
        ->resolve($parse($php));
}

/**
 * A minimal recursive engine over a fixed handler list — the re-entrancy guard is only observable
 * when a handler's callback really re-enters the same handler.
 */
final class VariableHandlersLoopEngine implements ExpressionEngine
{
    private ExpressionDispatcher $dispatcher;

    /** @param list<ExpressionHandler> $handlers */
    public function __construct(array $handlers, private AnalysisScope $scope)
    {
        $this->dispatcher = new ExpressionDispatcher($handlers);
    }

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        return $this->dispatcher->dispatch($expr, $this->scope, $this) ?? ValueResult::unknown();
    }

    public function spreadAnalysis(string $methodName): ?MethodAnalysis
    {
        throw new RuntimeException('spreadAnalysis() must not be called in this case');
    }

    public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
    {
        throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
    }
}

/**
 * Records the scope's narrowing fields as each ternary arm resolves, keyed by that arm's string literal,
 * so a binding that is only in force *during* one arm can be asserted after the fact.
 */
final class TernaryArmRecordingEngine implements ExpressionEngine
{
    /** @var array<string, string|null> */
    public array $forwardsPerArm = [];

    /** @var array<string, string|null> */
    public array $modelPerArm = [];

    /** Watches the scope the ternary under test narrows. */
    public function __construct(private AnalysisScope $scope) {}

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        if ($expr instanceof String_) {
            $this->forwardsPerArm[$expr->value] = $this->scope->forwardsUndeclaredMembersTo;
            $this->modelPerArm[$expr->value] = $this->scope->modelClass;
        }

        return ['type' => 'string', 'optional' => false];
    }

    /** Fails the test: no method is spread in this case. */
    public function spreadAnalysis(string $methodName): ?MethodAnalysis
    {
        throw new RuntimeException('spreadAnalysis() must not be called in this case');
    }

    /** Fails the test: no array is analyzed in this case. */
    public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
    {
        throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
    }
}

it('resolves a bound variable property fetch to the related model column type', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['item'] = User::class;

    $result = (new VariableHandler)->resolve(
        new PropertyFetch(new Variable('item'), 'name'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('resolves a bare bound variable to its model type', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['author'] = User::class;

    $result = (new VariableHandler)->resolve(
        new Variable('author'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
});

it('resolves a bare variable bound to a whole relation collection to the collection type', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varCollectionBindings['authors'] = ['type' => 'User[]', 'modelFqcn' => User::class];

    $result = (new VariableHandler)->resolve(
        new Variable('authors'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'User[]', 'optional' => false, 'modelFqcn' => User::class]);
});

it('resolves $variable->pluck() against the ambient closure relation model', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->closureRelationModelClass = User::class;

    $result = (new VariableHandler)->resolve(
        new MethodCall(new Variable('users'), 'pluck', [new Arg(new String_('name'))]),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'string[]', 'optional' => false]);
});

// Guard-order pin: the pluck guard must precede the generic `$variable->method()` guard. Both claim
// this node — the generic guard's bound model is non-null here — but it resolves `pluck` as a method
// on User, which has none, so swapping the two turns this into 'unknown'.
it('tries the pluck guard before the generic bound-method guard', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->closureRelationModelClass = User::class;
    $scope->varModelBindings['users'] = User::class;

    $result = (new VariableHandler)->resolve(
        new MethodCall(new Variable('users'), 'pluck', [new Arg(new String_('name'))]),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect(method_exists(User::class, 'pluck'))->toBeFalse()
        ->and($result)->toBe(['type' => 'string[]', 'optional' => false]);
});

// Guard-order pin: the model-binding guard must precede the expression-binding guard. Both claim
// this node — `$author` is in both maps — and the expression binding resolves to a string literal,
// so swapping the two returns 'string' instead of the model type.
it('tries the model binding before the local-expression binding for a bare variable', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['author'] = User::class;
    $scope->localVarBindings['author'] = new String_('literal');

    $engine = new VariableHandlersLoopEngine([new ScalarHandler, new VariableHandler], $scope);

    expect($engine->resolve(new String_('literal')))->toBe(['type' => 'string', 'optional' => false])
        ->and($engine->resolve(new Variable('author')))
        ->toBe(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
});

it('degrades a cyclic local-variable binding to unknown instead of recursing forever', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->localVarBindings['a'] = new Variable('b');
    $scope->localVarBindings['b'] = new Variable('a');

    $engine = new VariableHandlersLoopEngine([new VariableHandler], $scope);

    expect($engine->resolve(new Variable('a')))->toBe(['type' => 'unknown', 'optional' => false])
        ->and($scope->resolvingLocalVars)->toBe([]);
});

// Reflecting Model::except()'s `@return array` gives a list, and a collection-bound variable filters by primary key:
// the receiver rules own a filter on a variable, so the bound-method guard never answers one.
it('declines only() and except() on a bound variable, whatever the key list', function (string $method, Arg $keys) {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['author'] = User::class;
    $scope->closureRelationModelClass = User::class;

    $result = (new VariableHandler)->resolve(new MethodCall(new Variable('author'), $method, [$keys]), $scope, variableHandlersThrowingEngine());

    expect($result)->toBeNull();
})->with([
    'except($fields)' => ['except', new Arg(new Variable('fields'))],
    'only($fields)' => ['only', new Arg(new Variable('fields'))],
    'only([1])' => ['only', new Arg(new Array_([new ArrayItem(new Int_(1))]))],
]);

it('declines an expression it does not claim, leaving later handlers their turn', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $handler = new VariableHandler;
    $engine = variableHandlersThrowingEngine();

    expect($handler->resolve(new Variable('unbound'), $scope, $engine))->toBeNull()
        ->and($handler->resolve(new PropertyFetch(new Variable('unbound'), 'name'), $scope, $engine))->toBeNull()
        ->and($handler->resolve(new MethodCall(new Variable('unbound'), 'thing'), $scope, $engine))->toBeNull();
});

it('unions both ternary arms', function () {
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    $expr = new Ternary(new Variable('flag'), new String_('yes'), new Int_(1));

    expect($analyzer->resolve($expr))->toBe(['type' => 'string | number', 'optional' => false]);
});

it('unions an elvis expression through its condition as the truthy arm', function () {
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    $expr = new Ternary(new String_('yes'), null, new Int_(1));

    expect($analyzer->resolve($expr))->toBe(['type' => 'string | number', 'optional' => false]);
});

it('declines anything that is not a ternary', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new TernaryHandler)->resolve(new Variable('x'), $scope, variableHandlersThrowingEngine());

    expect($result)->toBeNull();
});

it('applies the known-method floor to a count() on an unclaimed receiver', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperCallResource::class), Order::class);

    $result = (new KnownMethodRuleHandler)->resolve(
        new MethodCall(new PropertyFetch(new Variable('this'), 'items'), 'count'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('applies the known-method floor to can() on a method-call receiver', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperCallResource::class), Order::class);

    $result = (new KnownMethodRuleHandler)->resolve(
        new MethodCall(new MethodCall(new Variable('request'), 'user'), 'can', [new Arg(new String_('view'))]),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'boolean', 'optional' => false]);
});

it('declines a method call no known-method rule matches', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperCallResource::class), Order::class);

    $result = (new KnownMethodRuleHandler)->resolve(
        new MethodCall(new Variable('thing'), 'somethingUnknown'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBeNull();
});

it('reads $variable->pluck(key: …, value: …) by name', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->closureRelationModelClass = User::class;

    $result = (new VariableHandler)->resolve(
        new MethodCall(new Variable('users'), 'pluck', [
            new Arg(new String_('id'), name: new Identifier('key')),
            new Arg(new String_('name'), name: new Identifier('value')),
        ]),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'string[]', 'optional' => false]);
});

it('reads $variable->map(callback: …) by name', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $body = new PropertyFetch(new Variable('user'), 'name');
    $closure = new ArrowFunction([
        'params' => [new Param(new Variable('user'), type: new Name(User::class))],
        'expr' => $body,
    ]);
    $expr = new MethodCall(new Variable('users'), 'map', [new Arg($closure, name: new Identifier('callback'))]);
    $engine = new VariableHandlersLoopEngine([new VariableHandler], $scope);

    expect((new VariableHandler)->resolve($expr, $scope, $engine))->toBe(['type' => 'string[]', 'optional' => false]);
});

// ReceiverClassResolver reads varModelBindings, not closureRelationModelClass, so a chain from the parameter needs it.
it('resolves a nullsafe chain from a typed map-closure parameter', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(PostCommentAuthorsResource::class), Post::class)->analyze()->properties)->keyBy('name');

    expect($props['authors']['type'])->toBe('({ id: number; who: string | null; who_or: string | null })[]')
        ->and($props['loaded']['type'])->toBe('({ who: string | null })[]')
        ->and($props['loaded']['optional'])->toBeTrue();
});

it('keeps a typed map over a relation the model does not declare through a trailing values()->all()', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(UserFeaturedPostsResource::class), User::class)->analyze()->properties)->keyBy('name');

    expect($props['featured_posts']['type'])->toBe('({ id: number; title: string; file: string | null })[]')
        ->and($props['featured_posts']['optional'])->toBeTrue();
});

// Only the map arm's own answer passes through an unnamed receiver: nothing is invented,
// and a named model still declines.
it('declines a trailing values()/all() the map arm does not answer, or on a variable that holds a model', function (string $php, string $model) {
    $scope = new AnalysisScope(new ReflectionClass(PostCommentAuthorsResource::class), $model);
    $scope->localVarBindings['one'] = new AstParser()->parseSource('<?php $this->resource->getRelation("author");')[0]->expr;

    expect(variableHandlersResolveOn($php, $model, $scope)['type'])->toBe('unknown');
})->with([
    'untyped map parameter' => ['$rows->map(fn ($c) => $c->user?->name)->values()->all()', Image::class],
    'scalar map parameter' => ['$rows->map(fn (string $c) => $c)->values()->all()', Image::class],
    'a variable holding a model' => ['$one->map(fn (\Workbench\App\Models\Comment $c) => $c->user?->name)->values()->all()', Post::class],
]);

it('resolves a nullsafe chain from an untyped map-closure parameter its receiver names the element of', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostCommentAuthorsResource::class), Post::class);
    $scope->closureRelationModelClass = Comment::class;
    $scope->varCollectionBindings['comments'] = ['type' => 'Comment[]', 'modelFqcn' => Comment::class];
    $expr = new AstParser()->parseSource('<?php $comments->map(fn ($comment) => $comment->user?->name);')[0]->expr;
    $engine = new ResourceAstAnalyzer(new ReflectionClass(PostCommentAuthorsResource::class), Post::class, 'toArray', null, $scope);

    expect((new VariableHandler)->resolve($expr, $scope, $engine))->toBe(['type' => '(string | null)[]', 'optional' => false]);
});

it('binds the map-closure parameter over an outer binding of the same name, only while the body resolves', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['comment'] = User::class;
    $closure = new ArrowFunction([
        'params' => [new Param(new Variable('comment'), type: new Name(Comment::class))],
        'expr' => new Variable('comment'),
    ]);
    $expr = new MethodCall(new Variable('rows'), 'map', [new Arg($closure)]);
    $engine = new VariableHandlersLoopEngine([new VariableHandler], $scope);

    expect((new VariableHandler)->resolve($expr, $scope, $engine))
        ->toBe(['type' => 'Comment[]', 'optional' => false, 'modelFqcn' => Comment::class])
        ->and($scope->varModelBindings)->toBe(['comment' => User::class])
        ->and($scope->closureRelationModelClass)->toBeNull();
});

it('restores the map-closure bindings when the body throws', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['comment'] = User::class;
    $closure = new ArrowFunction([
        'params' => [new Param(new Variable('comment'), type: new Name(Comment::class))],
        'expr' => new PropertyFetch(new Variable('comment'), 'id'),
    ]);
    $expr = new MethodCall(new Variable('rows'), 'map', [new Arg($closure)]);

    expect(fn () => (new VariableHandler)->resolve($expr, $scope, variableHandlersThrowingEngine()))
        ->toThrow(RuntimeException::class)
        ->and($scope->varModelBindings)->toBe(['comment' => User::class])
        ->and($scope->closureRelationModelClass)->toBeNull();
});

// A nested writer's parameter must beat an outer binding of its name in every table, whichever table each one uses.
it('lets a map-closure parameter own its name over an outer binding of the same name', function (string $php, string $model, string $type) {
    expect(variableHandlersResolveOn($php, $model)['type'])->toBe($type);
})->with([
    'collect() map in a typed variable-receiver map' => [
        '$rows->map(fn (\Workbench\App\Models\Comment $c) => collect(explode(" ", $c->content))->map(fn ($c) => ["word" => $c])->values()->all())->all()',
        Post::class,
        '{ word: string }[][]',
    ],
    'collect() map in an untyped whenLoaded map' => [
        '$this->whenLoaded("comments", fn ($comments) => $comments->map(fn ($c) => collect(explode(" ", $c->content))->map(fn ($c) => ["word" => $c])->values()->all()))',
        Post::class,
        '{ word: string }[][]',
    ],
    'collect() map in a relation-chain map' => [
        '$this->comments->map(fn ($c) => collect(explode(" ", $c->content))->map(fn ($c) => ["word" => $c])->values()->all())',
        Post::class,
        '{ word: string }[][]',
    ],
    'collect() map in a to-one whenLoaded closure' => [
        '$this->whenLoaded("author", fn ($c) => collect(explode(" ", $c->name))->map(fn ($c) => ["word" => $c])->values()->all())',
        Post::class,
        '{ word: string }[]',
    ],
    'typed variable-receiver map under a morphTo whenLoaded parameter' => [
        '$this->whenLoaded("reviewable", fn ($c) => $rows->map(fn (\Workbench\App\Models\Comment $c) => $c->user?->name)->all())',
        Image::class,
        '(string | null)[]',
    ],
]);

// The map parameter's own model binding must not leak into a nested closure that reuses the name, whoever binds it.
it('lets a nested closure parameter own its name over an outer map parameter of the same name', function (string $php, string $type) {
    expect(variableHandlersResolveOn($php)['type'])->toBe($type);
})->with([
    'to-many whenLoaded' => [
        '$rows->map(fn (\Workbench\App\Models\Comment $c) => $this->whenLoaded("comments", fn ($c) => $c))->all()',
        'Comment[][]',
    ],
    'transform() callback' => ['$rows->map(fn (\Workbench\App\Models\Comment $c) => $this->transform($this->title, fn ($c) => $c))->all()', 'string[]'],
    'when() on a property' => ['$rows->map(fn (\Workbench\App\Models\Comment $c) => $this->when($this->title, fn ($c) => $c))->all()', 'string[]'],
    'whenHas() value closure' => ['$rows->map(fn (\Workbench\App\Models\Comment $c) => $this->whenHas("title", fn ($c) => $c))->all()', 'string[]'],
    'when() binding nothing' => ['$rows->map(fn (\Workbench\App\Models\Comment $c) => $this->when(true, fn ($c) => $c))->all()', 'unknown'],
    'transform() default closure' => [
        '$rows->map(fn (\Workbench\App\Models\Comment $c) => $this->transform($this->title, fn ($t) => $t, fn ($c) => $c))->all()',
        'string[]',
    ],
]);

it('releases a map-closure parameter from an outer class narrowing of the same name', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostCommentAuthorsResource::class), Post::class);
    $scope->varClassBindings['c'] = [User::class];

    expect(variableHandlersResolveOn('$rows->map(fn (\Workbench\App\Models\Comment $c) => $c->user?->name)', Post::class, $scope)['type'])
        ->toBe('(string | null)[]')
        ->and($scope->varClassBindings)->toBe(['c' => [User::class]]);
});

// Collection::map() passes ($value, $key), so a variadic first parameter holds both and is never one element model.
it('binds no element model to a variadic map-closure parameter', function (string $php, string $type) {
    expect(variableHandlersResolveOn($php)['type'])->toBe($type);
})->with([
    'untyped, whenLoaded receiver' => ['$this->whenLoaded("comments", fn ($comments) => $comments->map(fn (...$c) => $c))', 'unknown'],
    'typed, variable receiver' => ['$rows->map(fn (\Workbench\App\Models\Comment ...$c) => $c)->all()', 'unknown'],
    'relation-chain receiver' => ['$this->comments->map(fn (...$c) => $c)', 'unknown'],
    'collect() receiver' => ['collect(explode(" ", $this->title))->map(fn (...$w) => $w)->all()', 'unknown'],
]);

it('narrows the forwarding target from the subject even when modelClass is null', function () {
    // resolveInstanceOfType() searches only If_ nodes, so a ternary-only `$this->resource instanceof X`
    // leaves both the backing model and the wrapped class null — the shape no workbench fixture builds.
    $scope = new AnalysisScope(new ReflectionClass(TeamSubscriberResource::class));

    expect($scope->forwardsUndeclaredMembersTo)->toBeNull();

    $engine = new TernaryArmRecordingEngine($scope);

    (new TernaryHandler)->resolve(
        new Ternary(
            new Instanceof_(new PropertyFetch(new Variable('this'), 'resource'), new Name(SubscribedTeam::class)),
            new String_('if'),
            new String_('else'),
        ),
        $scope,
        $engine,
    );

    // The true arm forwards to the narrowed model, so `$this->undeclaredMethod()` there resolves against
    // it rather than declining; the else arm and the scope afterwards are untouched.
    expect($engine->forwardsPerArm['if'])->toBe(SubscribedTeam::class)
        ->and($engine->modelPerArm['if'])->toBe(SubscribedTeam::class)
        ->and($engine->forwardsPerArm['else'])->toBeNull()
        ->and($scope->forwardsUndeclaredMembersTo)->toBeNull()
        ->and($scope->modelClass)->toBeNull();
});

/**
 * A scope over Image whose `$record` holds the `imageable` morphTo: Post, Product, User or CRM User.
 */
function ternaryRecordScope(): AnalysisScope
{
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);

    $scope = new AnalysisScope(new ReflectionClass(NarrowedImageableResource::class), Image::class);
    $scope->localVarBindings['record'] = new AstParser()->parseSource('<?php $this->imageable;')[0]->expr;

    return $scope;
}

it('narrows the arm an instanceof condition proves: the false arm of a negated test, and an || chain on a variable', function () {
    $teamScope = new AnalysisScope(new ReflectionClass(TeamSubscriberResource::class), Team::class);
    $negated = '! $this->resource instanceof \Workbench\App\Models\SubscribedTeam ? null : $this->resource->subscriber?->name';
    $either = '$record instanceof \Workbench\App\Models\Post || $record instanceof \Workbench\App\Models\User ? $record->comments : null';

    expect(variableHandlersResolveOn($negated, Team::class, $teamScope)['type'])->toBe('string | null')
        ->and(variableHandlersResolveOn($either, Image::class, ternaryRecordScope())['type'])->toBe('Comment[] | null');
});

it('does not narrow an arm that writes its subject, so a read after the write holds what the variable now does', function () {
    $written = '$record instanceof \Workbench\App\Models\Post ? ["r" => $record = $this->author, "t" => $record->title] : null';
    $unwritten = '$record instanceof \Workbench\App\Models\Post ? ["t" => $record->title] : null';

    expect(variableHandlersResolveOn($written, Image::class, ternaryRecordScope())['type'])->toBe('{ r: unknown; t: unknown } | null')
        ->and(variableHandlersResolveOn($unwritten, Image::class, ternaryRecordScope())['type'])->toBe('{ t: string } | null');
});

/**
 * An engine that records what a variable's class binding is while each string-literal arm resolves.
 */
final class TernaryClassBindingRecordingEngine implements ExpressionEngine
{
    /** @var array<string, list<class-string>|null> */
    public array $classesPerArm = [];

    /** Watches the scope the ternary under test narrows, for one variable. */
    public function __construct(private AnalysisScope $scope, private string $variable) {}

    /**
     * Record the variable's class binding while a string-literal arm resolves, and type every arm as a string.
     *
     * @return array<string, mixed>
     */
    public function resolve(Expr $expr): array
    {
        if ($expr instanceof String_) {
            $this->classesPerArm[$expr->value] = $this->scope->varClassBindings[$this->variable] ?? null;
        }

        return ['type' => 'string', 'optional' => false];
    }

    /** Fails the test: no method is spread in this case. */
    public function spreadAnalysis(string $methodName): ?MethodAnalysis
    {
        throw new RuntimeException('spreadAnalysis() must not be called in this case');
    }

    /** Fails the test: no array is analyzed in this case. */
    public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
    {
        throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
    }
}

it('binds the proven arm\'s subject to what the test leaves of its own classes, as the receiver path does', function (string $php, array $classes) {
    $scope = new AnalysisScope(new ReflectionClass(PostCommentAuthorsResource::class), Post::class);
    $scope->localVarBindings['post'] = new AstParser()->parseSource('<?php $this->resource;')[0]->expr;
    $engine = new TernaryClassBindingRecordingEngine($scope, 'post');

    (new TernaryHandler)->resolve(new AstParser()->parseSource('<?php '.$php.';')[0]->expr, $scope, $engine);

    expect($engine->classesPerArm)->toBe($classes)
        ->and($scope->varClassBindings)->toBe([]);
})->with([
    'a supertype' => ['$post instanceof \Illuminate\Database\Eloquent\Model ? "if" : "else"', ['if' => [Post::class], 'else' => null]],
    'a negated interface' => ['! $post instanceof \JsonSerializable ? "if" : "else"', ['else' => [Post::class], 'if' => null]],
    'a sibling chain' => ['$post instanceof \Workbench\App\Models\Post || $post instanceof \Workbench\App\Models\Comment ? "if" : "else"', ['if' => [Post::class], 'else' => null]],
]);
