<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * Per-run analysis warnings, printed by ts:publish after the summary. Static like DependencyRecorder.
 *
 * @phpstan-type AnalysisWarning = array{subject: string, message: string}
 */
final class AnalysisWarnings
{
    /** @var list<AnalysisWarning> */
    protected static array $warnings = [];

    /**
     * Record a warning for one subject (e.g. a `Controller@method` action).
     */
    public static function add(string $subject, string $message): void
    {
        self::$warnings[] = ['subject' => $subject, 'message' => $message];
    }

    /**
     * Record a warning unless this run already holds the same one, for a check that can run more than once per run.
     */
    public static function addOnce(string $subject, string $message): void
    {
        if (! in_array(['subject' => $subject, 'message' => $message], self::$warnings, true)) {
            self::add($subject, $message);
        }
    }

    /**
     * All warnings recorded so far this run.
     *
     * @return list<AnalysisWarning>
     */
    public static function all(): array
    {
        return self::$warnings;
    }

    /**
     * Clear every recorded warning.
     */
    public static function reset(): void
    {
        self::$warnings = [];
    }
}
