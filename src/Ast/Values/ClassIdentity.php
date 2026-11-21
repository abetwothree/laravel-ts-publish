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
 * A model and a resource can print one name. Each kind's channels keep their own tokens' order only, so a value that
 * holds both records which of those tokens is whose (`alikeClassFqcns`), and the alias passes read that record.
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
     * The token kinds whose names one name can spell for two classes: a model and a resource both print a type's name,
     * so each may print the other's; an enum's type and a wrap's const each print names of their own.
     */
    private const array NAME_SPACES = [
        [TokenKind::Model, TokenKind::Resource],
        [TokenKind::Enum],
        [TokenKind::WrapConst],
    ];

    /**
     * The classes behind a result's tokens, one queue per token kind. Where its record says which tokens of a name a
     * model and a resource both print are whose, each of their queues reads both kinds' tokens and hands out its own.
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

        if (self::recordsAlike($result)) {
            $models = TokenKind::Model->channelFqcns($result);
            $isModel = static fn (string $fqcn): bool => in_array($fqcn, $models, true);
            $queues[TokenKind::Model->value] = self::classQueue($result, $isModel);
            $queues[TokenKind::Resource->value] = self::classQueue($result, static fn (string $fqcn): bool => ! $isModel($fqcn));
        }

        return $queues;
    }

    /**
     * The queue a result's models and resources are read off together, in print order, where its record says which
     * tokens of a name both kinds print are whose; null otherwise. A reader taking members off the result's queues
     * takes each member's tokens off this one too, so the member keeps its own record (alikeRecord()).
     *
     * @param  ValueExpressionResult  $result
     */
    public static function alikeQueue(array $result): ?ClassTokenQueue
    {
        return self::recordsAlike($result) ? self::classQueue($result) : null;
    }

    /**
     * Whether one name spells two different classes, of one kind or of a model and a resource.
     *
     * @param  list<ValueExpressionResult>  $results
     */
    public static function spellsTwoClassesAlike(array $results): bool
    {
        return array_any(self::NAME_SPACES, static fn (array $kinds): bool => self::sharesAName(array_merge(...array_map(
            static fn (TokenKind $kind): array => self::named($kind, array_merge(...array_map($kind->channelFqcns(...), $results))),
            $kinds,
        ))));
    }

    /**
     * A key's alias queue with each name a model and a resource both print read in its tokens' print order: the
     * record's order, where it holds exactly the queue's classes of those names; else the queue as it came.
     *
     * @param  list<class-string>  $queue  the key's classes of every kind, each kind's in its own tokens' order
     * @param  list<class-string>  $alike  the model or resource behind each token of a name both kinds print, in order
     * @return list<class-string>
     */
    public static function queueByToken(array $queue, array $alike): array
    {
        [$alikeClasses, $ownClasses] = self::splitAlike($queue);

        return $alikeClasses !== [] && self::linesUp($alikeClasses, $alike) ? [...$ownClasses, ...$alike] : $queue;
    }

    /**
     * Whether the print order of a queue's models and resources is known: no name is printed by both kinds, or the
     * record says which of its tokens is whose.
     *
     * @param  list<class-string>  $queue
     * @param  list<class-string>  $alike
     */
    public static function ordersAlike(array $queue, array $alike): bool
    {
        $alikeClasses = self::splitAlike($queue)[0];

        return $alikeClasses === [] || self::linesUp($alikeClasses, $alike);
    }

    /**
     * A result's models and resources as one queue, as queueByToken() lists them from its channels and its record.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    public static function classesOf(array $result): array
    {
        return self::queueByToken(self::channelClasses($result), $result['alikeClassFqcns'] ?? []);
    }

    /**
     * The record a value keeps of the names its models and resources both print: the classes of `$classes`, the model
     * or resource behind each token in print order, that spell such a name.
     *
     * @param  list<class-string>  $models
     * @param  list<class-string>  $resources
     * @param  list<class-string>  $classes
     * @return list<class-string>
     */
    public static function alikeRecord(array $models, array $resources, array $classes): array
    {
        $shared = self::sharedNames($models, $resources);
        $nameOf = self::classNameOf($models);

        return array_values(array_filter($classes, static fn (string $fqcn): bool => in_array($nameOf($fqcn), $shared, true)));
    }

    /**
     * Give a union one member per class where its arms spell one name for two: a member repeats an earlier one only
     * when its text and the classes behind its tokens both match, and the queues carry one FQCN per token, in order.
     * The merge by text stands when an arm's own queue does not line up with its tokens.
     *
     * The union records which tokens of a name a model and a resource both print are whose, unless an arm holding both
     * kinds of such a name does not say.
     *
     * @param  ValueExpressionResult  $union  the arms as merged by their text
     * @param  list<ValueExpressionResult>  $arms
     * @return ValueExpressionResult
     */
    public static function membersByClass(array $union, array $arms): array
    {
        /** @var array<string, string> $members identity => text */
        $members = [];
        $nullable = false;
        /** @var array<value-of<TokenKind>, list<class-string>> $byKind kind => the class behind each member's tokens */
        $byKind = [];
        /** @var list<class-string> $classes the model or resource behind each member's tokens, in print order */
        $classes = [];
        $ordered = true;

        foreach ($arms as $arm) {
            $queues = self::queues($arm);
            $classQueue = self::alikeQueue($arm);
            $ordered = $ordered && self::ordersAlike(self::channelClasses($arm), $arm['alikeClassFqcns'] ?? []);

            foreach (TsTypeString::splitTopLevelUnion($arm['type']) as $member) {
                if ($member === 'null') {
                    $nullable = true;

                    continue;
                }

                $taken = array_map(static fn (ClassTokenQueue $queue): array => $queue->take($member), $queues);
                $both = $classQueue?->take($member) ?? [...$taken[TokenKind::Model->value], ...$taken[TokenKind::Resource->value]];
                $identity = implode("\0", [$member, ...array_merge(...array_map(
                    static fn (array $fqcns): array => ['', ...$fqcns],
                    array_values($taken),
                ))]);

                if (isset($members[$identity])) {
                    continue;
                }

                $members[$identity] = $member;
                array_push($classes, ...$both);

                foreach ($taken as $kind => $fqcns) {
                    $byKind[$kind] = [...$byKind[$kind] ?? [], ...$fqcns];
                }
            }

            // An arm that queues a name for two classes more often than it spells it holds a class behind another's
            // token, and does not say which token is whose (a merge by text is such an arm): the merge by text stands.
            if (array_any($queues, static fn (ClassTokenQueue $queue): bool => $queue->outrunsItsTokens())) {
                return $union;
            }
        }

        unset($union['embeddedModelFqcns'], $union['embeddedResourceFqcns'], $union['alikeClassFqcns']);

        // An enum or a const no member's token reads keeps the queue mergeUnion() built.
        if (($byKind[TokenKind::Enum->value] ?? []) !== []) {
            $union['embeddedEnumFqcns'] = $byKind[TokenKind::Enum->value];
        }

        if (($byKind[TokenKind::WrapConst->value] ?? []) !== []) {
            $union['embeddedEnumResourceFqcns'] = $byKind[TokenKind::WrapConst->value];
        }

        $union['type'] = implode(' | ', [...array_values($members), ...($nullable ? ['null'] : [])]);
        $models = $byKind[TokenKind::Model->value] ?? [];
        $resources = $byKind[TokenKind::Resource->value] ?? [];
        $alike = $ordered ? self::alikeRecord($models, $resources, $classes) : [];

        if ($models !== []) {
            $union['embeddedModelFqcns'] = $models;
        }

        if ($resources !== []) {
            $union['embeddedResourceFqcns'] = $resources;
        }

        if ($alike !== []) {
            $union['alikeClassFqcns'] = $alike;
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

        if (! self::sharesAName(self::named(TokenKind::Model, $models))) {
            return null;
        }

        $queue = TokenKind::Model->queueOf($result);
        $queue->take($result['type']);

        return $queue->outrunsItsTokens() ? null : $models;
    }

    /**
     * A result's models, then its resources, as their channels queue them.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    private static function channelClasses(array $result): array
    {
        return [...TokenKind::Model->channelFqcns($result), ...TokenKind::Resource->channelFqcns($result)];
    }

    /**
     * The queue a result's models and resources are read off together, in queueByToken()'s order, each by its name.
     *
     * @param  ValueExpressionResult  $result
     * @param  (Closure(class-string): bool)|null  $hands  the classes whose tokens it hands out; null for both kinds
     */
    private static function classQueue(array $result, ?Closure $hands = null): ClassTokenQueue
    {
        $nameOf = self::classNameOf(TokenKind::Model->channelFqcns($result));

        return new ClassTokenQueue(self::classesOf($result), $nameOf, hands: $hands);
    }

    /**
     * Whether a result's models and resources print a name in common and its record says which tokens are whose.
     *
     * @param  ValueExpressionResult  $result
     */
    private static function recordsAlike(array $result): bool
    {
        $alikeClasses = self::splitAlike(self::channelClasses($result))[0];

        return $alikeClasses !== [] && self::linesUp($alikeClasses, $result['alikeClassFqcns'] ?? []);
    }

    /**
     * A queue split in two, each part in its order: the models and resources that print a name the other kind prints
     * too, then every other class.
     *
     * @param  list<class-string>  $queue
     * @return array{list<class-string>, list<class-string>}
     */
    private static function splitAlike(array $queue): array
    {
        $shared = self::sharedNames(self::ofKind($queue, TokenKind::Model), self::ofKind($queue, TokenKind::Resource));
        $prints = static function (string $fqcn) use ($shared): bool {
            $kind = TokenKind::ofClass($fqcn);

            return ($kind === TokenKind::Model || $kind === TokenKind::Resource) && in_array($kind->nameOf($fqcn), $shared, true);
        };

        return [
            array_values(array_filter($queue, $prints)),
            array_values(array_filter($queue, static fn (string $fqcn): bool => ! $prints($fqcn))),
        ];
    }

    /**
     * Whether a record holds exactly the given classes of the names a model and a resource both print, each kind's in
     * its own order.
     *
     * @param  list<class-string>  $alikeClasses  in the queue's order
     * @param  list<class-string>  $alike
     */
    private static function linesUp(array $alikeClasses, array $alike): bool
    {
        return count($alike) === count($alikeClasses)
            && self::ofKind($alike, TokenKind::Model) === self::ofKind($alikeClasses, TokenKind::Model)
            && self::ofKind($alike, TokenKind::Resource) === self::ofKind($alikeClasses, TokenKind::Resource);
    }

    /**
     * The classes of a list whose channel is the kind's, in order.
     *
     * @param  list<class-string>  $fqcns
     * @return list<class-string>
     */
    private static function ofKind(array $fqcns, TokenKind $kind): array
    {
        return array_values(array_filter($fqcns, static fn (string $fqcn): bool => TokenKind::ofClass($fqcn) === $kind));
    }

    /**
     * The name a model's or a resource's token is spelled with, telling the models apart by the list given.
     *
     * @param  list<class-string>  $models
     * @return Closure(class-string): string
     */
    private static function classNameOf(array $models): Closure
    {
        return static fn (string $fqcn): string => in_array($fqcn, $models, true)
            ? TokenKind::Model->nameOf($fqcn)
            : TokenKind::Resource->nameOf($fqcn);
    }

    /**
     * Each name a model of the one list and a resource of the other both print.
     *
     * @param  list<class-string>  $models
     * @param  list<class-string>  $resources
     * @return list<string>
     */
    private static function sharedNames(array $models, array $resources): array
    {
        return array_values(array_unique(array_intersect(
            array_map(TokenKind::Model->nameOf(...), $models),
            array_map(TokenKind::Resource->nameOf(...), $resources),
        )));
    }

    /**
     * Each class of a kind's list beside the name its token is spelled with.
     *
     * @param  list<class-string>  $fqcns
     * @return list<array{class-string, string}>
     */
    private static function named(TokenKind $kind, array $fqcns): array
    {
        return array_map(static fn (string $fqcn): array => [$fqcn, $kind->nameOf($fqcn)], $fqcns);
    }

    /**
     * Whether two different classes of the list are spelled with one name.
     *
     * @param  list<array{class-string, string}>  $named  each class beside the name its token is spelled with
     */
    private static function sharesAName(array $named): bool
    {
        /** @var array<string, class-string> $firstOf name => the first class seen under it */
        $firstOf = [];

        foreach ($named as [$fqcn, $name]) {
            if (($firstOf[$name] ??= $fqcn) !== $fqcn) {
                return true;
            }
        }

        return false;
    }
}
