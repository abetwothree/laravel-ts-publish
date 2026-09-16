<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\Model\AccessorBodyAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Resolves the TypeScript type of a model accessor or mutator by name.
 *
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 */
trait ResolvesAccessorType
{
    use NamesAccessorMethods;
    use ResolvesClassNames;

    /**
     * Resolve the TypeScript type info for a model accessor/mutator by attribute name.
     *
     * Handles new-style `Attribute::make(get: fn () => ...)` and old-style `get*Attribute()`.
     *
     * @param  ReflectionClass<Model>  $reflectionModel
     * @param  bool  $carriesImports  false when the reader carries no import; only the getter body step reads it
     * @return TypeScriptTypeInfo
     */
    protected function resolveAccessorType(
        string $name,
        Model $modelInstance,
        ReflectionClass $reflectionModel,
        bool $carriesImports = true,
    ): array {
        $result = LaravelTsPublish::emptyTypeScriptInfo();
        ['newStyle' => $newStyle, 'oldStyle' => $oldStyle] = $this->accessorMethodNames($name);

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

                    // Both annotations are vague, so what the getter body returns is the better answer.
                    $bodyReturn = $this->resolveAccessorBodyType($reflectionModel->getName(), $name, $carriesImports);

                    if ($bodyReturn !== null) {
                        return $bodyReturn;
                    }

                    if ($getterReturn['type'] !== 'unknown') {
                        return $getterReturn;
                    }

                    return $docblockReturn;
                }

                // A `never` Get — bare or its nullable spelling (`?never`, `never|null`, stripped before
                // comparing) — states that no getter exists, not what reading returns; the raw column
                // applies instead, so this falls through to omittedTypeScriptInfo() rather than publish it.
                $docblockReturn = LaravelTsPublish::attributeDocblockReturnTypes($method);
                $docblockNonNullType = ValueResult::stripNullArm($docblockReturn['type']);

                if (
                    $docblockReturn['type'] !== 'unknown'
                    && $docblockNonNullType !== 'never'
                    && ! $this->isVagueTsType($docblockReturn['type'])
                ) {
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

            if ($getterReturn['type'] !== 'unknown' && ! $this->isVagueTsType($getterReturn['type'])) {
                return $getterReturn;
            }

            $bodyReturn = $this->resolveAccessorBodyType($reflectionModel->getName(), $name, $carriesImports);

            if ($bodyReturn !== null) {
                return $bodyReturn;
            }

            if ($getterReturn['type'] !== 'unknown') {
                return $getterReturn;
            }
        }

        return $result;
    }

    /**
     * The getter body's type when it beats vague annotations, or null to fall back to them.
     *
     * A reader that carries no import gets the getter analyzed without imports. That spelling can be vague where the
     * published one is not, `unknown[]` for `Comment[]`, so it still wins wherever the published body answer wins.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return TypeScriptTypeInfo|null
     */
    protected function resolveAccessorBodyType(string $modelFqcn, string $name, bool $carriesImports): ?array
    {
        $analyzer = resolve(AccessorBodyAnalyzer::class);
        $bodyReturn = $analyzer->analyze($modelFqcn, $name, $carriesImports);

        if ($bodyReturn === null || ! $this->isVagueTsType($bodyReturn['type'])) {
            return $bodyReturn;
        }

        if ($carriesImports) {
            return null;
        }

        $publishedReturn = $analyzer->analyze($modelFqcn, $name);

        return $publishedReturn !== null && ! $this->isVagueTsType($publishedReturn['type']) ? $bodyReturn : null;
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
