<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PageItemCollection;
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

it('rebases a model scope onto an API resource over the model, which forwards to it, and keeps the body\'s file', function () {
    $model = new ReflectionClass(Post::class);
    $resource = new ReflectionClass(PostResource::class);
    $scope = new AnalysisScope($model, Post::class);

    $scope->rebase($resource, Post::class);

    expect($scope->subjectReflection)->toBe($resource)
        ->and($scope->modelClass)->toBe(Post::class)
        ->and($scope->forwardsUndeclaredMembersTo)->toBe(Post::class)
        ->and($scope->declaringFileClass)->toBe($model);
});

it('rebases a resource scope onto a resource collection, which forwards nothing: its items are the proxy', function () {
    $collection = new ReflectionClass(PageItemCollection::class);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);

    $scope->rebase($collection, Post::class);

    expect($scope->subjectReflection)->toBe($collection)
        ->and($scope->modelClass)->toBe(Post::class)
        ->and($scope->forwardsUndeclaredMembersTo)->toBeNull();
});

/** A scope with `item`, `key` and `kept` bound in every name-keyed binding table. */
function analysisScopeWithEveryTableBound(): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $expr = new Variable('outer');

    foreach (['item', 'key', 'kept'] as $name) {
        $scope->closureParamExprBindings[$name] = $expr;
        $scope->varClassBindings[$name] = [User::class];
        $scope->varGuardBindings[$name] = [['classes' => [User::class], 'after' => PHP_INT_MIN, 'before' => null]];
        $scope->varDocBindings[$name] = [['type' => 'User', 'context' => $scope->declaringFileClass, 'after' => 0, 'before' => null, 'expr' => new Variable('outer')]];
        $scope->varModelBindings[$name] = User::class;
        $scope->varDeclaredBindings[$name] = [User::class];
        $scope->varCollectionBindings[$name] = ['type' => 'User[]', 'modelFqcn' => User::class];
        $scope->varValueBindings[$name] = ['type' => 'string', 'optional' => false];
        $scope->localVarBindings[$name] = $expr;
        $scope->byReferenceForms[$name] = [['effect' => ByReferenceEffect::KeepsList, 'values' => [], 'path' => [], 'callback' => null, 'after' => PHP_INT_MIN, 'before' => null, 'sole' => false]];
        $scope->requestVarNames[$name] = Request::class;
        $scope->nonNullReads[$name] = [['read' => new Variable($name), 'after' => null, 'before' => null]];
        $scope->bindingEnds[$name] = 0;
        $scope->bindingGaps[$name] = [[5, 9, null, false]];
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
it('copies every entry a variable held in a capture onto a parameter, in every name-keyed table but the proofs, ends and gaps', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    $scope->copyBindings(new Variable('item'), 'param', $captured);

    expect(array_map(array_keys(...), array_diff_key($scope->nameBindings(), ['claimedClosures' => true, 'nonNullReads' => true, 'bindingEnds' => true, 'bindingGaps' => true])))->each->toBe(['param'])
        ->and($scope->nonNullReads)->toBe([])
        ->and($scope->bindingEnds)->toBe([])
        ->and($scope->bindingGaps)->toBe([])
        ->and($scope->closureParamExprBindings['param'])->toBe($captured['closureParamExprBindings']['item'])
        ->and($scope->varClassBindings['param'])->toBe([User::class])
        ->and($scope->varGuardBindings['param'])->toBe($captured['varGuardBindings']['item'])
        ->and($scope->varDocBindings['param'])->toBe($captured['varDocBindings']['item'])
        ->and($scope->varModelBindings['param'])->toBe(User::class)
        ->and($scope->varDeclaredBindings['param'])->toBe([User::class])
        ->and($scope->varCollectionBindings['param'])->toBe(['type' => 'User[]', 'modelFqcn' => User::class])
        ->and($scope->varValueBindings['param'])->toBe(['type' => 'string', 'optional' => false])
        ->and($scope->localVarBindings['param'])->toBe($captured['localVarBindings']['item'])
        ->and($scope->byReferenceForms['param'])->toBe($captured['byReferenceForms']['item'])
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

it('holds no binding inside a gap, and holds it again past the gap', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $scope->bindingGaps['post'] = [[10, 20, null, false]];
    $at = fn (int $offset): Variable => new Variable('post', ['startFilePos' => $offset]);

    expect($scope->holdsBinding($at(10)))->toBeTrue()
        ->and($scope->holdsBinding($at(11)))->toBeFalse()
        ->and($scope->holdsBinding($at(20)))->toBeFalse()
        ->and($scope->holdsBinding($at(21)))->toBeTrue();
});

it('finds the gap a read falls in, the one whose write came latest', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $first = [10, 30, [new Variable('first')], false];
    $second = [20, 30, [new Variable('second')], true];
    $third = [40, 50, null, false];
    $scope->bindingGaps['post'] = [$first, $second, $third];
    $at = fn (int $offset): Variable => new Variable('post', ['startFilePos' => $offset]);

    expect($scope->gapAt($at(15)))->toBe($first)
        ->and($scope->gapAt($at(25)))->toBe($second)
        ->and($scope->gapAt($at(45)))->toBe($third)
        ->and($scope->gapAt($at(35)))->toBeNull();
});

it('finds the guard a read falls in, the one that starts latest, or the one bound last where two start together', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $outer = ['classes' => [Post::class], 'after' => 10, 'before' => null];
    $inner = ['classes' => [User::class], 'after' => 20, 'before' => 30];
    $sameStart = ['classes' => [SubscribedTeam::class], 'after' => 20, 'before' => 30];
    $scope->varGuardBindings['post'] = [$outer, $inner];
    $at = fn (string $name, int $offset): Variable => new Variable($name, ['startFilePos' => $offset]);

    expect($scope->guardAt($at('post', 10)))->toBeNull()
        ->and($scope->guardAt($at('post', 15)))->toBe($outer)
        ->and($scope->guardAt($at('post', 25)))->toBe($inner)
        ->and($scope->guardAt($at('post', 30)))->toBe($inner)
        ->and($scope->guardAt($at('post', 31)))->toBe($outer)
        ->and($scope->guardAt($at('other', 25)))->toBeNull()
        ->and($scope->guardAt(new Variable(new Variable('post'), ['startFilePos' => 25])))->toBeNull();

    $scope->varGuardBindings['post'][] = $sameStart;

    expect($scope->guardAt($at('post', 25)))->toBe($sameStart);
});

// The parameter holds the value the call passed, so the guard in force at the call holds for each of its reads.
it('passes a parameter the guard in force where its variable is passed, for every read of the parameter', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $captured['varGuardBindings']['item'] = [
        ['classes' => [Post::class], 'after' => 10, 'before' => null],
        ['classes' => [SubscribedTeam::class], 'after' => 20, 'before' => 30],
    ];
    $passedAt = function (int $offset) use ($captured): array {
        $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
        $scope->copyBindings(new Variable('item', ['startFilePos' => $offset]), 'param', $captured);

        return $scope->varGuardBindings;
    };

    expect($passedAt(5))->toBe([])
        ->and($passedAt(25))->toBe(['param' => [['classes' => [SubscribedTeam::class], 'after' => PHP_INT_MIN, 'before' => null]]])
        ->and($passedAt(35))->toBe(['param' => [['classes' => [Post::class], 'after' => PHP_INT_MIN, 'before' => null]]]);
});

it('lifts one gap for a read, and puts it back', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $lifted = [10, 30, null, true];
    $kept = [40, 50, null, false];
    $scope->bindingGaps['post'] = [$lifted, $kept];

    $during = $scope->beforeGap('post', $lifted, fn (): array => $scope->bindingGaps);

    expect($during)->toBe(['post' => [$kept]])
        ->and($scope->bindingGaps)->toBe(['post' => [$lifted, $kept]])
        ->and($scope->beforeGap('post', $kept, fn (): bool => $scope->holdsBinding(new Variable('post', ['startFilePos' => 45]))))
        ->toBeTrue();
});

it('passes a variable inside a gap on as one past its end', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $captured['bindingEnds'] = [];
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    $scope->copyBindings(new Variable('item', ['startFilePos' => 6]), 'param', $captured);

    expect($scope->varModelBindings)->toBe([])
        ->and($scope->holdsBinding(new Variable('param', ['startFilePos' => 0])))->toBeFalse();
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

it('binds a class name from every parameter table but the plain values', function () {
    $scope = analysisScopeWithEveryTableBound();
    $bodyOnly = new AnalysisScope(new ReflectionClass(stdClass::class));
    $bodyOnly->localVarBindings['item'] = new Variable('outer');
    $tables = ['varModelBindings', 'varDeclaredBindings', 'varCollectionBindings', 'varClassBindings', 'varValueBindings', 'closureParamExprBindings', 'requestVarNames'];

    foreach ($tables as $table) {
        $one = new AnalysisScope(new ReflectionClass(stdClass::class));
        $one->{$table}['item'] = $scope->{$table}['item'];

        expect($one->bindsClassName('item'))->toBe($table !== 'varValueBindings', $table);
    }

    expect($bodyOnly->bindsClassName('item'))->toBeFalse()
        ->and($scope->bindsClassName('missing'))->toBeFalse();
});

// Past its end the variable holds only what the body assigned, so the parameter must not inherit the old class.
it('copies only the body\'s own bindings of a variable passed past its end, and ends the parameter\'s before any read', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    $scope->copyBindings(new Variable('item', ['startFilePos' => 1]), 'param', $captured);

    expect(array_keys(array_filter(array_diff_key($scope->nameBindings(), ['claimedClosures' => true]))))
        ->toBe(['varGuardBindings', 'varDocBindings', 'localVarBindings', 'byReferenceForms', 'bindingEnds'])
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

// leaveSubjectMethod() restores by hand each field enterSubjectMethod() set aside, so this round trip guards it.
it('restores every field a subject method read sets aside, the relation closure\'s model included', function () {
    $scope = analysisScopeWithEveryTableBound();
    $scope->resolvingLocalVars = ['item' => true];
    $scope->closureRelationModelClass = User::class;
    $declaring = $scope->declaringFileClass;
    $before = $scope->nameBindings();
    $proofs = ['this' => [['read' => new Variable('this'), 'after' => null, 'before' => null]]];

    $caller = $scope->enterSubjectMethod('helper', new ReflectionClass(Post::class), $proofs);

    expect($scope->nameBindings())->toBe([...AnalysisScope::NO_NAME_BINDINGS, 'nonNullReads' => $proofs])
        ->and($scope->resolvingLocalVars)->toBe([])
        ->and($scope->closureRelationModelClass)->toBeNull()
        ->and($scope->declaringFileClass->getName())->toBe(Post::class)
        ->and($scope->visitedSpreadMethods)->toBe(['helper' => true]);

    $scope->localVarBindings['inner'] = new Variable('body');
    $scope->bindingEnds['inner'] = 3;
    $scope->resolvingLocalVars['inner'] = true;
    $scope->closureRelationModelClass = Post::class;
    $scope->declaringFileClass = new ReflectionClass(User::class);

    $scope->leaveSubjectMethod('helper', $caller);

    expect($scope->nameBindings())->toBe($before)
        ->and($scope->resolvingLocalVars)->toBe(['item' => true])
        ->and($scope->closureRelationModelClass)->toBe(User::class)
        ->and($scope->declaringFileClass)->toBe($declaring)
        ->and($scope->visitedSpreadMethods)->toBe([]);
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
