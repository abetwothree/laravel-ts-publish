<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Which calls reach the default auth guard, through `auth()`, `Auth::` or `request()->user()`, and what its `user()`
 * and `id()` return.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait ResolvesAuthHelperCalls
{
    /**
     * Type `user()` or `id()` against the resolved auth model, or decline for any other name.
     *
     * @param  class-string<Model>|null  $model
     * @return ValueExpressionResult|null
     */
    protected function authMethodResult(string $method, ?string $model): ?array
    {
        $method = strtolower($method);

        if ($model === null) {
            return null;
        }

        if ($method === 'user') {
            return ['type' => class_basename($model).' | null', 'optional' => false, 'modelFqcn' => $model];
        }

        if ($method !== 'id') {
            return null;
        }

        DependencyRecorder::recordClass($model);

        $identifier = resolve(ModelAttributeResolver::class)->authIdentifierTsType($model);

        return $identifier === null ? null : ['type' => ValueResult::withNullArm($identifier), 'optional' => false];
    }

    /**
     * Whether a call reads the default guard's user: `user()` on it, `Auth::user()` or `request()->user()`.
     */
    protected function readsSignedInUser(MethodCall|NullsafeMethodCall|StaticCall $call): bool
    {
        if (! $call->name instanceof Identifier || $call->name->toLowerString() !== 'user' || $call->isFirstClassCallable()) {
            return false;
        }

        if ($call instanceof StaticCall) {
            return $this->isAuthFacade($call->class);
        }

        return $this->isDefaultGuard($call->var) || ($this->callsWithoutArguments($call->var, 'request') && $call->getRawArgs() === []);
    }

    /**
     * Whether an expression is the default guard: `auth()`, or `guard()` naming none on `auth()` or the `Auth` facade.
     */
    protected function isDefaultGuard(Expr $expr): bool
    {
        if ($this->callsWithoutArguments($expr, 'auth')) {
            return true;
        }

        if (! ($expr instanceof MethodCall || $expr instanceof StaticCall)
            || ! $expr->name instanceof Identifier
            || $expr->name->toLowerString() !== 'guard'
            || $expr->getRawArgs() !== []
        ) {
            return false;
        }

        return $expr instanceof StaticCall ? $this->isAuthFacade($expr->class) : $this->callsWithoutArguments($expr->var, 'auth');
    }

    /**
     * Whether a class reference names Laravel's `Auth` facade, imported or through its root alias.
     */
    private function isAuthFacade(Expr|Name $class): bool
    {
        return $class instanceof Name && ! $class->isSpecialClassName() && is_a($class->toString(), Auth::class, true);
    }

    /**
     * Whether an expression calls a global helper by name with no argument, so a guard name keeps its default.
     */
    private function callsWithoutArguments(Expr $expr, string $helper): bool
    {
        return $expr instanceof FuncCall
            && $expr->name instanceof Name
            && $expr->name->toLowerString() === $helper
            && $expr->getRawArgs() === [];
    }
}
