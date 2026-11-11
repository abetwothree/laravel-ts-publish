<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\PrintedKey;

/**
 * A printed object type read back into its keys, each carrying the classes its own type spells: the reverse of
 * InlineObjectResult::of(), so a value already typed as an object can start an analysis that writes more keys.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
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
        $members = self::members($value);

        if ($members === null) {
            return null;
        }

        $analysis = new MethodAnalysis;

        foreach ($members as [$name, $member]) {
            // Only a spread written in place carries a member's readings; a seeded walk reads its keys as it did.
            unset($member['openReadings']);
            $analysis->addProperty($name, $member);
        }

        $analysis->customImports = $value['customImports'] ?? [];
        $analysis->carry($value['carriedFqcns'] ?? []);

        return $analysis;
    }

    /**
     * The keys of the one object literal a value's type spells, each as the value it holds with the classes behind its
     * own tokens and the readings it leaves open, in the order the type spells them; null for any other type, or a
     * class it cannot place. The value's custom imports and carried classes stay on it.
     *
     * @param  ValueExpressionResult  $value
     * @return KeyedValues|null
     */
    public static function members(array $value): ?array
    {
        // A wrap spells `AsEnum<typeof X>`, a token no queue here reads, so its member could not keep its value import.
        if (isset($value['enumFqcn']) || isset($value['embeddedEnumResourceFqcns'])
            || isset($value['multiEnumResourceFqcns'])) {
            return null;
        }

        $printed = TsTypeShape::members($value['type']);

        // InlineObjectResult::of() prints an analysis with no keys as a Record, so `{}` has no analysis to read back.
        if ($printed === null || $printed === []) {
            return null;
        }

        [$models, $resources, $enums] = ValueResult::tokenQueues($value);
        $members = [];

        foreach ($printed as [$key, $type]) {
            $name = PrintedKey::decode($key);

            if ($name === null) {
                return null;
            }

            $holdsObject = TsTypeShape::holdsKeyedObject($type);
            $readings = self::memberReadings($value, $name);

            $optional = str_ends_with($key, '?');
            $members[] = [$name, [
                'type' => $type,
                // A printed `?` cannot tell an unset key from a removed value: read as unset, where a held value stays.
                'optional' => $optional,
                ...($optional ? ['absent' => true] : []),
                ...self::memberChannel($models->take($type), $holdsObject, 'modelFqcn', 'embeddedModelFqcns'),
                ...self::memberChannel($resources->take($type), $holdsObject, 'resourceFqcn', 'embeddedResourceFqcns'),
                ...self::memberChannel($enums->take($type), $holdsObject, 'directEnumFqcn', 'embeddedEnumFqcns'),
                // An object that keeps its keys keeps each member object's too, as InlineObjectResult::of() decides.
                ...(($value['keepsKeys'] ?? false) && $holdsObject ? ['keepsKeys' => true] : []),
                ...($readings === [] ? [] : ['openReadings' => $readings]),
            ]];
        }

        if ($models->outrunsItsTokens() || $resources->outrunsItsTokens() || $enums->outrunsItsTokens()) {
            return null;
        }

        return $members;
    }

    /**
     * The keys a value starts an analysis with, as analysis() reads them; null where a member would lose its text: a
     * model no generated file exports, which addProperty() types `unknown`, or any class an import-less analysis holds.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function seed(array $value, bool $carriesImports): ?MethodAnalysis
    {
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
        return self::keepsText($value, $carriesImports) ? self::members($value) : null;
    }

    /**
     * The same keys, each one its writer may leave unset.
     *
     * @param  KeyedValues  $keys
     * @return KeyedValues
     */
    public static function eachOptional(array $keys): array
    {
        return array_map(
            static fn (array $key): array => [$key[0], [...$key[1], 'optional' => true, 'absent' => true]],
            $keys,
        );
    }

    /**
     * Whether a value may hold a key its object type does not name: an array literal that holds one, or an object
     * whose signature stands beside a named key, where the signature's keys may be any of the others.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function isOpen(array $value): bool
    {
        if ($value['unnamedKeys'] ?? false) {
            return true;
        }

        $members = TsTypeShape::members($value['type']) ?? [];
        $signatures = array_filter($members, static fn (array $member): bool => IndexSignatureKey::is($member[0]));

        return $signatures !== [] && count($signatures) < count($members);
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

    /**
     * The readings a value leaves open under one member's key, each moved to the member's own path; the object's own
     * stays with it.
     *
     * @param  ValueExpressionResult  $value
     * @return list<string>
     */
    private static function memberReadings(array $value, string $name): array
    {
        $key = '.'.JsEmitter::validJsObjectKey($name, allowIndexSignature: true);

        return array_values(array_map(
            static fn (string $reading): string => substr($reading, strlen($key)),
            array_filter(
                $value['openReadings'] ?? [],
                static fn (string $reading): bool => str_starts_with($reading, $key)
                    && in_array($reading[strlen($key)] ?? '', ['.', '[', '{', "\0"], true),
            ),
        ));
    }
}
