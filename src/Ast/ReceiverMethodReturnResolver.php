<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\StringSerialization;
use Illuminate\Database\Eloquent\Model;
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
    /**
     * Type a method on every class the receiver holds, unioning the answers.
     *
     * @param  bool  $fromInside  a non-public method is reachable, as through `self::`, `static::` or `parent::`. For
     *                            direct callers resolving the analyzed class's own body: dispatch never passes it today,
     *                            because StaticCallHandler answers every call on a named class first.
     * @return ValueExpressionResult|null null when any receiver class cannot type the method
     */
    public function resolve(ReceiverType $receiver, string $methodName, AnalysisScope $scope, bool $fromInside = false): ?array
    {
        $results = [];

        foreach ($receiver->classes as $class) {
            $result = $this->ruleFor($receiver, $class, $methodName) ?? $this->resolveOn($class, $methodName, $fromInside);

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
    private function ruleFor(ReceiverType $receiver, string $class, string $methodName): ?array
    {
        if ($methodName === 'getKey' && $this->runsModelGetKey($class)) {
            $keyType = $this->keyType($class);

            return $keyType === null ? null : [...ValueResult::unknown(), 'type' => $keyType];
        }

        if ($methodName === 'modelKeys' && $receiver->elementModel !== null) {
            $keyType = $this->keyType($receiver->elementModel);

            return $keyType === null ? null : [...ValueResult::unknown(), 'type' => $keyType.'[]'];
        }

        return null;
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
     * The TypeScript spelling of a model's primary key type, or null when the model cannot be instantiated.
     *
     * @param  class-string  $modelFqcn
     */
    private function keyType(string $modelFqcn): ?string
    {
        $instance = resolve(ModelAttributeResolver::class)->getInstance($modelFqcn);

        if ($instance === null) {
            return null;
        }

        // getCasts() casts an incrementing key as getKeyType(), and castAttribute() treats `int` and `integer` alike.
        return in_array($instance->getKeyType(), ['int', 'integer'], true) ? 'number' : 'string';
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
            : resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($class), $methodName));

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
