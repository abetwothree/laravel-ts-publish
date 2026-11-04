<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use ReflectionMethod;

/**
 * Types what a method's body could not, from that same method's own `@return` docblock.
 *
 * Only a property the body left vague is filled, so a stale docblock can never overwrite a type the body resolved.
 *
 * @phpstan-import-type ShapeTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type CastMap from MethodAnalysis
 *
 * @internal
 */
final class ReturnShapeRefiner
{
    /**
     * Fill each key the body left vague, its `null` kept, from the method's own `@return array{...}` shape or
     * `array<string, V>` value, queue the classes the fill names as a cast's are, and mark a `key?:` key optional.
     * Only a spread helper keeps a bare name no class answers to: the analyzed method's may be a class never imported.
     */
    public function refine(MethodAnalysis $analysis, ReflectionMethod $method, bool $keepsUnresolvedNames = true): void
    {
        $shape = LaravelTsPublish::docblockReturnShapeInfos($method);
        $valueType = $shape === [] ? $this->recordValueType($method) : null;
        $untypedSignature = TsTypeString::orUndefined('unknown');
        $imports = [];

        /** @var CastMap $filled */
        $filled = [];

        foreach ($analysis->properties as &$property) {
            $name = $property['name'];
            $doc = $shape[$name] ?? $shape[$name.'?'] ?? $valueType;
            $isSignature = JsEmitter::isIndexSignatureKey($name);
            $resolved = null;

            // A signature fills only through the branch below, which keeps the floor a reconcile can put back; a spread
            // helper's own fill, kept beside a put-back floor, outranks this method's docblock.
            if (! $isSignature && TsTypeString::isVagueOnly($property['type'])) {
                $resolved = $this->resolvedType($doc, $property['type'] === 'unknown', $method, $keepsUnresolvedNames);

                if ($resolved !== null) {
                    $property['type'] = ValueResult::hasNullArm($property['type'])
                        ? ValueResult::withNullArm($resolved['type'])
                        : $resolved['type'];
                }
            } elseif ($property['type'] === $untypedSignature && $isSignature && ! isset($property['fillType'])) {
                $resolved = $this->resolvedType($doc, true, $method, $keepsUnresolvedNames);

                // A key matching the pattern may still be absent at runtime, so the value keeps its `| undefined`;
                // the body's own value is kept for IndexSignatureReconciler to put back where the fill would conflict.
                if ($resolved !== null) {
                    $property['bodyType'] = $property['type'];
                    $property['type'] = TsTypeString::orUndefined($resolved['type']);
                    $property['fillType'] = $property['type'];
                }
            }

            if ($resolved !== null) {
                $imports[] = $resolved['customImports'];
                $filled[$name] = [
                    'type' => $property['type'],
                    'import' => null,
                    'customImports' => $resolved['customImports'],
                    'tokenFqcns' => $resolved['tokenFqcns'] ?? [],
                ];
            }

            $property['optional'] = $property['optional'] || isset($shape[$name.'?']);
        }

        unset($property);
        $imports = array_filter($imports);

        if ($imports !== []) {
            $analysis->customImports = ImportMaps::merge($analysis->customImports, ...$imports);
        }

        resolve(CastChannels::class)->fit($analysis, $filled);
    }

    /**
     * The value type of a `@return array<string, V>` docblock, one `Record<string, V>`, with the custom imports it
     * spells and the class behind each of its tokens, or null for any other return type.
     *
     * @return ShapeTypeInfo|null
     */
    private function recordValueType(ReflectionMethod $method): ?array
    {
        $info = LaravelTsPublish::docblockReturnTypeInfo($method);
        $arguments = TsTypeShape::recordArguments($info['type']);

        // `Record<string, ` names no class, so the record's token slots are its value's.
        return $arguments !== null && $arguments[0] === 'string' ? [...$info, 'type' => $arguments[1]] : null;
    }

    /**
     * The type to publish for one key, with the custom imports and classes it needs: the member with each arm once,
     * kept where its own custom imports and classes bring in every name it spells, else the bare name the app itself
     * declares. Null leaves the property alone, as it does for a vague member over a key the body typed at all.
     *
     * @param  ShapeTypeInfo|null  $doc
     * @param  bool  $untyped  whether the body left the key exactly `unknown`, which no docblock member can widen
     * @return ShapeTypeInfo|null
     */
    private function resolvedType(?array $doc, bool $untyped, ReflectionMethod $method, bool $keepsUnresolvedNames): ?array
    {
        $member = $doc === null ? null : LaravelTsPublish::shapeMemberForKey($doc);

        if ($doc === null || $member === null) {
            return null;
        }

        if ($member['type'] !== 'unknown') {
            $vague = array_any(
                TsTypeString::splitTopLevelUnion($member['type']),
                fn (string $arm): bool => TsTypeString::isVagueOnly($arm),
            );

            return $untyped || ! $vague ? $member : null;
        }

        $phpDoc = $doc['phpDoc'] ?? null;

        if (! $untyped || ! $keepsUnresolvedNames || $phpDoc === null || preg_match('/^[A-Z]\w*$/', $phpDoc) !== 1) {
            return null;
        }

        // A shape value naming no PHP type at all resolves to 'unknown', but the app declares it as a global
        // (`custom_val: CustomObject`), so its own name stays the most specific thing known. A name a class answers
        // to needs an import this map cannot carry.
        return $this->namesClass($phpDoc, $method) ? null : ['type' => $phpDoc, 'customImports' => []];
    }

    /** Whether a docblock name resolves to a class, through its file's `use` statements and namespace. */
    private function namesClass(string $name, ReflectionMethod $method): bool
    {
        $fqcn = DocblockNames::resolve($name, LaravelTsPublish::methodDeclaringFileClass($method));

        return DocblockNames::isClassLike($fqcn);
    }
}
