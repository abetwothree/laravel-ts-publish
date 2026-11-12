<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\SourceFileVisitor;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Closure;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;
use Throwable;
use WeakMap;

/**
 * Audit trail for union arms the engine could not type and therefore left out of the union.
 *
 * Recording is off until a test calls start(), so a publish run pays one count and one null check per dropped arm.
 * The count is always kept, so leftNullSince() tells a `null` a dropped arm left from a literal one. A drop that
 * leaves a value only `null` or `never[]` also warns in the run output, whether recording or not.
 *
 * It also numbers the gap a dropped arm leaves, the same arm the same gap for the run, a file parsed again included,
 * and holds each gap's warnings by its id: a strip or a read that consumes the gap raises them, as does the end of the
 * extent the gap was made in unless a merge filled it.
 *
 * A read consumes a gap that reached it and that its result no longer spells, unless a merge inside it filled the gap.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type LeftOutWrite from ReceiverType
 *
 * @phpstan-type DroppedUnionArm array{subject: string, line: int, expression: string, site: string}
 * @phpstan-type LeftOutArm array{Expr, string}
 * @phpstan-type HeldWarning array{subject: string, message: string, arm?: Expr, gap?: int}
 * @phpstan-type GapWarning array{subject: string, message: string, arm: Expr|null, holds: int}
 * @phpstan-type DropWarning array{string, Expr|null}
 * @phpstan-type Drop array{Node, string, list<DropWarning>}
 *
 * @internal
 */
final class DroppedUnionArms
{
    /** What each placeholder a drop can leave publishes, as the warning words it. */
    private const array PLACEHOLDERS = [
        'null' => '`null`, as if it were always null',
        'never[]' => '`never[]`, an array that is always empty',
        'null|never[]' => '`null | never[]`, as if it were always null or an empty array',
    ];

    /** @var list<DroppedUnionArm>|null null while not recording */
    private static ?array $arms = null;

    private static int $dropped = 0;

    /** @var list<list<HeldWarning>> the warnings each replaceable() read under way holds, innermost last */
    private static array $held = [];

    /** @var array<string, int> each gap id, by the subject, site, gap-free union and place of the arm that left it */
    private static array $gapIds = [];

    /** @var WeakMap<Node, int>|null a number for each node with no place in a file, as one built by hand has none */
    private static ?WeakMap $unplaced = null;

    /** The last number unplaced nodes were given. */
    private static int $lastUnplaced = 0;

    /** The last gap id this run gave out. */
    private static int $lastGapId = 0;

    /**
     * @var list<int> each gap id made, replayed or spelled by a read's result this run, in order, and each id a merge
     *                filled, negated, which gapMark() marks a place in
     */
    private static array $gapLog = [];

    /** @var array<int, array<string, GapWarning>> the warnings each gap id holds, by subject and message */
    private static array $gapWarnings = [];

    /** @var array<int, true> the gap ids a merge filled */
    private static array $filled = [];

    /** How many extent() reads are under way. */
    private static int $extents = 0;

    /** Start collecting dropped arms for an audit. */
    public static function start(): void
    {
        self::$arms = [];
    }

    /**
     * Stop collecting and return every distinct dropped arm.
     *
     * @return list<DroppedUnionArm>
     */
    public static function stop(): array
    {
        $arms = self::$arms ?? [];
        self::$arms = null;

        return array_values(array_unique($arms, SORT_REGULAR));
    }

    /** Forget every gap this run numbered, the warnings each holds, the fills and the extents, as a run starts. */
    public static function reset(): void
    {
        self::$gapIds = [];
        self::$unplaced = null;
        self::$lastUnplaced = 0;
        self::$lastGapId = 0;
        self::$gapLog = [];
        self::$gapWarnings = [];
        self::$filled = [];
        self::$extents = 0;
    }

    /** How many arms this process has dropped, recording or not; a caller compares two readings. */
    public static function dropped(): int
    {
        return self::$dropped;
    }

    /**
     * Whether a type is the `null` an arm dropped since the count read $dropped left behind, not a literal one; a gap
     * beside that `null` is the arm it left.
     */
    public static function leftNullSince(string $type, int $dropped): bool
    {
        return VisibleGaps::aside($type)[0] === 'null' && self::$dropped !== $dropped;
    }

    /**
     * The arms whose drop left a placeholder that the innermost replaceable() read under way holds a warning for.
     *
     * @return list<Expr>
     */
    public static function heldPlaceholderArms(): array
    {
        $innermost = array_key_last(self::$held);

        return $innermost === null ? [] : array_column(self::$held[$innermost], 'arm');
    }

    /**
     * Count the arms a reused analysis dropped when it was first computed, as computing it again would, and announce
     * the gaps it made and filled again, so the extent and the reads under way see them as they see their own.
     *
     * @param  list<int>  $gaps
     * @param  list<int>  $fills
     */
    public static function replay(int $dropped, array $gaps = [], array $fills = []): void
    {
        self::$dropped += $dropped;
        array_push(self::$gapLog, ...$gaps);
        self::filled($fills);
    }

    /** Record one union arm that was left out because it resolved to unknown, naming the site that dropped it. */
    public static function record(Expr $arm, AnalysisScope $scope, string $site): void
    {
        self::$dropped++;

        if (self::$arms === null) {
            return;
        }

        self::$arms[] = [
            'subject' => $scope->subjectReflection->getName(),
            'line' => $arm->getStartLine(),
            'expression' => new Standard()->prettyPrintExpr($arm),
            'site' => $site,
        ];
    }

    /**
     * Record each arm a union left out under the site that dropped it, and warn once per arm where the drop left the
     * union only `null` and/or `never[]`; the union is returned as it is.
     *
     * @param  ValueExpressionResult  $union
     * @param  list<LeftOutArm>  $left  each arm the union left out, beside the site that dropped it
     * @return ValueExpressionResult
     */
    public static function leftOut(array $union, array $left, AnalysisScope $scope): array
    {
        $placeholder = self::placeholder($union['type']);

        return self::emitted($union, array_map(static fn (array $pair): array => [
            $pair[0],
            $pair[1],
            $placeholder === null ? [] : [[self::placeholderMessage($pair[0], $placeholder), $pair[0]]],
        ], self::recordedPairs($left, $scope)), $scope);
    }

    /**
     * Record each arm a join left out beside typed ones under the site that dropped it, and warn of each, as a value
     * that still reaches the read; the union is returned as it is.
     *
     * @param  ValueExpressionResult  $union
     * @param  list<LeftOutArm>  $left  each arm the join left out, beside the site that dropped it
     * @return ValueExpressionResult
     */
    public static function leftOutOfJoin(array $union, array $left, AnalysisScope $scope): array
    {
        $placeholder = self::placeholder($union['type']);

        return self::emitted($union, array_map(static fn (array $pair): array => [
            $pair[0],
            $pair[1],
            [$placeholder === null ? [self::leftOutMessage($pair[0]), null] : [self::placeholderMessage($pair[0], $placeholder), $pair[0]]],
        ], self::recordedPairs($left, $scope)), $scope);
    }

    /**
     * Record each write a member read left out of a join, and warn of each as declined() words it: `$this` read as a
     * class that does not declare the member or cannot type it, and any other write as left out; the read is returned
     * as it is, a gap arm per write beside it in the gap mode.
     *
     * @param  ValueExpressionResult  $read
     * @param  list<LeftOutWrite>  $writes
     * @return ValueExpressionResult
     */
    public static function declined(array $read, array $writes, AnalysisScope $scope): array
    {
        return self::emitted($read, self::declinedDrops($writes, $scope), $scope);
    }

    /**
     * Warn of what a receiver's join left out of a read: each untyped write, each object whose class could not be
     * named, both recorded by the caller, and each declined write as declined() records and words it. The read is
     * returned as it is, a gap arm per write beside it in the gap mode.
     *
     * @param  ValueExpressionResult  $read
     * @param  list<Expr>  $untyped
     * @param  list<Expr>  $objects
     * @param  list<LeftOutWrite>  $declined
     * @return ValueExpressionResult
     */
    public static function leftOutOfRead(array $read, array $untyped, array $objects, array $declined, AnalysisScope $scope): array
    {
        return self::emitted($read, [
            ...array_map(static fn (Expr $value): array => [$value, 'binding-join', [[self::leftOutMessage($value), null]]], $untyped),
            ...array_map(static fn (Expr $value): array => [$value, 'binding-join', [[self::unnamedObjectMessage($value), null]]], $objects),
            ...self::declinedDrops($declined, $scope),
        ], $scope);
    }

    /**
     * The id of the gap a drop at $site leaves at a node, the same for the run wherever the same subject drops it from
     * the same union, so two readings of one value spell one gap; each call logs it as made.
     */
    public static function gap(AnalysisScope $scope, string $site, Node $at, string $union): int
    {
        $key = implode("\0", [$scope->subjectReflection->getName(), $site, VisibleGaps::stripped($union)[0], self::place($at)]);
        $id = self::$gapIds[$key] ??= ++self::$lastGapId;
        self::$gapLog[] = $id;

        return $id;
    }

    /** Where the gap log stands, for gapsSince() to read the gaps made or replayed after it. */
    public static function gapMark(): int
    {
        return count(self::$gapLog);
    }

    /**
     * Each gap made, replayed or spelled by a read's result since gapMark() gave $mark, once, in the order first
     * logged.
     *
     * @return list<int>
     */
    public static function gapsSince(int $mark): array
    {
        if ($mark >= count(self::$gapLog)) {
            return [];
        }

        $made = array_filter(array_slice(self::$gapLog, $mark), static fn (int $id): bool => $id > 0);

        return array_values(array_unique($made));
    }

    /**
     * Each gap a merge filled, or a reused analysis replayed as filled, since gapMark() gave $mark, once, in order.
     *
     * @return list<int>
     */
    public static function filledSince(int $mark): array
    {
        $filled = [];

        foreach (array_slice(self::$gapLog, $mark) as $logged) {
            if ($logged < 0) {
                $filled[-$logged] = -$logged;
            }
        }

        return array_values($filled);
    }

    /**
     * Hold a warning a gap's drop raises, to be raised by a strip or its extent, or settled by a replaceable() read
     * under way, which is asked with the arm whose placeholder it names, if it names one.
     */
    public static function hold(int $id, string $warning, ?Expr $arm, string $subject): void
    {
        $key = $subject."\0".$warning;
        self::$gapWarnings[$id][$key] ??= ['subject' => $subject, 'message' => $warning, 'arm' => $arm, 'holds' => 0];
        self::$gapWarnings[$id][$key]['holds']++;
        $held = ['subject' => $subject, 'message' => $warning, 'gap' => $id];
        self::holdInRead($arm === null ? $held : [...$held, 'arm' => $arm]);
    }

    /**
     * Mark gaps a merge filled, whose warnings the end of their extent then leaves unraised.
     *
     * @param  list<int>  $ids
     */
    public static function filled(array $ids): void
    {
        foreach ($ids as $id) {
            self::$filled[$id] = true;
            self::$gapLog[] = -$id;
        }
    }

    /**
     * Raise the warnings each stripped gap holds, once, even where a merge filled the same gap under another key.
     *
     * @param  list<int>  $ids
     */
    public static function stripped(array $ids): void
    {
        foreach ($ids as $id) {
            self::raise($id);
        }
    }

    /**
     * Raise the warnings of each gap that reached a read since gapMark() gave $mark, made, replayed or spelled by an
     * inner read's result, that the read's type no longer spells and no merge inside the read filled: the read consumed
     * it, whatever a merge filled before it. The gaps the type spells are logged for the read around it.
     */
    public static function consumed(int $mark, string $type): void
    {
        $spelled = VisibleGaps::ids($type);

        if ($mark < count(self::$gapLog)) {
            foreach (array_diff(self::gapsSince($mark), $spelled, self::filledSince($mark)) as $id) {
                // The holds stay for a strip or the extent: a read under way may still discard the warning raised here.
                foreach (self::$gapWarnings[$id] ?? [] as $warning) {
                    self::warn($warning['subject'], $warning['message'], $warning['arm']);
                }
            }
        }

        array_push(self::$gapLog, ...$spelled);
    }

    /**
     * Run a stripping frame's read or a documented helper's argument read, and on every way out of the outermost one
     * under way raise the warnings of each gap made or replayed in it that no merge filled and no strip raised.
     *
     * @template TRead
     *
     * @param  Closure(): TRead  $read
     * @return TRead
     */
    public static function extent(Closure $read): mixed
    {
        // A nested extent's gaps can still reach a merge of the one around it, so only the outermost raises.
        $outermost = self::$extents === 0;
        $mark = self::gapMark();
        self::$extents++;

        try {
            return $read();
        } finally {
            self::$extents = max(0, self::$extents - 1);
            $unfilled = $outermost ? array_diff(self::gapsSince($mark), array_keys(self::$filled)) : [];

            foreach ($unfilled as $id) {
                self::raise($id);
            }
        }
    }

    /**
     * Run a read whose placeholders its caller may replace, as a local's inline `@var` or a model's declared accessor
     * type does, and let each warning raised or gap warning held in it stand only where $kept says so, asked with the
     * arm whose placeholder it names, if any. A gap's warning that stands stays held; a read that throws keeps none.
     *
     * @template TRead
     *
     * @param  Closure(): TRead  $read
     * @param  Closure(TRead, Expr|null): bool  $kept
     * @return TRead
     */
    public static function replaceable(Closure $read, Closure $kept): mixed
    {
        self::$held[] = [];

        try {
            $value = $read();
        } catch (Throwable $thrown) {
            self::settle(array_pop(self::$held));

            throw $thrown;
        }

        $held = array_pop(self::$held) ?? [];

        foreach ($held as $warning) {
            if (! $kept($value, $warning['arm'] ?? null)) {
                self::settle([$warning]);
            } elseif (isset($warning['gap'])) {
                self::holdInRead($warning);
            } else {
                self::warn($warning['subject'], $warning['message'], $warning['arm'] ?? null);
            }
        }

        return $value;
    }

    /**
     * Run a read its caller may throw away, as a fixpoint check is: where $kept says the value does not stand, neither
     * its warnings, the gap warnings it held, its count nor its audit entries outlive it.
     *
     * @template TRead
     *
     * @param  Closure(): TRead  $read
     * @param  Closure(TRead): bool  $kept  asked more than once, so it must only inspect the value
     * @return TRead
     */
    public static function provisional(Closure $read, Closure $kept): mixed
    {
        $dropped = self::$dropped;
        $recorded = self::$arms === null ? null : count(self::$arms);
        $value = self::replaceable($read, $kept);

        if (! $kept($value)) {
            self::$dropped = $dropped;

            if ($recorded !== null && self::$arms !== null) {
                self::$arms = array_slice(self::$arms, 0, $recorded);
            }
        }

        return $value;
    }

    /** How the warning words what a type made only of `null` and/or `never[]` publishes; null for any other type. */
    private static function placeholder(string $type): ?string
    {
        $arms = array_unique(TsTypeString::splitTopLevelUnion(VisibleGaps::aside($type)[0]));

        return array_diff($arms, ['null', 'never[]']) === []
            ? self::PLACEHOLDERS[implode('|', array_intersect(['null', 'never[]'], $arms))] ?? null
            : null;
    }

    /**
     * Record each arm left out under the site that dropped it.
     *
     * @param  list<LeftOutArm>  $left
     * @return list<LeftOutArm> the pairs
     */
    private static function recordedPairs(array $left, AnalysisScope $scope): array
    {
        foreach ($left as [$arm, $site]) {
            self::record($arm, $scope, $site);
        }

        return $left;
    }

    /**
     * Record each declined write, and the drop each is, worded as declined() words it.
     *
     * @param  list<LeftOutWrite>  $writes
     * @return list<Drop>
     */
    private static function declinedDrops(array $writes, AnalysisScope $scope): array
    {
        $drops = [];

        foreach ($writes as $write) {
            self::record($write['value'], $scope, 'binding-join');
            $message = isset($write['readAs'])
                ? self::readAsMessage($write['value'], ...$write['readAs'])
                : self::leftOutMessage($write['value']);
            $drops[] = [$write['value'], 'binding-join', [[$message, null]]];
        }

        return $drops;
    }

    /**
     * Raise each drop's warnings and hand the union back as it is; in the gap mode, a union that is no `unknown` takes
     * a gap arm per drop instead, and each warning is held by that gap's id, to rise where the gap is stripped.
     *
     * @param  ValueExpressionResult  $union
     * @param  list<Drop>  $drops
     * @return ValueExpressionResult
     */
    private static function emitted(array $union, array $drops, AnalysisScope $scope): array
    {
        $subject = $scope->subjectReflection->getName();

        if ($drops === [] || $union['type'] === 'unknown' || ! resolve(AnalysisMemo::class)->showsGaps()) {
            foreach ($drops as [, , $warnings]) {
                foreach ($warnings as [$message, $arm]) {
                    self::warn($subject, $message, $arm);
                }
            }

            return $union;
        }

        $type = $union['type'];

        foreach ($drops as [$at, $site, $warnings]) {
            $id = self::gap($scope, $site, $at, $union['type']);
            $type = VisibleGaps::withArm($type, $id);

            foreach ($warnings as [$message, $arm]) {
                self::hold($id, $message, $arm, $subject);
            }
        }

        return [...$union, 'type' => $type];
    }

    /**
     * The warning that a dropped arm left a value only a placeholder, which claims it is always null or always empty.
     */
    private static function placeholderMessage(Expr $arm, string $placeholder): string
    {
        return sprintf(
            'The value on line %d publishes %s, because `%s` could not be typed and was left out of its union. Give '
            .'what it reads a return type or a `@return` docblock.',
            $arm->getStartLine(),
            $placeholder,
            new Standard()->prettyPrintExpr($arm),
        );
    }

    /**
     * The warning that a join left out an arm beside typed ones, so the published type may not admit every value.
     */
    private static function leftOutMessage(Expr $arm): string
    {
        return sprintf(
            'The value on line %d leaves out `%s`, which could not be typed, so its published type may not admit '
            .'every value it sends. Give what it reads a return type or a `@return` docblock.',
            $arm->getStartLine(),
            new Standard()->prettyPrintExpr($arm),
        );
    }

    /**
     * The warning that a receiver's join left out an object whose class could not be named.
     */
    private static function unnamedObjectMessage(Expr $arm): string
    {
        return sprintf(
            'The value on line %d leaves out `%s`, an object whose class could not be named, so its published type '
            .'may not admit every value it sends. Give what it reads a return type or a `@return` docblock naming a class.',
            $arm->getStartLine(),
            new Standard()->prettyPrintExpr($arm),
        );
    }

    /**
     * The warning that a member read left out a write `$this` reads as a class that does not declare the member, or
     * cannot type it.
     */
    private static function readAsMessage(Expr $value, string $class, string $member, bool $declared): string
    {
        return sprintf(
            'The value on line %d leaves out `%s`, read as `%s`, %s, so its published type may not admit every '
            .'value it sends. %s',
            $value->getStartLine(),
            new Standard()->prettyPrintExpr($value),
            $class,
            $declared ? "whose `{$member}` could not be typed" : "which does not declare `{$member}`",
            $declared
                ? 'Type it, or give the local a `@var` naming a class that types it.'
                : 'Give the local a `@var` naming a class that declares it.',
        );
    }

    /**
     * Where a node stands, its file and span, which a file parsed again keeps; a node built by hand has none, so it
     * stands for itself.
     */
    private static function place(Node $at): string
    {
        if ($at->getStartFilePos() >= 0) {
            $file = $at->getAttribute(SourceFileVisitor::ATTRIBUTE);

            return (is_string($file) ? $file : '')."\0".$at->getStartFilePos()."\0".$at->getEndFilePos();
        }

        self::$unplaced ??= new WeakMap;

        return '#'.(self::$unplaced[$at] ??= ++self::$lastUnplaced);
    }

    /**
     * Raise the warnings a gap holds, once, as warn() raises one.
     */
    private static function raise(int $id): void
    {
        $warnings = self::$gapWarnings[$id] ?? [];
        unset(self::$gapWarnings[$id]);

        foreach ($warnings as $warning) {
            self::warn($warning['subject'], $warning['message'], $warning['arm']);
        }
    }

    /**
     * Let go of the gap warnings among warnings a read held that no longer stand, each held once fewer.
     *
     * @param  list<HeldWarning>  $held
     */
    private static function settle(array $held): void
    {
        foreach ($held as $warning) {
            $id = $warning['gap'] ?? null;
            $key = $warning['subject']."\0".$warning['message'];

            if ($id === null || ! isset(self::$gapWarnings[$id][$key])) {
                continue;
            }

            // The same gap held by a read that stands still raises it.
            if (--self::$gapWarnings[$id][$key]['holds'] === 0) {
                unset(self::$gapWarnings[$id][$key]);
            }
        }
    }

    /**
     * Hold a warning for the innermost replaceable() read under way; false where none is.
     *
     * @param  HeldWarning  $warning
     */
    private static function holdInRead(array $warning): bool
    {
        $innermost = array_key_last(self::$held);

        if ($innermost === null) {
            return false;
        }

        self::$held[$innermost][] = $warning;

        return true;
    }

    /**
     * Hold a warning for the innermost replaceable() read under way, with the arm whose placeholder it names, or add it
     * to the run's warnings.
     */
    private static function warn(string $subject, string $message, ?Expr $placeholderArm = null): void
    {
        $warning = ['subject' => $subject, 'message' => $message];

        if (! self::holdInRead($placeholderArm === null ? $warning : [...$warning, 'arm' => $placeholderArm])) {
            AnalysisWarnings::addOnce($subject, $message);
        }
    }
}
