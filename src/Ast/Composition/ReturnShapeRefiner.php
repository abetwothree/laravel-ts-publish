<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;
use ReflectionMethod;

/**
 * Types what a method's body could not, from that same method's own `@return` docblock.
 *
 * Only a property the body left vague is filled, or an `unknown` position inside one it typed, so a stale docblock can
 * never overwrite a type the body resolved.
 *
 * @phpstan-import-type ShapeTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type Slots from TokenSlots
 *
 * @phpstan-type Filled = array{string, Slots}
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
                [$property['type'], $resolved] = $this->filledPositions($property['type'], $doc, $analysis->hasFqcnChannel($name));
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
     * A key the body typed, each `unknown` position in it filled from the member $doc gives it, beside the fill to lay
     * over the key's channels where it spells a class or a name the member's imports bring.
     *
     * Such a position fills only in a key with no channel spelling no such name, as no slot of the body records one.
     *
     * @param  ShapeTypeInfo|null  $doc
     * @return array{string, ShapeTypeInfo|null}
     */
    private function filledPositions(string $type, ?array $doc, bool $hasChannel): array
    {
        $member = $doc === null || ! TsTypeString::typeNameOccursIn('unknown', $type) ? null : LaravelTsPublish::shapeMemberForKey($doc);

        if ($member === null) {
            return [$type, null];
        }

        $slots = $member['tokenFqcns'] ?? [];
        $own = TokenSlots::namesOf('classes', array_values(array_unique(array_filter($slots))));
        $names = [...$own, ...ImportMaps::localNames($member['customImports'])];
        $spells = fn (string $text): bool => array_any($names, fn (string $name): bool => TsTypeString::typeNameOccursIn($name, $text));
        $named = ! $hasChannel && ! $spells($type);

        [$filled, $filledSlots] = $this->unknownsFilled($type, [$member['type'], $slots], $own, fn (string $fill): bool => $named || ! $spells($fill));

        // A literal spelled like a class can leave a part's share of the slots short, so its tokens would misalign.
        if ($named && $spells($filled) && $this->tokenCount($own, $filled) !== count($filledSlots)) {
            [$filled] = $this->unknownsFilled($type, [$member['type'], $slots], $own, fn (string $fill): bool => ! $spells($fill));
        }

        return match (true) {
            $filled === $type => [$type, null],
            ! $named || ! $spells($filled) => [$filled, null],
            default => [$filled, [
                'type' => $filled,
                'customImports' => $member['customImports'],
                ...($filledSlots === [] ? [] : ['tokenFqcns' => $filledSlots]),
            ]],
        };
    }

    /**
     * $type with each position it holds as `unknown`, a whole value, a member, an element or a record value, taken
     * from $declared at that position where $fills admits it, beside the slots of the parts taken, in print order.
     *
     * An arm takes the one arm of $declared, `null` aside, of its own kind: an object, a list or a Record.
     *
     * @param  Filled  $declared  a type, beside the slot of each token of $own it spells
     * @param  list<string>  $own
     * @param  Closure(string): bool  $fills
     * @return Filled
     */
    private function unknownsFilled(string $type, array $declared, array $own, Closure $fills): array
    {
        if (TsTypeString::isUnknownOnly($type)) {
            return ! TsTypeString::isUnknownOnly($declared[0]) && $fills($declared[0])
                ? [TsTypeString::withNullArmOf($type, $declared[0]), $declared[1]]
                : [$type, []];
        }

        $declaredArms = [];
        $at = 0;

        foreach (TsTypeString::splitTopLevelUnion($declared[0]) as $arm) {
            $count = $this->tokenCount($own, $arm);
            $declaredArms = $arm === 'null' ? $declaredArms : [...$declaredArms, [$arm, array_slice($declared[1], $at, $count)]];
            $at += $count;
        }

        $arms = TsTypeString::splitTopLevelUnion($type);

        if (count($arms) > 1) {
            $filled = array_map(function (string $arm) use ($declaredArms, $own, $fills): array {
                $match = $this->armOfKind($arm, $declaredArms);

                return $match === null ? [$arm, []] : $this->unknownsFilled($arm, $match, $own, $fills);
            }, $arms);
            $texts = array_column($filled, 0);

            return $texts === $arms ? [$type, []] : [implode(' | ', $texts), array_merge([], ...array_column($filled, 1))];
        }

        $match = $this->armOfKind($type, $declaredArms);

        return match (true) {
            $match === null || ! TsTypeString::typeNameOccursIn('unknown', $type) => [$type, []],
            TsTypeShape::members($type) !== null => $this->membersFilled($type, $match, $own, $fills),
            TsTypeShape::recordArguments($type) !== null => $this->recordFilled($type, $match, $own, $fills),
            default => $this->elementFilled($type, $match, $own, $fills),
        };
    }

    /**
     * An object type with each member's `unknown` positions filled from $declared's member of that key, `?` aside.
     *
     * @param  Filled  $declared
     * @param  list<string>  $own
     * @param  Closure(string): bool  $fills
     * @return Filled
     */
    private function membersFilled(string $object, array $declared, array $own, Closure $fills): array
    {
        $declaredMembers = [];
        $at = 0;

        foreach (TsTypeShape::members($declared[0]) ?? [] as [$key, $value]) {
            $at += $this->tokenCount($own, $key);
            $count = $this->tokenCount($own, $value);
            $declaredMembers[rtrim($key, '?')] = [$value, array_slice($declared[1], $at, $count)];
            $at += $count;
        }

        $members = [];
        $slots = [];
        $changed = false;

        foreach (TsTypeShape::members($object) ?? [] as [$key, $value]) {
            $fill = $declaredMembers[rtrim($key, '?')] ?? null;
            [$filled, $filledSlots] = $fill === null ? [$value, []] : $this->unknownsFilled($value, $fill, $own, $fills);
            $changed = $changed || $filled !== $value;
            $members[] = $key.': '.$filled;
            array_push($slots, ...$filledSlots);
        }

        return $changed ? ['{ '.implode('; ', $members).' }', $slots] : [$object, []];
    }

    /**
     * A `Record<K, V>` with V's `unknown` positions filled from $declared's value.
     *
     * @param  Filled  $declared
     * @param  list<string>  $own
     * @param  Closure(string): bool  $fills
     * @return Filled
     */
    private function recordFilled(string $record, array $declared, array $own, Closure $fills): array
    {
        [$key, $value] = TsTypeShape::recordArguments($record) ?? ['', ''];
        [$declaredKey, $declaredValue] = TsTypeShape::recordArguments($declared[0]) ?? ['', 'unknown'];
        $valueSlots = array_slice($declared[1], $this->tokenCount($own, $declaredKey));
        [$filled, $slots] = $this->unknownsFilled($value, [$declaredValue, $valueSlots], $own, $fills);

        return $filled === $value ? [$record, []] : ['Record<'.$key.', '.$filled.'>', $slots];
    }

    /**
     * A `T[]` list with T's `unknown` positions filled from $declared's element.
     *
     * @param  Filled  $declared
     * @param  list<string>  $own
     * @param  Closure(string): bool  $fills
     * @return Filled
     */
    private function elementFilled(string $list, array $declared, array $own, Closure $fills): array
    {
        $element = (string) TsTypeShape::elementType($list);
        [$filled, $slots] = $this->unknownsFilled($element, [(string) TsTypeShape::elementType($declared[0]), $declared[1]], $own, $fills);

        return $filled === $element ? [$list, []] : [TsTypeShape::postfixOperand($filled).'[]', $slots];
    }

    /**
     * The one arm of $arms of $arm's kind, an object, a `T[]` list or a Record; null where none or several are, or
     * $arm is of none of those kinds.
     *
     * @param  list<Filled>  $arms
     * @return Filled|null
     */
    private function armOfKind(string $arm, array $arms): ?array
    {
        $kind = $this->kindOf($arm);
        $matches = $kind === null ? [] : array_values(array_filter($arms, fn (array $other): bool => $this->kindOf($other[0]) === $kind));

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * Whether one arm is an object literal, a Record or a `T[]` list; null for any other arm.
     *
     * @return 'object'|'record'|'list'|null
     */
    private function kindOf(string $arm): ?string
    {
        return match (true) {
            TsTypeShape::members($arm) !== null => 'object',
            TsTypeShape::recordArguments($arm) !== null => 'record',
            $arm !== '[]' && str_ends_with($arm, '[]') && TsTypeShape::elementType($arm) !== null => 'list',
            default => null,
        };
    }

    /**
     * How many slots a part of a declared type takes: one per token of $own it spells, as TokenSlots reads them.
     *
     * @param  list<string>  $own
     */
    private function tokenCount(array $own, string $text): int
    {
        return $own === [] ? 0 : count(TokenSlots::tokens('classes', $own, $text));
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
