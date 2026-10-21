<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use Closure;
use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;

/**
 * Audit trail for union arms the engine could not type and therefore left out of the union.
 *
 * Recording is off until a test calls start(), so a publish run pays one count and one null check per dropped arm.
 * The count is always kept: AccessorBodyAnalyzer reads it to tell a `null` a dropped arm left from a literal one. A
 * drop that leaves a value only `null` or `never[]` also warns in the run output, whether recording or not.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type DroppedUnionArm array{subject: string, line: int, expression: string, site: string}
 * @phpstan-type HeldWarning array{subject: string, message: string}
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

    /** How many arms this process has dropped, recording or not; a caller compares two readings. */
    public static function dropped(): int
    {
        return self::$dropped;
    }

    /** Count the arms a reused analysis dropped when it was first computed, as computing it again would. */
    public static function replay(int $dropped): void
    {
        self::$dropped += $dropped;
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
     * Warn in the run output, once per dropped arm, where the drop left a union only `null` and/or `never[]`, a type
     * that claims the value is always null or always empty; the union is returned as it is.
     *
     * @param  ValueExpressionResult  $union
     * @param  list<Expr>  $dropped  the arms the union left out
     * @return ValueExpressionResult
     */
    public static function warnOfPlaceholder(array $union, array $dropped, AnalysisScope $scope): array
    {
        if ($dropped === []) {
            return $union;
        }

        $arms = array_unique(TsTypeString::splitTopLevelUnion($union['type']));
        $placeholder = self::PLACEHOLDERS[implode('|', array_intersect(['null', 'never[]'], $arms))] ?? null;

        if ($placeholder === null || array_diff($arms, ['null', 'never[]']) !== []) {
            return $union;
        }

        foreach ($dropped as $arm) {
            self::warn($scope->subjectReflection->getName(), sprintf(
                'The value on line %d publishes %s, because `%s` could not be typed and was left out of its union. Give '
                .'what it reads a return type or a `@return` docblock.',
                $arm->getStartLine(),
                $placeholder,
                new Standard()->prettyPrintExpr($arm),
            ));
        }

        return $union;
    }

    /**
     * Run a read whose placeholder its caller may replace, as a local's inline `@var` or a model's declared accessor
     * type does, and let the warnings raised inside it reach the run output only where $kept says the value stands.
     *
     * @template TRead
     *
     * @param  Closure(): TRead  $read
     * @param  Closure(TRead): bool  $kept
     * @return TRead
     */
    public static function replaceable(Closure $read, Closure $kept): mixed
    {
        self::$held[] = [];

        try {
            $value = $read();
        } finally {
            $held = array_pop(self::$held) ?? [];
        }

        if ($kept($value)) {
            foreach ($held as $warning) {
                self::warn($warning['subject'], $warning['message']);
            }
        }

        return $value;
    }

    /** Hold a warning for the innermost replaceable() read under way, or add it to the run's warnings. */
    private static function warn(string $subject, string $message): void
    {
        $innermost = array_key_last(self::$held);

        if ($innermost === null) {
            AnalysisWarnings::addOnce($subject, $message);

            return;
        }

        self::$held[$innermost][] = ['subject' => $subject, 'message' => $message];
    }
}
