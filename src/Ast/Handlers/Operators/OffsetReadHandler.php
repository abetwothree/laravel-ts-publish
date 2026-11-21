<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ClassIdentity;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ObjectMembers;
use AbeTwoThree\LaravelTsPublish\Ast\Values\TokenKind;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\PrintedKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * A literal-key read, `$data['a']` or `$pair[1]`, typed from the member each arm of its holder's type names for the
 * key, with `null` for a `null` arm. An optional member reads optional, for the `MissingValue` a `when*()` slot holds,
 * unless the holder's `absentKeys` says it only lacks the key, whose bare read throws and never sends `null`.
 *
 * Each member keeps the classes behind its tokens, read off the holder's queues as the holder's own members read them.
 * Declines an arm that is no object literal or tuple, since a list, a Record or an index signature names no key the
 * array is sure to hold, an arm lacking the key, an `unknown` member, and a member naming a class its holder carries
 * no channel for.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TokenQueues from ClassIdentity
 *
 * @internal
 */
final class OffsetReadHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [ArrayDimFetch::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof ArrayDimFetch || (! $expr->dim instanceof String_ && ! $expr->dim instanceof Int_)) {
            return null;
        }

        $holder = ValueResult::unsplit($engine->resolve($expr->var));
        $read = $this->keyRead($holder, (string) $expr->dim->value);

        return $read !== null && ($holder['absentKeys'] ?? false) ? [...$read, 'optional' => false] : $read;
    }

    /**
     * A holder's one member as the key reads it: a member naming no class with its arms each once and `null` last.
     *
     * @param  ValueExpressionResult  $member
     * @return ValueExpressionResult
     */
    private static function alone(array $member): array
    {
        // hoistNull() merges arms by their text, which would fold two classes one name spells into one arm.
        return self::namesNoClass($member)
            ? [...$member, 'type' => TsTypeString::hoistNull([$member['type']])]
            : $member;
    }

    /**
     * Whether a member's every token names a class its own channels carry, or `AsEnum` beside a wrap's const.
     *
     * @param  ValueExpressionResult  $member
     */
    private static function carriesItsClasses(array $member): bool
    {
        $names = [];

        foreach (TokenKind::cases() as $kind) {
            foreach ($kind->channelFqcns($member) as $fqcn) {
                array_push($names, $kind->nameOf($fqcn), ...$kind->siblingNames($fqcn));
            }
        }

        if (TokenKind::WrapConst->channelFqcns($member) !== []) {
            $names[] = 'AsEnum';
        }

        return ! TsTypeString::shapeValueHasUnimportableToken($member['type'], $names);
    }

    /**
     * Whether a member carries no class on any channel.
     *
     * @param  ValueExpressionResult  $member
     */
    private static function namesNoClass(array $member): bool
    {
        return array_all(TokenKind::cases(), static fn (TokenKind $kind): bool => $kind->channelFqcns($member) === []);
    }

    /**
     * What a key reads from a holder: each arm's member, and `null` for a `null` arm, optional where a member is; an
     * empty array arm holds none. Null where an arm declines, no arm holds a member, or the holder's queues cannot say
     * which token is whose. The member of a gap arm, which no rule typed, is that gap.
     *
     * @param  ValueExpressionResult  $holder
     * @return ValueExpressionResult|null
     */
    private function keyRead(array $holder, string $key): ?array
    {
        [$type, $gaps] = VisibleGaps::aside($holder['type']);
        $queues = ClassIdentity::queues($holder);
        $members = [];

        foreach (TsTypeShape::splitTopLevel($type, ['|']) as $arm) {
            // An empty array holds no key, whose bare read throws and whose `??` gives the default.
            if ($arm === 'never[]' || $arm === '[]') {
                continue;
            }

            $member = $arm === 'null'
                ? ['type' => 'null', 'optional' => false]
                : $this->armMember($arm, $key, $queues, $holder['wrappedEnumFqcns'] ?? []);

            if ($member === null) {
                return null;
            }

            $members[] = $member;
        }

        if (array_all($members, static fn (array $member): bool => $member['type'] === 'null')
            || array_any($queues, static fn (ClassTokenQueue $queue): bool => $queue->outrunsItsTokens())) {
            return null;
        }

        $read = count($members) === 1 ? self::alone($members[0]) : ValueResult::unionResults($members);

        return [...$read, 'type' => VisibleGaps::restored($read['type'], $gaps)];
    }

    /**
     * The member a key reads from an object literal or tuple arm, with the classes behind its tokens. Every member of
     * the arm takes its tokens off the queues in print order, so the next arm reads on from there. Null where the arm
     * is neither, lacks the key, or its member is `unknown` or names a class no channel of its own carries.
     *
     * @param  TokenQueues  $queues
     * @param  list<class-string>  $wrapped  the enums the holder wraps
     * @return ValueExpressionResult|null
     */
    private function armMember(string $arm, string $key, array $queues, array $wrapped): ?array
    {
        $parts = match (true) {
            TsTypeShape::isWrapped($arm, '{', '}') => $this->objectParts($arm, $key),
            TsTypeShape::isWrapped($arm, '[', ']') => $this->tupleParts($arm, $key),
            default => [],
        };
        $member = null;

        foreach ($parts as [$type, $optional, $named]) {
            $value = ObjectMembers::memberValue($type, $optional, $queues, $wrapped);
            $member ??= $named ? $value : null;
        }

        return $member === null || $member['type'] === 'unknown' || ! self::carriesItsClasses($member) ? null : $member;
    }

    /**
     * Each member an object literal names, written as it is, whether it is optional, and whether it is the key's; an
     * index signature names no key.
     *
     * @return list<array{string, bool, bool}>
     */
    private function objectParts(string $object, string $key): array
    {
        return array_map(
            static fn (array $member): array => [
                $member[1],
                str_ends_with($member[0], '?'),
                PrintedKey::names($member[0], $key),
            ],
            TsTypeShape::members($object) ?? [],
        );
    }

    /**
     * Each element a tuple holds, without the `?` an optional one carries, whether it is optional, and whether it sits
     * at the key's index; a rest element holds no single index.
     *
     * @return list<array{string, bool, bool}>
     */
    private function tupleParts(string $tuple, string $key): array
    {
        $index = PhpArrayKey::listIndex($key);
        $elements = TsTypeShape::tupleElements($tuple);

        return array_map(
            static fn (array $element, int $at): array => [
                $element[0],
                $element[1],
                $at === $index && ! str_starts_with($element[0], '...'),
            ],
            $elements,
            array_keys($elements),
        );
    }
}
