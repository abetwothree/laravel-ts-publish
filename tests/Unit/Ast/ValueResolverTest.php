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
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ArrayJsonCarbon;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ArrayJsonDate;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\StringJsonArrayable;
use Carbon\Carbon as CarbonCarbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Stringable;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\ClassConstantResource;
use Workbench\App\Models\User;
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
    'a global constant' => ['[PHP_INT_SIZE, PHP_EOL]', [PHP_INT_SIZE, PHP_EOL]],
    'an enum case ->name and ->value' => ['[\\'.Status::class.'::Published->name, \\'.Status::class.'::Published->value]', ['Published', 1]],
    'the magic constants' => ['[__LINE__, __CLASS__, __FUNCTION__, __METHOD__]', [1, ClassConstantResource::class, '{closure}', '{closure}']],
    'the path, namespace, trait and property magic constants' => ['[__DIR__, __FILE__, __NAMESPACE__, __TRAIT__, __PROPERTY__]', [
        dirname((string) new ReflectionClass(ClassConstantResource::class)->getFileName()),
        (string) new ReflectionClass(ClassConstantResource::class)->getFileName(),
        'Workbench\\App\\Http\\Resources',
        '',
        '',
    ]],
]);

it('throws for what a constant expression reads that the evaluator cannot', function (string $php) {
    expect(fn () => new ValueResolver()->evaluateConstantExpression(valueResolverParse($php), valueResolverTestScope()))
        ->toThrow(ConstExprEvaluationException::class);
})->with([
    'new' => ['new Foo'],
    'an undefined constant' => ['[NO_SUCH_CONSTANT_ANYWHERE]'],
    'a division by zero' => ['1 / 0'],
    'a constant whose initializer throws' => ['\\'.ChannelDefaults::class.'::BROKEN'],
    'a property of an enum case other than name or value' => ['\\'.Status::class.'::Draft->label'],
    'a variable' => ['$other'],
    'a missing class constant' => ['self::MISSING'],
]);

it('types an evaluated value as a constant, declining a numeric-keyed record a resource re-indexes into a list', function (mixed $value, ?string $type) {
    expect(new ValueResolver()->resolveConstantValue($value, valueResolverLeafEngine())['type'] ?? null)->toBe($type);
})->with([
    'a list' => [[1, 'a'], '(number | string)[]'],
    'int keys that form a list' => [[0 => 'a', 1 => 'b'], 'string[]'],
    'a record with an int key beside a string one' => [['a' => 1, 2], '{ a: number }'],
    'int keys that do not form a list' => [[1 => 'a'], null],
    'the same, nested in a record' => [['a' => [2 => 'x']], null],
    'float-string keys, which is_numeric() accepts' => [['1.5' => 'x', '-0' => 'y'], null],
    'an int key beside a float-string one' => [[1 => 'a', '1.5' => 'b'], null],
    'a numeric key beside a word key' => [['1.5' => 1, 'a' => 2], '{ "1.5": number; a: number }'],
]);

it('types a new default as string only when the class publishes and encodes as one', function (string $class, ?string $type) {
    $new = new New_(new Name($class));

    expect(new ValueResolver()->resolveStringSerializedNew($new)['type'] ?? null)->toBe($type);
})->with([
    'a Carbon date' => [Carbon::class, 'string'],
    'a DateTime, published as string but written by json_encode() as an object' => [DateTime::class, null],
    'a model' => [User::class, null],
    'a class that is not a string' => [stdClass::class, null],
    'a __toString() class json_encode() writes as an object' => [HtmlString::class, null],
    'a Stringable, which json_encode() writes as a string' => [Stringable::class, 'string'],
    'a DateTime whose jsonSerialize() returns an array' => [ArrayJsonDate::class, null],
    'a Carbon date whose jsonSerialize() override returns an array' => [ArrayJsonCarbon::class, null],
    'an Arrayable whose jsonSerialize() returns a string, which json_encode() prefers' => [StringJsonArrayable::class, 'string'],
]);

// timestamps_as_date publishes a Carbon attribute as Date, but json_encode() still writes a Carbon value as a string.
it('types a Carbon new default as string under timestamps_as_date', function (string $class) {
    config()->set('ts-publish.timestamps_as_date', true);

    expect(new ValueResolver()->resolveStringSerializedNew(new New_(new Name($class)))['type'] ?? null)->toBe('string');
})->with([
    'Illuminate\\Support\\Carbon' => [Carbon::class],
    'Carbon\\Carbon' => [CarbonCarbon::class],
    'Carbon\\CarbonImmutable' => [CarbonImmutable::class],
]);
