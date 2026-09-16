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
     * @param  bool  $fromInside  a non-public method is reachable, as through `self::`, `static::` or `parent::`. For
     *                            direct callers resolving the analyzed class's own body: dispatch never passes it today,
     *                            because StaticCallHandler answers every call on a named class first.
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
            $result = $this->ruleFor($receiver, $class, $methodName, $call) ?? $this->resolveOn($class, $methodName, $fromInside);

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        if (count($results) === 1) {
            return $results[0];
        }

        return ValueResult::mergeUnion(array_values(array_unique(array_column($results, 'type'))), $results);
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

        if (in_array($methodName, $this->supportedAttributeFilters(), true)) {
            return $this->attributeFilterRule($receiver, $methodName, $call);
        }

        return null;
    }

    /**
     * `only()`/`except()` on a lone concrete model receiver, typed as that model's own filtered members.
     *
     * Builds exactly what RelationFilterHandler builds for a relation to the same model, so the two agree
     * wherever both claim a call. Without a literal key list it is the model's attribute-keyed array,
     * `Record<string, unknown>`. A StaticCall carries no filter keys this can read, so it declines.
     *
     * @return ValueExpressionResult|null
     */
    private function attributeFilterRule(
        ReceiverType $receiver,
        string $methodName,
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

        // A runtime key list names nothing to pick, but either filter still returns an array keyed by attribute name.
        if ($keys === null || $keys === []) {
            return [...ValueResult::unknown(), 'type' => 'Record<string, unknown>'];
        }

        $include = $methodName === 'only';
        $reference = $this->relationFilterModelReference($models[0], $keys, $include);

        if ($reference !== null) {
            $result = [...ValueResult::unknown(), 'type' => $reference, 'modelFqcn' => $models[0]];

            return ValueResult::namesOnlyPublishedModels($result) ? $result : null;
        }

        $filtered = $this->resolveFilteredRelationType($models[0], $keys, $include);

        if ($filtered['type'] === 'unknown') {
            return null;
        }

        $result = [
            ...ValueResult::unknown(),
            'type' => $filtered['type'],
            'embeddedEnumFqcns' => $filtered['enumFqcns'],
            'embeddedModelFqcns' => $filtered['modelFqcns'],
            'customImports' => $filtered['customImports'],
        ];

        return ValueResult::namesOnlyPublishedModels($result) ? $result : null;
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
