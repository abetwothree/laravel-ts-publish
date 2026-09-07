<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SubjectProps;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function thisPropertyHandlerThrowingEngine(): ExpressionEngine
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
 * A stub engine resolving each distinct expression instance to its own canned result, keyed by
 * object identity — mirrors the convention used by the other handler test suites.
 */
final class ThisPropertyHandlerArmStubEngine implements ExpressionEngine
{
    /** @param list<array{0: Expr, 1: array<string, mixed>}> $arms */
    public function __construct(private array $arms) {}

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        foreach ($this->arms as [$candidate, $result]) {
            if ($candidate === $expr) {
                return $result;
            }
        }

        throw new RuntimeException('Unexpected expression passed to ThisPropertyHandlerArmStubEngine');
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

it('resolves $this->id against a model-backed scope to the column type', function () {
    $expr = new PropertyFetch(new Variable('this'), 'id');
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);

    $result = (new ThisPropertyHandler)->resolve($expr, $scope, thisPropertyHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('declines a property fetch not rooted at $this', function () {
    $expr = new PropertyFetch(new Variable('other'), 'id');
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);

    $result = (new ThisPropertyHandler)->resolve($expr, $scope, thisPropertyHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('extractPropertiesFromArray() resolves each keyed item through the engine, forcing $optional', function () {
    $nameValue = new Variable('nameExpr');

    $array = new Array_([
        new ArrayItem($nameValue, new String_('name')),
    ]);

    $engine = new ThisPropertyHandlerArmStubEngine([
        [$nameValue, ['type' => 'string', 'optional' => false]],
    ]);

    $analysis = (new ThisPropertyHandler)->extractPropertiesFromArray($array, $engine, optional: true);

    expect($analysis)->toBeInstanceOf(ResourceAnalysis::class)
        ->and($analysis->properties)->toBe([
            ['name' => 'name', 'type' => 'string', 'optional' => true, 'description' => ''],
        ]);
});

it('extractPropertiesFromArray() skips unkeyed items — it does not support spreads', function () {
    $array = new Array_([
        new ArrayItem(new Variable('spread'), null, unpack: true),
    ]);

    $analysis = (new ThisPropertyHandler)->extractPropertiesFromArray($array, thisPropertyHandlerThrowingEngine());

    expect($analysis->properties)->toBe([]);
});

it('resolves a model-less subject through its own declared properties and property chains', function () {
    $analysis = resolve(AstEngine::class)->analyzeMethod(SubjectProps::class, 'payload');

    $props = collect($analysis->properties)->keyBy('name');

    expect($props->keys()->all())->toBe(['team', 'tags', 'title', 'count'])
        ->and($props['team']['type'])->toBe('number')
        ->and($props['tags']['type'])->toBe('string[]')
        ->and($props['title']['type'])->toBe('string')
        ->and($props['count']['type'])->toBe('number');
});

it('routes a subject property typed as a model into the modelFqcns channel', function () {
    $analysis = resolve(AstEngine::class)->analyzeMethod(SubjectProps::class, 'subject');

    expect($analysis->properties)->toBe([
        ['name' => 'post', 'type' => 'Post', 'optional' => false, 'description' => ''],
    ])->and($analysis->modelFqcns)->toBe(['post' => Post::class]);
});

it('declines a subject-mode chain whose root property is not a model', function () {
    $analysis = resolve(AstEngine::class)->analyzeMethod(SubjectProps::class, 'unresolvableChain');

    expect($analysis->properties)->toBe([
        ['name' => 'nope', 'type' => 'unknown', 'optional' => false, 'description' => ''],
    ]);
});

// Subject mode is the model-less branch only: a model-backed scope must still miss on a property
// the subject declares but the model does not, or every resource's output could shift.
it('leaves a model-backed scope on the model, never on the subject own property', function () {
    $expr = new PropertyFetch(new Variable('this'), 'teamId');
    $scope = new AnalysisScope(new ReflectionClass(SubjectProps::class), Post::class);

    $result = (new ThisPropertyHandler)->resolve($expr, $scope, thisPropertyHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'unknown', 'optional' => false]);
});

// An accessor typed Attribute<Status|Priority, never> spells both enum names, and only the first
// fits directEnumFqcn — the rest reached no channel at all, so nothing could import PriorityType.
it('routes every enum FQCN of a multi-enum accessor, not only the first', function () {
    $analysis = resolve(AstEngine::class)->analyzeMethod(WarehouseResource::class);
    $property = collect($analysis->properties)->firstWhere('name', 'review_priority');

    expect($property['type'])->toBe('StatusType | PriorityType | null')
        ->and($analysis->inlineEnumFqcns['review_priority'] ?? null)->toBe([Status::class, Priority::class])
        ->and($analysis->directEnumFqcns)->not->toHaveKey('review_priority');
});
