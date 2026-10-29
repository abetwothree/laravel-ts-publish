<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\MacroCallResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Http\Request;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * `<receiver>->m()`, `<receiver>?->m()` and `$expr::m()` typed from the method's return on the receiver's PHP class,
 * including a bare `$this->m()` a resource forwards to its model.
 *
 * Registered last before the convention rules, so every specific handler keeps priority.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ReceiverMethodCallHandler implements ExpressionHandler
{
    use FiltersAttributeKeys;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class, StaticCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall || $expr instanceof StaticCall)
            || $expr->isFirstClassCallable()
            || ! $expr->name instanceof Identifier
        ) {
            return null;
        }

        $receivers = resolve(ReceiverClassResolver::class);
        $method = $expr->name->toString();

        // PHP sends a static-syntax call the subject lacks to `__call()`, so it reads as `$this->m()` does.
        $onThis = $expr instanceof StaticCall
            ? $receivers->sendsToSubjectCall($expr, $scope)
            : $expr->var instanceof Variable && $expr->var->name === 'this';
        $reached = match (true) {
            ! $onThis => null,
            $expr instanceof StaticCall => $scope->staticCallClass($expr),
            default => $scope->thisMemberClass($method, true),
        };

        // A macro runs before anything the subject forwards to; `$this` is never null, so `?->` adds no `| null`.
        if ($reached !== null && resolve(MacroCallResolver::class)->runsMacro($reached, $method)) {
            return resolve(MacroCallResolver::class)->resolve($scope, $method);
        }

        $receiver = match (true) {
            $reached !== null => $receivers->forwardedThisReceiver($method, $scope, $reached) ?? $this->filteredModelSubject($expr, $scope),
            $expr instanceof StaticCall => $receivers->resolveStaticReceiver($expr, $scope),
            default => $receivers->resolve($expr->var, $scope),
        };

        // A request's methods belong to KnownMethodRuleHandler, which reads form-request rules and the auth guard.
        if ($receiver === null || array_any($receiver->classes, fn (string $class): bool => is_a($class, Request::class, true))) {
            return null;
        }

        $fromInside = ! $onThis && $expr instanceof StaticCall && $expr->class instanceof Name && $expr->class->isSpecialClassName();
        $site = new CallSite($expr, $scope, $engine);
        $result = resolve(ReceiverMethodReturnResolver::class)
            ->resolve($receiver, $expr->name->toString(), $scope, $fromInside, $expr, $site);

        if ($result === null) {
            return null;
        }

        // `$this` is never null, so `$this?->m()` short-circuits nothing. `unknown` already holds `null`.
        $nullable = ($expr instanceof NullsafeMethodCall && ! $onThis) || $receiver->shortCircuits;

        if ($nullable && ! TsTypeString::isUnknownOnly($result['type']) && ! in_array('null', TsTypeString::splitTopLevelUnion($result['type']), true)) {
            $result['type'] .= ' | null';
        }

        return $result;
    }

    /**
     * `$this` itself for an `only()`/`except()` in a model's own body, which the chain handler declines.
     *
     * The receiver rules then answer as they do for any model receiver, token-free where the scope carries no import.
     */
    private function filteredModelSubject(MethodCall|NullsafeMethodCall|StaticCall $call, AnalysisScope $scope): ?ReceiverType
    {
        return $call instanceof StaticCall || ! $this->callsAttributeFilter($call)
            ? null
            : resolve(ReceiverClassResolver::class)->modelSubject($scope);
    }
}
