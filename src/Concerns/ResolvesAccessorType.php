<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * Resolves the TypeScript type of a model accessor or mutator by name,
 * handling both new-style Attribute::make() and old-style get*Attribute() patterns.
 *
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 */
trait ResolvesAccessorType
{
    use ResolvesClassNames;

    /**
     * Resolve the TypeScript type info for a model accessor/mutator by attribute name.
     *
     * Handles new-style `Attribute::make(get: fn () => ...)` and old-style `get*Attribute()`.
     *
     * @param  ReflectionClass<Model>  $reflectionModel
     * @return TypeScriptTypeInfo
     */
    protected function resolveAccessorType(string $name, Model $modelInstance, ReflectionClass $reflectionModel): array
    {
        $result = LaravelTsPublish::emptyTypeScriptInfo();
        $newStyle = Str::camel($name);
        $oldStyle = 'get'.Str::studly($name).'Attribute';

        // New-style: protected function titleDisplay(): Attribute
        // Must invoke via reflection because the method is protected
        if ($reflectionModel->hasMethod($newStyle)) {
            $method = $reflectionModel->getMethod($newStyle);
            $method->setAccessible(true);

            $attrInstance = $method->invoke($modelInstance);

            if ($attrInstance instanceof Attribute) {
                if ($attrInstance->get !== null) {
                    /** @var \Closure $getter */
                    $getter = $attrInstance->get;

                    $getterReturn = LaravelTsPublish::closureReturnedTypes($getter);
                    if ($getterReturn['type'] !== 'unknown') {
                        return $getterReturn;
                    }

                    // read from doc blocks */
                    return $this->resolveAccessorTypeFromMethodDocBlock($method);
                }

                // write-only mutator (set only, no get) — not readable on the model shape
                return $result;
            }
        }

        // Old-style: public function getTitleDisplayAttribute($value): string
        if ($reflectionModel->hasMethod($oldStyle)) {
            $getterReturn = LaravelTsPublish::methodReturnedTypes($reflectionModel, $oldStyle);
            if ($getterReturn['type'] !== 'unknown') {
                return $getterReturn;
            }

            // read from doc blocks */
            return $this->resolveAccessorTypeFromMethodDocBlock($reflectionModel->getMethod($oldStyle));
        }

        return $result;
    }

    /**
     * Attempt to read from @return Attribute<string, never> or similar docblock on the accessor method.
     *
     * The first parameter of Attribute<string, never> is the return type of the getter,
     * and the second parameter is the accepted type for the setter.
     *
     * @return TypeScriptTypeInfo
     */
    protected function resolveAccessorTypeFromMethodDocBlock(ReflectionMethod $method): array
    {
        $emptyResult = LaravelTsPublish::emptyTypeScriptInfo();
        $docComment = $method->getDocComment();
        if ($docComment === false) {
            return $emptyResult;
        }

        // Look for @return Attribute<GetterType, SetterType>
        if (preg_match('/@return\s+Attribute\s*<\s*([^,>\s]+)\s*,\s*([^>]+)\s*>/i', $docComment, $matches)) {
            $getterType = trim($matches[1]);

            return $this->resolveAccessorTypesFromMatches($method, $getterType);
        }

        // look for @return TypeName (non-Attribute) — less precise, but still better than unknown
        if (preg_match('/@return\s+([^\s]+)/', $docComment, $matches)) {
            $getterType = trim($matches[1]);

            return $this->resolveAccessorTypesFromMatches($method, $getterType);
        }

        return $emptyResult; // @codeCoverageIgnore
    }

    /** @return TypeScriptTypeInfo */
    protected function resolveAccessorTypesFromMatches(ReflectionMethod $method, string $getterType): array
    {
        $parts = array_map('trim', explode('|', $getterType));

        // Resolve each part against the declaring class's use statements so that
        // short names (after Pint reformats FQCNs) still resolve to the right class.
        $declaringClass = $method->getDeclaringClass();
        $parts = array_map(
            fn (string $part) => $this->resolveDocblockType($part, $declaringClass),
            $parts,
        );

        if (count($parts) === 1) {
            return LaravelTsPublish::toTsType($parts[0]);
        }

        // Union type: convert each component and merge all metadata
        $infos = array_map(
            fn (string $part) => LaravelTsPublish::toTsType($this->resolveDocblockType($part, $declaringClass)),
            $parts,
        );

        return LaravelTsPublish::mergeTypeScriptInfos($infos);
    }
}
