<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectHelperReturnResolver;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceUntypedHelperResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Enums\WeekDays;
use Workbench\App\Http\Resources\PostStatusSourcesResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * Helpers the workbench fixture leaves out, one per edge of the rule: each wraps one of the team's enums, or returns a
 * shape the rule leaves alone.
 *
 * @mixin Team
 */
final class SubjectHelperReturnProbeResource extends JsonResource
{
    /**
     * The team's latest status as an enum resource, read off a local holding the team.
     */
    public function localStatusResource(): EnumResource
    {
        $team = $this->resource;

        return EnumResource::make($team->latest_status);
    }

    /**
     * The team's status history as enum resources.
     */
    public function statusHistoryResource(): AnonymousResourceCollection
    {
        return EnumResource::collection($this->status_history);
    }

    /**
     * The team's latest status as an enum resource without a team, else its week days as enum resources.
     */
    public function statusOrWeekDaysResource(): JsonResource
    {
        if ($this->resource === null) {
            return EnumResource::make($this->latest_status);
        }

        return EnumResource::collection($this->week_days);
    }

    /**
     * The team's latest status read through getAttribute(), which the engine cannot type, or null without a team.
     */
    public function attributeStatusResource(): ?EnumResource
    {
        return $this->resource === null ? null : EnumResource::make($this->resource->getAttribute('latest_status'));
    }

    /**
     * An enum resource around the helper's own call.
     */
    public function selfWrappedResource(): EnumResource
    {
        return EnumResource::make($this->selfWrappedResource());
    }

    /**
     * Nothing to wrap.
     */
    public function noStatusResource(): ?EnumResource
    {
        return null;
    }

    /**
     * The team itself when set, which a short ternary returns, else its latest status as an enum resource.
     */
    public function fallbackStatusResource(): mixed
    {
        return $this->resource ?: EnumResource::make($this->latest_status);
    }

    /**
     * The team in a resource that wraps no enum.
     */
    public function teamResource(): JsonResource
    {
        return new JsonResource($this->resource);
    }
}

/**
 * An engine that fails the test if the resolver reads a helper's body, proving it declined on the returns' shape alone.
 */
function subjectHelperReturnThrowingEngine(): ExpressionEngine
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
 * Resolve a helper call from a caller inside a relation closure whose every table binds a `status` the helper must not
 * see, with the caller's state before and after the call.
 *
 * @param  MethodCall|string  $call  a call, or the name of a method to call on `$this`
 * @param  class-string  $subject
 * @param  class-string  $model
 * @return array{0: array<string, mixed>|null, 1: AnalysisScope, 2: array{list<mixed>, list<mixed>}}
 */
function subjectHelperReturn(
    MethodCall|string $call,
    string $subject = PostStatusSourcesResource::class,
    string $model = Post::class,
    ?ExpressionEngine $engine = null,
): array {
    $scope = new AnalysisScope(new ReflectionClass($subject), $model);
    $scope->localVarBindings['status'] = new PropertyFetch(new Variable('this'), 'title');
    $scope->closureParamExprBindings['status'] = new PropertyFetch(new Variable('this'), 'visibility');
    $scope->resolvingLocalVars['status'] = true;
    $scope->closureRelationModelClass = User::class;
    $engine ??= new ResourceAstAnalyzer(new ReflectionClass($subject), $model, 'toArray', null, $scope);
    $callerState = fn (): array => [$scope->nameBindings(), $scope->resolvingLocalVars, $scope->closureRelationModelClass, $scope->declaringFileClass];
    $before = $callerState();

    $result = resolve(SubjectHelperReturnResolver::class)->resolveEnumResourceReturn(
        is_string($call) ? new MethodCall(new Variable('this'), $call) : $call,
        $scope,
        $engine,
    );

    return [$result, $scope, [$before, $callerState()]];
}

it('reads a helper whose every return wraps an enum or is null', function (string $method, array $expected) {
    [$result, $scope, [$before, $after]] = subjectHelperReturn($method);

    expect($result)->toBe($expected)
        ->and(array_keys($scope->localVarBindings))->toBe(['status'])
        ->and($scope->visitedSpreadMethods)->toBe([])
        ->and($after)->toBe($before);
})->with([
    'a declared EnumResource' => ['statusResource', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'a ternary with null' => ['visibilityResource', ['type' => 'VisibilityType | null', 'optional' => false, 'enumFqcn' => Visibility::class]],
]);

it('declines a helper with a return that wraps no enum', function (string $method) {
    [$result, , [$before, $after]] = subjectHelperReturn($method, engine: subjectHelperReturnThrowingEngine());

    expect($result)->toBeNull()
        ->and($after)->toBe($before);
})->with([
    'an enum read' => ['currentStatus'],
    'an array' => ['toArray'],
    'a JsonResource helper' => ['whenLoaded'],
    'an undeclared method' => ['noSuchMethod'],
]);

// The helper's body reads the subject, so a variable's property is read on the subject's model, not the caller's
// relation.
it('reads a helper on the subject\'s own model, and the collection it wraps', function (string $method, array $expected) {
    expect(subjectHelperReturn($method, SubjectHelperReturnProbeResource::class, Team::class)[0])->toBe($expected);
})->with([
    'a property of its own local, under the caller\'s relation' => ['localStatusResource', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'a collection' => ['statusHistoryResource', ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class]],
    'two enums, one per return' => ['statusOrWeekDaysResource', ['type' => 'StatusType | WeekDaysType[] | null', 'optional' => false, 'multiEnumResourceFqcns' => [Status::class, WeekDays::class]]],
]);

it('never reads a call or a body the rule leaves alone', function (MethodCall|string $call) {
    expect(subjectHelperReturn($call, SubjectHelperReturnProbeResource::class, Team::class, subjectHelperReturnThrowingEngine())[0])
        ->toBeNull();
})->with([
    'a helper that returns only null' => ['noStatusResource'],
    'a short ternary, which returns its condition' => ['fallbackStatusResource'],
    'a resource that wraps no enum' => ['teamResource'],
    'a receiver other than $this' => [new MethodCall(new Variable('team'), 'localStatusResource')],
    'a first-class callable' => [new MethodCall(new Variable('this'), 'localStatusResource', [new VariadicPlaceholder])],
]);

// Once the engine drops the wrap it cannot type, only a `null` that says nothing about the wrap could be left.
it('declines a helper whose every wrap the engine leaves untyped', function (string $method) {
    expect(subjectHelperReturn($method, SubjectHelperReturnProbeResource::class, Team::class)[0])->toBeNull();
})->with([
    'beside a null' => ['attributeStatusResource'],
    'around its own call' => ['selfWrappedResource'],
]);

// No value return shows the null an untyped helper returns when its body ends without a value; a declared return type
// throws there instead.
it('gives an untyped helper its null when its body can end without a value', function (string $method, array $expected) {
    expect(subjectHelperReturn($method, EnumResourceUntypedHelperResource::class)[0])->toBe($expected);
})->with([
    'running off its end' => ['pinnedStatus', ['type' => 'StatusType | null', 'optional' => false, 'enumFqcn' => Status::class]],
    'a bare return' => ['pinnedStatusOrNothing', ['type' => 'StatusType | null', 'optional' => false, 'enumFqcn' => Status::class]],
    'a trailing throw' => ['pinnedStatusOrFail', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'a comment after the last return' => ['notedStatus', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'a bare return in a nested closure' => ['statusBesideCallback', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'a declared return type' => ['declaredPinnedStatus', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
]);

// Only the last statement is read, not whether each branch returns, so the arm is a sound superset here.
it('gives an untyped helper ending in an if/else that returns on both arms a null it never returns', function () {
    expect(subjectHelperReturn('statusOrDraft', EnumResourceUntypedHelperResource::class)[0])
        ->toBe(['type' => 'StatusType | null', 'optional' => false, 'enumFqcn' => Status::class]);
});

// The helper wraps `$this->visibility`, a read that keeps imports, so only the resolver's own check leaves it unknown.
it('declines in a scope that carries no import', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostStatusSourcesResource::class), Post::class);
    $scope->carriesImports = false;
    $engine = new ResourceAstAnalyzer(new ReflectionClass(PostStatusSourcesResource::class), Post::class, 'toArray', null, $scope);

    expect(resolve(SubjectHelperReturnResolver::class)->resolveEnumResourceReturn(new MethodCall(new Variable('this'), 'visibilityResource'), $scope, $engine))
        ->toBeNull();
});
