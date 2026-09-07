<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Resolves the TypeScript type of a model accessor or mutator by name.
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

        // New-style `protected function titleDisplay(): Attribute` — protected, so invoke via reflection.
        if ($reflectionModel->hasMethod($newStyle)) {
            $method = $reflectionModel->getMethod($newStyle);

            $attrInstance = $method->invoke($modelInstance);

            if ($attrInstance instanceof Attribute) {
                if ($attrInstance->get !== null) {
                    /** @var \Closure $getter */
                    $getter = $attrInstance->get;

                    $getterReturn = LaravelTsPublish::closureReturnedTypes($getter);

                    if ($getterReturn['type'] !== 'unknown' && ! $this->isVagueTsType($getterReturn['type'])) {
                        return $getterReturn;
                    }

                    // The docblock may still carry generics the signature erases: Attribute<Collection<int, X>, never>.
                    $docblockReturn = LaravelTsPublish::attributeDocblockReturnTypes($method);

                    if ($docblockReturn['type'] !== 'unknown' && ! $this->isVagueTsType($docblockReturn['type'])) {
                        return $docblockReturn;
                    }

                    if ($getterReturn['type'] !== 'unknown') {
                        return $getterReturn;
                    }

                    return $docblockReturn;
                }

                // Set-only: no getter closure to read a runtime type from, but the method's own
                // docblock may still document a Get generic (Attribute<Get, Set>).
                $docblockReturn = LaravelTsPublish::attributeDocblockReturnTypes($method);

                if ($docblockReturn['type'] !== 'unknown' && ! $this->isVagueTsType($docblockReturn['type'])) {
                    return $docblockReturn;
                }

                // Nothing to read this attribute's shape from at all — the caller decides whether a
                // DB column of the same name still applies; when none does, this signals to omit it.
                return LaravelTsPublish::omittedTypeScriptInfo();
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

    /**
     * A "vague" TS type carries no element information, so a docblock generic can usually do better.
     *
     * Delegates to TsTypeString::isVagueTsType() — the single definition of "vague" shared with
     * methodOrDocblockReturnTypes() — rather than duplicating the predicate here.
     */
    protected function isVagueTsType(string $type): bool
    {
        return TsTypeString::isVagueTsType($type);
    }
}
