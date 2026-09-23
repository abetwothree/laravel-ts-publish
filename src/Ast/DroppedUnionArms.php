<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;

/**
 * Audit trail for union arms the engine could not type and therefore left out of the union.
 *
 * Recording is off until a test calls start(), so a publish run pays one count and one null check per dropped arm.
 * The count is always kept: AccessorBodyAnalyzer reads it to tell a `null` a dropped arm left from a literal one.
 *
 * @phpstan-type DroppedUnionArm array{subject: string, line: int, expression: string, site: string}
 *
 * @internal
 */
final class DroppedUnionArms
{
    /** @var list<DroppedUnionArm>|null null while not recording */
    private static ?array $arms = null;

    private static int $dropped = 0;

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
}
