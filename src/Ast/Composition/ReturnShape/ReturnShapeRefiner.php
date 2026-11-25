<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
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
     * Fill each key the body left vague, its `null` kept, from $members, the method's own `@return array{...}` shape
     * or `array<string, V>` value, queue the classes the fill names as a cast's are, and mark a `key?:` key optional.
     * Only a spread helper keeps a bare name no class answers to: the analyzed method's may be a class never imported.
     *
     * A key the body typed merges with its member at every position (merged()), unless $fillsPositions says the caller
     * lays the docblock over every key itself; a gap a fill takes out of a key never warns. $relists says the client
     * gets the body's int-keyed arrays as lists, as a resource keeping no keys sends them, bar a key keeping its own.
     */
    public function refine(
        MethodAnalysis $analysis,
        ReflectionMethod $method,
        ReturnMembers $members,
        bool $keepsUnresolvedNames = true,
        bool $fillsPositions = true,
        bool $relists = false,
    ): void {
        $untypedSignature = TsTypeString::orUndefined('unknown');
        $imports = [];

        /** @var CastMap $filled */
        $filled = [];

        foreach ($analysis->properties as &$property) {
            $name = $property['name'];
            $doc = $members->member($name);
            $isSignature = JsEmitter::isIndexSignatureKey($name);
            $resolved = null;
            // A gap stands for what the body could not type, so the key is vague where the rest of it is; a vague key
            // keeps its declared `null`, as a nullable column's.
            $read = VisibleGaps::aside(VisibleGaps::unmarked($property['type']))[0];

            // A signature fills only through the branch below, which keeps the floor a reconcile can put back; a spread
            // helper's own fill, kept beside a put-back floor, outranks this method's docblock.
            if (! $isSignature && TsTypeString::isVagueOnly($read)) {
                $resolved = $this->resolvedType($doc, $read === 'unknown', $method, $keepsUnresolvedNames);

                if ($resolved !== null) {
                    DroppedUnionArms::filled(VisibleGaps::ids($property['type']));
                    $property['type'] = TsTypeString::withNullArmOf($read, $resolved['type']);
                }
            } elseif ($read === $untypedSignature && $isSignature && ! isset($property['fillType'])) {
                $resolved = $this->resolvedType($doc, true, $method, $keepsUnresolvedNames);

                // A key matching the pattern may still be absent at runtime, so the value keeps its `| undefined`;
                // the body's own value is kept for IndexSignatureReconciler to put back where the fill would conflict.
                if ($resolved !== null) {
                    $property['bodyType'] = $property['type'];
                    $property['type'] = TsTypeString::orUndefined($resolved['type']);
                    $property['fillType'] = $property['type'];
                }
            } elseif (! $isSignature && $fillsPositions) {
                $relistsKey = $relists && ! ($property['keepsKeys'] ?? false);
                [$property['type'], $brought, $resolved] = $this->merged($analysis, $name, $property['type'], $doc, $relistsKey);
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

            $property['optional'] = $property['optional'] || $members->isOptional($name);
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
    private function merged(MethodAnalysis $analysis, string $name, string $type, ?array $doc, bool $relists): array
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
        $consts = TokenSlots::namesOf('consts', $analysis->wrapConstFqcns($name));
        $declared = TokenSlots::declaredSlots($member['type'], $declaredSlots, $names);
        [$merged, $slots] = new DeclaredTypeMerge($names, relists: $relists, consts: $consts)->merge(
            [$type, $this->readSlots($analysis, $name, $type, $names, $consts)],
            [$member['type'], TokenSlots::withConstSlots($member['type'], $declared, [], $names, $consts)],
        );

        if ($merged === $type) {
            return [$type, [], null];
        }

        DroppedUnionArms::filled(array_values(array_diff(VisibleGaps::ids($type), VisibleGaps::ids($merged))));

        // A merge keeps every wrap the body sends, in its order, so the const queue already reads the merged text.
        $slots = TokenSlots::withoutConstSlots($merged, $slots, $names, $consts);
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
     * The class behind each token of $names a key's text spells, as the publisher reads the key: by the slots a
     * docblock declared for it, else by its channels' queue in print order; null where no class answers to the name.
     * Each const of $consts after `typeof` takes the enum its wrap queue gives it, in print order among them.
     *
     * @param  list<string>  $names
     * @param  list<string>  $consts
     * @return Slots
     */
    private function readSlots(MethodAnalysis $analysis, string $name, string $text, array $names, array $consts): array
    {
        $declared = $analysis->declaredTokenFqcns[$name] ?? null;
        $classes = $declared === null
            ? TokenSlots::queueRead($analysis->channelFqcns($name), $text, $names)
            : TokenSlots::declaredSlots($text, $declared, $names);
        $wraps = TokenSlots::constRead($analysis->wrapConstFqcns($name), $text, $consts);

        return TokenSlots::withConstSlots($text, $classes, $wraps, $names, $consts);
    }

    /**
     * Queue the classes a merged key names on its channels so the publisher reads each token's class from them, as it
     * reads a body's (DeclaredFill::requeue()).
     *
     * False, with no channel touched, where neither reads it so, as for an override spelled like a class beside it.
     *
     * @param  Slots  $slots
     * @param  list<string>  $names
     */
    private function requeued(MethodAnalysis $analysis, string $name, string $text, array $slots, array $names): bool
    {
        $requeue = DeclaredFill::requeue($analysis->channelFqcns($name), $analysis->singleChannelFqcns($name), $text, $slots, $names);

        if ($requeue === null) {
            return false;
        }

        [$classes, $replaces, $alike] = $requeue;

        if ($replaces) {
            $analysis->requeueInline($name, $classes);
        } else {
            $analysis->queueEmbeddedClasses($name, $classes);
        }

        if ($alike !== []) {
            $analysis->alikeClassFqcns[$name] = $alike;
        }

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
