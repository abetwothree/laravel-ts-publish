<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Enums;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;

/**
 * What an enum wrap's `AsEnum<typeof Const>` makes a file import: the consts its types spell after `typeof`, as values,
 * and the `AsEnum` type. Every publisher reads its types through it, so it imports exactly what they spell.
 *
 * @internal
 */
final class EnumWrapImports
{
    /** A name a `typeof` reads, not inside a longer name or after a member access. */
    private const string TYPEOF_NAME = '/(?<![A-Za-z0-9_$.\x80-\xff])'.TsTypeStringService::TYPEOF_ANCHOR
        .TsTypeStringService::TYPEOF_OPERAND.'/';

    /** The `AsEnum` generic as its own name. */
    private const string AS_ENUM = '/(?<![A-Za-z0-9_$.\x80-\xff])AsEnum\s*</';

    /**
     * The queue's entries whose const the type spells after `typeof`, one per token, in print order. A token spelling
     * the alias the file gives an entry's const is that entry's; one spelling a const takes its next unread entry.
     *
     * @param  list<class-string>  $queue  the enum behind each wrap, in the order the type was printed
     * @param  array<string, string>  $aliases  FQCN => the alias the file gives its const
     * @return list<class-string>
     */
    public static function written(string $type, array $queue, array $aliases = []): array
    {
        /** @var array<string, list<class-string>> $unread const name => its entries not yet read, in order */
        $unread = [];
        $aliased = [];

        foreach ($queue as $fqcn) {
            $unread[EnumNames::constName($fqcn)][] = $fqcn;

            if (isset($aliases[$fqcn])) {
                $aliased[$aliases[$fqcn]] = $fqcn;
            }
        }

        if ($unread === []) {
            return [];
        }

        $names = array_keys([...$unread, ...$aliased]);
        preg_match_all(TsTypeString::queuedTokenPattern($names, TsTypeStringService::TYPEOF_ANCHOR), $type, $matches);
        $written = [];

        foreach ($matches[0] as $token) {
            $fqcn = $aliased[$token] ?? $unread[$token][0] ?? null;
            $const = $fqcn === null ? null : EnumNames::constName($fqcn);
            $at = $const === null ? false : array_search($fqcn, $unread[$const], true);

            if ($at !== false) {
                array_splice($unread[$const], $at, 1);
                $written[] = $fqcn;
            }
        }

        return $written;
    }

    /**
     * The enums whose const the keys' types write after `typeof`, once each, each key's queue read as written() reads.
     *
     * @param  array<array-key, string>  $types  key => its type
     * @param  array<array-key, list<class-string>>  $queues  key => the enum behind each wrap, in print order
     * @param  array<string, string>  $aliases  FQCN => the alias the file gives its const
     * @return list<class-string>
     */
    public static function writtenIn(array $types, array $queues, array $aliases = []): array
    {
        $fqcns = [];

        foreach ($queues as $key => $queue) {
            array_push($fqcns, ...self::written($types[$key] ?? '', $queue, $aliases));
        }

        return array_values(array_unique($fqcns));
    }

    /**
     * Every const name the types spell after `typeof`, once each, in the order they first spell it.
     *
     * @return list<string>
     */
    public static function consts(string ...$types): array
    {
        $names = [];

        foreach ($types as $type) {
            preg_match_all(self::TYPEOF_NAME, $type, $matches);
            array_push($names, ...$matches[0]);
        }

        return array_values(array_unique($names));
    }

    /**
     * Whether any of the types spells `AsEnum<`, which a file then imports from the tolki package.
     */
    public static function needsAsEnum(string ...$types): bool
    {
        return array_any($types, static fn (string $type): bool => preg_match(self::AS_ENUM, $type) === 1);
    }
}
