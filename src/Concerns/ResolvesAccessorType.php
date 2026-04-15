<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

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

            $attrInstance = $method->invoke($modelInstance);

            if ($attrInstance instanceof Attribute) {
                if ($attrInstance->get !== null) {
                    /** @var \Closure $getter */
                    $getter = $attrInstance->get;

                    $getterReturn = LaravelTsPublish::closureReturnedTypes($getter);

                    if ($getterReturn['type'] !== 'unknown') {
                        return $getterReturn;
                    }

                    // read from doc blocks — tries Attribute<Get, Set> first, then @return
                    return LaravelTsPublish::attributeDocblockReturnTypes($method);
                }

                // write-only mutator (set only, no get) — not readable on the model shape
                return $result;
            }
        }

        // Old-style: public function getTitleDisplayAttribute($value): string
        if ($reflectionModel->hasMethod($oldStyle)) {
            $getterReturn = LaravelTsPublish::methodOrDocblockReturnTypes($reflectionModel, $oldStyle);

            if ($getterReturn['type'] !== 'unknown') {
                return $getterReturn;
            }
        }

        return $result;
    }
}
