<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\CallMatcher;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;

/**
 * @template TNode of object
 *
 * @param  class-string<TNode>  $type
 * @return TNode
 */
function firstNodeOfType(string $php, string $type): object
{
    $stmts = new AstParser()->parseSource($php);
    $node = (new NodeFinder)->findFirst($stmts, fn ($n) => $n instanceof $type);

    expect($node)->toBeInstanceOf($type);

    return $node;
}

function firstStaticCall(string $php): StaticCall
{
    $stmts = new AstParser()->parseSource($php);

    return (new NodeFinder)->findFirst($stmts, fn ($n) => $n instanceof StaticCall);
}

it('matches Inertia::render by class suffix and method name', function () {
    $call = firstStaticCall('<?php use Inertia\Inertia; Inertia::render("Home");');

    $matcher = new CallMatcher;

    expect($matcher->isStaticCallTo($call, 'Inertia', 'render'))->toBeTrue()
        ->and($matcher->isStaticCallTo($call, 'Inertia', 'share'))->toBeFalse()
        ->and($matcher->isStaticCallTo($call, 'Route', 'render'))->toBeFalse();
});

it('matches a fully-qualified class name by suffix, not equality', function () {
    // The predicate is str_ends_with, not ===: a fully-qualified \Inertia\Inertia::render() must still match.
    $call = firstStaticCall('<?php \Inertia\Inertia::render("Home");');

    expect(new CallMatcher()->isStaticCallTo($call, 'Inertia', 'render'))->toBeTrue();
});

it('does not match a same-named method on an unrelated class', function () {
    $call = firstStaticCall('<?php Route::render("Home");');

    expect(new CallMatcher()->isStaticCallTo($call, 'Inertia', 'render'))->toBeFalse();
});

it('rejects a non-StaticCall node outright', function () {
    $call = firstNodeOfType('<?php $inertia->render("Home");', MethodCall::class);

    expect(new CallMatcher()->isStaticCallTo($call, 'Inertia', 'render'))->toBeFalse();
});

it('reads the method name off a MethodCall, StaticCall, and NullsafeMethodCall alike', function () {
    $matcher = new CallMatcher;

    $methodCall = firstNodeOfType('<?php $obj->foo();', MethodCall::class);
    $staticCall = firstNodeOfType('<?php Foo::bar();', StaticCall::class);
    $nullsafeCall = firstNodeOfType('<?php $obj?->baz();', NullsafeMethodCall::class);

    expect($matcher->methodCallName($methodCall))->toBe('foo')
        ->and($matcher->methodCallName($staticCall))->toBe('bar')
        ->and($matcher->methodCallName($nullsafeCall))->toBe('baz');
});

it('returns null for a dynamic method name or an unrelated node', function () {
    $matcher = new CallMatcher;

    $dynamicCall = firstNodeOfType('<?php $obj->{$dynamic}();', MethodCall::class);
    $funcCall = firstNodeOfType('<?php foo();', FuncCall::class);

    expect($matcher->methodCallName($dynamicCall))->toBeNull()
        ->and($matcher->methodCallName($funcCall))->toBeNull();
});

it('resolves the class of a typed $this property', function () {
    $reflection = new ReflectionClass(MethodContext::class);
    $expr = firstNodeOfType('<?php $this->reflection;', PropertyFetch::class);

    expect(new CallMatcher()->resolveThisPropertyClass($reflection, $expr))->toBe(ReflectionClass::class);
});

it('returns null for a property fetch not rooted at $this', function () {
    $reflection = new ReflectionClass(MethodContext::class);
    $expr = firstNodeOfType('<?php $other->reflection;', PropertyFetch::class);

    expect(new CallMatcher()->resolveThisPropertyClass($reflection, $expr))->toBeNull();
});

it('returns null for an expression that is not a property fetch', function () {
    $reflection = new ReflectionClass(MethodContext::class);
    $expr = firstNodeOfType('<?php $this;', Variable::class);

    expect(new CallMatcher()->resolveThisPropertyClass($reflection, $expr))->toBeNull();
});

it('returns null when the property does not exist on the reflected class', function () {
    $reflection = new ReflectionClass(MethodContext::class);
    $expr = firstNodeOfType('<?php $this->doesNotExist;', PropertyFetch::class);

    expect(new CallMatcher()->resolveThisPropertyClass($reflection, $expr))->toBeNull();
});

it('returns null when the property is builtin-typed', function () {
    // MethodContext::$fileStmts is declared `array`, a builtin type the resolver deliberately excludes.
    $reflection = new ReflectionClass(MethodContext::class);
    $expr = firstNodeOfType('<?php $this->fileStmts;', PropertyFetch::class);

    expect(new CallMatcher()->resolveThisPropertyClass($reflection, $expr))->toBeNull();
});
