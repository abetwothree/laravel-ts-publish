<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;

/**
 * Audit trail for union arms the engine could not type and therefore left out of the union.
 *
 * Recording is off until a test calls start(), so a publish run pays one null check per dropped arm.
 *
 * @phpstan-type DroppedUnionArm array{subject: string, line: int, expression: string, site: string}
 *
 * @internal
 */
final class DroppedUnionArms
{
    /** @var list<DroppedUnionArm>|null null while not recording */
    private static ?array $arms = null;

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

    /** Record one union arm that was left out because it resolved to unknown, naming the site that dropped it. */
    public static function record(Expr $arm, AnalysisScope $scope, string $site): void
    {
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
