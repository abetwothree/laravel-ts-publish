<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use Workbench\App\Http\Resources\TeamSubscriberResource;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\User;

it('constructs with the given subject reflection and model class', function () {
    $reflection = new ReflectionClass(stdClass::class);

    $scope = new AnalysisScope($reflection, 'App\\Models\\Post');

    expect($scope->subjectReflection)->toBe($reflection)
        ->and($scope->modelClass)->toBe('App\\Models\\Post')
        ->and($scope->instanceOfWrappedClass)->toBeNull()
        ->and($scope->forwardsUndeclaredMembersTo)->toBeNull()
        ->and($scope->closureRelationModelClass)->toBeNull()
        ->and($scope->closureParamExprBindings)->toBe([])
        ->and($scope->varModelBindings)->toBe([])
        ->and($scope->varCollectionBindings)->toBe([])
        ->and($scope->localVarBindings)->toBe([])
        ->and($scope->resolvingLocalVars)->toBe([])
        ->and($scope->visitedSpreadMethods)->toBe([]);
});

it('defaults modelClass to null when omitted', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    expect($scope->modelClass)->toBeNull();
});

it('derives the forwarding target from a JsonResource subject with a backing model', function () {
    $scope = new AnalysisScope(new ReflectionClass(TeamSubscriberResource::class), SubscribedTeam::class);

    expect($scope->forwardsUndeclaredMembersTo)->toBe(SubscribedTeam::class);
});

it('derives no forwarding target without both a proxying subject and a backing model', function () {
    $nonResource = new AnalysisScope(new ReflectionClass(SubscribedTeam::class), SubscribedTeam::class);
    $resourceWithoutModel = new AnalysisScope(new ReflectionClass(TeamSubscriberResource::class));

    expect($nonResource->forwardsUndeclaredMembersTo)->toBeNull()
        ->and($resourceWithoutModel->forwardsUndeclaredMembersTo)->toBeNull();
});

/** A scope with `item`, `key` and `kept` bound in every name-keyed binding table. */
function analysisScopeWithEveryTableBound(): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $expr = new Variable('outer');

    foreach (['item', 'key', 'kept'] as $name) {
        $scope->closureParamExprBindings[$name] = $expr;
        $scope->varClassBindings[$name] = [User::class];
        $scope->varModelBindings[$name] = User::class;
        $scope->varCollectionBindings[$name] = ['type' => 'User[]', 'modelFqcn' => User::class];
        $scope->varValueBindings[$name] = ['type' => 'string', 'optional' => false];
        $scope->localVarBindings[$name] = $expr;
        $scope->requestVarNames[$name] = Request::class;
    }

    return $scope;
}

/**
 * The names each binding table holds, leaving out the claimed-closure marks.
 *
 * @return array<string, list<array-key>>
 */
function analysisScopeBoundNames(AnalysisScope $scope): array
{
    return array_map(array_keys(...), array_diff_key($scope->nameBindings(), ['claimedClosures' => true]));
}

it('claims every closure parameter out of every name-keyed binding table, and restores them from a capture', function () {
    $scope = analysisScopeWithEveryTableBound();
    $captured = $scope->nameBindings();
    $closure = new ArrowFunction([
        'params' => [new Param(new Variable('item')), new Param(new Variable('key'))],
        'expr' => new Variable('item'),
    ]);

    $scope->claimParameters($closure);

    expect(analysisScopeBoundNames($scope))->each->toBe(['kept'])
        ->and($scope->claimedClosures)->toBe([spl_object_id($closure) => true]);

    $scope->restoreNameBindings($captured);

    expect($scope->nameBindings())->toBe($captured)
        ->and($scope->claimedClosures)->toBe([]);
});

it('releases an unclaimed closure parameter and leaves a claimed closure parameter bound', function () {
    $unclaimed = analysisScopeWithEveryTableBound();
    $claimed = analysisScopeWithEveryTableBound();
    $closure = new ArrowFunction(['params' => [new Param(new Variable('item'))], 'expr' => new Variable('item')]);

    $unclaimed->releaseUnclaimedParameters($closure);
    $claimed->claimParameters($closure);
    $claimed->varModelBindings['item'] = User::class;
    $claimed->releaseUnclaimedParameters($closure);

    expect(analysisScopeBoundNames($unclaimed))->each->toBe(['key', 'kept'])
        ->and($claimed->varModelBindings)->toBe(['key' => User::class, 'kept' => User::class, 'item' => User::class]);
});
