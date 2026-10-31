<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;

/**
 * How the values a body returns reach the client, which decides how they are read: as `json_encode()` sends them, as
 * Inertia sends a page's props and shared data, or as Inertia sends them once a JsonResource's `filter()` ran.
 *
 * @internal
 */
enum Reading
{
    /** `json_encode()` sends them: what a class publishes for itself, and what reaches Inertia already JSON. */
    case Encoded;

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
            self::Inertia => '@inertia',
            self::InertiaFiltered => '@inertia@filtered',
        };
    }

    /**
     * The reading a method's body takes when this one reads it: an Inertia read of a JsonResource's `toArray()` whole,
     * which `resolve()` filters, is filtered from there down; calling it for its value runs no filter.
     *
     * @param  ReflectionClass<object>  $runs  the class the body runs for
     */
    public function entering(ReflectionClass $runs, string $method, bool $readsCall): self
    {
        if ($this !== self::Inertia || $readsCall || $method !== 'toArray') {
            return $this;
        }

        return is_a($runs->getName(), JsonResource::class, true) ? self::InertiaFiltered : $this;
    }

    /**
     * The reading the body a call runs takes from a value read in the scope: `filter()` never enters a map() kept as a
     * Collection, so what a call returns there reaches Inertia unfiltered.
     */
    public function calledFrom(AnalysisScope $scope): self
    {
        return $this === self::InertiaFiltered && ! $this->filterReaches($scope) ? self::Inertia : $this;
    }

    /**
     * Whether `JsonResource::filter()` reaches a value read in the scope, which sends a resource around null as null.
     */
    public function filterReaches(AnalysisScope $scope): bool
    {
        return $scope->filtersNestedResources && $this->filtersBodyOf($scope);
    }

    /**
     * Whether `filter()` runs over what the scope's body returns: below a filtered Inertia read, and outside Inertia
     * over a JsonResource subject's own; it enters arrays only, which AnalysisScope::$filtersNestedResources tracks.
     */
    public function filtersBodyOf(AnalysisScope $scope): bool
    {
        return match ($this) {
            self::Encoded => $scope->subjectReflection->isSubclassOf(JsonResource::class),
            self::Inertia => false,
            self::InertiaFiltered => true,
        };
    }
}
