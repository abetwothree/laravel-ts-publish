<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

/**
 * A count of the spread items an analysis did not read whole, though PHP writes every key they hold, an earlier one's
 * included. A caller compares two readings to tell a body read fully from one that is not, and AnalysisMemo replays a
 * reused answer's own count, so that reading does not depend on what the run happened to read first.
 *
 * @internal
 */
final class UnreadSpreads
{
    private static int $count = 0;

    /** How many unread spread items this process has met; a caller compares two readings. */
    public static function count(): int
    {
        return self::$count;
    }

    /** Count one spread item no arm read whole. */
    public static function record(): void
    {
        self::$count++;
    }

    /** Count the unread spread items a reused answer met when it was first computed, as computing it again would. */
    public static function replay(int $count): void
    {
        self::$count += $count;
    }
}
