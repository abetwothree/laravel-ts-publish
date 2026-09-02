<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AuthUserResolver;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesAuthHelperCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Support\Facades\Config;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use ReflectionFunction;
use stdClass;

/**
 * A call to a known PHP built-in function (`count(...)`, `strtoupper(...)`, etc.), typed from its
 * reflected return type, plus the two Laravel helpers whose shape is knowable: `config('literal')`
 * and `auth()->user()`/`auth()->id()`. Declines anything else.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 */
final class KnownFunctionCallHandler implements ExpressionHandler
{
    use ResolvesAuthHelperCalls;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [FuncCall::class, MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof MethodCall) {
            return $this->authHelperMethodRule($expr);
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name) {
            $name = $expr->name->getLast();

            if ($name === 'config') {
                return $this->resolveConfigCallType($expr, $engine);
            }

            $tsType = $this->resolveKnownFunctionCallType($name);

            if ($tsType !== null) {
                return ['type' => $tsType, 'optional' => false];
            }
        }

        return null;
    }

    /**
     * Resolve `auth()->user()` / `auth()->id()`, or decline any other receiver.
     *
     * `auth('admin')` names a guard AuthUserResolver does not read, and answering with the default
     * guard's model would be confidently wrong — worse than the `unknown` a decline leaves.
     *
     * @return ValueExpressionResult|null
     */
    private function authHelperMethodRule(MethodCall $expr): ?array
    {
        if (! $expr->name instanceof Identifier
            || ! $expr->var instanceof FuncCall
            || ! $expr->var->name instanceof Name
            || $expr->var->name->getLast() !== 'auth'
            || $expr->var->isFirstClassCallable()
            || ! CallArguments::for($expr->var, new ReflectionFunction('auth'))->isEmpty()) {
            return null;
        }

        return $this->authMethodResult($expr->name->toString(), resolve(AuthUserResolver::class)->model());
    }

    /**
     * Resolve `config('some.key')` to the TypeScript type of the live configuration value.
     *
     * The package runs inside the booted app, so reading the value is honest; a computed key
     * cannot be read and declines.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveConfigCallType(FuncCall $expr, ExpressionEngine $engine): ?array
    {
        $args = CallArguments::for($expr, new ReflectionFunction('config'));
        $keyArg = $args->named('key');

        if (! $keyArg?->value instanceof String_) {
            return null;
        }

        $absent = new stdClass;
        $value = Config::get($keyArg->value->value, $absent);

        // Only an ABSENT key falls through to the default; a key set to null hands the caller null.
        if ($value === $absent) {
            $defaultArg = $args->named('default');

            if ($defaultArg !== null) {
                return $engine->resolve($defaultArg->value);
            }

            $value = null;
        }

        $type = match (true) {
            is_string($value) => 'string',
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            $value === null => 'null',
            is_array($value) => 'unknown[]',
            default => null,
        };

        return $type === null ? null : ['type' => $type, 'optional' => false];
    }

    /**
     * Resolve a PHP built-in function name to its TypeScript return type, or null when unresolvable.
     */
    private function resolveKnownFunctionCallType(string $name): ?string
    {
        $tsInfo = LaravelTsPublish::nativePhpFunctionReturnedTypes($name);

        return ! str_contains($tsInfo['type'], 'unknown') ? $tsInfo['type'] : null;
    }
}
