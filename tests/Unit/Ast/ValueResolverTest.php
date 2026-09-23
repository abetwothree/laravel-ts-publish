<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\ExpressionDispatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConstFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ScalarHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\ClassConstantResource;
use Workbench\App\Services\ChannelDefaults;

/**
 * A throwaway scope carrying ClassConstantResource, so self/static/parent constants resolve too.
 */
function valueResolverTestScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ClassConstantResource::class));
}

/**
 * An engine that fails the test if ValueResolver calls back into it, proving a decline (over-limit
 * array, ::class, enum case) happened before any leaf recursion.
 */
function valueResolverThrowingEngine(): ExpressionEngine
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
 * A real engine backed by the production scalar/const-fetch handlers — the same ones a scalar
 * constant's BuilderFactory::val() leaf would dispatch through inside the full analyzer — so
 * ValueResolver's recursion is exercised for real instead of through a canned stub.
 */
function valueResolverLeafEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        private ExpressionDispatcher $dispatcher;

        public function __construct()
        {
            $this->dispatcher = new ExpressionDispatcher([new ScalarHandler, new ConstFetchHandler]);
        }

        public function resolve(Expr $expr): array
        {
            return $this->dispatcher->dispatch($expr, valueResolverTestScope(), $this) ?? ValueResult::unknown();
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
 * Parse one PHP expression, names resolved as the analyzer resolves them.
 */
function valueResolverParse(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/**
 * Build a `Fqcn::CONST_NAME` ClassConstFetch node.
 *
 * @param  class-string  $fqcn
 */
function valueResolverClassConstFetch(string $fqcn, string $constName): ClassConstFetch
{
    return new ClassConstFetch(new Name($fqcn), new Identifier($constName));
}

// resolveClassConstant() — scalar, list, and record constants

it('resolves a scalar constant to its literal type', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'MAX_RETRIES');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope(), valueResolverLeafEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('resolves self:: and parent:: constants via the scope subject reflection', function () {
    $resolver = new ValueResolver;
    $scope = valueResolverTestScope();

    $selfResult = $resolver->resolveClassConstant(
        valueResolverClassConstFetch('self', 'SCHEMA_VERSION'), $scope, valueResolverLeafEngine(),
    );
    $parentResult = $resolver->resolveClassConstant(
        valueResolverClassConstFetch('parent', 'BASE_VERSION'), $scope, valueResolverLeafEngine(),
    );

    expect($selfResult)->toBe(['type' => 'number', 'optional' => false])
        ->and($parentResult)->toBe(['type' => 'number', 'optional' => false]);
});

it('resolves a plain-list constant to an element-union array', function () {
    $resolver = new ValueResolver;
    $scope = valueResolverTestScope();

    $agreeing = $resolver->resolveClassConstant(
        valueResolverClassConstFetch(ChannelDefaults::class, 'CHANNEL_TAGS'), $scope, valueResolverLeafEngine(),
    );
    $disagreeing = $resolver->resolveClassConstant(
        valueResolverClassConstFetch(ChannelDefaults::class, 'MIXED_TAGS'), $scope, valueResolverLeafEngine(),
    );

    expect($agreeing)->toBe(['type' => 'string[]', 'optional' => false])
        ->and($disagreeing)->toBe(['type' => '(string | number)[]', 'optional' => false]);
});

it('resolves a keyed record constant to an inline object, including nested records', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'DEFAULT_CHANNELS');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope(), valueResolverLeafEngine());

    expect($result)->toBe([
        'type' => '{ in_app: { status_updates: boolean; comments: boolean }; '
            .'digest: { status_updates: boolean; comments: boolean } }',
        'optional' => false,
    ]);
});

it('propagates an embedded enum case FQCN out of a nested list/record constant', function () {
    $resolver = new ValueResolver;
    $scope = valueResolverTestScope();

    $listResult = $resolver->resolveClassConstant(
        valueResolverClassConstFetch(ChannelDefaults::class, 'STATUS_LIST'), $scope, valueResolverLeafEngine(),
    );
    $recordResult = $resolver->resolveClassConstant(
        valueResolverClassConstFetch(ChannelDefaults::class, 'STATUS_MAP'), $scope, valueResolverLeafEngine(),
    );

    expect($listResult['embeddedEnumFqcns'] ?? null)->not->toBeNull()
        ->and($recordResult['embeddedEnumFqcns'] ?? null)->not->toBeNull();
});

// resolveClassConstant() — bails to null (never recurses) past the array limits

it('declines an over-element-count constant array without recursing into it', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'OVER_ELEMENT_LIMIT');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope(), valueResolverThrowingEngine());

    expect($result)->toBeNull();
});

it('declines an over-depth constant array without recursing into it', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'OVER_DEPTH_LIMIT');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope(), valueResolverThrowingEngine());

    expect($result)->toBeNull();
});

// resolveClassConstant() — the exclusions that keep EnumResource::make()/toResource() paths intact

it('resolves Foo::class to a plain string, not a decline', function () {
    // This is the "New behaviour" pinned in ResourceAstAnalyzerTest's resource_marker case: the four
    // risky call sites never reach this branch (they read resolveClassConstArgument() directly).
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'class');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope(), valueResolverThrowingEngine());

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('declines a bare enum-case fetch, leaving it to resolveEnumFromPropertyArg()', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(Status::class, 'Draft');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope(), valueResolverThrowingEngine());

    expect($result)->toBeNull();
});

it('declines a constant whose lazily-evaluated initializer throws, instead of aborting', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'BROKEN');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope(), valueResolverThrowingEngine());

    expect($result)->toBeNull();
});

// resolveClassConstArgument() — the public helper analyzeToResourceCall()/analyzeToResourceCollectionCall() reuse

it('resolves a Foo::class argument to its FQCN', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'class');

    expect($resolver->resolveClassConstArgument($expr))->toBe(ChannelDefaults::class);
});

it('declines an enum-case fetch as a ::class argument', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(Status::class, 'Draft');

    expect($resolver->resolveClassConstArgument($expr))->toBeNull();
});

it('declines a plain (non-::class) constant fetch as a ::class argument', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'MAX_RETRIES');

    expect($resolver->resolveClassConstArgument($expr))->toBeNull();
});

// evaluateConstantExpression() and resolveConstantValue() — a parameter default, evaluated as PHP evaluates it

it('evaluates a constant expression as PHP does, reading class constants and enum cases through the subject', function (string $php, mixed $value) {
    expect(new ValueResolver()->evaluateConstantExpression(valueResolverParse($php), valueResolverTestScope()))->toBe($value);
})->with([
    'protected self:: and parent:: constants' => ['[self::SCHEMA_VERSION, parent::BASE_VERSION]', [2, 1]],
    'self::class' => ['self::class', ClassConstantResource::class],
    'an enum case, a spread and an operator' => ['[\\'.Status::class.'::Draft, ...[1 + 1]]', [Status::Draft, 2]],
    'a constant holding an enum case' => ['self::DEFAULT_STATUS', Status::Draft],
    'a ternary' => ['self::SCHEMA_VERSION > 1 ? [1] : "x"', [1]],
]);

it('throws for what a constant expression reads that the evaluator cannot', function (string $php) {
    expect(fn () => new ValueResolver()->evaluateConstantExpression(valueResolverParse($php), valueResolverTestScope()))
        ->toThrow(ConstExprEvaluationException::class);
})->with([
    'new' => ['new Foo'],
    'a global constant' => ['[PHP_INT_SIZE]'],
    'a variable' => ['$other'],
    'a missing class constant' => ['self::MISSING'],
]);

it('types an evaluated value as a constant, declining an int-keyed record a resource re-indexes into a list', function (mixed $value, ?string $type) {
    expect(new ValueResolver()->resolveConstantValue($value, valueResolverLeafEngine())['type'] ?? null)->toBe($type);
})->with([
    'a list' => [[1, 'a'], '(number | string)[]'],
    'int keys that form a list' => [[0 => 'a', 1 => 'b'], 'string[]'],
    'a record with an int key beside a string one' => [['a' => 1, 2], '{ a: number }'],
    'int keys that do not form a list' => [[1 => 'a'], null],
    'the same, nested in a record' => [['a' => [2 => 'x']], null],
]);
