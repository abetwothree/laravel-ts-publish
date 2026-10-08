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
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\StringSerialization;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Config;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
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
 * A call to a known PHP built-in function (`count(...)`, `strtoupper(...)`, etc.), typed from its reflected return
 * type, plus the Laravel helpers whose shape is knowable: `config('literal')`, `config()->get()` and its typed
 * accessors, `auth()->user()`/`auth()->id()`, and `now()`, `today()`, `str()`, `url()` and `collect()`.
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

            $helper = $this->valueHelperRule($name, $expr, $scope, $engine);

            if ($helper !== null) {
                return $helper;
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
            DroppedUnionArms::record($default, $scope, 'data-get-default');
        }

        // The default stands in only for a MISSING key, never for a present-but-null value, so it
        // unions alongside the chain's own `null` arm rather than removing it.
        return ValueResult::unionResults([$result, $defaultResult]);
    }

    /**
     * Resolve `now()`, `today()`, `str()`, `url()` or `collect()` as json_encode() writes what it returns.
     *
     * `str($s)` is the string its Stringable serializes to, and `collect($items)` the list or object its items encode
     * as. Reflection cannot type their returns: an interface, a conditional docblock or a collection.
     *
     * @return ValueExpressionResult|null
     */
    private function valueHelperRule(string $name, FuncCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $name = strtolower($name);

        if (! in_array($name, ['now', 'today', 'str', 'url', 'collect'], true)) {
            return null;
        }

        $args = CallArguments::for($call, new ReflectionFunction($name));

        // A spread hides which arguments were passed, and every rule below depends on that.
        if ($args->hasUnpack()) {
            return null;
        }

        return match ($name) {
            'now', 'today' => $this->dateHelperResult($name),
            // With no argument at all str() returns an anonymous proxy that json_encode() writes as `{}`.
            'str' => ['type' => $args->passedCount() === 0 ? TsTypeStringService::EMPTY_OBJECT : 'string', 'optional' => false],
            'url' => $this->urlRule($args, $engine),
            'collect' => $this->collectRule($args, $scope, $engine),
        };
    }

    /**
     * `now()` and `today()` through the one rule for a string-serialized class, so `timestamps_as_date` applies.
     *
     * @return ValueExpressionResult|null
     */
    private function dateHelperResult(string $name): ?array
    {
        $class = ReceiverClassResolver::HELPER_CLASSES[$name];

        return StringSerialization::jsonStringType($class) === null
            ? null
            : ['type' => LaravelTsPublish::toTsType($class)['type'], 'optional' => false];
    }

    /**
     * `url($path)` is a string for any path but null, which returns the UrlGenerator itself.
     *
     * @return ValueExpressionResult|null
     */
    private function urlRule(CallArguments $args, ExpressionEngine $engine): ?array
    {
        $path = $args->named('path')?->value;

        $type = $path === null ? null : $engine->resolve($path)['type'];

        // `unknown` admits null, which returns the UrlGenerator.
        if ($type === null || $type === 'unknown' || ValueResult::hasNullArm($type)) {
            return null;
        }

        return ['type' => 'string', 'optional' => false];
    }

    /**
     * Resolve `collect($items)` as the list or object its items encode as.
     *
     * None or null is `never[]`, a constant array is typed as a parameter default's value is, a list literal is a list
     * of its elements, and an expression typed as a list or an object shape keeps that type. A literal holding a
     * `when*()` value declines: Laravel's filter never recurses into a Collection, so the value encodes as `{}`.
     *
     * @return ValueExpressionResult|null
     */
    private function collectRule(CallArguments $args, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $items = $args->named('value')?->value;

        if ($items === null) {
            return ['type' => 'never[]', 'optional' => false];
        }

        $resolver = new ValueResolver;

        try {
            $value = $resolver->evaluateConstantExpression($items, $scope);

            // Arr::wrap() turns null into no items, and a scalar into one, which no caller writes.
            return match (true) {
                $value === null => ['type' => 'never[]', 'optional' => false],
                is_array($value) => $resolver->resolveConstantValue($value, $engine),
                default => null,
            };
        } catch (ConstExprEvaluationException) {
        }

        if ($items instanceof Array_ && array_any($items->items, fn (ArrayItem $item): bool => $engine->resolve($item->value)['optional'])) {
            return null;
        }

        if ($items instanceof Array_ && array_all($items->items, fn (ArrayItem $item): bool => $item->key === null && ! $item->unpack)) {
            return $this->listLiteralResult($items, $engine);
        }

        $result = $engine->resolve($items);

        return count(TsTypeString::splitTopLevelUnion($result['type'])) === 1
            && (str_ends_with($result['type'], '[]') || str_starts_with($result['type'], '{'))
            ? [...$result, 'optional' => false]
            : null;
    }

    /**
     * A list literal as a list of its element types, or null when one of them does not type.
     *
     * @return ValueExpressionResult|null
     */
    private function listLiteralResult(Array_ $list, ExpressionEngine $engine): ?array
    {
        $elements = array_values(array_map(fn (ArrayItem $item): array => $engine->resolve($item->value), $list->items));

        if (array_any($elements, fn (array $element): bool => $element['type'] === 'unknown')) {
            return null;
        }

        $union = ValueResult::unionResults($elements);

        return [...$union, 'type' => ValueResult::arrayWrapType($union['type']), 'optional' => false];
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
