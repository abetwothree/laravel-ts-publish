<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\PrintedKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;

/**
 * A printed object type read back into its keys, each carrying the classes its own type spells: the reverse of
 * InlineObjectResult::of(), so a value already typed as an object can start an analysis that writes more keys. A
 * literal printed one object per variant is read as its one object (ValueResult::unsplit()).
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type PresenceFacts from MethodAnalysis
 * @phpstan-import-type TokenQueues from ClassIdentity
 *
 * @phpstan-type KeyedValues = list<array{string, ValueExpressionResult}>
 * @phpstan-type MemberChannel = array{
 *      modelFqcn?: class-string,
 *      embeddedModelFqcns?: list<class-string>,
 *      resourceFqcn?: class-string,
 *      embeddedResourceFqcns?: list<class-string>,
 *      directEnumFqcn?: class-string,
 *      embeddedEnumFqcns?: list<class-string>,
 * }
 * @phpstan-type WrapChannels = array{
 *      embeddedEnumResourceFqcns?: list<class-string>,
 *      wrappedEnumFqcns?: list<class-string>,
 * }
 *
 * @internal
 */
final class ObjectMembers
{
    /**
     * The keys of the one object literal a value's type spells, each with the classes behind its own tokens, handed
     * out member by member in the order the type spells them; null for any other type, or a class it cannot place.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function analysis(array $value): ?MethodAnalysis
    {
        $value = ValueResult::unsplit($value);

        $members = self::members($value);

        if ($members === null) {
            return null;
        }

        $analysis = new MethodAnalysis;

        foreach ($members as [$name, $member]) {
            $analysis->addProperty($name, $member);
        }

        $analysis->customImports = $value['customImports'] ?? [];
        $analysis->carry($value['carriedFqcns'] ?? []);

        return $analysis;
    }

    /**
     * The keys of the one object literal a value's type spells, each as the value it holds with the classes behind its
     * own tokens, in the order the type spells them; null for any other type, or a class it cannot place. The value's
     * custom imports and carried classes stay on it.
     *
     * Its gap arms and its open marker name no key, so the keys are read as with both stripped.
     *
     * @param  ValueExpressionResult  $value
     * @return KeyedValues|null
     */
    public static function members(array $value): ?array
    {
        $value = ValueResult::unsplit($value);

        $printed = VisibleGaps::members(VisibleGaps::aside($value['type'])[0])[0] ?? null;

        // InlineObjectResult::of() prints an analysis with no keys as a Record, so `{}` has no analysis to read back.
        if ($printed === null || $printed === []) {
            return null;
        }

        $queues = ClassIdentity::queues($value);
        $members = [];

        foreach ($printed as [$key, $type]) {
            $name = PrintedKey::decode($key);

            if ($name === null) {
                return null;
            }

            // A printed `?` cannot tell an unset key from a removed value, so it reads as removed, as a when() is.
            $member = self::memberValue($type, str_ends_with($key, '?'), $queues, $value['wrappedEnumFqcns'] ?? []);

            // An object that keeps its keys keeps each member object's too, as InlineObjectResult::of() decides.
            $members[] = [$name, ($value['keepsKeys'] ?? false) && TsTypeShape::holdsKeyedObject($type)
                ? [...$member, 'keepsKeys' => true]
                : $member];
        }

        if (array_any($queues, static fn (ClassTokenQueue $queue): bool => $queue->outrunsItsTokens())) {
            return null;
        }

        return $members;
    }

    /**
     * One member of a printed value as the value it holds, with the classes behind its own tokens taken next off the
     * printed value's queues, which read its members in the order it spells them.
     *
     * @param  TokenQueues  $queues
     * @param  list<class-string>  $wrapped  the enums the printed value wraps
     * @return ValueExpressionResult
     */
    public static function memberValue(string $type, bool $optional, array $queues, array $wrapped): array
    {
        $holdsObject = TsTypeShape::holdsKeyedObject($type);
        $taken = static fn (TokenKind $kind): array => $queues[$kind->value]->take($type);

        return [
            'type' => $type,
            'optional' => $optional,
            ...self::memberChannel($taken(TokenKind::Model), $holdsObject, 'modelFqcn', 'embeddedModelFqcns'),
            ...self::memberChannel($taken(TokenKind::Resource), $holdsObject, 'resourceFqcn', 'embeddedResourceFqcns'),
            ...self::memberChannel($taken(TokenKind::Enum), $holdsObject, 'directEnumFqcn', 'embeddedEnumFqcns'),
            ...self::wrapChannels($taken(TokenKind::WrapConst), $wrapped),
        ];
    }

    /**
     * The keys a value starts an analysis with, as analysis() reads them; null where a member would lose its text: a
     * model no generated file exports, which addProperty() types `unknown`, or any class an import-less analysis holds.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function seed(array $value, bool $carriesImports): ?MethodAnalysis
    {
        $value = ValueResult::unsplit($value);

        return self::keepsText($value, $carriesImports) ? self::analysis($value) : null;
    }

    /**
     * The keys a value writes into another analysis, as members() reads them; null where a member would lose its text,
     * as seed() declines.
     *
     * @param  ValueExpressionResult  $value
     * @return KeyedValues|null
     */
    public static function seededMembers(array $value, bool $carriesImports): ?array
    {
        $value = ValueResult::unsplit($value);

        return self::keepsText($value, $carriesImports) ? self::members($value) : null;
    }

    /**
     * The keys a spread writes, as writtenMembers() reads them; null where a member would lose its text, as seed()
     * declines.
     *
     * @param  ValueExpressionResult  $value
     * @return KeyedValues|null
     */
    public static function seededWrittenMembers(array $value, bool $carriesImports): ?array
    {
        $value = ValueResult::unsplit($value);

        return self::keepsText($value, $carriesImports) ? self::writtenMembers($value) : null;
    }

    /**
     * The same keys, each one its writer may leave unset; a value that may be removed stays so beside it.
     *
     * @param  KeyedValues  $keys
     * @return KeyedValues
     */
    public static function eachOptional(array $keys): array
    {
        return array_map(static function (array $key): array {
            $removed = $key[1]['optional'] && (! ($key[1]['absent'] ?? false) || ($key[1]['removed'] ?? false));

            return [$key[0], [...$key[1], 'optional' => true, 'absent' => true, ...($removed ? ['removed' => true] : [])]];
        }, $keys);
    }

    /**
     * Whether a value may hold a key its object type does not name: an array literal that holds one, or an object
     * whose signature stands beside a named key, where the signature's keys may be any of the others.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function isOpen(array $value): bool
    {
        $value = ValueResult::unsplit($value);

        if ($value['unnamedKeys'] ?? false) {
            return true;
        }

        $members = VisibleGaps::members(VisibleGaps::aside($value['type'])[0])[0] ?? [];
        $signatures = array_filter($members, static fn (array $member): bool => IndexSignatureKey::is($member[0]));

        return $signatures !== [] && count($signatures) < count($members);
    }

    /**
     * The presence facts InlineObjectResult::of() recorded for each optional member of $object, the object text it
     * printed: a printed `?` alone cannot tell a key a branch may leave unset from a removed value, which a spread over
     * an earlier key must. Empty where the value holds no record for that text, which is keyed with its gaps stripped.
     *
     * @param  ValueExpressionResult  $value
     * @return array<string, PresenceFacts>
     */
    public static function keyPresence(array $value, string $object): array
    {
        return $value['keyPresence'][VisibleGaps::stripped($object)[0]] ?? [];
    }

    /**
     * The keys a spread of a value writes, as members() reads them, each `?` member with the facts a literal's fold
     * reads: those recorded for it, else "may be unset and may be removed". members() itself keeps a bare `?` removed,
     * for a seed whose `??=` no removed value takes: two callers, two answers.
     *
     * @param  ValueExpressionResult  $value
     * @return KeyedValues|null
     */
    public static function writtenMembers(array $value): ?array
    {
        $value = ValueResult::unsplit($value);

        $members = self::members($value);

        if ($members === null) {
            return null;
        }

        $recorded = self::keyPresence($value, $value['type']);
        $everyAbsent = $value['absentKeys'] ?? false;

        return array_map(static function (array $member) use ($recorded, $everyAbsent): array {
            [$name, $result] = $member;

            if (! $result['optional']) {
                return $member;
            }

            $facts = $everyAbsent ? ['absent' => true] : $recorded[$name] ?? ['absent' => true, 'removed' => true];
            unset($result['absent'], $result['removed']);

            // A removed-only member stays as members() reads it; absent marks it unset, `removed` beside it both.
            return [$name, [
                ...$result,
                ...(isset($facts['absent']) ? ['absent' => true] : []),
                ...(isset($facts['absent'], $facts['removed']) ? ['removed' => true] : []),
            ]];
        }, $members);
    }

    /**
     * A value whose objects' optional members are each one its writer may leave unset, never a removed value: the keys
     * `resolve()` returns, which hold no MissingValue, and a shape a `@return` declares, whose `?` says only that.
     *
     * @param  ValueExpressionResult  $value
     * @return ValueExpressionResult
     */
    public static function withOptionalKeysAbsent(array $value): array
    {
        $presence = $value['keyPresence'] ?? [];

        $types = array_unique([$value['type'], ValueResult::unsplitType($value)]);

        foreach (TsTypeString::splitTopLevelUnion(TsTypeString::withoutNullArm(implode(' | ', $types))) as $object) {
            foreach (TsTypeShape::members($object) ?? [] as [$key]) {
                $name = PrintedKey::decode($key);

                if ($name !== null && str_ends_with($key, '?')) {
                    $presence[VisibleGaps::stripped($object)[0]][$name] = ['absent' => true];
                }
            }
        }

        return $presence === [] ? $value : [...$value, 'keyPresence' => $presence];
    }

    /**
     * The channel a member's classes of one kind ride: the single-entry channel for a member that is its one class, as
     * a value read from the class carries it, else the embedded queue, one per token, as a nested object leaves them.
     *
     * @param  list<class-string>  $fqcns
     * @param  'modelFqcn'|'resourceFqcn'|'directEnumFqcn'  $single
     * @param  'embeddedModelFqcns'|'embeddedResourceFqcns'|'embeddedEnumFqcns'  $embedded
     * @return MemberChannel
     */
    private static function memberChannel(array $fqcns, bool $holdsObject, string $single, string $embedded): array
    {
        return match (true) {
            $fqcns === [] => [],
            count($fqcns) === 1 && ! $holdsObject => [$single => $fqcns[0]],
            default => [$embedded => $fqcns],
        };
    }

    /**
     * The channels a member's wraps ride: the enum behind each const its type spells, one per token, and among them the
     * enums the value wraps, once each.
     *
     * @param  list<class-string>  $consts
     * @param  list<class-string>  $wrapped
     * @return WrapChannels
     */
    private static function wrapChannels(array $consts, array $wrapped): array
    {
        $own = array_values(array_intersect(array_unique($consts), $wrapped));

        return [
            ...($consts === [] ? [] : ['embeddedEnumResourceFqcns' => $consts]),
            ...($own === [] ? [] : ['wrappedEnumFqcns' => $own]),
        ];
    }

    /**
     * Whether each member keeps its text once written: every model it names is exported, and an import-less analysis
     * reads no class.
     *
     * @param  ValueExpressionResult  $value
     */
    private static function keepsText(array $value, bool $carriesImports): bool
    {
        return ValueResult::namesOnlyExportedClasses($value)
            && ($carriesImports || ! TsTypeString::shapeValueHasUnimportableToken($value['type']));
    }
}
