<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks\BindsMapCallbacks;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections\ReadsCollectedItems;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesAuthHelperCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\StringSerialization;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use Illuminate\Config\Repository;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Config;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
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
 * A call to a known PHP built-in function typed from its reflected return type, `array_filter()`, `array_values()`,
 * `array_keys()` and `array_map()` as the array they send, and Laravel helpers whose shape is knowable: `config()`,
 * `auth()->user()`, `request()->user()`, `now()`, `today()`, `str()`, `url()` and `collect()`.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class KnownFunctionCallHandler implements ExpressionHandler
{
    use BindsMapCallbacks;
    use ReadsCollectedItems;
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

            if ($name === 'array_filter') {
                return $this->arrayFilterRule(CallArguments::for($expr, new ReflectionFunction('array_filter')), $scope, $engine);
            }

            if ($name === 'array_values') {
                return $this->arrayValuesRule(CallArguments::for($expr, new ReflectionFunction('array_values')), $scope, $engine);
            }

            if ($name === 'array_map') {
                return $this->arrayMapRule($expr, $scope, $engine);
            }

            if ($name === 'array_keys') {
                return $this->arrayKeysRule(CallArguments::for($expr, new ReflectionFunction('array_keys')), $scope, $engine);
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
     * Resolve `user()` or `id()` on the default guard, and `request()->user()`, or decline any other receiver.
     *
     * `auth('admin')` names a guard AuthUserResolver does not read, and answering with the default
     * guard's model would be confidently wrong — worse than the `unknown` a decline leaves.
     *
     * @return ValueExpressionResult|null
     */
    private function authHelperMethodRule(MethodCall $expr): ?array
    {
        if (! $expr->name instanceof Identifier) {
            return null;
        }

        return match (true) {
            $this->callsWithoutArguments($expr->var, 'auth') => $this->authMethodResult($expr->name->toString()),
            $this->isDefaultGuard($expr->var) => $this->authMethodResult($expr->name->toString(), onlyWhileSole: true),
            $this->readsSignedInUser($expr) => $this->authMethodResult('user', onlyWhileSole: true),
            default => null,
        };
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
        $dropped = $defaultResult['type'] === 'unknown' ? [$default] : [];

        if ($dropped !== []) {
            DroppedUnionArms::record($default, $scope, 'data-get-default');
        }

        // The default stands in only for a MISSING key, never for a present-but-null value, so it
        // unions alongside the chain's own `null` arm rather than removing it.
        return DroppedUnionArms::warnOfPlaceholder(ValueResult::unionResults([$result, $defaultResult]), $dropped, $scope);
    }

    /**
     * `array_filter($array)` as the array it is passed, arm by arm: each key it keeps is kept, so a list it leaves a
     * gap in sends json_encode()'s object of int keys, each member of a shape may be absent, and with no callback no
     * value is null.
     *
     * A `null` arm is left out, as PHP throws a TypeError on null, and a Collection, which it throws on too, keeps its
     * items' type beside a warning; null when another arm, such as a tuple, leaves the array unread.
     *
     * @return ValueExpressionResult|null
     */
    private function arrayFilterRule(CallArguments $args, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $array = $args->named('array')?->value;

        if ($array === null || $args->hasUnpack()) {
            return null;
        }

        $this->warnOfCollection('array_filter', $array, $scope);

        $callback = $args->named('callback')?->value;
        $dropsNull = $callback === null || ($callback instanceof ConstFetch && $callback->name->toLowerString() === 'null');
        $result = $engine->resolve($array);
        $unnamed = $result['unnamedKeys'] ?? false;
        // A key the shape leaves out may be all the filter keeps, and its keys may re-index into a list.
        $arms = ArrayArms::arms($result['type'], static fn (string $arm): ?string => $unnamed && TsTypeShape::members($arm) !== null
            ? null
            : ArrayArms::filtered($arm, $dropsNull));

        if ($arms === null || $arms === []) {
            return null;
        }

        unset($result['keepsKeys']);

        return [...$result, 'type' => implode(' | ', array_values(array_unique($arms))), 'optional' => false];
    }

    /**
     * `array_values($array)` as the list of its values, keyed 0..n-1, arm by arm: a list or tuple as itself, a Record
     * or an object literal as its values. A `null` arm is left out, as PHP throws on it, and a Collection keeps its
     * items' type beside a warning; null when another arm leaves the array unread.
     *
     * @return ValueExpressionResult|null
     */
    private function arrayValuesRule(CallArguments $args, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $array = $args->named('array')?->value;

        if ($array === null || $args->hasUnpack()) {
            return null;
        }

        $this->warnOfCollection('array_values', $array, $scope);

        $result = $engine->resolve($array);
        $arms = ArrayArms::arms($result['type'], ArrayArms::valuesOf(...));

        if ($arms === null || $arms === []) {
            return null;
        }

        unset($result['keepsKeys'], $result['unnamedKeys']);

        return [...$result, 'type' => implode(' | ', array_values(array_unique($arms))), 'optional' => false, 'listKeys' => true];
    }

    /**
     * `array_keys($array)` as the list of its keys: a constant array's or a literal's own, else its type's as
     * ArrayArms::keysOf() reads them; a search value keeps some of the same keys. A `null` arm is left out, as PHP
     * throws a TypeError on null; null for an object, which PHP throws on too, or an arm it cannot read.
     *
     * @return ValueExpressionResult|null
     */
    private function arrayKeysRule(CallArguments $arguments, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $array = $arguments->named('array')?->value;

        if ($array === null || $arguments->hasUnpack()) {
            return null;
        }

        $this->warnOfCollection('array_keys', $array, $scope);

        if ($this->namesObject($array, $scope)) {
            return null;
        }

        return $this->collectedKeys($array, $engine->resolve($array), $scope);
    }

    /**
     * `array_map($callback, $array, ...$arrays)` as the array it returns: one array keeps its keys arm by arm, and two
     * or more re-index into a list. A `null` callback returns the one array, or zips several into tuples.
     *
     * Null for an object, which PHP throws on, a value no arm of which is an array, or a callback that cannot be read.
     *
     * @return ValueExpressionResult|null
     */
    private function arrayMapRule(FuncCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $args = CallArguments::for($call, new ReflectionFunction('array_map'));
        $callback = $args->named('callback')?->value;
        $arrays = [];

        for ($position = 1; $position < $args->passedCount(); $position++) {
            $arrays[] = $args->at($position)?->value;
        }

        if ($callback === null || $arrays === [] || $args->hasUnpack() || in_array(null, $arrays, true)) {
            return null;
        }

        $read = fn (): ?array => $this->mappedInputs($arrays, $scope, $engine);
        $inputs = $read();

        if ($inputs === null) {
            return null;
        }

        $zips = $callback instanceof ConstFetch && $callback->name->toLowerString() === 'null';
        $result = $this->mappedReadingCases(
            $inputs,
            $read,
            fn (array $inputs): ?array => count($inputs) === 1
                ? $this->mappedOneArray($callback, $inputs[0], $arrays[0], $zips, $scope, $engine)
                : $this->mappedArrays($callback, $inputs, $arrays, $zips, $scope, $engine),
            $scope,
        );

        if ($result === null) {
            return null;
        }

        // The result is a plain array, so a resource re-indexes it as it does any other.
        unset($result['keepsKeys']);

        return $result;
    }

    /**
     * The value each array passed to array_map() holds; null where one is an object, which PHP throws on.
     *
     * @param  non-empty-list<Expr>  $arrays
     * @return non-empty-list<ValueExpressionResult>|null
     */
    private function mappedInputs(array $arrays, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $inputs = [];

        foreach ($arrays as $array) {
            $this->warnOfCollection('array_map', $array, $scope);

            if ($this->namesObject($array, $scope)) {
                return null;
            }

            $inputs[] = $engine->resolve($array);
        }

        return $inputs;
    }

    /**
     * What array_map() returns for one array: the array itself for a `null` callback, else each arm mapped with its
     * keys kept, the callback's parameter guarded where the array is read off a guarded receiver and bound to the
     * classes the array declares its elements as.
     *
     * @param  ValueExpressionResult  $input
     * @return ValueExpressionResult|null
     */
    private function mappedOneArray(Expr $callback, array $input, Expr $array, bool $zips, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $zips) {
            $resolver = resolve(ReceiverClassResolver::class);

            return $this->mappedArrayResult(
                $callback,
                $input,
                true,
                $scope,
                $engine,
                guarded: $resolver->readsGuarded($array, $scope),
                declaredElement: $resolver->elementClasses($array, $scope),
            );
        }

        $arms = ArrayArms::arms($input['type'], static fn (string $arm): ?string => ArrayArms::isArray($arm) ? $arm : null);

        return $arms === null || $arms === [] ? null : [...$input, 'type' => implode(' | ', $arms), 'optional' => false];
    }

    /**
     * What array_map() returns for two or more arrays, re-indexed into a list: tuples of each array's element for a
     * `null` callback, else what the callback returns, each element `| null` as a shorter array pads with it, and
     * each parameter bound to the classes its array declares its elements as.
     *
     * @param  non-empty-list<ValueExpressionResult>  $inputs
     * @param  non-empty-list<Expr>  $arrays
     * @return ValueExpressionResult|null
     */
    private function mappedArrays(Expr $callback, array $inputs, array $arrays, bool $zips, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $parameters = [];

        foreach ($inputs as $input) {
            $elements = ArrayArms::arms($input['type'], ArrayArms::elementOf(...));
            $elements = $elements === null ? [] : array_values(array_diff(array_unique($elements), ['never']));

            if ($elements === [] || in_array('unknown', $elements, true)) {
                return null;
            }

            $element = TsTypeString::withNullArm(implode(' | ', $elements));
            $parameters[] = [...ValueResult::elementOf($input, $element), 'type' => $element];
        }

        // Each element's channels ride along, so the tuples import the enums and models their arms name.
        if ($zips) {
            return [...ValueResult::unionResults($parameters), 'type' => '['.implode(', ', array_column($parameters, 'type')).'][]', 'optional' => false];
        }

        $resolver = resolve(ReceiverClassResolver::class);
        $declared = array_filter(array_map(fn (Expr $array): ?ReceiverType => $resolver->elementClasses($array, $scope), $arrays));
        $body = $this->resolveArrayCallback($callback, $parameters, null, true, $scope, $engine, declaredElements: $declared);

        return $body === null || $body['type'] === 'unknown'
            ? null
            : ValueResult::holdingOpenShapes($body, [...$body, 'type' => ValueResult::arrayWrapType($body['type']), 'optional' => false], '[]');
    }

    /**
     * Whether ReceiverClassResolver names an object for an array function's argument, which PHP rejects with a
     * TypeError.
     */
    private function namesObject(Expr $array, AnalysisScope $scope): bool
    {
        return resolve(ReceiverClassResolver::class)->resolve($array, $scope) !== null;
    }

    /**
     * Record a warning naming the line where an array function is passed a Collection, which PHP rejects with a
     * TypeError; the Collection's all() is the array each of them takes.
     */
    private function warnOfCollection(string $function, Expr $array, AnalysisScope $scope): void
    {
        $receiver = resolve(ReceiverClassResolver::class)->resolve($array, $scope);

        if ($receiver !== null && array_any($receiver->classes, static fn (string $class): bool => is_a($class, Enumerable::class, true))) {
            AnalysisWarnings::addOnce($scope->subjectReflection->getName(), sprintf(
                '%s() on line %d is passed a Collection, which PHP rejects with a TypeError; pass its ->all() instead.',
                $function,
                $array->getStartLine(),
            ));
        }
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
        if ($type === null || $type === 'unknown' || TsTypeString::hasNullArm($type)) {
            return null;
        }

        return ['type' => 'string', 'optional' => false];
    }

    /**
     * Resolve `collect($items)` as the list or object its items encode as.
     *
     * None or null is `never[]`, a constant array is typed as a parameter default is, and an expression typed as a
     * list, a Record or an object shape keeps that type. A literal holding a `when*()` value declines: Laravel's filter
     * never recurses into a Collection, so the value encodes as `{}`.
     *
     * @return ValueExpressionResult|null
     */
    private function collectRule(CallArguments $args, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $items = $args->named('value')?->value;

        // Laravel's filter never recurses into a Collection, so a `when*()` item encodes as `{}`.
        if ($items instanceof Array_ && array_any($items->items, fn (ArrayItem $item): bool => $engine->resolve($item->value)['optional'])) {
            return null;
        }

        $result = $items === null ? ['type' => 'never[]', 'optional' => false] : $this->collectedItems($items, $scope, $engine);

        // The value is a Collection, whose keys json_encode() writes as they are, so a resource's re-index skips it.
        return $result === null || $result['type'] === 'unknown' ? null : [...$result, 'keepsKeys' => true];
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
