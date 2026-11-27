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

    /** @var array<string, true> each once-key a warning was added under this run */
    private static array $onceKeys = [];

    /**
     * Record a warning for one subject (e.g. a `Controller@method` action).
     */
    public static function add(string $subject, string $message): void
    {
        self::$warnings[] = ['subject' => $subject, 'message' => $message];
    }

    /**
     * Record a warning unless this run already holds the same one, for a check that can run more than once per run:
     * with a once-key, unless a warning was added under that key, else unless one with the same text was.
     */
    public static function addOnce(string $subject, string $message, ?string $onceKey = null): void
    {
        if ($onceKey !== null) {
            if (! isset(self::$onceKeys[$onceKey])) {
                self::$onceKeys[$onceKey] = true;
                self::add($subject, $message);
            }

            return;
        }

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
     * Clear every recorded warning and the once-keys they were added under.
     */
    public static function reset(): void
    {
        self::$warnings = [];
        self::$onceKeys = [];
    }
}
