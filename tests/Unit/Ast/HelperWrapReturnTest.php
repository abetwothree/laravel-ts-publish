<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\RequestRoleHelperEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumResourceUntypedHelperResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Enums\WeekDays;
use Workbench\App\Http\Resources\EnumWraps\WrapHelpersResource;
use Workbench\App\Http\Resources\PostStatusSourcesResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;
use Workbench\App\Support\Opaque;

/**
 * Helpers the workbench fixture leaves out, each wrapping one of the team's enums in an `EnumResource`.
 *
 * @mixin Team
 */
final class HelperWrapReturnProbeResource extends JsonResource
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
     * The team's latest status read through Opaque::value(), which the engine cannot type, or null without a team.
     */
    public function attributeStatusResource(): ?EnumResource
    {
        return $this->resource === null ? null : EnumResource::make(Opaque::value($this->resource->latest_status));
    }

    /**
     * An enum resource around the helper's own call.
     */
    public function selfWrappedResource(): EnumResource
    {
        return EnumResource::make($this->selfWrappedResource());
    }

    /**
     * The latest status of the team the container holds, once a guard proves it is one, else null.
     */
    public function guardedStatusResource(): ?EnumResource
    {
        $team = app('team');

        if (! $team instanceof Team) {
            return null;
        }

        return EnumResource::make($team->latest_status);
    }
}

/**
 * Resolve a helper call from a caller inside a relation closure whose every table binds a `status` the helper must not
 * see, with the caller's state before and after the call.
 *
 * @param  MethodCall|string  $call  a call, or the name of a method to call on `$this`
 * @param  class-string  $subject
 * @param  class-string  $model
 * @return array{0: array<string, mixed>, 1: AnalysisScope, 2: array{list<mixed>, list<mixed>}}
 */
function helperWrapReturn(
    MethodCall|string $call,
    string $subject = PostStatusSourcesResource::class,
    string $model = Post::class,
): array {
    $scope = new AnalysisScope(new ReflectionClass($subject), $model);
    $scope->localVarBindings['status'] = new PropertyFetch(new Variable('this'), 'title');
    $scope->closureParamExprBindings['status'] = new PropertyFetch(new Variable('this'), 'visibility');
    $scope->resolvingLocalVars['status'] = true;
    $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), $model, 'toArray', null, $scope);
    $callerState = fn (): array => [$scope->nameBindings(), $scope->resolvingLocalVars, $scope->declaringFileClass];
    $before = $callerState();

    $result = $engine->resolve(is_string($call) ? new MethodCall(new Variable('this'), $call) : $call);

    return [$result, $scope, [$before, $callerState()]];
}

it('reads a helper whose every return wraps an enum or is null', function (string $method, array $expected) {
    [$result, $scope, [$before, $after]] = helperWrapReturn($method);

    expect($result)->toBe($expected)
        ->and(array_keys($scope->localVarBindings))->toBe(['status'])
        ->and($scope->visitedSpreadMethods)->toBe([])
        ->and($after)->toBe($before);
})->with([
    'a declared EnumResource' => ['statusResource', ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
    'a ternary with null' => ['visibilityResource', ['type' => 'AsEnum<typeof Visibility> | null', 'optional' => false, 'embeddedEnumResourceFqcns' => [Visibility::class], 'wrappedEnumFqcns' => [Visibility::class]]],
]);

// G7: the helper's ternary of two wraps keeps each wrap as its own member, read off the set of enums it wraps.
it('reads a helper returning a ternary of two wraps as each wrap', function () {
    expect(helperWrapReturn('pick', WrapHelpersResource::class)[0])->toBe([
        'type' => 'AsEnum<typeof Status> | AsEnum<typeof Visibility> | null',
        'optional' => false,
        'embeddedEnumResourceFqcns' => [Status::class, Visibility::class],
        'wrappedEnumFqcns' => [Status::class, Visibility::class],
    ]);
});

// The helper's body reads the subject, so a variable's property is read on the subject's model, not the caller's
// relation.
it('reads a helper on the subject\'s own model, and the collection it wraps', function (string $method, array $expected) {
    expect(helperWrapReturn($method, HelperWrapReturnProbeResource::class, Team::class)[0])->toBe($expected);
})->with([
    'a property of its own local, under the caller\'s relation' => ['localStatusResource', ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
    'a collection' => ['statusHistoryResource', ['type' => 'AsEnum<typeof Status>[]', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
    'two enums, one per return' => ['statusOrWeekDaysResource', ['type' => 'AsEnum<typeof Status> | AsEnum<typeof WeekDays>[] | null', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class, WeekDays::class], 'wrappedEnumFqcns' => [Status::class, WeekDays::class]]],
]);

// The helper's body is seeded as any method body is: its guards narrow, and a Request parameter binds as a request.
it('reads a helper\'s body with its guards, and with its request parameter outside an API resource', function (MethodCall|string $call, string $subject, array $expected) {
    expect(helperWrapReturn($call, $subject, Team::class)[0])->toBe($expected);
})->with([
    'an early-exit instanceof guard' => [
        'guardedStatusResource',
        HelperWrapReturnProbeResource::class,
        ['type' => 'AsEnum<typeof Status> | null', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]],
    ],
    'a broadcast event\'s Request parameter' => [
        new MethodCall(new Variable('this'), 'roleOf', [new Arg(new FuncCall(new Name('request')))]),
        RequestRoleHelperEvent::class,
        ['type' => 'AsEnum<typeof Role> | never[]', 'optional' => false, 'embeddedEnumResourceFqcns' => [Role::class], 'wrappedEnumFqcns' => [Role::class]],
    ],
]);

// No filter() runs over an event's payload, so a wrap around a null role sends the `[]` resolve() returns.
it('publishes a broadcast event\'s helper that wraps what its request reads', function () {
    expect(resolve(AstEngine::class)->analyze(RequestRoleHelperEvent::class, 'broadcastWith')->properties)
        ->toBe([['name' => 'role', 'type' => 'AsEnum<typeof Role> | never[]', 'optional' => false, 'description' => '']]);
});

// Once the engine drops the wrap it cannot type, only a `null` that says nothing about the wrap could be left.
it('reads a helper whose every wrap the engine leaves untyped as unknown', function (string $method) {
    expect(helperWrapReturn($method, HelperWrapReturnProbeResource::class, Team::class)[0]['type'])->toBe('unknown');
})->with([
    'beside a null' => ['attributeStatusResource'],
    'around its own call' => ['selfWrappedResource'],
]);

// No value return shows the null an untyped helper returns when its body ends without a value; a declared return type
// throws there instead.
it('gives an untyped helper its null when its body can end without a value', function (string $method, array $expected) {
    expect(helperWrapReturn($method, EnumResourceUntypedHelperResource::class)[0])->toBe($expected);
})->with([
    'running off its end' => ['pinnedStatus', ['type' => 'AsEnum<typeof Status> | null', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
    'a bare return' => ['pinnedStatusOrNothing', ['type' => 'AsEnum<typeof Status> | null', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
    'a trailing throw' => ['pinnedStatusOrFail', ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
    'a comment after the last return' => ['notedStatus', ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
    'a bare return in a nested closure' => ['statusBesideCallback', ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
    'a declared return type' => ['declaredPinnedStatus', ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]],
]);

// Only the last statement is read, not whether each branch returns, so the arm is a sound superset here.
it('gives an untyped helper ending in an if/else that returns on both arms a null it never returns', function () {
    expect(helperWrapReturn('statusOrDraft', EnumResourceUntypedHelperResource::class)[0])
        ->toBe(['type' => 'AsEnum<typeof Status> | null', 'optional' => false, 'embeddedEnumResourceFqcns' => [Status::class], 'wrappedEnumFqcns' => [Status::class]]);
});
