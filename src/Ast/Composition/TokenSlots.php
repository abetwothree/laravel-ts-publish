<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * Reads a declaration's `tokenFqcns`: one slot per token of the names of the classes its slots hold, in print order,
 * the one reading every writer and reader of those slots shares.
 *
 * @phpstan-type Slots = list<class-string|null>
 * @phpstan-type Token = array{string, int, bool}
 * @phpstan-type DeclaredToken = array{token: string, offset: int, slot: class-string|null, own: bool, read: bool}
 * @phpstan-type DeclaredArm = array{0: string, 1: Slots, 2?: list<string>}
 *
 * @internal
 */
final class TokenSlots
{
    /** The name a `typeof` reads, which is a const's and never a class channel's type. */
    private const string AFTER_TYPEOF = '/(?<![A-Za-z0-9_$.])typeof\s+\K[A-Za-z_$\x80-\xff][A-Za-z0-9_$\x80-\xff]*/';

    /**
     * Each token in a type of a declaration's own names, the names of the classes its slots hold, and of `$names`, in
     * print order. An own name's token takes the next slot, an unread one's in a literal or after `typeof` too, and
     * any other token takes none: the one reading of `tokenFqcns` every builder and reader of it shares.
     *
     * @param  Slots  $slots
     * @param  list<string>  $names
     * @return list<DeclaredToken>
     */
    public static function declaredTokens(string $type, array $slots, array $names = []): array
    {
        return self::declaredTokensOf($type, $slots, self::ownNames($slots), $names);
    }

    /**
     * The slot declaredTokens() gives each token of a declaration's own names and of `$names` in a text.
     *
     * @param  Slots  $declared
     * @param  list<string>  $names
     * @return Slots
     */
    public static function declaredSlots(string $text, array $declared, array $names): array
    {
        return array_column(self::declaredTokens($text, $declared, $names), 'slot');
    }

    /**
     * The class a queue of classes, as a key's channels list them, gives each token of $names a text spells, as the
     * publisher's aliasing walk hands them out; null for a token of a name the queue holds no class of.
     *
     * @param  list<class-string>  $queue
     * @param  list<string>  $names
     * @return Slots
     */
    public static function queueRead(array $queue, string $text, array $names): array
    {
        $spellings = new ClassTokenQueue($queue, self::typeName(...), ValueResult::enumSiblingNames(...))->spellings($text);
        $slots = [];

        foreach ($names === [] ? [] : self::tokens('classes', $names, $text) as [$token]) {
            $next = $spellings === [] || $spellings[0][1] !== $token ? null : array_shift($spellings);
            $slots[] = $next[0] ?? null;
        }

        return $slots;
    }

    /**
     * The slots a merged key declares: the class behind each token of the names of the classes it holds, null for an
     * override spelled like one, as a whole key's fill declares them.
     *
     * @param  Slots  $slots
     * @param  list<string>  $names
     * @return Slots
     */
    public static function ownSlots(string $text, array $slots, array $names): array
    {
        $own = self::ownNames($slots);
        $declared = [];

        foreach ($names === [] ? [] : self::tokens('classes', $names, $text) as $index => [$token]) {
            if (in_array($token, $own, true)) {
                $declared[] = $slots[$index] ?? null;
            }
        }

        return $declared;
    }

    /**
     * A declaration's type with each token a class stands behind blanked, leaving the names its imports must bring.
     *
     * @param  Slots  $slots
     */
    public static function typeLeftToImports(string $type, array $slots): string
    {
        $left = '';
        $from = 0;

        foreach (self::declaredTokens($type, $slots) as $token) {
            if ($token['slot'] !== null && $token['read']) {
                $left .= substr($type, $from, $token['offset'] - $from).' ';
                $from = $token['offset'] + strlen($token['token']);
            }
        }

        return $left.substr($type, $from);
    }

    /**
     * Each top-level arm of a declaration's type, beside the slots its own names' tokens take, in print order, and
     * those names, since an arm's share of the slots may hold no class of a name it spells.
     *
     * @param  Slots  $slots
     * @return list<DeclaredArm>
     */
    public static function declaredArms(string $type, array $slots): array
    {
        $own = self::ownNames($slots);
        $arms = [];
        $at = 0;

        foreach (TsTypeString::splitTopLevelUnion($type) as $arm) {
            $count = $own === [] ? 0 : count(self::tokens('classes', $own, $arm));
            $arms[] = [$arm, array_slice($slots, $at, $count), $own];
            $at += $count;
        }

        return $arms;
    }

    /**
     * A declaration's type with each top-level arm once, beside its slots: arms of one text whose tokens name different
     * classes, as an override spelled like a model's, stay apart.
     *
     * @param  Slots  $slots
     * @return array{string, Slots}
     */
    public static function uniqueArms(string $type, array $slots): array
    {
        $arms = [];

        foreach (self::declaredArms($type, $slots) as [$arm, $armSlots]) {
            $arms[serialize([$arm, $armSlots])] ??= [$arm, $armSlots];
        }

        $texts = array_column($arms, 0);

        return [
            count($texts) === 1 ? $texts[0] : implode(' | ', array_map(TsTypeShape::unionArm(...), $texts)),
            array_merge([], ...array_column($arms, 1)),
        ];
    }

    /**
     * The slots of a type joined from arms: one per token of the names of the classes any arm's slots hold. An arm's
     * slots go to the tokens of its declaration's own names, a whole declaration's read from its slots, and any other
     * token, one another arm's class is spelled like or a literal's, takes a null.
     *
     * @param  list<DeclaredArm>  $arms
     * @return Slots
     */
    public static function joinedSlots(array $arms): array
    {
        $names = self::ownNames(array_merge([], ...array_column($arms, 1)));
        $slots = [];

        foreach ($arms as $arm) {
            $tokens = self::declaredTokensOf($arm[0], $arm[1], $arm[2] ?? self::ownNames($arm[1]), $names);
            array_push($slots, ...array_column($tokens, 'slot'));
        }

        return $slots;
    }

    /**
     * Whether a channel listing each enum once replays a declaration's enum slots as aliasing walks it: each token of a
     * name takes the next enum spelled so, the last covering the rest. Two that share a name and alternate do not.
     *
     * @param  Slots  $slots
     */
    public static function enumsTakeTurns(string $type, array $slots): bool
    {
        $enumSlots = array_values(array_filter($slots, static fn (?string $fqcn): bool => $fqcn !== null && enum_exists($fqcn)));
        $enums = array_values(array_unique($enumSlots));
        $names = array_map(self::typeName(...), $enums);

        return count(array_unique($names)) === count($names)
            || new ClassTokenQueue($enums, self::typeName(...), ValueResult::enumSiblingNames(...))->take($type) === $enumSlots;
    }

    /**
     * A declaration's type read with no class, as every fill a value folds in is read, since a value carries no slots:
     * a member whose tokens outside its nested objects take a class's slot is `unknown`, at any depth, and an
     * override's or a literal's token stays.
     *
     * @param  Slots  $slots
     */
    public static function classLess(string $type, array $slots): string
    {
        return self::classLessOf($type, $slots, self::ownNames($slots));
    }

    /**
     * Whether any of the types spells a name as a token a reader binds to an import: outside a quoted literal, a
     * generated index signature key and the const after `typeof`.
     */
    public static function readsName(string $name, string ...$types): bool
    {
        return array_any($types, static fn (string $type): bool => array_any(
            self::tokens('classes', [$name], $type),
            static fn (array $token): bool => $token[2],
        ));
    }

    /**
     * Each token of the names in the text, its offset, and whether the channel reads it.
     *
     * A literal's or an index signature key's text is a value or a key, and the name after `typeof` is a const's.
     *
     * @param  'classes'|'consts'  $channel
     * @param  non-empty-list<string>  $names
     * @return list<Token>
     */
    public static function tokens(string $channel, array $names, string $text): array
    {
        if (preg_match_all(self::patternOf($channel, $names), $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $unread = TsTypeString::literalSpans($text);

        if ($channel === 'classes' && preg_match_all(self::AFTER_TYPEOF, $text, $consts, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($consts[0] as [$const, $at]) {
                $unread[] = [$at, $at + strlen($const)];
            }
        }

        return array_map(static fn (array $match): array => [
            $match[0],
            $match[1],
            ! array_any($unread, static fn (array $span): bool => $match[1] >= $span[0] && $match[1] < $span[1]),
        ], $matches[0]);
    }

    /**
     * The name each class's token is spelled with: a `@tolki/types` class's, an enum's type or a resource's or model's.
     */
    public static function typeName(string $fqcn): string
    {
        return TolkiTypes::MAP[$fqcn]
            ?? (enum_exists($fqcn) ? ValueResult::enumTypeName($fqcn) : TsNaming::resourceTypeName($fqcn));
    }

    /**
     * Every name a channel's classes are spelled with: a type name and a backed enum's `…Kind`, or an enum's const.
     *
     * @param  'classes'|'consts'  $channel
     * @param  list<class-string>  $fqcns
     * @return list<string>
     */
    public static function namesOf(string $channel, array $fqcns): array
    {
        $names = $channel === 'classes'
            ? array_merge([], ...array_map(
                static fn (string $fqcn): array => [self::typeName($fqcn), ...ValueResult::enumSiblingNames($fqcn)],
                $fqcns,
            ))
            : array_map(ValueResult::enumConstName(...), $fqcns);

        return array_values(array_unique($names));
    }

    /**
     * The names a declaration's tokens take slots for: those of the classes its slots hold.
     *
     * @param  Slots  $slots
     * @return list<string>
     */
    private static function ownNames(array $slots): array
    {
        return self::namesOf('classes', array_values(array_unique(array_filter($slots))));
    }

    /**
     * declaredTokens() of slots that belong to a declaration whose own names are `$own`.
     *
     * @param  Slots  $slots
     * @param  list<string>  $own
     * @param  list<string>  $names
     * @return list<DeclaredToken>
     */
    private static function declaredTokensOf(string $type, array $slots, array $own, array $names): array
    {
        $read = array_values(array_unique([...$own, ...$names]));

        if ($read === []) {
            return [];
        }

        $at = 0;
        $tokens = [];

        foreach (self::tokens('classes', $read, $type) as [$token, $offset, $isRead]) {
            $isOwn = in_array($token, $own, true);
            $slot = $isOwn ? $slots[$at++] ?? null : null;
            $tokens[] = ['token' => $token, 'offset' => $offset, 'slot' => $slot, 'own' => $isOwn, 'read' => $isRead];
        }

        return $tokens;
    }

    /**
     * classLess() of slots that belong to a declaration whose own names are `$own`.
     *
     * @param  Slots  $slots
     * @param  list<string>  $own
     */
    private static function classLessOf(string $type, array $slots, array $own): string
    {
        $tokens = self::declaredTokensOf($type, $slots, $own, []);
        $classed = static fn (array $token): bool => $token['read'] && $token['slot'] !== null;

        if (! array_any($tokens, $classed)) {
            return $type;
        }

        $spans = self::objectSpans($type);
        $inSpan = static fn (array $token, array $span): bool => $token['offset'] >= $span[0]
            && $token['offset'] < $span[1];
        $outside = static fn (array $token): bool => ! array_any($spans, static fn (array $span): bool => $inSpan($token, $span));

        if (array_any($tokens, static fn (array $token): bool => $classed($token) && $outside($token))) {
            return 'unknown';
        }

        $read = '';
        $from = 0;

        foreach ($spans as [$start, $end]) {
            $inside = array_values(array_filter($tokens, static fn (array $token): bool => $inSpan($token, [$start, $end])));

            if (array_any($inside, $classed)) {
                $object = self::classLessObject(substr($type, $start, $end - $start), array_column($inside, 'slot'), $own);
                $read .= substr($type, $from, $start - $from).$object;
                $from = $end;
            }
        }

        return $read.substr($type, $from);
    }

    /**
     * An object type with each member's value read by classLessOf() against its share of the slots, or `unknown` for
     * one TsTypeShape cannot split into keys and values.
     *
     * @param  Slots  $slots
     * @param  list<string>  $own
     */
    private static function classLessObject(string $object, array $slots, array $own): string
    {
        $members = TsTypeShape::members($object);

        if ($members === null) {
            return 'unknown';
        }

        $read = [];
        $at = 0;

        foreach ($members as [$key, $value]) {
            $at += count(self::declaredTokensOf($key, [], $own, []));
            $count = count(self::declaredTokensOf($value, [], $own, []));
            $read[] = $key.': '.self::classLessOf($value, array_slice($slots, $at, $count), $own);
            $at += $count;
        }

        return '{ '.implode('; ', $read).' }';
    }

    /**
     * The spans, end exclusive, of each object literal a type holds outside any other, its quoted literals skipped.
     *
     * @return list<array{int, int}>
     */
    private static function objectSpans(string $type): array
    {
        $skip = [];

        foreach (TsTypeString::literalSpans($type) as [$start, $end]) {
            $skip[$start] = $end;
        }

        $spans = [];
        $depth = 0;
        $open = 0;
        $at = 0;

        while ($at < strlen($type)) {
            if (isset($skip[$at])) {
                $at = $skip[$at];

                continue;
            }

            if ($type[$at] === '{' && $depth++ === 0) {
                $open = $at;
            } elseif ($type[$at] === '}' && $depth > 0 && --$depth === 0) {
                $spans[] = [$open, $at + 1];
            }

            $at++;
        }

        return $spans;
    }

    /**
     * The pattern a channel's tokens are read with: a const only after `typeof`.
     *
     * @param  'classes'|'consts'  $channel
     * @param  non-empty-list<string>  $names
     */
    private static function patternOf(string $channel, array $names): string
    {
        return TsTypeString::queuedTokenPattern($names, $channel === 'classes' ? '' : 'typeof\s+\K');
    }
}
