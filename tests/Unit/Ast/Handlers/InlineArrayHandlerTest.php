<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\NestedResourceSpreadResource;
use Workbench\App\Models\User;

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved the
 * spread arm from scope bindings alone, without recursing into a sub-expression.
 */
function inlineArrayHandlerThrowingEngine(): ExpressionEngine
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
 * A stub engine whose returnArrayAnalysis() returns a canned analysis for the exact array passed
 * in, standing in for ResourceAstAnalyzer::analyzeReturnArray()'s real per-key resolution.
 */
final class InlineArrayHandlerReturnArrayStubEngine implements ExpressionEngine
{
    public function __construct(private Array_ $expectedArray, private ResourceAnalysis $analysis) {}

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
        if ($array !== $this->expectedArray) {
            throw new RuntimeException('Unexpected array passed to InlineArrayHandlerReturnArrayStubEngine');
        }

        return $this->analysis;
    }
}

it('resolves an inline array with a nested key and a model spread arm to an Omit<> intersection', function () {
    // Mirrors NestedResourceSpreadResource::$members_model_spread's shape — a sibling key plus
    // ...$member->toArray() — proving the moved analyzeInlineArray()/collectInlineArraySpreadArms()
    // pair still Omit<>'s the explicit key against the model arm without engine recursion.
    $array = new Array_([
        new ArrayItem(new Variable('placeholder'), new String_('note')),
        new ArrayItem(new MethodCall(new Variable('member'), 'toArray'), null, unpack: true),
    ]);

    $analysis = new ResourceAnalysis(properties: [
        ['name' => 'note', 'type' => 'string', 'optional' => false, 'description' => ''],
    ]);

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));
    $scope->varModelBindings['member'] = User::class;

    $engine = new InlineArrayHandlerReturnArrayStubEngine($array, $analysis);

    $result = (new InlineArrayHandler)->resolve($array, $scope, $engine);

    expect($result)->toBe([
        'type' => "Omit<User, 'note'> & { note: string }",
        'optional' => false,
        'embeddedModelFqcns' => [User::class],
    ]);
});

it('declines a non-array expression', function () {
    $expr = new Variable('foo');
    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));

    $result = (new InlineArrayHandler)->resolve($expr, $scope, inlineArrayHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('keeps both arms of a nested mixed EnumResource ternary that rendered the same string', function () {
    // Mirrors TeamStatusAuditResource::$audit's inner key: both arms read the same list-shaped
    // accessor, so the union merge collapsed them to one member before the enum rewrite ran. Only
    // the per-arm shape still says the [] belongs on both.
    config()->set('ts-publish.enums.use_tolki_package', true);

    $array = new Array_([
        new ArrayItem(new Variable('placeholder'), new String_('history')),
    ]);

    $analysis = new ResourceAnalysis(
        properties: [
            ['name' => 'history', 'type' => 'StatusType[]', 'optional' => false, 'description' => ''],
        ],
        enumResources: ['history' => Status::class],
        directEnumFqcns: ['history' => Status::class],
        enumResourceArmShapes: ['history' => ['wrapIsCollection' => true, 'directIsArray' => true]],
    );

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));

    $engine = new InlineArrayHandlerReturnArrayStubEngine($array, $analysis);

    $result = (new InlineArrayHandler)->resolve($array, $scope, $engine);

    expect($result)->toBe([
        'type' => '{ history: AsEnum<typeof Status>[] | StatusType[] }',
        'optional' => false,
        'embeddedEnumFqcns' => [Status::class],
        'embeddedEnumResourceFqcns' => [Status::class],
    ]);
});
