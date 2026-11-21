<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use Closure;

/**
 * Which class stands behind each token of a result's type, read kind by kind, so a union keeps two classes that print
 * one name apart.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type TokenQueues = array<value-of<TokenKind>, ClassTokenQueue>
 *
 * @internal
 */
final class ClassIdentity
{
    /**
     * The classes behind a result's tokens, one queue per token kind.
     *
     * @param  ValueExpressionResult  $result
     * @return TokenQueues
     */
    public static function queues(array $result): array
    {
        $queues = [];

        foreach (TokenKind::cases() as $kind) {
            $queues[$kind->value] = $kind->queueOf($result);
        }

        return $queues;
    }

    /**
     * Whether one name spells two different classes of one kind.
     *
     * @param  list<ValueExpressionResult>  $results
     */
    public static function spellsTwoClassesAlike(array $results): bool
    {
        return array_any(
            TokenKind::cases(),
            static fn (TokenKind $kind): bool => self::sharesAName(
                array_merge(...array_map($kind->channelFqcns(...), $results)),
                $kind->nameOf(...),
            ),
        );
    }

    /**
     * Give a union one member per class where its arms spell one name for two: a member repeats an earlier one only
     * when its text and the classes behind its tokens both match, and the queues carry one FQCN per token, in order.
     * The merge by text stands when an arm's own queue does not line up with its tokens.
     *
     * @param  ValueExpressionResult  $union  the arms as merged by their text
     * @param  list<ValueExpressionResult>  $arms
     * @return ValueExpressionResult
     */
    public static function membersByClass(array $union, array $arms): array
    {
        $kinds = TokenKind::cases();
        /** @var array<string, string> $members identity => text */
        $members = [];
        $nullable = false;
        /** @var array<value-of<TokenKind>, list<class-string>> $byKind kind => the class behind each member's tokens */
        $byKind = [];

        foreach ($arms as $arm) {
            $queues = array_map(static fn (TokenKind $kind): ClassTokenQueue => $kind->queueOf($arm), $kinds);

            foreach (TsTypeString::splitTopLevelUnion($arm['type']) as $member) {
                if ($member === 'null') {
                    $nullable = true;

                    continue;
                }

                $taken = [];
                $parts = [$member];

                foreach ($queues as $index => $queue) {
                    $taken[$index] = $queue->take($member);
                    array_push($parts, '', ...$taken[$index]);
                }

                $identity = implode("\0", $parts);

                if (isset($members[$identity])) {
                    continue;
                }

                $members[$identity] = $member;

                foreach ($kinds as $index => $kind) {
                    $byKind[$kind->value] = [...$byKind[$kind->value] ?? [], ...$taken[$index]];
                }
            }

            // An arm that queues a name for two classes more often than it spells it holds a class behind another's
            // token, and does not say which token is whose (a merge by text is such an arm): the merge by text stands.
            if (array_any($queues, static fn (ClassTokenQueue $queue): bool => $queue->outrunsItsTokens())) {
                return $union;
            }
        }

        unset($union['embeddedModelFqcns'], $union['embeddedResourceFqcns']);

        // An enum or a const no member's token reads keeps the queue mergeUnion() built.
        if (($byKind[TokenKind::Enum->value] ?? []) !== []) {
            $union['embeddedEnumFqcns'] = $byKind[TokenKind::Enum->value];
        }

        if (($byKind[TokenKind::WrapConst->value] ?? []) !== []) {
            $union['embeddedEnumResourceFqcns'] = $byKind[TokenKind::WrapConst->value];
        }

        $union['type'] = implode(' | ', [...array_values($members), ...($nullable ? ['null'] : [])]);

        if (($byKind[TokenKind::Model->value] ?? []) !== []) {
            $union['embeddedModelFqcns'] = $byKind[TokenKind::Model->value];
        }

        if (($byKind[TokenKind::Resource->value] ?? []) !== []) {
            $union['embeddedResourceFqcns'] = $byKind[TokenKind::Resource->value];
        }

        return $union;
    }

    /**
     * The result's model queue, when two of its models share a name and the queue does not outrun the type's tokens.
     *
     * Reads the model channels only. ResultTypeInfoBridge hands the queue on as a type info's classTokenFqcns.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>|null
     */
    public static function modelQueueByToken(array $result): ?array
    {
        $models = TokenKind::Model->channelFqcns($result);

        if (! self::sharesAName($models, TokenKind::Model->nameOf(...))) {
            return null;
        }

        $queue = TokenKind::Model->queueOf($result);
        $queue->take($result['type']);

        return $queue->outrunsItsTokens() ? null : $models;
    }

    /**
     * Whether two different classes of the list are spelled with one name.
     *
     * @param  list<class-string>  $fqcns
     * @param  Closure(class-string): string  $nameOf  the name a class's token is spelled with
     */
    private static function sharesAName(array $fqcns, Closure $nameOf): bool
    {
        /** @var array<string, class-string> $firstOf name => the first class seen under it */
        $firstOf = [];

        foreach ($fqcns as $fqcn) {
            if (($firstOf[$nameOf($fqcn)] ??= $fqcn) !== $fqcn) {
                return true;
            }
        }

        return false;
    }
}
