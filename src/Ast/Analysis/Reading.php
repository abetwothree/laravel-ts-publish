<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;

/**
 * How the values a body returns reach the client, which decides how they are read: as `json_encode()` sends them, with
 * or without a JsonResource's `filter()` over them, as Inertia sends a page's props and shared data, or as Inertia
 * sends them once `filter()` ran. A call's body takes its caller's reading by calledFrom().
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
enum Reading
{
    /** `json_encode()` sends them: what a class publishes for itself, and what reaches Inertia already JSON. */
    case Encoded;

    /** `json_encode()` sends them, no `filter()` over them: the keys a resource's response adds beside its body. */
    case EncodedUnfiltered;

    /** `json_encode()` sends them once `filter()` ran: a body an encoded API resource's body calls where it filters. */
    case EncodedFiltered;

    /** Inertia sends them, calling each callable among them: a page's props, shared data and every body they read. */
    case Inertia;

    /** Inertia sends them once `filter()` ran: an API resource's `toArray()` that `resolve()` returns, and below it. */
    case InertiaFiltered;

    /**
     * The memo key suffix of a body read this way; none for the encoded reading.
     */
    public function memoKey(): string
    {
        return match ($this) {
            self::Encoded => '',
            self::EncodedUnfiltered => '@unfiltered',
            self::EncodedFiltered => '@filtered',
            self::Inertia => '@inertia',
            self::InertiaFiltered => '@inertia@filtered',
        };
    }

    /**
     * The reading a body takes from this one: filtered where an unfiltered read enters a resource's whole `toArray()`.
     *
     * @param  ReflectionClass<object>  $runs  the class the body runs for
     */
    public function entering(ReflectionClass $runs, string $method, bool $readsCall): self
    {
        if ($readsCall || $method !== 'toArray' || ! is_a($runs->getName(), JsonResource::class, true)) {
            return $this;
        }

        return match ($this) {
            self::Inertia => self::InertiaFiltered,
            self::EncodedUnfiltered => self::Encoded,
            default => $this,
        };
    }

    /**
     * The reading a body over the class takes: over an API resource the filtered encoded reading is the encoded one,
     * which reads that resource's own filter() already, so the two share their memo entries.
     *
     * @param  class-string  $class
     */
    public function over(string $class): self
    {
        return $this === self::EncodedFiltered && is_subclass_of($class, JsonResource::class) ? self::Encoded : $this;
    }

    /**
     * A value as an array read this way holds it, as a key's or an element's: in a response's extras, an object PHP
     * holds is the `[]` Inertia decodes `json_encode()`'s `{}` into.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public function arrayValue(array $result): array
    {
        return $this === self::EncodedUnfiltered ? ValueResult::heldObjectAsDecoded($result) : $result;
    }

    /**
     * Whether `json_encode()` sends the values, which sends a callable it holds as `{}` where Inertia would call it.
     */
    public function encodes(): bool
    {
        return $this === self::Encoded || $this === self::EncodedUnfiltered || $this === self::EncodedFiltered;
    }

    /**
     * The reading a call's body takes from a value read in the scope: filtered where the scope's filter() reaches the
     * value, which a body on another class cannot tell from its own subject, and unfiltered inside a kept Collection.
     */
    public function calledFrom(AnalysisScope $scope): self
    {
        return match ($this) {
            self::InertiaFiltered => $this->filterReaches($scope) ? $this : self::Inertia,
            self::Encoded, self::EncodedFiltered => $this->filterReaches($scope) ? self::EncodedFiltered : self::Encoded,
            default => $this,
        };
    }

    /**
     * Whether `JsonResource::filter()` reaches a value read in the scope, which sends a resource around null as null.
     */
    public function filterReaches(AnalysisScope $scope): bool
    {
        return $scope->filtersNestedResources && $this->filtersBodyOf($scope);
    }

    /**
     * Whether `filter()` runs over the scope's body: below a filtered read, or a resource's own when encoded.
     */
    public function filtersBodyOf(AnalysisScope $scope): bool
    {
        return match ($this) {
            self::Encoded => $scope->subjectReflection->isSubclassOf(JsonResource::class),
            self::EncodedUnfiltered, self::Inertia => false,
            self::EncodedFiltered, self::InertiaFiltered => true,
        };
    }
}
