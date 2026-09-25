<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesFilteredRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\StringSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use ReflectionClass;
use ReflectionMethod;

/**
 * Types a method call from the PHP classes its receiver holds; the rules are in
 * docs/components/receiver-types.md § Following a method's return type.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ReceiverMethodReturnResolver
{
    use FiltersAttributeKeys;
    use ResolvesFilteredRelationTypes;

    /**
     * Type a method on every class the receiver holds, unioning the answers.
     *
     * @param  bool  $fromInside  a non-public method is reachable, as through `self::`, `static::` or `parent::`; only
     *                            a direct caller passes it: StaticCallHandler answers every named-class call first
     * @param  MethodCall|NullsafeMethodCall|StaticCall|null  $call  the call node, for a rule that reads its arguments
     * @return ValueExpressionResult|null null when any receiver class cannot type the method
     */
    public function resolve(
        ReceiverType $receiver,
        string $methodName,
        AnalysisScope $scope,
        bool $fromInside = false,
        MethodCall|NullsafeMethodCall|StaticCall|null $call = null,
    ): ?array {
        $results = [];

        foreach ($receiver->classes as $class) {
            $result = $this->ruleFor($receiver, $class, $methodName, $scope, $call) ?? $this->resolveOn($class, $methodName, $fromInside);

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        $result = count($results) === 1
            ? $results[0]
            : ValueResult::mergeUnion(array_values(array_unique(array_column($results, 'type'))), $results);

        return in_array($methodName, $this->supportedAttributeFilters(), true) && ! $scope->carriesImports
            ? $this->filterReturnWithoutImports($result, $methodName, $call)
            : $result;
    }

    /**
     * Whether the filter answers type a model's only()/except(): it runs Model's own, or overrides it with a return
     * reflection cannot type, such as `return parent::only($attributes)`. A typed override publishes its own return.
     *
     * RelationFilterHandler asks the same, so a relation, accessor or map proxy to such a model agrees with this rule.
     *
     * @param  class-string  $class
     */
    public function typesAsModelFilter(string $class, string $methodName): bool
    {
        return $this->runsModelFilter($class, $methodName)
            || (is_a($class, Model::class, true) && $this->resolveOn($class, $methodName, false) === null);
    }

    /**
     * Convention rules that need the receiver's model rather than a reflected signature.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function ruleFor(
        ReceiverType $receiver,
        string $class,
        string $methodName,
        AnalysisScope $scope,
        MethodCall|NullsafeMethodCall|StaticCall|null $call,
    ): ?array {
        $resolver = resolve(ModelAttributeResolver::class);

        if ($methodName === 'getKey' && $this->runsModelGetKey($class)) {
            $keyType = $resolver->keyTsType($class);

            return $keyType === null ? null : [...ValueResult::unknown(), 'type' => $keyType];
        }

        if ($methodName === 'modelKeys' && $receiver->elementModel !== null) {
            $keyType = $resolver->keyTsType($receiver->elementModel);

            return $keyType === null ? null : [...ValueResult::unknown(), 'type' => $keyType.'[]'];
        }

        if (! in_array($methodName, $this->supportedAttributeFilters(), true)) {
            return null;
        }

        if ($this->runsCollectionFilter($class, $methodName)) {
            return $this->attributeRecordResult(nullable: false);
        }

        return $this->typesAsModelFilter($class, $methodName) ? $this->attributeFilterRule($receiver, $methodName, $scope, $call) : null;
    }

    /**
     * `only()`/`except()` on a receiver holding one model class, built from RelationFilterHandler's helpers so the two
     * agree wherever both claim a call. It also answers a literal list naming nothing typed, which that handler
     * declines; a StaticCall carries no key list this can read, so it declines.
     *
     * @return ValueExpressionResult|null
     */
    private function attributeFilterRule(
        ReceiverType $receiver,
        string $methodName,
        AnalysisScope $scope,
        MethodCall|NullsafeMethodCall|StaticCall|null $call,
    ): ?array {
        if (! $call instanceof MethodCall && ! $call instanceof NullsafeMethodCall) {
            return null;
        }

        $models = $receiver->models();

        if (count($models) !== 1 || $models !== $receiver->classes) {
            return null;
        }

        $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));

        if ($keys === null || $keys === []) {
            return $this->attributeRecordResult(nullable: false);
        }

        $result = $this->literalKeyFilterResult($models[0], $keys, $methodName === 'only', $scope->carriesImports);

        // Model::only() keys a name it cannot find to null, so the value is still an attribute-keyed array.
        if ($result === null) {
            return $this->attributeRecordResult(nullable: false);
        }

        return ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }

    /**
     * An `only()`/`except()` answer where the scope imports nothing, such as an override declaring `: static`.
     * The body fallback drops a whole shape naming a token, so a top-level model arm, or a list of one, is spelled as
     * the object it serializes to; any other token, such as an enum or a nested model, leaves `unknown`.
     *
     * @param  ValueExpressionResult  $result
     * @param  MethodCall|NullsafeMethodCall|StaticCall|null  $call  the call whose key list shapes a returned model
     * @return ValueExpressionResult
     */
    private function filterReturnWithoutImports(array $result, string $methodName, MethodCall|NullsafeMethodCall|StaticCall|null $call): array
    {
        if (! TsTypeString::shapeValueHasUnimportableToken($result['type'])) {
            return $result;
        }

        $models = [];

        foreach ([...(isset($result['modelFqcn']) ? [$result['modelFqcn']] : []), ...($result['embeddedModelFqcns'] ?? [])] as $model) {
            if (is_a($model, Model::class, true)) {
                $models[class_basename($model)] = $model;
            }
        }

        $keys = $call instanceof MethodCall || $call instanceof NullsafeMethodCall
            ? $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName))
            : null;
        $arms = [];

        foreach (TsTypeString::splitTopLevelUnion($result['type']) as $arm) {
            $element = Str::chopEnd($arm, '[]');

            if (isset($models[$element])) {
                $shape = $this->serializedModelShape($models[$element], $keys, $methodName === 'only');
                $arm = $element === $arm ? $shape : ValueResult::arrayWrapType($shape);
            } elseif (TsTypeString::shapeValueHasUnimportableToken($arm)) {
                return ValueResult::unknown();
            }

            $arms[] = $arm;
        }

        return [...ValueResult::unknown(), 'type' => TsTypeString::hoistNull($arms)];
    }

    /**
     * The object a model an override returns serializes to: the columns and appended accessors it writes, narrowed to
     * the call's literal keys. What `$hidden`, `$visible` or a missing append keeps out never reaches JSON, so it is
     * never named; a member naming a token is `unknown`, and runtime or empty keys leave `Record<string, unknown>`.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>|null  $keys
     */
    private function serializedModelShape(string $model, ?array $keys, bool $include): string
    {
        if ($keys === null) {
            return 'Record<string, unknown>';
        }

        $names = resolve(ModelAttributeResolver::class)->serializedAttributeNames($model);
        $picked = $include ? array_values(array_intersect(array_unique($keys), $names)) : array_values(array_diff($names, $keys));
        $shape = $picked === [] ? null : $this->literalKeyFilterResult($model, $picked, true, carriesImports: false);

        return $shape['type'] ?? 'Record<string, unknown>';
    }

    /**
     * Whether a concrete model runs Model::getKey() itself, whose `mixed` only the key type narrows.
     *
     * An override declares its own return, which PHP's covariance holds every subclass to, so reflection answers it.
     *
     * @param  class-string  $class
     */
    private function runsModelGetKey(string $class): bool
    {
        return is_a($class, Model::class, true)
            && ! new ReflectionClass($class)->isAbstract()
            && new ReflectionMethod($class, 'getKey')->getDeclaringClass()->getName() === Model::class;
    }

    /**
     * Type one class's method: a receiver-returning method keeps the receiver's own type, anything else reflects.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function resolveOn(string $class, string $methodName, bool $fromInside): ?array
    {
        if (! method_exists($class, $methodName)) {
            return null;
        }

        $method = new ReflectionMethod($class, $methodName);

        // A non-public method called from outside goes to __call(), never to the declaration.
        if (! $fromInside && ! $method->isPublic()) {
            return null;
        }

        // A model's toArray() serializes whichever relations are loaded, runtime state no declaration describes.
        if ($methodName === 'toArray' && is_a($class, Model::class, true)) {
            return null;
        }

        if (StringSerialization::methodReturnsFalseString($class, $methodName)) {
            return null;
        }

        $result = resolve(ReceiverClassResolver::class)->returnClasses($class, $methodName) === [$class]
            ? $this->selfType($class, $this->returnAllowsNull($method))
            : resolve(MethodReturnTypeResolver::class)->resolve($class, $methodName);

        // A vague `unknown[]` claims a list where an associative array or a keyBy() collection is a JSON object.
        if ($result === null || TsTypeString::isVagueTsType($result['type'])) {
            return null;
        }

        return ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }

    /**
     * The TypeScript type of an instance of the receiver class itself, when it has one that can be imported.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function selfType(string $class, bool $nullable): ?array
    {
        $result = resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::toTsType($class));

        if ($result === null) {
            return null;
        }

        if ($nullable && ! in_array('null', TsTypeString::splitTopLevelUnion($result['type']), true)) {
            $result['type'] .= ' | null';
        }

        return $result;
    }

    /**
     * Whether a method's native return type, else its `@return` docblock, admits null.
     */
    private function returnAllowsNull(ReflectionMethod $method): bool
    {
        $native = $method->getReturnType();

        if ($native !== null) {
            return $native->allowsNull();
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $method->getDocComment()) ?? '';

        foreach (LaravelTsPublish::splitPhpDocUnionType($docblock) as $part) {
            $part = trim($part);

            if (strtolower($part) === 'null' || str_starts_with($part, '?')) {
                return true;
            }
        }

        return false;
    }
}
