<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;

/**
 * `$this->whenLoaded(...)` mapped against JsonResource::whenLoaded($relationship, $value, $default).
 *
 * @param  list<Arg>  $args
 */
function whenLoadedArguments(array $args): CallArguments
{
    return CallArguments::for(
        new MethodCall(new Variable('this'), 'whenLoaded', $args),
        new ReflectionMethod(JsonResource::class, 'whenLoaded'),
    );
}

/**
 * `config(...)` mapped against config($key, $default).
 *
 * @param  list<Arg>  $args
 */
function configArguments(array $args): CallArguments
{
    return CallArguments::for(new FuncCall(new Name('config'), $args), new ReflectionFunction('config'));
}

it('maps positional arguments to their declared names', function () {
    $relationship = new Arg(new String_('profile'));
    $value = new Arg(new ArrowFunction(['expr' => new Variable('profile')]));

    $args = whenLoadedArguments([$relationship, $value]);

    expect($args->at(0))->toBe($relationship)
        ->and($args->named('relationship'))->toBe($relationship)
        ->and($args->at(1))->toBe($value)
        ->and($args->named('value'))->toBe($value)
        ->and($args->at(2))->toBeNull()
        ->and($args->named('default'))->toBeNull()
        ->and($args->positionOf('default'))->toBe(2)
        ->and($args->passedCount())->toBe(2)
        ->and($args->hasUnpack())->toBeFalse()
        ->and($args->isEmpty())->toBeFalse();
});

it('maps named arguments written in declared order to their positions', function () {
    $relationship = new Arg(new String_('profile'), name: new Identifier('relationship'));
    $value = new Arg(new ArrowFunction(['expr' => new Variable('profile')]), name: new Identifier('value'));
    $default = new Arg(new ConstFetch(new Name('null')), name: new Identifier('default'));

    $args = whenLoadedArguments([$relationship, $value, $default]);

    expect($args->at(0))->toBe($relationship)
        ->and($args->at(1))->toBe($value)
        ->and($args->at(2))->toBe($default)
        ->and($args->named('default'))->toBe($default)
        ->and($args->passedCount())->toBe(3);
});

// Laravel's func_num_args() sees `whenLoaded('rel', default: [])` as three arguments even though
// `value` was never written, so the count must say 3 while the skipped slot stays empty.
it('counts a named argument that skips a middle parameter as passing every earlier position', function () {
    $relationship = new Arg(new String_('profile'), name: new Identifier('relationship'));
    $default = new Arg(new Array_([]), name: new Identifier('default'));

    $args = whenLoadedArguments([$relationship, $default]);

    expect($args->at(0))->toBe($relationship)
        ->and($args->at(1))->toBeNull()
        ->and($args->named('value'))->toBeNull()
        ->and($args->at(2))->toBe($default)
        ->and($args->passedCount())->toBe(3);
});

it('mixes positional arguments with a trailing named one', function () {
    $relationship = new Arg(new String_('profile'));
    $default = new Arg(new ConstFetch(new Name('null')), name: new Identifier('default'));

    $args = whenLoadedArguments([$relationship, $default]);

    expect($args->named('relationship'))->toBe($relationship)
        ->and($args->named('default'))->toBe($default)
        ->and($args->at(1))->toBeNull()
        ->and($args->passedCount())->toBe(3);
});

it('raises passedCount() to a named argument\'s position plus one, and no further', function () {
    $default = new Arg(new String_('fallback'), name: new Identifier('default'));

    $args = configArguments([$default]);

    expect($args->named('key'))->toBeNull()
        ->and($args->named('default'))->toBe($default)
        ->and($args->passedCount())->toBe(2)
        ->and(configArguments([new Arg(new String_('app.name'))])->passedCount())->toBe(1);
});

it('reports a spread argument without counting it', function () {
    $value = new Arg(new String_('profile'));
    $spread = new Arg(new Array_([]), unpack: true);

    $args = whenLoadedArguments([$value, $spread]);

    expect($args->hasUnpack())->toBeTrue()
        ->and($args->at(0))->toBe($value)
        ->and($args->at(1))->toBeNull()
        ->and($args->passedCount())->toBe(1)
        ->and($args->isEmpty())->toBeFalse();
});

it('keeps an undeclared name reachable by that name only, outside the count', function () {
    $bogus = new Arg(new Int_(1), name: new Identifier('bogus'));

    $args = configArguments([$bogus]);

    expect($args->named('bogus'))->toBe($bogus)
        ->and($args->named('nope'))->toBeNull()
        ->and($args->positionOf('bogus'))->toBeNull()
        ->and($args->at(0))->toBeNull()
        ->and($args->passedCount())->toBe(0);
});

// JsonResource::make(...$parameters) forwards to __construct($resource); a caller wanting the
// constructor's names reflects that instead (Task 37). A variadic parameter has no declared
// position, so make() itself is reachable only by at(N), never by the name `parameters`.
it('stops declared parameter names before a variadic tail and treats a first-class callable as empty', function () {
    $first = new Arg(new Variable('a'));
    $second = new Arg(new Variable('b'));
    $make = new ReflectionMethod(JsonResource::class, 'make');

    $args = CallArguments::for(
        new StaticCall(new Name(JsonResource::class), 'make', [$first, $second]),
        $make,
    );
    $callable = CallArguments::for(
        new StaticCall(new Name(JsonResource::class), 'make', [new VariadicPlaceholder]),
        $make,
    );

    expect($args->at(0))->toBe($first)
        ->and($args->positionOf('parameters'))->toBeNull()
        ->and($args->named('parameters'))->toBeNull()
        ->and($args->at(1))->toBe($second)
        ->and($args->passedCount())->toBe(2)
        ->and($callable->isEmpty())->toBeTrue()
        ->and($callable->passedCount())->toBe(0);
});

it('builds from an explicit name list when no reflection target exists', function () {
    $callback = new Arg(new ArrowFunction(['expr' => new Variable('x')]), name: new Identifier('callback'));

    $args = CallArguments::fromNames([$callback], ['callback']);

    expect($args->at(0))->toBe($callback)
        ->and($args->named('callback'))->toBe($callback)
        ->and($args->passedCount())->toBe(1);
});

it('treats a call with no arguments as empty', function () {
    $args = configArguments([]);

    expect($args->isEmpty())->toBeTrue()
        ->and($args->passedCount())->toBe(0)
        ->and($args->at(0))->toBeNull()
        ->and($args->named('key'))->toBeNull()
        ->and($args->hasUnpack())->toBeFalse();
});

it('reads a nullsafe method call the same as a plain one', function () {
    $relationship = new Arg(new String_('profile'));

    $args = CallArguments::for(
        new NullsafeMethodCall(new Variable('this'), 'whenLoaded', [$relationship]),
        new ReflectionMethod(JsonResource::class, 'whenLoaded'),
    );

    expect($args->at(0))->toBe($relationship)
        ->and($args->named('relationship'))->toBe($relationship)
        ->and($args->passedCount())->toBe(1);
});

it('reads arguments from a `new` expression against its constructor', function () {
    $resource = new Arg(new Variable('model'));

    $args = CallArguments::for(
        new New_(new Name(JsonResource::class), [$resource]),
        new ReflectionMethod(JsonResource::class, '__construct'),
    );

    expect($args->at(0))->toBe($resource)
        ->and($args->named('resource'))->toBe($resource)
        ->and($args->passedCount())->toBe(1);
});

// `config(...$a, default: $x)` is legal since PHP 8.1. A spread reaching the named position is a
// fatal "overwrites previous argument" error, so every valid call of this shape has a real
// func_num_args() equal to the named position plus one, however many elements the spread holds.
it('gets passedCount() right for a spread followed by a trailing named argument', function () {
    $spread = new Arg(new Variable('a'), unpack: true);
    $default = new Arg(new String_('fallback'), name: new Identifier('default'));

    $args = configArguments([$spread, $default]);

    expect($args->hasUnpack())->toBeTrue()
        ->and($args->passedCount())->toBe(2);
});

it('indexes fromNames() positional arguments by an internal counter, not the array key', function () {
    $first = new Arg(new Variable('x'));
    $second = new Arg(new Variable('y'));

    $args = CallArguments::fromNames(['unrelated' => $first, 7 => $second], ['a', 'b']);

    expect($args->at(0))->toBe($first)
        ->and($args->at(1))->toBe($second)
        ->and($args->passedCount())->toBe(2);
});

// Both fixtures declare a same-named `shared()` with different parameters, pinning that a closure
// or first-class callable is read fresh rather than memoized under its colliding bare name.
it('keeps same-named closures distinct, since a closure is never memoized', function () {
    $alpha = new Arg(new Variable('a'));
    $beta = new Arg(new Variable('b'));
    $gamma = new Arg(new Variable('c'));

    $fromA = CallArguments::for(
        new FuncCall(new Name('shared'), [$alpha]),
        new ReflectionFunction((new CallArgumentsFixtureA)->shared(...)),
    );
    $fromB = CallArguments::for(
        new FuncCall(new Name('shared'), [$beta, $gamma]),
        new ReflectionFunction((new CallArgumentsFixtureB)->shared(...)),
    );

    expect($fromA->positionOf('alpha'))->toBe(0)
        ->and($fromA->positionOf('beta'))->toBeNull()
        ->and($fromB->positionOf('beta'))->toBe(0)
        ->and($fromB->positionOf('gamma'))->toBe(1)
        ->and($fromB->positionOf('alpha'))->toBeNull();
});

/**
 * Declares `shared(string $alpha)`, distinct from CallArgumentsFixtureB's `shared()`, to pin that
 * closures reflecting same-named methods on different classes are never memoized together.
 */
class CallArgumentsFixtureA
{
    public function shared(string $alpha): void {}
}

/**
 * Declares `shared(string $beta, string $gamma)`, distinct from CallArgumentsFixtureA's `shared()`,
 * to pin that closures reflecting same-named methods on different classes are never memoized together.
 */
class CallArgumentsFixtureB
{
    public function shared(string $beta, string $gamma): void {}
}
