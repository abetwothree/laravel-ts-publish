<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use ReflectionMethod;

/**
 * Types what a method's body could not, from that same method's own `@return` docblock.
 *
 * Only a property the body left vague is filled, or an `unknown` position inside one it typed, so a stale docblock can
 * never overwrite a type the body resolved.
 *
 * @phpstan-import-type ShapeTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @internal
 */
final class ReturnShapeRefiner
{
    /**
     * Fill each key the body left vague, its `null` kept, from the method's own `@return array{...}` shape or
     * `array<string, V>` value, queue the classes the fill names as a cast's are, and mark a `key?:` key optional.
     * Only a spread helper keeps a bare name no class answers to: the analyzed method's may be a class never imported.
     *
     * In a key the body typed, each `unknown` member, element or record value takes the member's type at that position
     * (filledPositions()), unless $fillsPositions says the caller lays the docblock over every key itself.
     */
    public function refine(
        MethodAnalysis $analysis,
        ReflectionMethod $method,
        bool $keepsUnresolvedNames = true,
        bool $fillsPositions = true,
    ): void {
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
                    $property['type'] = TsTypeString::withNullArmOf($property['type'], $resolved['type']);
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
            } elseif (! $isSignature && $fillsPositions) {
                [$property['type'], $brought] = $this->filledPositions($analysis, $name, $property['type'], $doc);
                $imports[] = $brought;
            }

            if ($resolved !== null) {
                $imports[] = $resolved['customImports'];
                // A member naming no class declares no slots, so the fit leaves a key with no channel as it is.
                $filled[$name] = [
                    'type' => $property['type'],
                    'import' => null,
                    'customImports' => $resolved['customImports'],
                    ...(isset($resolved['tokenFqcns']) ? ['tokenFqcns' => $resolved['tokenFqcns']] : []),
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
     * A key the body typed, each `unknown` position in it filled from the member $doc gives it, as UnknownPositions
     * fills them, each class the fill names queued on the key's channels as one its body names, beside the custom
     * imports the names it spells need. A name the key cannot give one class alone fills no position.
     *
     * @param  ShapeTypeInfo|null  $doc
     * @return array{string, TypesImportMap}
     */
    private function filledPositions(MethodAnalysis $analysis, string $name, string $type, ?array $doc): array
    {
        $member = $doc === null || ! TsTypeString::typeNameOccursIn('unknown', $type) ? null : LaravelTsPublish::shapeMemberForKey($doc);

        if ($member === null) {
            return [$type, []];
        }

        $classes = array_values(array_unique(array_filter($member['tokenFqcns'] ?? [])));
        $overrides = ImportMaps::localNames($member['customImports']);
        $held = $analysis->channelFqcns($name);
        $queued = array_values(array_filter(
            $classes,
            fn (string $class): bool => $this->queuesBeside($class, $classes, $overrides, $held, $type),
        ));
        $heldNames = TokenSlots::namesOf('classes', $held);
        $safeOverrides = array_values(array_filter(
            $overrides,
            static fn (string $override): bool => ! in_array($override, $heldNames, true) && ! TokenSlots::readsName($override, $type),
        ));
        $blocked = array_values(array_diff(
            [...TokenSlots::namesOf('classes', $classes), ...$overrides],
            [...TokenSlots::namesOf('classes', $queued), ...$safeOverrides],
        ));
        $filled = UnknownPositions::filled(
            $type,
            $member['type'],
            static fn (string $fill): bool => ! array_any($blocked, static fn (string $blockedName): bool => TsTypeString::typeNameOccursIn($blockedName, $fill)),
        );

        if ($filled === $type) {
            return [$type, []];
        }

        $analysis->queueEmbeddedClasses($name, array_values(array_filter(
            $queued,
            static fn (string $class): bool => ! in_array($class, $held, true)
                && array_any(TokenSlots::namesOf('classes', [$class]), static fn (string $spelled): bool => TokenSlots::readsName($spelled, $filled)),
        )));

        $brought = [];

        foreach ($member['customImports'] as $path => $names) {
            $spelled = array_values(array_filter($names, static fn (string $import): bool => TokenSlots::readsName(ImportMaps::localName($import), $filled)));

            if ($spelled !== []) {
                $brought[$path] = $spelled;
            }
        }

        return [$filled, $brought];
    }

    /**
     * Whether a class a fill names can join a key's channels, which give each name one class: one of a kind with a
     * channel, whose names no other class or override of the member, nor any other class or text of the key, spells.
     *
     * @param  class-string  $class
     * @param  list<class-string>  $classes  every class the member names
     * @param  list<string>  $overrides  the names the member's own imports bring
     * @param  list<class-string>  $held  the classes on the key's channels
     */
    private function queuesBeside(string $class, array $classes, array $overrides, array $held, string $type): bool
    {
        if (! MethodAnalysis::queuesEmbedded($class)) {
            return false;
        }

        foreach (TokenSlots::namesOf('classes', [$class]) as $spelled) {
            $others = array_filter([...$classes, ...$held], static fn (string $other): bool => $other !== $class);
            $holdsIt = in_array($class, $held, true);

            if (in_array($spelled, [...$overrides, ...TokenSlots::namesOf('classes', array_values($others))], true)
                || (! $holdsIt && TokenSlots::readsName($spelled, $type))
            ) {
                return false;
            }
        }

        return true;
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
     * declares. Null leaves the property alone, as it does for a vague member over a key not exactly `unknown`.
     *
     * @param  ShapeTypeInfo|null  $doc
     * @return ShapeTypeInfo|null
     */
    private function resolvedType(?array $doc, bool $exactlyUnknown, ReflectionMethod $method, bool $keepsUnresolvedNames): ?array
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

            return $exactlyUnknown || ! $vague ? $member : null;
        }

        $phpDoc = $doc['phpDoc'] ?? null;

        if (! $exactlyUnknown || ! $keepsUnresolvedNames || $phpDoc === null || preg_match('/^[A-Z]\w*$/', $phpDoc) !== 1) {
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

        return DeclaredClasses::isClassLike($fqcn);
    }
}
