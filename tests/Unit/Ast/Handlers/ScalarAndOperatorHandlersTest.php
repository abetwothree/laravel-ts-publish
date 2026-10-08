<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\BinaryOpHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CastHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConstFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ScalarHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast\Array_ as CastArray_;
use PhpParser\Node\Expr\Cast\Bool_ as CastBool;
use PhpParser\Node\Expr\Cast\Object_ as CastObject;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/**
 * A throwaway scope for tests that never inspect its contents.
 */
function handlerTestScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(stdClass::class));
}

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function throwingEngine(): ExpressionEngine
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
 * A spy engine recording the expression it was asked to resolve, returning a canned result.
 */
final class SpyExpressionEngine implements ExpressionEngine
{
    public ?Expr $receivedExpr = null;

    /** @param array<string, mixed> $result */
    public function __construct(private array $result) {}

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        $this->receivedExpr = $expr;

        return $this->result;
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

// FirstClassCallableHandler

it('resolves a first-class-callable call of each kind to the empty object json_encode() writes for a Closure', function (Expr $expr) {
    $result = new FirstClassCallableHandler()->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'Record<string, never>', 'optional' => false]);
})->with([
    '$this->when(...)' => fn (): Expr => new MethodCall(new Variable('this'), 'when', [new VariadicPlaceholder]),
    'strlen(...)' => fn (): Expr => new FuncCall(new Name('strlen'), [new VariadicPlaceholder]),
    'Str::upper(...)' => fn (): Expr => new StaticCall(new Name('Str'), 'upper', [new VariadicPlaceholder]),
]);

it('resolves a first-class callable as the call it stands for where the caller invokes every callable', function () {
    $expr = new MethodCall(new Variable('this'), 'helper', [new VariadicPlaceholder]);
    $engine = new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            return ['type' => $expr instanceof MethodCall && $expr->args === [] ? 'string' : 'wrong', 'optional' => false];
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            return null;
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            return new MethodAnalysis;
        }
    };

    expect(new FirstClassCallableHandler(invoked: true)->resolve($expr, handlerTestScope(), $engine))
        ->toBe(['type' => 'string', 'optional' => false]);
});

it('turns only a first-class callable into the zero-argument call it stands for', function () {
    $callable = new FuncCall(new Name('now'), [new VariadicPlaceholder], ['startLine' => 3]);
    $call = FirstClassCallableHandler::invokedCall($callable);
    $closure = new ArrowFunction(['expr' => new Int_(1)]);

    expect($call)->toBeInstanceOf(FuncCall::class)->not->toBe($callable)
        ->and($call instanceof FuncCall && $call->isFirstClassCallable())->toBeFalse()
        ->and($call->getStartLine())->toBe(3)
        ->and($callable->isFirstClassCallable())->toBeTrue()
        ->and(FirstClassCallableHandler::invokedCall($closure))->toBe($closure);
});

// R25: a key that holds a Closure sends `{}`, so its author almost certainly meant to call it.
it('warns of a first-class callable nothing calls, and never of one Laravel calls', function () {
    $strlen = new FuncCall(new Name('strlen'), [new VariadicPlaceholder], ['startLine' => 1]);
    $when = new AstParser()->parseSource('<?php $this->when($this->title, $this->helper(...));')[0]->expr;

    new FirstClassCallableHandler(invoked: true)
        ->resolve($strlen, handlerTestScope(), new SpyExpressionEngine(['type' => 'number', 'optional' => false]));
    new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($when);

    expect(AnalysisWarnings::all())->toBe([]);

    new FirstClassCallableHandler()->resolve($strlen, handlerTestScope(), throwingEngine());

    expect(AnalysisWarnings::all())->toBe([[
        'subject' => stdClass::class,
        'message' => 'A first-class callable on line 1 is a value nothing calls, so Laravel sends it as {}. Call the method, or pass the callable where Laravel calls it, such as when() or whenLoaded().',
    ]]);
});

it('declines an ordinary (non-first-class-callable) MethodCall', function () {
    $handler = new FirstClassCallableHandler;
    $expr = new MethodCall(new Variable('this'), 'when', []);

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBeNull();
});

// CastHandler

it('resolves a bool cast to boolean', function () {
    $handler = new CastHandler;
    $expr = new CastBool(new Variable('x'));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'boolean', 'optional' => false]);
});

it('resolves an array cast of an array literal by delegating to the engine for the inner array', function () {
    $handler = new CastHandler;
    $inner = new Array_([]);
    $expr = new CastArray_($inner);
    $engine = new SpyExpressionEngine(['type' => 'never[]', 'optional' => false]);

    $result = $handler->resolve($expr, handlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'never[]', 'optional' => false])
        ->and($engine->receivedExpr)->toBe($inner);
});

it('resolves an array cast of a non-array operand to unknown[] without calling the engine', function () {
    $handler = new CastHandler;
    $expr = new CastArray_(new Variable('x'));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'unknown[]', 'optional' => false]);
});

it('declines an object cast', function () {
    $handler = new CastHandler;
    $expr = new CastObject(new Variable('x'));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBeNull();
});

// ScalarHandler

it('resolves a string literal to string', function () {
    $handler = new ScalarHandler;
    $expr = new String_('x');

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('resolves an int literal to number', function () {
    $handler = new ScalarHandler;
    $expr = new Int_(1);

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('declines a MagicConst scalar (e.g. __LINE__)', function () {
    $handler = new ScalarHandler;
    $expr = new MagicConst\Line;

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBeNull();
});

// ConstFetchHandler

it('resolves the null constant fetch', function () {
    $handler = new ConstFetchHandler;
    $expr = new ConstFetch(new Name('null'));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'null', 'optional' => false]);
});

it('resolves true/false constant fetches to boolean', function () {
    $handler = new ConstFetchHandler;
    $scope = handlerTestScope();
    $engine = throwingEngine();

    expect($handler->resolve(new ConstFetch(new Name('true')), $scope, $engine))
        ->toBe(['type' => 'boolean', 'optional' => false])
        ->and($handler->resolve(new ConstFetch(new Name('false')), $scope, $engine))
        ->toBe(['type' => 'boolean', 'optional' => false]);
});

it('declines a user-defined constant fetch', function () {
    $handler = new ConstFetchHandler;
    $expr = new ConstFetch(new Name('PHP_EOL'));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBeNull();
});

// BinaryOpHandler

it('resolves arithmetic operators to number', function () {
    $handler = new BinaryOpHandler;
    $expr = new BinaryOp\Plus(new Int_(1), new Int_(2));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('resolves concat to string', function () {
    $handler = new BinaryOpHandler;
    $expr = new BinaryOp\Concat(new String_('a'), new String_('b'));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('resolves comparison, logical, not, instanceof, isset, and empty operators to boolean', function () {
    $handler = new BinaryOpHandler;
    $scope = handlerTestScope();
    $engine = throwingEngine();

    $nodes = [
        new BinaryOp\Identical(new Variable('a'), new Variable('b')),
        new BinaryOp\BooleanAnd(new Variable('a'), new Variable('b')),
        new BooleanNot(new Variable('a')),
        new Instanceof_(new Variable('a'), new Name('Foo')),
        new Isset_([new Variable('a')]),
        new Empty_(new Variable('a')),
    ];

    foreach ($nodes as $expr) {
        expect($handler->resolve($expr, $scope, $engine))->toBe(['type' => 'boolean', 'optional' => false]);
    }
});

it('resolves spaceship to number', function () {
    $handler = new BinaryOpHandler;
    $expr = new BinaryOp\Spaceship(new Variable('a'), new Variable('b'));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('resolves unary minus/plus to number', function () {
    $handler = new BinaryOpHandler;
    $expr = new UnaryMinus(new Variable('x'));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('declines Coalesce, which CoalesceHandler owns', function () {
    $handler = new BinaryOpHandler;
    $expr = new BinaryOp\Coalesce(new Variable('a'), new ConstFetch(new Name('null')));

    $result = $handler->resolve($expr, handlerTestScope(), throwingEngine());

    expect($result)->toBeNull();
});
