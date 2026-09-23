<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use ReflectionMethod;

/**
 * Types what a method's body could not, from that same method's own `@return` docblock.
 *
 * Only an untyped property is filled, so a stale docblock can never overwrite a type the body resolved.
 *
 * @internal
 */
final class ReturnShapeRefiner
{
    /**
     * Fill every property the body left untyped from the method's own `@return array{...}` shape (or
     * `array<string, V>` value type), and mark a key the shape declares `key?:` optional. Untyped means `unknown`,
     * or `unknown | undefined` on an interpolated key's index signature, which keeps its `| undefined` once filled.
     *
     * A bare name no class answers to is kept only for a spread helper, whose `@return` always published it; the
     * analyzed method's own docblock is new to this path, and there the name may be a class its file never imported.
     */
    public function refine(MethodAnalysis $analysis, ReflectionMethod $method, bool $keepsUnresolvedNames = true): void
    {
        $shape = LaravelTsPublish::parseDocblockReturnArrayShape($method);
        $rawShape = $shape === [] ? [] : $this->rawShapeTypes($method);
        $valueType = $shape === [] ? $this->recordValueType($method) : null;
        $untypedSignature = TsTypeString::orUndefined('unknown');

        foreach ($analysis->properties as &$property) {
            $name = $property['name'];
            $docType = $shape[$name] ?? $shape[$name.'?'] ?? $valueType;
            $rawType = $rawShape[$name] ?? $rawShape[$name.'?'] ?? null;

            if ($property['type'] === 'unknown') {
                $property['type'] = $this->resolvedType($docType, $rawType, $method, $keepsUnresolvedNames)
                    ?? $property['type'];
            } elseif ($property['type'] === $untypedSignature && JsEmitter::isIndexSignatureKey($name)) {
                $resolved = $this->resolvedType($docType, $rawType, $method, $keepsUnresolvedNames);

                // A key matching the pattern may still be absent at runtime, so the value keeps its `| undefined`;
                // the body's own value is kept for IndexSignatureReconciler to put back where the fill would conflict.
                if ($resolved !== null) {
                    $property['bodyType'] = $property['type'];
                    $property['type'] = TsTypeString::orUndefined($resolved);
                }
            }

            $property['optional'] = $property['optional'] || isset($shape[$name.'?']);
        }

        unset($property);
    }

    /** The value type of a `@return array<string, V>` docblock, or null for any other return type. */
    private function recordValueType(ReflectionMethod $method): ?string
    {
        $type = LaravelTsPublish::docblockReturnTypes($method)['type'];

        return preg_match('/^Record<string, (.+)>$/', $type, $m) === 1 ? $m[1] : null;
    }

    /**
     * The type to publish for one key: the resolved shape value with each arm once, else the bare name the app
     * itself declares. Null leaves the property alone.
     */
    private function resolvedType(
        ?string $docType,
        ?string $rawType,
        ReflectionMethod $method,
        bool $keepsUnresolvedNames,
    ): ?string {
        $docType = $docType === null ? null : implode(' | ', array_unique(TsTypeString::splitTopLevelUnion($docType)));

        if ($docType !== null && $docType !== 'unknown') {
            return TsTypeString::shapeValueHasUnimportableToken($docType) ? null : $docType;
        }

        if (! $keepsUnresolvedNames || $rawType === null || preg_match('/^[A-Z]\w*$/', $rawType) !== 1) {
            return null;
        }

        // A shape value naming no PHP type at all resolves to 'unknown', but the app declares it as a global
        // (`custom_val: CustomObject`), so its own name stays the most specific thing known. A name a class answers
        // to needs an import this string-only map cannot carry.
        return $this->namesClass($rawType, $method) ? null : $rawType;
    }

    /** Whether a docblock name resolves to a class, through its file's `use` statements and namespace. */
    private function namesClass(string $name, ReflectionMethod $method): bool
    {
        $file = LaravelTsPublish::methodDeclaringFileClass($method);
        $useMap = LaravelTsPublish::parseFileUseStatements($file);
        $fqcn = LaravelTsPublish::resolveDocblockTypeName($name, $useMap, $file->getNamespaceName());

        return class_exists($fqcn) || interface_exists($fqcn) || enum_exists($fqcn) || trait_exists($fqcn);
    }

    /**
     * The method's `@return array{...}` keys mapped to their raw, unresolved PHPDoc type strings.
     *
     * @return array<string, string>
     */
    private function rawShapeTypes(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return []; // @codeCoverageIgnore
        }

        $returnType = LaravelTsPublish::extractReturnTypeFromDocblock($docComment);

        if ($returnType === null || ! str_starts_with($returnType, 'array{') || ! str_ends_with($returnType, '}')) {
            return []; // @codeCoverageIgnore
        }

        $types = [];

        foreach (LaravelTsPublish::splitAtTopLevelCommas(trim(substr($returnType, 6, -1))) as $entry) {
            if (preg_match('/^(\w+)(\??)\s*:\s*(.+)$/s', trim($entry), $m) === 1) {
                $types[$m[1].$m[2]] = trim($m[3]);
            }
        }

        return $types;
    }
}
