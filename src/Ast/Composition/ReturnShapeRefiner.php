<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use ReflectionMethod;

/**
 * Types what a method's body could not, from that same method's own `@return` docblock.
 *
 * Only an untyped property is filled, so a stale docblock can never overwrite a type the body resolved.
 *
 * @phpstan-import-type ShapeTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 *
 * @internal
 */
final class ReturnShapeRefiner
{
    /**
     * Fill every property the body left untyped (`unknown`, or an index signature's `unknown | undefined`) from the
     * method's own `@return array{...}` shape or `array<string, V>` value type, and mark a `key?:` key optional.
     * Only a spread helper keeps a bare name no class answers to: the analyzed method's may be a class never imported.
     */
    public function refine(MethodAnalysis $analysis, ReflectionMethod $method, bool $keepsUnresolvedNames = true): void
    {
        $shape = LaravelTsPublish::docblockReturnShapeInfos($method);
        $rawShape = $shape === [] ? [] : $this->rawShapeTypes($method);
        $valueType = $shape === [] ? $this->recordValueType($method) : null;
        $untypedSignature = TsTypeString::orUndefined('unknown');
        $imports = [];

        foreach ($analysis->properties as &$property) {
            $name = $property['name'];
            $doc = $shape[$name] ?? $shape[$name.'?'] ?? $valueType;
            $rawType = $rawShape[$name] ?? $rawShape[$name.'?'] ?? null;

            $isSignature = JsEmitter::isIndexSignatureKey($name);
            $resolved = null;

            // A signature fills only through the branch below, which keeps the floor a reconcile can put back; a spread
            // helper's own fill, kept beside a put-back floor, outranks this method's docblock.
            if ($property['type'] === 'unknown' && ! $isSignature) {
                $resolved = $this->resolvedType($doc, $rawType, $method, $keepsUnresolvedNames);
                $property['type'] = $resolved['type'] ?? $property['type'];
            } elseif ($property['type'] === $untypedSignature && $isSignature && ! isset($property['fillType'])) {
                $resolved = $this->resolvedType($doc, $rawType, $method, $keepsUnresolvedNames);

                // A key matching the pattern may still be absent at runtime, so the value keeps its `| undefined`;
                // the body's own value is kept for IndexSignatureReconciler to put back where the fill would conflict.
                if ($resolved !== null) {
                    $property['bodyType'] = $property['type'];
                    $property['type'] = TsTypeString::orUndefined($resolved['type']);
                    $property['fillType'] = $property['type'];
                }
            }

            $imports[] = $resolved['customImports'] ?? [];
            $property['optional'] = $property['optional'] || isset($shape[$name.'?']);
        }

        unset($property);
        $imports = array_filter($imports);

        if ($imports !== []) {
            $analysis->customImports = ImportMaps::merge($analysis->customImports, ...$imports);
        }
    }

    /**
     * The value type of a `@return array<string, V>` docblock with the custom imports it spells, or null for any other
     * return type.
     *
     * @return ShapeTypeInfo|null
     */
    private function recordValueType(ReflectionMethod $method): ?array
    {
        $info = LaravelTsPublish::docblockReturnTypes($method);

        return preg_match('/^Record<string, (.+)>$/', $info['type'], $m) === 1
            ? ['type' => $m[1], 'customImports' => $info['customImports']]
            : null;
    }

    /**
     * The type to publish for one key, with the custom imports it needs: the resolved shape value with each arm once,
     * kept where its own custom imports bring in every name it spells, else the bare name the app itself declares.
     * Null leaves the property alone.
     *
     * @param  ShapeTypeInfo|null  $doc
     * @return ShapeTypeInfo|null
     */
    private function resolvedType(
        ?array $doc,
        ?string $rawType,
        ReflectionMethod $method,
        bool $keepsUnresolvedNames,
    ): ?array {
        $docType = $doc === null ? 'unknown' : implode(' | ', array_unique(TsTypeString::splitTopLevelUnion($doc['type'])));

        if ($doc !== null && $docType !== 'unknown') {
            return TsTypeString::importsCover($docType, $doc['customImports'])
                ? ['type' => $docType, 'customImports' => $doc['customImports']]
                : null;
        }

        if (! $keepsUnresolvedNames || $rawType === null || preg_match('/^[A-Z]\w*$/', $rawType) !== 1) {
            return null;
        }

        // A shape value naming no PHP type at all resolves to 'unknown', but the app declares it as a global
        // (`custom_val: CustomObject`), so its own name stays the most specific thing known. A name a class answers
        // to needs an import this map cannot carry.
        return $this->namesClass($rawType, $method) ? null : ['type' => $rawType, 'customImports' => []];
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
     * The method's `@return array{...}` keys, an optional one with its `?`, mapped to their raw PHPDoc type strings.
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
        $shape = $returnType === null ? null : LaravelTsPublish::arrayShapeEntries($returnType);

        if ($shape === null) {
            return []; // @codeCoverageIgnore
        }

        $types = [];

        foreach ($shape['entries'] as $entry) {
            $types[$entry['key'].($entry['optional'] ? '?' : '')] = $entry['type'];
        }

        return $types;
    }
}
