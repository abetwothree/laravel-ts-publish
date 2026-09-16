<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AuthUserResolver;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesAuthHelperCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Config;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use stdClass;

/**
 * A call to a known PHP built-in function (`count(...)`, `strtoupper(...)`, etc.), typed from its
 * reflected return type, plus the Laravel helpers whose shape is knowable: `config('literal')`,
 * `config()->get()` and its typed accessors, and `auth()->user()`/`auth()->id()`. Declines anything else.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
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
            return $this->authHelperMethodRule($expr) ?? $this->typedConfigAccessorRule($expr, $engine);
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name) {
            $name = $expr->name->getLast();

            if ($name === 'config') {
                return $this->resolveConfigCallType(CallArguments::for($expr, new ReflectionFunction('config')), $engine);
            }

            if ($name === 'data_get') {
                return $this->dataGetRule(CallArguments::for($expr, new ReflectionFunction('data_get')), $scope, $engine);
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
     * Resolve `config()->integer('key', 0)` and the other typed accessors from Repository's declared return
     * type — the default never changes it — and `config()->get(...)` exactly like `config(...)`.
     *
     * @return ValueExpressionResult|null
     */
    private function typedConfigAccessorRule(MethodCall $expr, ExpressionEngine $engine): ?array
    {
        if (! $expr->name instanceof Identifier
            || ! $expr->var instanceof FuncCall
            || ! $expr->var->name instanceof Name
            || $expr->var->name->getLast() !== 'config'
            || $expr->var->isFirstClassCallable()
            || ! CallArguments::for($expr->var, new ReflectionFunction('config'))->isEmpty()
            || ! method_exists(Repository::class, $expr->name->toString())) {
            return null;
        }

        $method = $expr->name->toString();

        if ($method === 'get') {
            return $this->resolveConfigCallType(CallArguments::for($expr, new ReflectionMethod(Repository::class, 'get')), $engine);
        }

        $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass(Repository::class), $method);

        return resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);
    }

    /**
     * Resolve `config('some.key')` to the TypeScript type of the live configuration value.
     *
     * The package runs inside the booted app, so reading the value is honest; a computed key
     * cannot be read and declines.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveConfigCallType(CallArguments $args, ExpressionEngine $engine): ?array
    {
        $keyArg = $args->named('key');

        if (! $keyArg?->value instanceof String_) {
            return null;
        }

        $absent = new stdClass;
        $value = Config::get($keyArg->value->value, $absent);

        // Only an ABSENT key falls through to the default; a key set to null hands the caller null.
        // Unlike Request methods, config()'s default is the whole value when the key is absent, so it is
        // typed. The typed accessors (config()->integer() etc., typedConfigAccessorRule()) follow the Request
        // rule instead: declared return type, default ignored. See requestMethodRule() for the other half.
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
     * `data_get($target, 'a.b')` as the nullsafe chain `$target?->a?->b`, unioned with an explicit default.
     *
     * A `*` segment expands to a list of every match, so the chain would describe one element rather
     * than the value the call returns — the same decline `validated('options.*')` makes.
     *
     * @return ValueExpressionResult|null
     */
    private function dataGetRule(CallArguments $args, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $target = $args->named('target')?->value;
        $key = $args->named('key')?->value;

        if ($target === null || ! $key instanceof String_ || in_array('*', explode('.', $key->value), true)) {
            return null;
        }

        $chain = $target;

        foreach (explode('.', $key->value) as $segment) {
            $chain = new NullsafePropertyFetch($chain, $segment);
        }

        $result = $engine->resolve($chain);

        if ($result['type'] === 'unknown') {
            return null;
        }

        $default = $args->named('default')?->value;

        if ($default === null) {
            return $result;
        }

        $defaultResult = $engine->resolve($default);

        // This path resolves its arms itself, so it records its own drop: analyzeClosureUnion() never sees it.
        if ($defaultResult['type'] === 'unknown') {
            DroppedUnionArms::record($default, $scope);
        }

        // The default stands in only for a MISSING key, never for a present-but-null value, so it
        // unions alongside the chain's own `null` arm rather than removing it.
        return ValueResult::unionResults([$result, $defaultResult]);
    }

    /**
     * Resolve a PHP built-in function name to its TypeScript return type, or null when unresolvable.
     */
    private function resolveKnownFunctionCallType(string $name): ?string
    {
        // Both are natively `array`, which reflects to the `unknown[]` rejected below, but every
        // element either one produces is a string.
        if ($name === 'explode' || $name === 'str_split') {
            return 'string[]';
        }

        $tsInfo = LaravelTsPublish::nativePhpFunctionReturnedTypes($name);

        return ! str_contains($tsInfo['type'], 'unknown') ? $tsInfo['type'] : null;
    }
}
