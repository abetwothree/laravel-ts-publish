<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionMethod;

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
        $onThis = ! $expr instanceof StaticCall && $expr->var instanceof Variable && $expr->var->name === 'this';
        $receiver = match (true) {
            $expr instanceof StaticCall => $receivers->resolveStaticReceiver($expr, $scope),
            $onThis => $receivers->forwardedThisReceiver($expr->name->toString(), $scope) ?? $this->filteredModelSubject($expr, $scope),
            default => $receivers->resolve($expr->var, $scope),
        };

        // A request's methods belong to KnownMethodRuleHandler, which reads form-request rules and the auth guard.
        if ($receiver === null || array_any($receiver->classes, fn (string $class): bool => is_a($class, Request::class, true))) {
            return null;
        }

        $fromInside = $expr instanceof StaticCall && $expr->class instanceof Name && $expr->class->isSpecialClassName();
        $result = resolve(ReceiverMethodReturnResolver::class)
            ->resolve($receiver, $expr->name->toString(), $scope, $fromInside, $expr);

        if ($result === null) {
            return null;
        }

        // `$this` is never null, so `$this?->m()` short-circuits nothing.
        $nullable = ($expr instanceof NullsafeMethodCall && ! $onThis) || $receiver->shortCircuits;

        if ($nullable && ! in_array('null', TsTypeString::splitTopLevelUnion($result['type']), true)) {
            $result['type'] .= ' | null';
        }

        return $result;
    }

    /**
     * `$this` itself for a runtime-key `only()`/`except()` in a model's own body, which the chain handler declines.
     *
     * A literal key list stays unanswered there: its `Pick<Model, …>` names a token a method-body shape cannot import,
     * so MethodReturnTypeResolver would drop the whole shape the call sits in.
     */
    private function filteredModelSubject(MethodCall|NullsafeMethodCall|StaticCall $call, AnalysisScope $scope): ?ReceiverType
    {
        if ($call instanceof StaticCall || ! $this->callsAttributeFilter($call) || ! $call->name instanceof Identifier) {
            return null;
        }

        return $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $call->name->toString())) === null
            ? resolve(ReceiverClassResolver::class)->modelSubject($scope)
            : null;
    }
}
