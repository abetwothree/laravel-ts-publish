<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ClassIdentity;
use AbeTwoThree\LaravelTsPublish\Ast\Values\TokenKind;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;

/**
 * An outer declaration laid over the value it types, an inline `@var` over its local's or a closure's `@return` over
 * its body's, merged position by position as a method's `@return` merges with a key (DeclaredTypeMerge), and the
 * queue of classes the publisher reads each merged token's class off.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type Slots from TokenSlots
 *
 * @phpstan-type Requeue = array{list<class-string>, bool, list<class-string>}
 *
 * @internal
 */
final class DeclaredFill
{
    /** The channel each kind of class a merged type names is queued on inside a value. */
    private const array EMBEDDED = [
        'enums' => 'embeddedEnumFqcns',
        'models' => 'embeddedModelFqcns',
        'resources' => 'embeddedResourceFqcns',
    ];

    /**
     * A reading merged with the declaration over it: the declaration whole, with what the reading says the value is,
     * where the merge reads as it; the reading where the merge leaves it or no queue of its channels can tell which
     * class each merged token names; else the reading retyped, each class the merge names queued as requeue() says.
     *
     * The reading merges as a read out of the gap mode spells it, so a gap stays for the declaration of the key it
     * reaches, and the fill reads alike in either mode.
     *
     * @param  ValueExpressionResult  $reading
     * @param  ValueExpressionResult  $declared
     * @return ValueExpressionResult
     */
    public static function merged(array $reading, array $declared): array
    {
        $read = VisibleGaps::withoutGaps($reading['type']);
        $held = self::queue($reading);
        $own = self::queue($declared);
        $names = TokenSlots::namesOf('classes', array_values(array_unique([...$held, ...$own])));
        [$text, $slots] = new DeclaredTypeMerge($names)->merge(
            [$read, TokenSlots::queueRead($held, $read, $names)],
            [$declared['type'], TokenSlots::queueRead($own, $declared['type'], $names)],
        );

        if ($text === $read) {
            return $reading;
        }

        if ($text === $declared['type']) {
            return ValueResult::withValueFactsOf($declared, $reading);
        }

        $requeue = self::requeue($held, self::singles($reading), $text, $slots, $names);

        if ($requeue === null) {
            return $reading;
        }

        $merged = self::requeued([...$reading, 'type' => $text], ...$requeue);
        $imports = ImportMaps::spelledIn($declared['customImports'] ?? [], $text);

        return $imports === [] ? $merged : [...$merged, 'customImports' => ImportMaps::merge($merged['customImports'] ?? [], $imports)];
    }

    /**
     * How the classes a merged type names are queued, so the publisher reads each token's class as its slot says, as it
     * reads a body's: each class the held queue lacks appended after it, else every class in print order after the
     * single entries that lead each name's queue, replacing the inline queues; null where neither reads it so. The
     * record of a name a model and a resource both print keeps that queue's order, which each kind's channels lose.
     *
     * @param  list<class-string>  $held  the classes the channels hold, in the order the publisher reads them
     * @param  list<class-string>  $singles  the held classes no inline queue holds, in that order
     * @param  Slots  $slots
     * @param  list<string>  $names
     * @return Requeue|null the classes to queue, whether they replace the inline queues, and the alike record
     */
    public static function requeue(array $held, array $singles, string $text, array $slots, array $names): ?array
    {
        // A class no channel holds, as an override's, is the custom import's to bring: no queue reads its token.
        $expected = array_map(
            static fn (?string $class): ?string => $class !== null && MethodAnalysis::queuesEmbedded($class) ? $class : null,
            $slots,
        );
        $classes = array_values(array_filter($expected));
        $added = array_values(array_unique(array_diff($classes, $held)));

        if (TokenSlots::queueRead([...$held, ...$added], $text, $names) === $expected) {
            return [$added, false, self::alikeOf([...$held, ...$added])];
        }

        $leading = [];

        foreach ($singles as $single) {
            $leading[TokenSlots::typeName($single)][] = $single;
        }

        $inline = [];

        foreach ($classes as $class) {
            if (($leading[TokenSlots::typeName($class)][0] ?? null) === $class) {
                array_shift($leading[TokenSlots::typeName($class)]);
            } else {
                $inline[] = $class;
            }
        }

        return TokenSlots::queueRead([...$singles, ...$inline], $text, $names) === $expected
            ? [$inline, true, self::alikeOf([...$singles, ...$inline])]
            : null;
    }

    /**
     * The classes behind a value's tokens as the publisher reads them once it is a key's: its enums, then its models
     * and resources.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    private static function queue(array $result): array
    {
        return [...TokenKind::Enum->channelFqcns($result), ...ClassIdentity::classesOf($result)];
    }

    /**
     * The classes a value holds on no inline queue, in queue() order.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    private static function singles(array $result): array
    {
        return array_values(array_filter([$result['directEnumFqcn'] ?? null, $result['modelFqcn'] ?? null, $result['resourceFqcn'] ?? null]));
    }

    /**
     * The models and resources of a queue whose names a model and a resource both print, in the queue's order.
     *
     * @param  list<class-string>  $queue
     * @return list<class-string>
     */
    private static function alikeOf(array $queue): array
    {
        $ofKind = static fn (TokenKind $kind): array => array_values(array_filter(
            $queue,
            static fn (string $fqcn): bool => TokenKind::ofClass($fqcn) === $kind,
        ));
        $models = $ofKind(TokenKind::Model);
        $resources = $ofKind(TokenKind::Resource);

        return ClassIdentity::alikeRecord($models, $resources, array_values(array_intersect($queue, [...$models, ...$resources])));
    }

    /**
     * A value with classes queued on its inline channels, after the ones it holds or in their place, and its record of
     * which tokens of a name a model and a resource both print are whose.
     *
     * @param  ValueExpressionResult  $result
     * @param  list<class-string>  $classes
     * @param  list<class-string>  $alike
     * @return ValueExpressionResult
     */
    private static function requeued(array $result, array $classes, bool $replaces, array $alike): array
    {
        if ($replaces) {
            unset($result['embeddedEnumFqcns'], $result['embeddedModelFqcns'], $result['embeddedResourceFqcns'], $result['alikeClassFqcns']);
        }

        foreach ($classes as $class) {
            $channel = self::EMBEDDED[MethodAnalysis::channelKind($class) ?? ''] ?? null;

            if ($channel !== null) {
                $result[$channel] = [...$result[$channel] ?? [], $class];
            }
        }

        if ($alike !== []) {
            $result['alikeClassFqcns'] = $alike;
        }

        return $result;
    }
}
