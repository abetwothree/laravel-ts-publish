<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Http\Request;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * `<receiver>->m()`, `<receiver>?->m()` and `$expr::m()` typed from the method's return on the receiver's PHP class.
 *
 * Registered last before the convention rules, so every specific handler keeps priority.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ReceiverMethodCallHandler implements ExpressionHandler
{
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
        $receiver = $expr instanceof StaticCall
            ? $receivers->resolveStaticReceiver($expr, $scope)
            : $receivers->resolve($expr->var, $scope);

        // A request's methods belong to KnownMethodRuleHandler, which reads form-request rules and the auth guard.
        if ($receiver === null || array_any($receiver->classes, fn (string $class): bool => is_a($class, Request::class, true))) {
            return null;
        }

        $fromInside = $expr instanceof StaticCall && $expr->class instanceof Name && $expr->class->isSpecialClassName();
        $result = resolve(ReceiverMethodReturnResolver::class)->resolve($receiver, $expr->name->toString(), $scope, $fromInside);

        if ($result === null) {
            return null;
        }

        $nullable = $expr instanceof NullsafeMethodCall || $receiver->shortCircuits;

        if ($nullable && ! in_array('null', TsTypeString::splitTopLevelUnion($result['type']), true)) {
            $result['type'] .= ' | null';
        }

        return $result;
    }
}
