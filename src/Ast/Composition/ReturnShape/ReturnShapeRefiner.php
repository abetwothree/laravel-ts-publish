<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Support\Facades\Config;
use ReflectionMethod;

/**
 * Types what a method's body could not, from that same method's own `@return` docblock.
 *
 * A property the body left vague takes the member whole; one it typed merges with it position by position, so the body
 * decides which members are sent and a declaration only refines a reading it is a subtype of (DeclaredTypeMerge).
 *
 * @phpstan-import-type ShapeTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type Slots from TokenSlots
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
     * A key the body typed merges with its member at every position (merged()), unless $fillsPositions says the caller
     * lays the docblock over every key itself.
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
                [$property['type'], $brought, $resolved] = $this->merged($analysis, $name, $property['type'], $doc);
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
     * A key the body typed, merged with the member $doc gives it position by position (DeclaredTypeMerge), the body's
     * classes read as the publisher reads the key and the member's from its slots.
     *
     * Each class the merged type names is queued on the key's channels in print order, as its body's are (requeued()),
     * or, where they cannot say which token is whose, declared token by token as a whole key's fill is.
     *
     * @param  ShapeTypeInfo|null  $doc
     * @return array{string, TypesImportMap, ShapeTypeInfo|null} the type, the imports it brings, and a fill to lay over
     *                                                           the key's channels as a cast is
     */
    private function merged(MethodAnalysis $analysis, string $name, string $type, ?array $doc): array
    {
        $member = $doc === null ? null : LaravelTsPublish::shapeMemberForKey($doc);

        // One text merges to itself, whichever classes either side means by it.
        if ($member === null || $member['type'] === $type) {
            return [$type, [], null];
        }

        $declaredSlots = $member['tokenFqcns'] ?? [];
        $names = TokenSlots::namesOf('classes', array_values(array_unique(array_filter([
            ...$analysis->channelFqcns($name),
            ...$analysis->declaredTokenFqcns[$name] ?? [],
            ...$declaredSlots,
        ]))));
        $wraps = $analysis->topWrappedEnumFqcns($name);
        $written = $this->writtenWrap($analysis, $name);
        $read = $written === null ? $type : TsTypeString::substituteEnumType(
            $type,
            ValueResult::enumTypeName($written),
            'AsEnum<typeof '.ValueResult::enumConstName($written).'>',
        );
        [$merged, $slots] = new DeclaredTypeMerge($names, $wraps, $analysis->openReadings[$name] ?? [])->merge(
            [$read, $this->readSlots($analysis, $name, $read, $names)],
            [$member['type'], TokenSlots::declaredSlots($member['type'], $declaredSlots, $names)],
        );

        if ($merged === $read) {
            return [$type, [], null];
        }

        // The publisher's rewrite would wrap every bare token of the enum, a bare one the member fills included.
        if ($written !== null) {
            unset($analysis->enumResources[$name], $analysis->enumResourceArmShapes[$name]);
            $analysis->inlineEnumResourceFqcns[$name] = [$written, ...$analysis->inlineEnumResourceFqcns[$name] ?? []];
        }

        $imports = $this->importsSpelled($member['customImports'], $merged);

        if ($this->requeued($analysis, $name, $merged, $slots, $names)) {
            return [$merged, $imports, null];
        }

        return [$merged, [], [
            'type' => $merged,
            'customImports' => $imports,
            'tokenFqcns' => TokenSlots::ownSlots($merged, $slots, $names),
        ]];
    }

    /**
     * The one enum a key wraps in an `EnumResource` and spells bare at its top, which the publisher rewrites as
     * `AsEnum<typeof …>` with the tolki package on; null for a key wrapping none, several, or one it reads bare too.
     *
     * @return class-string|null
     */
    private function writtenWrap(MethodAnalysis $analysis, string $name): ?string
    {
        $wrapped = $analysis->enumResources[$name] ?? null;

        return $wrapped === null || ($analysis->directEnumFqcns[$name] ?? null) === $wrapped
            || isset($analysis->multiEnumResourceFqcns[$name]) || ! Config::boolean('ts-publish.enums.use_tolki_package')
            ? null
            : $wrapped;
    }

    /**
     * The class behind each token of $names a key's text spells, as the publisher reads the key: by the slots a
     * docblock declared for it, else by its channels' queue in print order; null where no class answers to the name.
     *
     * @param  list<string>  $names
     * @return Slots
     */
    private function readSlots(MethodAnalysis $analysis, string $name, string $text, array $names): array
    {
        $declared = $analysis->declaredTokenFqcns[$name] ?? null;

        return $declared === null
            ? TokenSlots::queueRead($analysis->channelFqcns($name), $text, $names)
            : TokenSlots::declaredSlots($text, $declared, $names);
    }

    /**
     * Queue the classes a merged key names on its channels so the publisher reads each token's class from them, as it
     * reads a body's: each new class appended after the ones the key holds, else every class in print order after the
     * single entries that lead each name's queue.
     *
     * False, with no channel touched, where neither reads it so, as for an override spelled like a class beside it.
     *
     * @param  Slots  $slots
     * @param  list<string>  $names
     */
    private function requeued(MethodAnalysis $analysis, string $name, string $text, array $slots, array $names): bool
    {
        // A class no channel holds, as an override's, is the custom import's to bring: no queue reads its token.
        $expected = array_map(
            static fn (?string $class): ?string => $class !== null && MethodAnalysis::queuesEmbedded($class) ? $class : null,
            $slots,
        );
        $classes = array_values(array_filter($expected));
        $held = $analysis->channelFqcns($name);
        $added = array_values(array_unique(array_diff($classes, $held)));

        if (TokenSlots::queueRead([...$held, ...$added], $text, $names) === $expected) {
            $analysis->queueEmbeddedClasses($name, $added);
            unset($analysis->declaredTokenFqcns[$name]);

            return true;
        }

        $singles = $analysis->singleChannelFqcns($name);
        $leading = [];

        foreach ($singles as $single) {
            $leading[TokenSlots::typeName($single)][] = $single;
        }

        $inline = [];

        foreach ($classes as $class) {
            if (($leading[TokenSlots::typeName($class)][0] ?? null) === $class) {
                array_shift($leading[TokenSlots::typeName($class)]);
            } else {
                $inline[] = $class;
            }
        }

        if (TokenSlots::queueRead([...$singles, ...$inline], $text, $names) !== $expected) {
            return false;
        }

        $analysis->requeueInline($name, $inline);
        unset($analysis->declaredTokenFqcns[$name]);

        return true;
    }

    /**
     * The custom imports among $imports whose names a filled type reads.
     *
     * @param  TypesImportMap  $imports
     * @return TypesImportMap
     */
    private function importsSpelled(array $imports, string $filled): array
    {
        $spelled = [];

        foreach ($imports as $path => $names) {
            $read = array_values(array_filter($names, static fn (string $import): bool => TokenSlots::readsName(ImportMaps::localName($import), $filled)));

            if ($read !== []) {
                $spelled[$path] = $read;
            }
        }

        return $spelled;
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
