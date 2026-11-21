<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumWrapImports;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumWrapSpelling;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Closure;

/**
 * An analysis as the inline object type it publishes, intersected with the arms its spreads add, carrying the import
 * channels of every class it names. An inline array, a returned variable's walk and a JSON:API resource object's
 * attributes all publish their object through it.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type ObjectPrint from ExpressionHandler
 * @phpstan-import-type PresenceFacts from MethodAnalysis
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type InlineModelFqcnsMap from MethodAnalysis
 * @phpstan-import-type InlineResourceFqcnsMap from MethodAnalysis
 *
 * @phpstan-type InlineSpreadArm = array{fqcn: class-string, isModel: bool, isCollection: bool}
 * @phpstan-type ObjectSpreadArm = array{type: string, result: ValueExpressionResult}
 * @phpstan-type SpreadArmFqcns = array{
 *      models: list<class-string>,
 *      resources: list<class-string>,
 *      enums: list<class-string>,
 *      wrapConsts: list<class-string>,
 * }
 * @phpstan-type MemberNames = array<string, list<string>>
 *
 * @internal
 */
final class InlineObjectResult
{
    use BuildsInlineObjectTypes;

    /**
     * Read class-less each member a declared fill typed, as before slots rode a type: the value an object folds into
     * carries no slots, and its per-name queues would hand an override's or a literal's token, at any depth, a class.
     */
    public static function readFillsClassLess(MethodAnalysis $analysis): void
    {
        if ($analysis->declaredTokenFqcns === []) {
            return;
        }

        $types = array_column($analysis->properties, 'type', 'name');

        foreach ($analysis->declaredTokenFqcns as $name => $slots) {
            if (isset($types[$name])) {
                $analysis->retypeClassLess((string) $name, TokenSlots::classLess($types[$name], $slots));
            }
        }
    }

    /**
     * Whether a union of an object with others reads each enum token of it from its own place in the queue: no enum a
     * member nests shares its name with another enum the object holds, read or wrapped, as memberEnumFqcns() queues a
     * nested enum again after the members, where such a union reads it as the next object's token.
     */
    public static function queuesEnumsApart(MethodAnalysis $object): bool
    {
        $held = [
            ...array_values($object->directEnumFqcns),
            ...array_merge(...array_values($object->inlineEnumFqcns)),
            ...$object->allWrapConstFqcns(),
            ...$object->allWrappedEnumFqcns(),
        ];
        $nested = array_diff(self::selfKeyedEnums($object), $object->allWrappedEnumFqcns());

        return ! array_any($nested, static fn (string $enum): bool => array_any(
            $held,
            static fn (string $other): bool => $other !== $enum
                && ValueResult::enumTypeName($other) === ValueResult::enumTypeName($enum),
        ));
    }

    /**
     * An analysis as the inline object type it publishes, carrying the import channels of every class it names: one
     * object per variant where the literal's rows still hold its groups (LiteralVariants), else one object.
     *
     * A JSON:API resource object publishes its `attributes` analysis through this, whatever source built it.
     *
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $spreadArms  the arms the object type intersects with
     * @return ValueExpressionResult
     */
    public function of(MethodAnalysis $analysis, array $spreadArms = []): array
    {
        $variants = $analysis->variants;
        $objects = $variants !== null && $variants->holdsFor($analysis) ? $variants->objects() : [];

        if ($objects === [] || ! array_all($objects, self::queuesEnumsApart(...))) {
            return $this->oneObject($analysis, $spreadArms);
        }

        // Each print takes its own copy, as one object would otherwise carry a spread arm's imports once per print.
        $union = ValueResult::unionResults(array_map(
            fn (MethodAnalysis $object): array => $this->oneObject(clone $object, $spreadArms),
            $objects,
        ));

        // A reader that takes the keys back reads them from one object, as ValueResult::unsplit() hands it over.
        return [...$union, 'unsplit' => ['of' => $union['type'], 'print' => $this->oneObject($analysis, $spreadArms)]];
    }

    /**
     * The enums an object keys by their own FQCN, which no member owns: those its members nest.
     *
     * @return array<array-key, class-string>
     */
    private static function selfKeyedEnums(MethodAnalysis $object): array
    {
        return array_diff_key($object->directEnumFqcns, array_flip(array_column($object->properties, 'name')));
    }

    /**
     * A result's classes of one kind: its single-entry import channel's class, then its embedded queue.
     *
     * @param  class-string|null  $single
     * @param  list<class-string>  $embedded
     * @return list<class-string>
     */
    private static function channelFqcns(?string $single, array $embedded): array
    {
        return [...($single !== null ? [$single] : []), ...$embedded];
    }

    /**
     * An analysis as the one inline object type its rows print, whatever variants it holds, carrying the import
     * channels of every class it names.
     *
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $spreadArms  the arms the object type intersects with
     * @return ObjectPrint
     */
    private function oneObject(MethodAnalysis $analysis, array $spreadArms): array
    {
        self::readFillsClassLess($analysis);

        // No outer cast can retype a member, so a member's own method-level cast is the one in force.
        resolve(CastChannels::class)->fit($analysis, $analysis->casts);

        if ($analysis->properties === [] && $spreadArms === []) {
            return ['type' => 'Record<string, unknown>', 'optional' => false];
        }

        $useTolki = EnumWrapSpelling::enabled();

        // Each spread resource intersects with the remaining explicit keys, minus whichever of its
        // own keys a later spread arm or an explicit key also sets — PHP's `[...a, ...b, 'k' => v]`
        // lets the later assignment win, `&` does not, so the earlier arm needs Omit<>'d.
        $spreadArmTypes = array_values(array_unique(
            $this->buildSpreadArmTypes($spreadArms, array_values(array_unique(array_column($analysis->properties, 'name')))),
        ));
        // In the gap mode, an object that may hold keys no name names carries the open marker, which a merge fills; a
        // string-keyed Record beside it already types every key.
        $open = ($analysis->holdsUnnamedKeys || $analysis->holdsUnreadKeys) && resolve(AnalysisMemo::class)->showsGaps()
            && ! array_any($spreadArmTypes, static fn (string $arm): bool => (TsTypeShape::recordArguments($arm)[0] ?? null) === 'string');
        $object = fn (): string => VisibleGaps::opened($this->buildInlineObjectType($analysis), $open);
        $type = match (true) {
            $spreadArms === [] => $object(),
            $analysis->properties === [] => implode(' & ', [...$spreadArmTypes, ...($open ? ['{ '.VisibleGaps::MARKER.' }'] : [])]),
            default => implode(' & ', [...$spreadArmTypes, $object()]),
        };

        $result = ['type' => $type, 'optional' => false];
        $presence = $spreadArms === [] ? $this->keyPresence($analysis) : [];

        if ($presence !== []) {
            $result['keyPresence'] = [VisibleGaps::stripped($type)[0] => $presence];
        }

        if ($spreadArms === [] && $this->keepsMemberKeys($analysis)) {
            $result['keepsKeys'] = true;
        }

        // Propagate import metadata so FQCNs referenced inside the inline object reach the outer analysis.
        // With Tolki enabled, enum resources need value imports (const) rather than type imports; direct
        // enum accesses always need type imports.
        if ($useTolki) {
            // Never deduped: aliasPropertyType() walks this list positionally against left-to-right
            // occurrences of each bare enum name in the rendered type, so a real repeat must survive.
            $embeddedEnumFqcns = $this->memberEnumFqcns($analysis, false);
            // Never deduped either, for the same reason, on the wraps' const channel.
            $embeddedEnumResourceFqcns = array_merge([], ...array_values($analysis->inlineEnumResourceFqcns));
        } else {
            // Tolki OFF: all enum FQCNs (both direct and EnumResource) need type imports. Never
            // deduped, same positional reasoning as the Tolki-on branch above.
            $embeddedEnumFqcns = $this->memberEnumFqcns($analysis, true);
            $embeddedEnumResourceFqcns = [];
        }

        // Spread arms lead the type, so their tokens lead each queue.
        $armFqcns = $this->spreadArmFqcns($spreadArms);
        $embeddedEnumFqcns = [...$armFqcns['enums'], ...$embeddedEnumFqcns];
        $embeddedEnumResourceFqcns = [...$armFqcns['wrapConsts'], ...$embeddedEnumResourceFqcns];

        $resourceNameOf = static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn);
        $sharedNames = $this->sharedNames($analysis, $resourceNameOf);

        // Spread arms lead the type, then each key once, at its first position, with every occurrence kept: the
        // self-keyed $analysis->modelFqcns map collapses repeated FQCNs onto one key, dropping a multi-FQCN accessor
        // member's own arms.
        $embeddedModelFqcns = [
            ...$armFqcns['models'],
            ...$this->memberFqcns(
                $analysis,
                $analysis->inlineModelFqcns,
                $analysis->modelFqcns,
                class_basename(...),
                $sharedNames,
            ),
        ];

        // An enum none of whose names occurs in the final type must not claim an import the transformer would then emit
        // unused; a `…Kind` is one of them.
        $embeddedEnumFqcns = array_values(array_filter(
            $embeddedEnumFqcns,
            fn (string $fqcn): bool => array_any(
                ValueResult::enumNames($fqcn),
                fn (string $name): bool => TsTypeString::typeNameOccursIn($name, $result['type']),
            ),
        ));

        if ($embeddedEnumFqcns !== []) {
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        // A member's own cast may have displaced a wrap, whose const the outer queue must not name.
        $embeddedEnumResourceFqcns = EnumWrapImports::written($result['type'], $embeddedEnumResourceFqcns);

        if ($embeddedEnumResourceFqcns !== []) {
            $result['embeddedEnumResourceFqcns'] = $embeddedEnumResourceFqcns;
        }

        $wrapped = array_values(array_unique([
            ...array_merge([], ...array_map(
                static fn (array $arm): array => isset($arm['result']) ? $arm['result']['wrappedEnumFqcns'] ?? [] : [],
                $spreadArms,
            )),
            ...$analysis->allWrappedEnumFqcns(),
        ]));

        if ($wrapped !== []) {
            $result['wrappedEnumFqcns'] = $wrapped;
        }

        if ($embeddedModelFqcns !== []) {
            $result['embeddedModelFqcns'] = $embeddedModelFqcns;
        }

        // Nested resources are tracked separately so they merge into resource imports, not model imports. One entry
        // per resource token, in the order the type spells them: spread arms lead the type, then each member, and a
        // member whose own union names two resources carries both. Never deduped, as for the models above.
        $embeddedResourceFqcns = [
            ...$armFqcns['resources'],
            ...$this->memberFqcns(
                $analysis,
                $analysis->inlineResourceFqcns,
                $analysis->nestedResources,
                $resourceNameOf,
                $sharedNames,
            ),
        ];

        if ($embeddedResourceFqcns !== []) {
            $result['embeddedResourceFqcns'] = $embeddedResourceFqcns;
        }

        foreach ($spreadArms as $arm) {
            if (isset($arm['result'])) {
                $analysis->carry($arm['result']['carriedFqcns'] ?? []);

                foreach ($arm['result']['customImports'] ?? [] as $path => $types) {
                    $analysis->customImports[$path] = [...($analysis->customImports[$path] ?? []), ...$types];
                }
            }
        }

        // A #[TsType(import: …)] token inside the inline object is spelled in the emitted type string,
        // so its import has to travel out with it.
        if ($analysis->customImports !== []) {
            $result['customImports'] = $analysis->customImports;
        }

        // A member's carried class travels on its own, never queued: only a text with no class of its name keeps it.
        if ($analysis->carried !== []) {
            $result['carriedFqcns'] = $analysis->carried;
        }

        return $result;
    }

    /**
     * The class behind each token of the members' types, in the order the inline object type spells them.
     *
     * That type keeps a key declared twice at its first position with its last value, so each key is walked once: a
     * second row for it would queue the same classes again and push every later token onto the wrong alias.
     *
     * @param  InlineModelFqcnsMap|InlineResourceFqcnsMap  $queues  member name => the class behind each of its tokens
     * @param  ClassMapType  $singles  member name => the one class its type names, for a member without a queue
     * @param  Closure(class-string): string  $nameOf  the name a class's token is spelled with
     * @param  MemberNames  $sharedNames  member name => the names it queues on both channels
     * @return list<class-string>
     */
    private function memberFqcns(
        MethodAnalysis $analysis,
        array $queues,
        array $singles,
        Closure $nameOf,
        array $sharedNames,
    ): array {
        $fqcns = [];
        $types = array_column($analysis->properties, 'type', 'name');

        foreach (array_unique(array_column($analysis->properties, 'name')) as $memberName) {
            $queue = $this->memberQueue($memberName, $queues, $singles);
            $shared = $sharedNames[$memberName] ?? [];

            array_push($fqcns, ...ClassTokenQueue::perToken($queue, $types[$memberName], $nameOf, $shared));
        }

        return $fqcns;
    }

    /**
     * The enum behind each enum token of the members' types, member by member in the order the inline object type
     * spells them, then each enum keyed by its own FQCN, which no member owns.
     *
     * A member whose cast moved its enum to an inline queue keeps its place, as aliasing reads each name's queue in
     * order. With `$withWraps`, a member's `EnumResource` consts join its own enums for type imports. A wrap's enum
     * rides its member's queue alone, as one printed without the tolki package is a token of that member.
     *
     * @return list<class-string>
     */
    private function memberEnumFqcns(MethodAnalysis $analysis, bool $withWraps): array
    {
        $fqcns = [];
        $memberNames = array_unique(array_column($analysis->properties, 'name'));

        foreach ($memberNames as $memberName) {
            $direct = $analysis->directEnumFqcns[$memberName] ?? null;

            array_push(
                $fqcns,
                ...($direct !== null ? [$direct] : []),
                ...($analysis->inlineEnumFqcns[$memberName] ?? []),
                ...($withWraps ? ($analysis->inlineEnumResourceFqcns[$memberName] ?? []) : []),
            );
        }

        $wrapped = $analysis->allWrappedEnumFqcns();

        foreach (self::selfKeyedEnums($analysis) as $fqcn) {
            if (! in_array($fqcn, $wrapped, true)) {
                $fqcns[] = $fqcn;
            }
        }

        return $fqcns;
    }

    /**
     * The names each member queues on both channels, a class on each: a model and a resource under one name.
     *
     * Both queues are merged for aliasing, models first, so a name queued on both is left as it came on both.
     *
     * @param  Closure(class-string): string  $resourceNameOf
     * @return MemberNames
     */
    private function sharedNames(MethodAnalysis $analysis, Closure $resourceNameOf): array
    {
        $shared = [];

        foreach (array_unique(array_column($analysis->properties, 'name')) as $memberName) {
            $models = $this->memberQueue($memberName, $analysis->inlineModelFqcns, $analysis->modelFqcns);
            $resources = $this->memberQueue($memberName, $analysis->inlineResourceFqcns, $analysis->nestedResources);

            $shared[$memberName] = array_values(array_intersect(
                array_map(class_basename(...), $models),
                array_map($resourceNameOf, $resources),
            ));
        }

        return $shared;
    }

    /**
     * The classes a member's own analysis queued on one channel: its queue, else the one class its type names.
     *
     * @param  InlineModelFqcnsMap|InlineResourceFqcnsMap  $queues
     * @param  ClassMapType  $singles
     * @return list<class-string>
     */
    private function memberQueue(string $memberName, array $queues, array $singles): array
    {
        return $queues[$memberName] ?? (isset($singles[$memberName]) ? [$singles[$memberName]] : []);
    }

    /**
     * Why each optional key may be left out, where a body says only one of the two: unset on some path, or a value
     * Laravel may remove. A spread of the object reads it back (ObjectMembers::writtenMembers()).
     *
     * @return array<string, PresenceFacts>
     */
    private function keyPresence(MethodAnalysis $analysis): array
    {
        $presence = [];

        foreach ($analysis->writtenProperties() as $property) {
            $absent = $property['absent'] ?? false;
            $removed = $property['removed'] ?? false;

            if ($property['optional'] && $absent !== $removed && ! JsEmitter::isIndexSignatureKey($property['name'])) {
                $presence[$property['name']] = MethodAnalysis::presenceFacts($absent, $removed);
            }
        }

        return $presence;
    }

    /**
     * Whether an API resource's re-index leaves the literal's object as it is: a key that is not a number keeps every
     * key, and each member holding an object keeps its own.
     */
    private function keepsMemberKeys(MethodAnalysis $analysis): bool
    {
        // Keyed by name, a later member replacing an earlier one, as buildInlineObjectType() prints them.
        $members = array_column($analysis->properties, null, 'name');

        return ! array_all(array_keys($members), fn (int|string $name): bool => TsTypeShape::isNumericKey((string) $name))
            && array_all($members, fn (array $member): bool => ($member['keepsKeys'] ?? false)
                || ! TsTypeShape::holdsKeyedObject($member['type']));
    }

    /**
     * The classes the spread arms' tokens name, per queue, in arm order.
     *
     * A named arm queues its class on the import channel of its kind, or the emitted `Model &` token would be looked up
     * among the resources and never resolve to an import; an object arm queues what its own value carries.
     *
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $spreadArms
     * @return SpreadArmFqcns
     */
    private function spreadArmFqcns(array $spreadArms): array
    {
        $fqcns = ['models' => [], 'resources' => [], 'enums' => [], 'wrapConsts' => []];

        foreach ($spreadArms as $arm) {
            if (! isset($arm['result'])) {
                $fqcns[$arm['isModel'] ? 'models' : 'resources'][] = $arm['fqcn'];

                continue;
            }

            $result = $arm['result'];

            array_push($fqcns['models'], ...self::channelFqcns($result['modelFqcn'] ?? null, $result['embeddedModelFqcns'] ?? []));
            array_push($fqcns['resources'], ...self::channelFqcns($result['resourceFqcn'] ?? null, $result['embeddedResourceFqcns'] ?? []));
            array_push($fqcns['enums'], ...self::channelFqcns($result['directEnumFqcn'] ?? null, $result['embeddedEnumFqcns'] ?? []));
            array_push($fqcns['wrapConsts'], ...($result['embeddedEnumResourceFqcns'] ?? []));
        }

        return $fqcns;
    }

    /**
     * Build each spread's intersection arm, `Omit<>`'d against every key a later arm or an
     * explicit key will overwrite at runtime. `Omit<T, K>` doesn't require `K extends keyof T`,
     * so a later arm's own shape never has to be resolved — only its name, for `keyof`.
     *
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $spreadArms
     * @param  list<string>  $explicitKeyNames
     * @return list<string>
     */
    private function buildSpreadArmTypes(array $spreadArms, array $explicitKeyNames): array
    {
        $explicitKeyLiterals = array_map(fn (string $key): string => "'{$key}'", $explicitKeyNames);

        return array_map(function (int $index) use ($spreadArms, $explicitKeyLiterals): string {
            $armName = $this->spreadArmName($spreadArms[$index]);

            // Spreading a collection renumbers its elements 0..n, so a collection arm holds only
            // numeric keys: nothing string-keyed can overwrite it, and it overwrites nothing.
            if ($spreadArms[$index]['isCollection'] ?? false) {
                return "Record<number, {$armName}>";
            }

            $laterArmNames = array_values(array_unique(array_map(
                $this->spreadArmName(...),
                array_filter(array_slice($spreadArms, $index + 1), fn (array $arm): bool => ! ($arm['isCollection'] ?? false)),
            )));

            $excluded = [
                ...$explicitKeyLiterals,
                ...array_map(fn (string $name): string => "keyof {$name}", $laterArmNames),
            ];

            return $excluded === [] ? $armName : 'Omit<'.$armName.', '.implode(' | ', $excluded).'>';
        }, array_keys($spreadArms));
    }

    /**
     * The type a spread arm is spelled with: a named arm's class, or an object arm's own type.
     *
     * @param  InlineSpreadArm|ObjectSpreadArm  $arm
     */
    private function spreadArmName(array $arm): string
    {
        return isset($arm['type']) ? $arm['type'] : TsNaming::resourceTypeName($arm['fqcn']);
    }
}
