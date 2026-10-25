<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * A printed object type read back into its keys, each carrying the classes its own type spells: the reverse of
 * InlineObjectResult::of(), so a value already typed as an object can start an analysis that writes more keys.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
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
        // A wrap spells `AsEnum<typeof X>`, a token no queue here reads, so its member could not keep its value import.
        if (isset($value['enumFqcn']) || isset($value['embeddedEnumResourceFqcns'])
            || isset($value['multiEnumResourceFqcns'])) {
            return null;
        }

        $members = TsTypeShape::members($value['type']);

        // InlineObjectResult::of() prints an analysis with no keys as a Record, so `{}` has no analysis to read back.
        if ($members === null || $members === []) {
            return null;
        }

        $models = new ClassTokenQueue(
            self::channelFqcns($value['modelFqcn'] ?? null, $value['embeddedModelFqcns'] ?? []),
            class_basename(...),
        );
        $resources = new ClassTokenQueue(
            self::channelFqcns($value['resourceFqcn'] ?? null, $value['embeddedResourceFqcns'] ?? []),
            static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn),
        );
        $enums = new ClassTokenQueue(
            self::channelFqcns($value['directEnumFqcn'] ?? null, $value['embeddedEnumFqcns'] ?? []),
            ValueResult::enumTypeName(...),
            ValueResult::enumSiblingNames(...),
        );

        $analysis = new MethodAnalysis;

        foreach ($members as [$key, $type]) {
            $optional = str_ends_with($key, '?');
            $name = self::keyName($optional ? substr($key, 0, -1) : $key);

            if ($name === null) {
                return null;
            }

            $holdsObject = TsTypeShape::holdsKeyedObject($type);

            $analysis->addProperty($name, [
                'type' => $type,
                'optional' => $optional,
                ...self::memberChannel($models->take($type), $holdsObject, 'modelFqcn', 'embeddedModelFqcns'),
                ...self::memberChannel($resources->take($type), $holdsObject, 'resourceFqcn', 'embeddedResourceFqcns'),
                ...self::memberChannel($enums->take($type), $holdsObject, 'directEnumFqcn', 'embeddedEnumFqcns'),
                // An object that keeps its keys keeps each member object's too, as InlineObjectResult::of() decides.
                ...(($value['keepsKeys'] ?? false) && $holdsObject ? ['keepsKeys' => true] : []),
            ]);
        }

        if ($models->outrunsItsTokens() || $resources->outrunsItsTokens() || $enums->outrunsItsTokens()) {
            return null;
        }

        $analysis->customImports = $value['customImports'] ?? [];
        $analysis->carry($value['carriedFqcns'] ?? []);

        return $analysis;
    }

    /**
     * The keys a value starts an analysis with, as analysis() reads them; null where a member would lose its text: a
     * model no generated file exports, which addProperty() types `unknown`, or any class an import-less analysis holds.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function seed(array $value, bool $carriesImports): ?MethodAnalysis
    {
        $losesText = ! ValueResult::namesOnlyExportedClasses($value)
            || (! $carriesImports && TsTypeString::shapeValueHasUnimportableToken($value['type']));

        return $losesText ? null : self::analysis($value);
    }

    /**
     * A value's classes of one kind: its single-entry channel's class, then its embedded queue.
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
     * The key a printed key names: a bare identifier or integer as written, a quoted one unquoted; null for any other,
     * an index signature among them.
     */
    private static function keyName(string $printed): ?string
    {
        if (preg_match('/^(?:[a-zA-Z_$][a-zA-Z0-9_$]*|0|[1-9]\d*)$/', $printed) === 1) {
            return $printed;
        }

        // The printer quotes a key with json_encode(), so json_decode() reads back exactly the key it was given.
        $name = str_starts_with($printed, '"') ? json_decode($printed) : null;

        return is_string($name) ? $name : null;
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
}
