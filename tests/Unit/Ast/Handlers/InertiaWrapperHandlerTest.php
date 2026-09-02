<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\ControllerExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaWrapperHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Models\Post;

/** An engine that answers every request with one canned scalar result. */
function inertiaWrapperEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            return ['type' => 'string', 'optional' => false, 'modelFqcn' => 'Some\Model'];
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        public function returnArrayAnalysis(Array_ $array): MethodAnalysis
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
        ->toBe(['type' => 'string', 'optional' => false, 'modelFqcn' => 'Some\Model']);
})->with(['always', 'merge', 'deepMerge', 'scroll', 'once']);

it('marks an IgnoreFirstLoad wrapper optional', function (string $method) {
    expect((new InertiaWrapperHandler)->resolve(inertiaWrapperCall('Inertia', $method), inertiaWrapperScope(), inertiaWrapperEngine())['optional'])
        ->toBeTrue();
})->with(['defer', 'optional', 'lazy']);

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

    $analyzer = new ResourceAstAnalyzer(
        new ReflectionClass(InertiaController::class),
        null,
        'dashboard',
        ControllerExpressionHandlers::make(),
    );

    expect($analyzer->resolve($expr))->toBe([
        'type' => 'Post[]',
        'optional' => true,
        'modelFqcn' => Post::class,
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

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        public function returnArrayAnalysis(Array_ $array): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };

    expect((new InertiaWrapperHandler)->resolve($expr, inertiaWrapperScope(), $engine))
        ->toBe(['type' => 'Post[]', 'optional' => true]);
});
