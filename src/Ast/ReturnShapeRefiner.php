<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use ReflectionMethod;

/**
 * Types what a method's body could not, from that same method's own `@return` docblock.
 *
 * The body always wins: this only fills a property the AST left `unknown`, so a stale docblock can
 * never overwrite a type the analyzer actually resolved.
 *
 * @internal
 */
final class ReturnShapeRefiner
{
    /**
     * Fill every `unknown` property from the method's own `@return array{...}` shape (or
     * `array<string, V>` value type), and mark a key the shape declares `key?:` optional.
     */
    public function refine(MethodAnalysis $analysis, ReflectionMethod $method): void
    {
        $shape = LaravelTsPublish::parseDocblockReturnArrayShape($method);
        $rawShape = $shape === [] ? [] : $this->rawShapeTypes($method);
        $valueType = $shape === [] ? $this->recordValueType($method) : null;

        foreach ($analysis->properties as &$property) {
            $name = $property['name'];
            $docType = $shape[$name] ?? $shape[$name.'?'] ?? $valueType;

            if ($property['type'] === 'unknown') {
                $property['type'] = $this->resolvedType($docType, $rawShape[$name] ?? $rawShape[$name.'?'] ?? null)
                    ?? $property['type'];
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
     * The type to publish for one key: the resolved shape value, else the bare name the app itself
     * declares. Null leaves the property alone.
     */
    private function resolvedType(?string $docType, ?string $rawType): ?string
    {
        if ($docType !== null && $docType !== 'unknown') {
            return TsTypeString::shapeValueHasUnimportableToken($docType) ? null : $docType;
        }

        // A shape value naming no PHP type at all resolves to 'unknown', but the app declares it as a
        // global (`custom_val: CustomObject`), so its own name stays the most specific thing known. It is
        // deliberately unguarded: an undeclared token cannot ship, because tsc reports TS2304 at baseline 0.
        return $rawType !== null && preg_match('/^[A-Z]\w*$/', $rawType) === 1 ? $rawType : null;
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
