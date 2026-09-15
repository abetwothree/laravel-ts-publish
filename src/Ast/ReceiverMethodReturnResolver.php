<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

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
     * @param  bool  $fromInside  the call is `self::`, `static::` or `parent::`, so a non-public method is reachable
     * @return ValueExpressionResult|null null when any receiver class cannot type the method
     */
    public function resolve(ReceiverType $receiver, string $methodName, AnalysisScope $scope, bool $fromInside = false): ?array
    {
        $results = [];

        foreach ($receiver->classes as $class) {
            $result = $this->resolveOn($class, $methodName, $fromInside);

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

        if ($this->namesStringifiedObject($method, $class)) {
            return null;
        }

        $result = resolve(ReceiverClassResolver::class)->returnClasses($class, $methodName) === [$class]
            ? $this->selfType($class, $this->returnAllowsNull($method))
            : resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($class), $methodName));

        // A vague `unknown[]` claims a list where an associative array or a keyBy() collection is a JSON object.
        return $result === null || TsTypeString::isVagueTsType($result['type']) ? null : $result;
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

    /**
     * Whether the declared return names a class toTsType() publishes as `string` that json_encode() emits otherwise.
     *
     * toTsType() reads any `__toString()` as `string`, but json_encode() ignores it: a CarbonInterval is an object.
     */
    private function namesStringifiedObject(ReflectionMethod $method, string $receiverClass): bool
    {
        foreach ($this->declaredReturnClassNames($method, $receiverClass) as $name) {
            if ($this->isStringifiedObject($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every class name a method's native return type and its `@return` docblock spell, `self`/`static` resolved.
     *
     * @return list<string>
     */
    private function declaredReturnClassNames(ReflectionMethod $method, string $receiverClass): array
    {
        $declaring = $method->getDeclaringClass()->getName();
        $native = $method->getReturnType();
        $names = [];

        $arms = match (true) {
            $native instanceof ReflectionUnionType, $native instanceof ReflectionIntersectionType => $native->getTypes(),
            $native === null => [],
            default => [$native],
        };

        foreach ($arms as $arm) {
            // A DNF arm is an intersection nested inside a union: flatten one level to reach its names.
            foreach ($arm instanceof ReflectionIntersectionType ? $arm->getTypes() : [$arm] as $named) {
                if ($named instanceof ReflectionNamedType && (! $named->isBuiltin() || $named->getName() === 'static')) {
                    $names[] = $named->getName();
                }
            }
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $method->getDocComment());

        if ($docblock !== null && preg_match_all('/\$this|[\\\\A-Z][\\\\\w]*|\bs(?:elf|tatic)\b/', $docblock, $matches) > 0) {
            $context = LaravelTsPublish::methodDeclaringFileClass($method);
            $useMap = LaravelTsPublish::parseFileUseStatements($context);

            foreach ($matches[0] as $token) {
                $names[] = in_array($token, ['$this', 'static', 'self'], true)
                    ? $token
                    : LaravelTsPublish::resolveDocblockTypeName($token, $useMap, $context->getNamespaceName());
            }
        }

        return array_map(fn (string $name): string => match ($name) {
            '$this', 'static' => $receiverClass,
            'self' => $declaring,
            default => $name,
        }, $names);
    }

    /**
     * Whether toTsType() would erase a class to `string` through `__toString()` while json_encode() emits no string.
     */
    private function isStringifiedObject(string $class): bool
    {
        if (! class_exists($class)
            || is_a($class, Model::class, true)
            || is_a($class, Arrayable::class, true)
            || ! method_exists($class, '__toString')
        ) {
            return false;
        }

        if (! is_a($class, JsonSerializable::class, true)) {
            return true;
        }

        // Carbon's jsonSerialize() is its ISO string; any other object is a JSON string only when it declares one.
        if (is_a($class, DateTimeInterface::class, true)) {
            return false;
        }

        $serialized = new ReflectionMethod($class, 'jsonSerialize')->getReturnType();

        return ! ($serialized instanceof ReflectionNamedType && $serialized->getName() === 'string');
    }
}
