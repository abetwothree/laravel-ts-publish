<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\TeamSubscriberResource;
use Workbench\App\Models\Post;
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

it('copies every entry a variable held in a capture onto a parameter, in every name-keyed table', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    $scope->copyBindings('item', 'param', $captured);

    expect(array_map(array_keys(...), array_diff_key($scope->nameBindings(), ['claimedClosures' => true])))->each->toBe(['param'])
        ->and($scope->closureParamExprBindings['param'])->toBe($captured['closureParamExprBindings']['item'])
        ->and($scope->varClassBindings['param'])->toBe([User::class])
        ->and($scope->varModelBindings['param'])->toBe(User::class)
        ->and($scope->varCollectionBindings['param'])->toBe(['type' => 'User[]', 'modelFqcn' => User::class])
        ->and($scope->varValueBindings['param'])->toBe(['type' => 'string', 'optional' => false])
        ->and($scope->localVarBindings['param'])->toBe($captured['localVarBindings']['item'])
        ->and($scope->requestVarNames['param'])->toBe(Request::class);
});

it('binds each parameter a call passes nothing to what it holds: a default, or an empty list', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $engine = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class, 'toArray', null, $scope);
    $closure = new ArrowFunction([
        'params' => [
            new Param(new Variable('passed'), default: new Int_(1)),
            new Param(new Variable('required')),
            new Param(new Variable('none'), default: new ConstFetch(new Name('null'))),
            new Param(new Variable('rest'), variadic: true),
        ],
        'expr' => new Variable('passed'),
    ]);

    $scope->bindUnpassedParameters($closure, 1, $engine);

    expect($scope->varValueBindings)->toBe([
        'none' => ['type' => 'null', 'optional' => false],
        'rest' => ['type' => 'never[]', 'optional' => false],
    ]);
});

it('binds a default to the value PHP evaluates it to, and one the evaluator cannot read only where the engine reads it right', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $engine = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class, 'toArray', null, $scope);
    $closure = new AstParser()->parseSource('<?php fn ($list = [1, [2]], $record = ["a" => new Foo],
        $recordList = ["a" => [new Foo]], $intLike = ["0" => new Foo], $numeric = ["1.5" => new Foo], $reads = $list,
        $new = new Foo, $date = new \\Illuminate\\Support\\Carbon) => 1;')[0]->expr;

    $scope->bindUnpassedParameters($closure, 0, $engine);

    expect(array_map(fn (array $held): string => $held['type'], $scope->varValueBindings))->toBe([
        'list' => '(number | number[])[]',
        'record' => '{ a: unknown }',
        'date' => 'string',
    ]);
});
