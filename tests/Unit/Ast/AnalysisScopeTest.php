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
        ->and($scope->varDocBindings)->toBe([])
        ->and($scope->declaringFileClass)->toBe($reflection)
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
        $scope->varGuardBindings[$name] = ['classes' => [User::class], 'after' => 0];
        $scope->varDocBindings[$name] = [['type' => 'User', 'context' => $scope->declaringFileClass, 'after' => 0, 'before' => null, 'expr' => new Variable('outer')]];
        $scope->varModelBindings[$name] = User::class;
        $scope->varDeclaredBindings[$name] = [User::class];
        $scope->varCollectionBindings[$name] = ['type' => 'User[]', 'modelFqcn' => User::class];
        $scope->varValueBindings[$name] = ['type' => 'string', 'optional' => false];
        $scope->localVarBindings[$name] = $expr;
        $scope->requestVarNames[$name] = Request::class;
        $scope->nonNullReads[$name] = [['read' => new Variable($name), 'after' => null, 'before' => null]];
        $scope->bindingEnds[$name] = 0;
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

// A proof spells the read it proves, `$item` here, so it never holds for a read of another name, and a binding's end
// is an offset in the caller's body, which the parameter's body never reaches.
it('copies every entry a variable held in a capture onto a parameter, in every name-keyed table but the proofs and ends', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    $scope->copyBindings(new Variable('item'), 'param', $captured);

    expect(array_map(array_keys(...), array_diff_key($scope->nameBindings(), ['claimedClosures' => true, 'nonNullReads' => true, 'bindingEnds' => true])))->each->toBe(['param'])
        ->and($scope->nonNullReads)->toBe([])
        ->and($scope->bindingEnds)->toBe([])
        ->and($scope->closureParamExprBindings['param'])->toBe($captured['closureParamExprBindings']['item'])
        ->and($scope->varClassBindings['param'])->toBe([User::class])
        ->and($scope->varGuardBindings['param'])->toBe(['classes' => [User::class], 'after' => 0])
        ->and($scope->varDocBindings['param'])->toBe($captured['varDocBindings']['item'])
        ->and($scope->varModelBindings['param'])->toBe(User::class)
        ->and($scope->varDeclaredBindings['param'])->toBe([User::class])
        ->and($scope->varCollectionBindings['param'])->toBe(['type' => 'User[]', 'modelFqcn' => User::class])
        ->and($scope->varValueBindings['param'])->toBe(['type' => 'string', 'optional' => false])
        ->and($scope->localVarBindings['param'])->toBe($captured['localVarBindings']['item'])
        ->and($scope->requestVarNames['param'])->toBe(Request::class);
});

it('holds a binding for a read up to its end, and always for a name with no end or a variable with no name', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $scope->bindingEnds['post'] = 10;
    $at = fn (string $name, int $offset): Variable => new Variable($name, ['startFilePos' => $offset]);

    expect($scope->holdsBinding($at('post', 10)))->toBeTrue()
        ->and($scope->holdsBinding($at('post', 11)))->toBeFalse()
        ->and($scope->holdsBinding(new Variable('post')))->toBeTrue()
        ->and($scope->holdsBinding($at('other', 11)))->toBeTrue()
        ->and($scope->holdsBinding(new Variable(new Variable('post'), ['startFilePos' => 11])))->toBeTrue();
});

it('binds a name a parameter or a seeded binding holds, never one only the body\'s own tables hold', function () {
    $scope = analysisScopeWithEveryTableBound();
    $bodyOnly = new AnalysisScope(new ReflectionClass(stdClass::class));
    $bodyOnly->localVarBindings['item'] = new Variable('outer');
    $bodyOnly->varDocBindings['item'] = $scope->varDocBindings['item'];
    $bodyOnly->varGuardBindings['item'] = $scope->varGuardBindings['item'];
    $bodyOnly->nonNullReads['item'] = $scope->nonNullReads['item'];
    $tables = ['varModelBindings', 'varDeclaredBindings', 'varCollectionBindings', 'varClassBindings', 'varValueBindings', 'closureParamExprBindings', 'requestVarNames'];

    foreach ($tables as $table) {
        $one = new AnalysisScope(new ReflectionClass(stdClass::class));
        $one->{$table}['item'] = $scope->{$table}['item'];

        expect($one->bindsName('item'))->toBeTrue("{$table} binds the name");
    }

    expect($bodyOnly->bindsName('item'))->toBeFalse()
        ->and($scope->bindsName('missing'))->toBeFalse();
});

// Past its end the variable holds only what the body assigned, so the parameter must not inherit the old class.
it('copies only the body\'s own bindings of a variable passed past its end, and ends the parameter\'s before any read', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    $scope->copyBindings(new Variable('item', ['startFilePos' => 1]), 'param', $captured);

    expect(array_keys(array_filter(array_diff_key($scope->nameBindings(), ['claimedClosures' => true]))))
        ->toBe(['varGuardBindings', 'varDocBindings', 'localVarBindings', 'bindingEnds'])
        ->and($scope->holdsBinding(new Variable('param', ['startFilePos' => 0])))->toBeFalse();
});

// The tables a body write can end are the ones a variable passed past its end stops passing on: a new table must join
// both lists or neither.
it('ends a name\'s binding in exactly the tables a variable passed past its end no longer passes on', function () {
    $every = analysisScopeWithEveryTableBound();

    foreach (array_keys(array_diff_key(AnalysisScope::NO_NAME_BINDINGS, ['claimedClosures' => true, 'bindingEnds' => true])) as $table) {
        $one = new AnalysisScope(new ReflectionClass(stdClass::class));
        $one->{$table}['item'] = $every->{$table}['item'];
        $captured = [...$one->nameBindings(), 'bindingEnds' => ['item' => 0]];
        $within = new AnalysisScope(new ReflectionClass(stdClass::class));
        $past = new AnalysisScope(new ReflectionClass(stdClass::class));

        $within->copyBindings(new Variable('item', ['startFilePos' => 0]), 'param', $captured);
        $past->copyBindings(new Variable('item', ['startFilePos' => 1]), 'param', $captured);

        expect($one->bindsName('item'))->toBe(isset($within->{$table}['param']) && ! isset($past->{$table}['param']), $table);
    }
});

it('captures every public array table but the re-entrancy guards as a name binding', function () {
    $tables = array_map(
        fn (ReflectionProperty $property): string => $property->getName(),
        array_filter(
            new ReflectionClass(AnalysisScope::class)->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property): bool => ! $property->isStatic() && (string) $property->getType() === 'array',
        ),
    );
    $guards = ['resolvingLocalVars', 'visitedSpreadMethods'];
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    expect(array_values(array_diff($tables, $guards)))->toEqualCanonicalizing(array_keys($scope->nameBindings()))
        ->and(array_keys(AnalysisScope::NO_NAME_BINDINGS))->toBe(array_keys($scope->nameBindings()));
});

it('binds any name only while a parameter table holds one', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $scope->localVarBindings['item'] = new Variable('outer');
    $bound = new AnalysisScope(new ReflectionClass(stdClass::class));
    $bound->requestVarNames['item'] = Request::class;

    expect($scope->bindsAnyName())->toBeFalse()
        ->and($bound->bindsAnyName())->toBeTrue();
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

    // The engine types a keyed literal by its keys, so a numeric one binds too; `"0"` alone is the list key 0.
    expect(array_map(fn (array $held): string => $held['type'], $scope->varValueBindings))->toBe([
        'list' => '(number | number[])[]',
        'record' => '{ a: unknown }',
        'intLike' => 'unknown[]',
        'numeric' => '{ "1.5": unknown }',
        'date' => 'string',
    ]);
});
