<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia\InertiaWrapperHandler;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PropObjectResource;
use Illuminate\Http\Request;
use Inertia\AlwaysProp;
use Inertia\DeferProp;
use Inertia\MergeProp;
use Inertia\OnceProp;
use Inertia\OptionalProp;
use Inertia\Response as InertiaResponse;
use Inertia\ScrollProp;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * An engine that answers every request with one canned scalar result, or the one given, under the reading given.
 *
 * @param  array<string, mixed>|null  $answer
 */
function inertiaWrapperEngine(Reading $reading = Reading::Encoded, ?array $answer = null): ExpressionEngine
{
    return new class($reading, $answer) implements ExpressionEngine
    {
        /** @param  array<string, mixed>|null  $answer */
        public function __construct(private Reading $reading, private ?array $answer) {}

        public function resolve(Expr $expr): array
        {
            return $this->answer ?? ['type' => 'string', 'optional' => false, 'modelFqcn' => 'Some\Model'];
        }

        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        /** Reads every value as the test says. */
        public function reading(): Reading
        {
            return $this->reading;
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

function inertiaWrapperCall(string $class, string $method): StaticCall
{
    return new StaticCall(new Name($class), $method, [new Arg(new String_('x'))]);
}

function inertiaWrapperScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(stdClass::class));
}

// scroll and once are present on the initial response — a ScrollProp defers only when the caller
// chains ->defer(), and an OnceProp is dropped only once the client reports holding it.
it('resolves the wrapped value and keeps its channels', function (string $method) {
    expect((new InertiaWrapperHandler)->resolve(inertiaWrapperCall('Inertia', $method), inertiaWrapperScope(), inertiaWrapperEngine()))
        ->toBe(['type' => 'string', 'optional' => false, 'modelFqcn' => 'Some\Model', 'heldObject' => true, 'heldProp' => true, 'closedToDottedKeys' => 'string']);
})->with(['always', 'merge', 'deepMerge', 'scroll', 'once']);

it('marks an IgnoreFirstLoad wrapper optional', function (string $method) {
    expect((new InertiaWrapperHandler)->resolve(inertiaWrapperCall('Inertia', $method), inertiaWrapperScope(), inertiaWrapperEngine())['optional'])
        ->toBeTrue();
})->with(['defer', 'optional', 'lazy']);

// A `new` builds what its factory returns, so it reads as the factory; LazyProp, gone in v3, reads by position.
it('reads a new of each prop class as its factory', function (string $class, bool $optional) {
    $expr = new New_(new Name($class), [new Arg(new String_('x'))]);

    expect((new InertiaWrapperHandler)->resolve($expr, inertiaWrapperScope(), inertiaWrapperEngine(Reading::Inertia)))
        ->toBe(['type' => 'string', 'optional' => $optional, 'modelFqcn' => 'Some\Model', 'heldObject' => true, 'heldProp' => true, 'closedToDottedKeys' => 'string']);
})->with([
    [DeferProp::class, true],
    [OptionalProp::class, true],
    ['Inertia\\LazyProp', true],
    [AlwaysProp::class, false],
    [MergeProp::class, false],
    [ScrollProp::class, false],
    [OnceProp::class, false],
]);

it('declines a new of another class, of a class it cannot name, and with no value', function () {
    $scope = inertiaWrapperScope();
    $engine = inertiaWrapperEngine(Reading::Inertia);

    expect((new InertiaWrapperHandler)->resolve(new New_(new Name(InertiaResponse::class), [new Arg(new String_('x'))]), $scope, $engine))->toBeNull()
        ->and((new InertiaWrapperHandler)->resolve(new New_(new Variable('class'), [new Arg(new String_('x'))]), $scope, $engine))->toBeNull()
        ->and((new InertiaWrapperHandler)->resolve(new New_(new Name(OptionalProp::class)), $scope, $engine))->toBeNull();
});

// json_encode() sends a prop object as `{}`, which only the `unknown` the handlers after this one answer admits.
it('declines a new of a prop class where json_encode() sends it', function () {
    $expr = new New_(new Name(AlwaysProp::class), [new Arg(new String_('x'))]);

    expect((new InertiaWrapperHandler)->resolve($expr, inertiaWrapperScope(), inertiaWrapperEngine()))->toBeNull();
});

// An API resource's own type reads its values as json_encode() sends them, and it sends a prop object as `{}`.
it('leaves a new of a prop class in an API resource unknown', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

    expect(resourceProps(PropObjectResource::class))
        ->toBe(['id' => 'number', 'always' => 'unknown', 'optional' => 'unknown', 'merged' => 'unknown'])
        ->and(json_encode(sentAsJson(new PropObjectResource($post)->resolve(Request::create('/')))))
        ->toBe('{"id":'.$post->id.',"always":{},"optional":{},"merged":{}}');
});

// Inertia calls the prop's callable and sends a callable that call returns as `{}`, which `unknown` alone admits; the
// factory form keeps the type it has always read.
it('reads a new of a prop whose callable returns a callable as unknown', function () {
    $returnsCallable = ['type' => 'number | null', 'optional' => false, 'heldObject' => true, 'returnsCallable' => true];
    $engine = inertiaWrapperEngine(Reading::Inertia, $returnsCallable);
    $args = [new Arg(new ArrowFunction(['expr' => new Variable('f')]))];

    expect((new InertiaWrapperHandler)->resolve(new New_(new Name(OptionalProp::class), $args), inertiaWrapperScope(), $engine))
        ->toBe(['type' => 'unknown', 'optional' => true, 'heldObject' => true, 'heldProp' => true, 'closedToDottedKeys' => 'unknown'])
        ->and((new InertiaWrapperHandler)->resolve(new StaticCall(new Name('Inertia'), 'optional', $args), inertiaWrapperScope(), $engine))
        ->toBe(['type' => 'number | null', 'optional' => true, 'heldObject' => true, 'heldProp' => true, 'closedToDottedKeys' => 'number | null']);
});

// shareOnce takes the value second, so claiming it would type the key string. It shares the prop
// itself, so it is written as a statement and never reaches this handler as a prop expression.
it('declines shareOnce rather than typing its key argument', function () {
    $expr = new StaticCall(new Name('Inertia'), 'shareOnce', [
        new Arg(new String_('permissions')),
        new Arg(new ArrowFunction(['expr' => new String_('value')])),
    ]);

    expect((new InertiaWrapperHandler)->resolve($expr, inertiaWrapperScope(), inertiaWrapperEngine()))->toBeNull();
});

it('declines another Inertia method, another class, and a first-class callable', function () {
    $scope = inertiaWrapperScope();
    $engine = inertiaWrapperEngine();

    expect((new InertiaWrapperHandler)->resolve(inertiaWrapperCall('Inertia', 'render'), $scope, $engine))->toBeNull()
        ->and((new InertiaWrapperHandler)->resolve(inertiaWrapperCall('Cache', 'defer'), $scope, $engine))->toBeNull()
        ->and((new InertiaWrapperHandler)->resolve(new StaticCall(new Name('Inertia'), 'defer', [new VariadicPlaceholder]), $scope, $engine))->toBeNull()
        ->and((new InertiaWrapperHandler)->resolve(new StaticCall(new Name('Inertia'), 'defer'), $scope, $engine))->toBeNull()
        ->and((new InertiaWrapperHandler)->resolve(new StaticCall(new Variable('inertia'), 'defer'), $scope, $engine))->toBeNull();
});

// The shape a controller actually writes: the wrapper's argument is a closure over an Eloquent
// finder, so the wrapped type comes from the controller profile rather than a canned engine.
it('types a deferred closure over an Eloquent finder through the controller profile', function () {
    $expr = new StaticCall(new Name('Inertia'), 'defer', [new Arg(new ArrowFunction([
        'expr' => new StaticCall(new Name(Post::class), 'all'),
    ]))]);

    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(InertiaController::class), null, 'dashboard', reading: Reading::Inertia);

    expect($analyzer->resolve($expr))->toBe([
        'type' => 'Post[]',
        'optional' => true,
        'modelFqcn' => Post::class,
        'heldObject' => true,
        'heldProp' => true,
        'closedToDottedKeys' => 'Post[]',
    ]);
});

// The only realistic named form: the group first, the callback by name. Position 0 is then a string,
// and typing the prop from it would be confidently wrong.
it('reads the wrapped value by name when the group is written first', function () {
    $callback = new ArrowFunction(['expr' => new StaticCall(new Name(Post::class), 'all')]);
    $expr = new StaticCall(new Name('Inertia'), 'defer', [
        new Arg(new String_('sidebar'), name: new Identifier('group')),
        new Arg($callback, name: new Identifier('callback')),
    ]);
    $engine = new class($callback) implements ExpressionEngine
    {
        public function __construct(private Expr $only) {}

        public function resolve(Expr $expr): array
        {
            if ($expr !== $this->only) {
                throw new RuntimeException('resolved the group string instead of the callback');
            }

            return ['type' => 'Post[]', 'optional' => false];
        }

        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        /** Reads every value as `json_encode()` sends it. */
        public function reading(): Reading
        {
            return Reading::Encoded;
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };

    expect((new InertiaWrapperHandler)->resolve($expr, inertiaWrapperScope(), $engine))
        ->toBe(['type' => 'Post[]', 'optional' => true, 'heldObject' => true, 'heldProp' => true, 'closedToDottedKeys' => 'Post[]']);
});

// The constructor's own names map the arguments, so the callback is read wherever it is written.
it('reads a new prop\'s value by its constructor\'s parameter name', function () {
    $callback = new ArrowFunction(['expr' => new StaticCall(new Name(Post::class), 'all')]);
    $expr = new New_(new Name(DeferProp::class), [
        new Arg(new String_('sidebar'), name: new Identifier('group')),
        new Arg($callback, name: new Identifier('callback')),
    ]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(InertiaController::class), null, 'dashboard', reading: Reading::Inertia);

    expect((new InertiaWrapperHandler)->resolve($expr, inertiaWrapperScope(), $analyzer))
        ->toBe(['type' => 'Post[]', 'optional' => true, 'modelFqcn' => Post::class, 'heldObject' => true, 'heldProp' => true, 'closedToDottedKeys' => 'Post[]']);
});
