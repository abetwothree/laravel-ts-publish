<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;

/**
 * Whether a key is sent, split by cause: `absent`, missing on some path, where a value held before it survives;
 * `removed`, its value may be removed, as a `when()` MissingValue is, which no held value survives. `optional` is the
 * printed `?`, which a cast or a docblock may set with neither. Each derivation keeps its own default for a bare `?`.
 *
 * @phpstan-import-type AnalyzedProperty from MethodAnalysis
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type KeyRow from KeyValue
 *
 * @internal
 */
final readonly class KeyPresence
{
    /**
     * Hold a key's printed `?` beside its two causes.
     */
    public function __construct(
        public bool $optional = false,
        public bool $absent = false,
        public bool $removed = false,
    ) {}

    /**
     * The presence a row says, exactly as it holds it.
     *
     * @param  AnalyzedProperty|KeyRow  $row
     */
    public static function ofRow(array $row): self
    {
        return new self($row['optional'], $row['absent'] ?? false, $row['removed'] ?? false);
    }

    /**
     * The presence a row's write carries into a literal's fold: none where it is required; else its own, a `?` with
     * neither (a declaration's) read as a key its writer may leave unset.
     *
     * @param  AnalyzedProperty  $row
     */
    public static function fromRow(array $row): self
    {
        if (! $row['optional']) {
            return new self;
        }

        $removed = $row['removed'] ?? false;

        return new self(true, ($row['absent'] ?? false) || ! $removed, $removed);
    }

    /**
     * The presence a written value carries, as MethodAnalysis::addProperty() reads it: a bare `?` is a value removed.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function fromValue(array $result): self
    {
        if (! $result['optional']) {
            return new self;
        }

        $absent = $result['absent'] ?? false;

        return new self(true, $absent, ! $absent || ($result['removed'] ?? false));
    }

    /**
     * A key's presence past a join of branches: absent where some branch lacks it or holds it absent, removed where
     * any branch may remove it, printed `?` where either holds or any branch prints one.
     *
     * @param  list<self>  $held  the presence of each branch that holds the key
     */
    public static function join(array $held, bool $inEveryBranch): self
    {
        return new self(
            ! $inEveryBranch || array_any($held, static fn (self $presence): bool => $presence->optional),
            ! $inEveryBranch || array_any($held, static fn (self $presence): bool => $presence->absent),
            array_any($held, static fn (self $presence): bool => $presence->removed),
        );
    }

    /**
     * The presence a key write leaves: its value's own, and where the write may not run, the key held before it, or
     * none, missing too.
     *
     * @param  bool  $valueOptional  whether the value read may be left out
     * @param  bool  $sentOptional  whether the value the array holds may be left out, as its reading sends it
     */
    public static function written(bool $valueOptional, bool $sentOptional, ?self $held, bool $conditional): self
    {
        return new self(
            $valueOptional || ($conditional && ($held === null || $held->optional)),
            $conditional && ($held === null || $held->absent),
            $sentOptional || ($conditional && $held !== null && $held->removed),
        );
    }

    /**
     * Whether the key is missing on some path and never removed: a value held before it survives wherever it is.
     */
    public function isAbsentOnly(): bool
    {
        return $this->absent && ! $this->removed;
    }

    /**
     * Whether the key is sent on every path.
     */
    public function isSure(): bool
    {
        return ! $this->optional && ! $this->absent && ! $this->removed;
    }

    /**
     * This presence where a whole write lands it over a key held before: where the write may not run, the held key's
     * presence stays beside its own.
     */
    public function after(self $held, bool $conditional): self
    {
        return new self(
            $this->optional || ($conditional && $held->optional),
            $this->absent || ($conditional && $held->absent),
            $this->removed || ($conditional && $held->removed),
        );
    }

    /**
     * This presence on the path where the key is held: required, any removal kept.
     */
    public function held(): self
    {
        return new self(false, false, $this->removed);
    }
}
