<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PageItemCollection;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Return_;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\TeamSubscriberResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

it('constructs with the given subject reflection and model class', function () {
    $reflection = new ReflectionClass(stdClass::class);

    $scope = new AnalysisScope($reflection, 'App\\Models\\Post');

    expect($scope->subjectReflection)->toBe($reflection)
        ->and($scope->modelClass)->toBe('App\\Models\\Post')
        ->and($scope->instanceOfWrappedClass)->toBeNull()
        ->and($scope->forwardsUndeclaredMembersTo)->toBeNull()
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
        $scope->bindingGaps[$name] = [[5, 9, null, false, false]];
        $scope->keyBuiltLocals[$name] = [new Return_(new Variable('outer'))];
        $scope->passedValues[$name] = $expr;
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

// A proof spells the read it proves, `$item` here, so it never holds for a read of another name; a binding's end is an
// offset in the caller's body, which the parameter's body never reaches, and a key-built local walks the caller's body.
it('copies every entry a variable held in a capture onto a parameter, in every name-keyed table but the proofs, ends, gaps and key-built locals', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    $scope->copyBindings(new Variable('item'), 'param', $captured);

    expect(array_map(array_keys(...), array_diff_key($scope->nameBindings(), ['claimedClosures' => true, 'nonNullReads' => true, 'bindingEnds' => true, 'bindingGaps' => true, 'keyBuiltLocals' => true])))->each->toBe(['param'])
        ->and($scope->nonNullReads)->toBe([])
        ->and($scope->bindingEnds)->toBe([])
        ->and($scope->bindingGaps)->toBe([])
        ->and($scope->keyBuiltLocals)->toBe([])
        ->and($scope->closureParamExprBindings['param'])->toBe($captured['closureParamExprBindings']['item'])
        ->and($scope->varClassBindings['param'])->toBe([User::class])
        ->and($scope->varGuardBindings['param'])->toBe($captured['varGuardBindings']['item'])
        ->and($scope->varDocBindings['param'])->toBe($captured['varDocBindings']['item'])
        ->and($scope->varModelBindings['param'])->toBe(User::class)
        ->and($scope->varDeclaredBindings['param'])->toBe([User::class])
        ->and($scope->varCollectionBindings['param'])->toBe(['type' => 'User[]', 'modelFqcn' => User::class])
        ->and($scope->varValueBindings['param'])->toBe(['type' => 'string', 'optional' => false])
        ->and($scope->passedValues['param'])->toBe($captured['passedValues']['item'])
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
    $scope->bindingGaps['post'] = [[10, 20, null, false, false]];
    $at = fn (int $offset): Variable => new Variable('post', ['startFilePos' => $offset]);

    expect($scope->holdsBinding($at(10)))->toBeTrue()
        ->and($scope->holdsBinding($at(11)))->toBeFalse()
        ->and($scope->holdsBinding($at(20)))->toBeFalse()
        ->and($scope->holdsBinding($at(21)))->toBeTrue();
});

it('finds the gap a read falls in, the one whose write came latest', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $first = [10, 30, [new Variable('first')], false, false];
    $second = [20, 30, [new Variable('second')], true, false];
    $third = [40, 50, null, false, false];
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

// The proofs on `$this->resource` sit under a key no variable spells, and their offsets are the body's that set them.
it('keeps the proofs on $this->resource from every parameter, and sets them aside for a subject method\'s body', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $proof = [['classes' => [SubscribedTeam::class], 'after' => 0, 'before' => 99]];
    $scope->varGuardBindings = [AnalysisScope::PROXY_GUARDS => $proof, 'item' => $proof];
    $captured = $scope->nameBindings();

    $scope->claimParameters(new ArrowFunction(['params' => [new Param(new Variable('item'))], 'expr' => new Variable('item')]));
    $scope->copyBindings(new Variable('item', ['startFilePos' => 5]), 'param', $captured);
    $claimed = array_keys($scope->varGuardBindings);
    $inside = $scope->within(
        fn (AnalysisScope $entered) => $entered->enterSubjectMethod('helper', $scope->declaringFileClass, []),
        fn (): array => $scope->varGuardBindings,
    );

    expect($claimed)->toBe([AnalysisScope::PROXY_GUARDS, 'param'])
        ->and($inside)->toBe([])
        ->and($scope->varGuardBindings[AnalysisScope::PROXY_GUARDS])->toBe($proof);
});

it('lifts one gap for a read, and puts it back', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $lifted = [10, 30, null, true, false];
    $kept = [40, 50, null, false, false];
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

// The old binding may still hold where it is passed, so the parameter holds it and the writes the gap carries alike.
it('passes a variable inside a gap its old binding may hold past as that binding joined with the gap\'s writes', function () {
    $captured = analysisScopeWithEveryTableBound()->nameBindings();
    $written = new Variable('written');
    $captured['bindingEnds'] = [];
    $captured['bindingGaps']['item'] = [[5, 9, [$written], true, true]];
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    $scope->copyBindings(new Variable('item', ['startFilePos' => 6]), 'param', $captured);

    expect($scope->varModelBindings)->toBe(['param' => User::class])
        ->and($scope->varValueBindings)->toBe(['param' => ['type' => 'string', 'optional' => false]])
        ->and($scope->bindingEnds)->toBe([])
        ->and($scope->bindingGaps)->toBe(['param' => [[PHP_INT_MIN, PHP_INT_MAX, [$written], true, true]]])
        ->and($scope->gapAt(new Variable('param', ['startFilePos' => 0])))->toBe([PHP_INT_MIN, PHP_INT_MAX, [$written], true, true]);
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
        ->toBe(['varGuardBindings', 'varDocBindings', 'localVarBindings', 'byReferenceForms', 'bindingEnds', 'passedValues'])
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

it('captures every public array table but the re-entrancy guards and the narrowed `$this` reads as a name binding', function () {
    $tables = array_map(
        fn (ReflectionProperty $property): string => $property->getName(),
        array_filter(
            new ReflectionClass(AnalysisScope::class)->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property): bool => ! $property->isStatic() && (string) $property->getType() === 'array',
        ),
    );
    $notNameKeyed = ['resolvingLocalVars', 'readingLocals', 'visitedSpreadMethods', 'narrowedReads'];
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    expect(array_values(array_diff($tables, $notNameKeyed)))->toEqualCanonicalizing(array_keys($scope->nameBindings()))
        ->and(array_keys(AnalysisScope::NO_NAME_BINDINGS))->toBe(array_keys($scope->nameBindings()));
});

/**
 * The scope fields a builder sets once, which within() leaves alone.
 *
 * @return list<string>
 */
function analysisScopeSetOnceFields(): array
{
    return ['subjectReflection', 'instanceOfWrappedClass', 'carriesImports', 'reindexesNumericKeys', 'collectedElement'];
}

/**
 * The bool fields within() puts back, one dataset row each; a placeholder row where there are none, so the test runs.
 *
 * @return list<string>
 */
function analysisScopeRestoredBools(): array
{
    $bools = array_values(array_map(
        fn (ReflectionProperty $field): string => $field->getName(),
        array_filter(
            new ReflectionClass(AnalysisScope::class)->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $field): bool => ! $field->isStatic() && (string) $field->getType() === 'bool'
                && ! in_array($field->getName(), analysisScopeSetOnceFields(), true),
        ),
    ));

    return $bools === [] ? [''] : $bools;
}

/**
 * A value for a scope field, from its name and the phase, so a field each phase sets reads as changed and a field
 * put back from another's value differs. A bool holds one of two values, so only $odd starts apart from the others.
 */
function analysisScopeFieldValue(ReflectionProperty $field, string $phase, string $odd): mixed
{
    $name = $field->getName();

    return match ((string) $field->getType()) {
        'array', '?array' => [$name.$phase => true],
        '?string' => "{$name}:{$phase}",
        'bool' => ($phase === 'before') === ($name === $odd),
        'ReflectionClass' => new ReflectionClass($phase === 'before' ? User::class : Post::class),
        default => throw new LogicException("No value for {$name}'s type."),
    };
}

// Every field but those a builder sets once is given a value, and $enter changes each, so a field added to the scope
// fails here until within() puts it back or it joins the fields set once.
it('within() restores every name table and ambient field after a read, a throw included', function (string $throws, string $odd) {
    $setOnce = analysisScopeSetOnceFields();
    $ambient = [
        'provenProxy', 'modelClass', 'forwardsUndeclaredMembersTo', 'declaringFileClass', 'resolvingLocalVars',
        'readingLocals', 'visitedSpreadMethods', 'narrowedReads', 'appliedProxyProof', 'filtersNestedResources',
        'mapResultArrayed',
    ];
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $fields = array_values(array_filter(
        new ReflectionClass(AnalysisScope::class)->getProperties(ReflectionProperty::IS_PUBLIC),
        fn (ReflectionProperty $field): bool => ! $field->isStatic() && ! in_array($field->getName(), $setOnce, true),
    ));
    $names = array_map(fn (ReflectionProperty $field): string => $field->getName(), $fields);

    foreach ($fields as $field) {
        $field->setValue($scope, analysisScopeFieldValue($field, 'before', $odd));
    }

    $before = get_object_vars($scope);
    $seen = [];
    $read = null;

    try {
        $read = $scope->within(
            function (AnalysisScope $entered) use ($fields, &$seen, $throws, $odd): void {
                foreach ($fields as $field) {
                    $field->setValue($entered, analysisScopeFieldValue($field, 'entered', $odd));
                }

                if ($throws === 'enter') {
                    $seen = get_object_vars($entered);

                    throw new RuntimeException('The enter failed.');
                }
            },
            function () use ($scope, &$seen, $throws): string {
                $seen = get_object_vars($scope);

                return $throws === 'read' ? throw new RuntimeException('The read failed.') : 'read';
            },
        );
    } catch (RuntimeException) {
    }

    expect($names)->toEqualCanonicalizing([...array_keys(AnalysisScope::NO_NAME_BINDINGS), ...$ambient])
        ->and(array_keys(array_filter($seen, fn (mixed $value, string $name): bool => $value !== $before[$name], ARRAY_FILTER_USE_BOTH)))
        ->toEqualCanonicalizing($names)
        ->and(get_object_vars($scope))->toBe($before)
        ->and($read)->toBe($throws === 'none' ? 'read' : null);
})->with([
    'a read that returns' => ['none'],
    'a read that throws' => ['read'],
    'an enter that throws' => ['enter'],
])->with(analysisScopeRestoredBools());

// within() puts back each field enterSubjectMethod() set aside, the body's own writes included.
it('restores every field a subject method read sets aside', function () {
    $scope = analysisScopeWithEveryTableBound();
    $scope->resolvingLocalVars = ['item' => true];
    $declaring = $scope->declaringFileClass;
    $before = $scope->nameBindings();
    $proofs = ['this' => [['read' => new Variable('this'), 'after' => null, 'before' => null]]];
    $inside = [];

    $scope->within(
        fn (AnalysisScope $entered) => $entered->enterSubjectMethod('helper', new ReflectionClass(Post::class), $proofs),
        function () use ($scope, &$inside): void {
            $inside = [
                $scope->nameBindings(),
                $scope->resolvingLocalVars,
                $scope->declaringFileClass->getName(),
                $scope->visitedSpreadMethods,
            ];

            $scope->localVarBindings['inner'] = new Variable('body');
            $scope->bindingEnds['inner'] = 3;
            $scope->resolvingLocalVars['inner'] = true;
            $scope->declaringFileClass = new ReflectionClass(User::class);
        },
    );

    expect($inside)->toBe([[...AnalysisScope::NO_NAME_BINDINGS, 'nonNullReads' => $proofs], [], Post::class, ['helper' => true]])
        ->and($scope->nameBindings())->toBe($before)
        ->and($scope->resolvingLocalVars)->toBe(['item' => true])
        ->and($scope->declaringFileClass)->toBe($declaring)
        ->and($scope->visitedSpreadMethods)->toBe([]);
});

// A write replaces what the caller's test saw on that path and every path through it, and only those.
it('keeps the caller\'s instanceof proofs on $this for a subject method\'s body but on a path the body writes whole', function (array $rewritten, bool $keepsProxy, array $kept) {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $scope->provenProxy = [SubscribedTeam::class];
    $scope->narrowedReads = [[['author'], ReceiverType::of(User::class)], [['author', 'team'], ReceiverType::of(Team::class)]];
    $before = $scope->narrowedReads;

    $inside = $scope->within(
        fn (AnalysisScope $entered) => $entered->enterSubjectMethod('helper', $scope->declaringFileClass, [], $rewritten),
        fn (): array => [$scope->provenProxy, array_column($scope->narrowedReads, 0)],
    );

    expect($inside)->toBe([$keepsProxy ? [SubscribedTeam::class] : null, $kept])
        ->and($scope->provenProxy)->toBe([SubscribedTeam::class])
        ->and($scope->narrowedReads)->toBe($before);
})->with([
    'a body that writes nothing' => [[], true, [['author'], ['author', 'team']]],
    'an unrelated $this->marker' => [[['marker']], true, [['author'], ['author', 'team']]],
    'a member of $this->resource' => [[['resource', 'title']], true, [['author'], ['author', 'team']]],
    'the inner path' => [[['author', 'team']], true, [['author']]],
    'the outer path, and every path through it' => [[['author']], true, []],
    '$this->resource, which every undeclared $this->prop reads through' => [[['resource']], false, []],
]);

it('takes one local by its syntax as the whole list does, leaving out one a by-reference call keeps in some form', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));
    $bound = new Int_(1);
    $scope->localVarBindings = ['kept' => $bound, 'sorted' => new Int_(2)];
    $scope->byReferenceForms['sorted'] = [['effect' => ByReferenceEffect::KeepsList, 'values' => [], 'path' => [], 'callback' => null, 'after' => PHP_INT_MIN, 'before' => null, 'sole' => false]];

    expect($scope->literalLocalBinding('kept'))->toBe($bound)
        ->and($scope->literalLocalBinding('sorted'))->toBeNull()
        ->and($scope->literalLocalBinding('missing'))->toBeNull()
        ->and($scope->literalLocalBindings())->toBe(['kept' => $bound]);
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
